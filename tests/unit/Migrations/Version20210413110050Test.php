<?php
/**
 * owncloud.online - files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus\Tests\unit\Migrations;

use OCA\Files_Antivirus\Migrations\Version20210413110050;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

// Migrationsklassen lädt sonst erst der MigrationService des Kerns
require_once __DIR__ . '/../../../appinfo/Migrations/Version20210413110050.php';

/**
 * Altbestand bis files_antivirus 0.16: av_path und av_cmd_options liegen in
 * oc_appconfig. Die Datenbank kommt beim Umzug vom Kunden. Diese Werte
 * bestimmen, welches Programm der Webserver mit welchen Argumenten startet
 * (Modus executable), deshalb dürfen sie nie aus der Datenbank in die
 * config.php wandern. Die Migration räumt sie nur weg.
 */
class Version20210413110050Test extends TestCase {
	private const KEYS = ['av_path', 'av_cmd_options'];

	/** @var IConfig */
	private $config;
	/** @var IDBConnection */
	private $connection;
	/** @var array<string, mixed> config.php-Werte vor dem Test */
	private $systemValuesBefore = [];

	protected function setUp(): void {
		parent::setUp();
		$this->config = \OC::$server->getConfig();
		$this->connection = \OC::$server->getDatabaseConnection();
		foreach (self::KEYS as $key) {
			$this->systemValuesBefore[$key] = $this->config->getSystemValue('files_antivirus.' . $key, null);
			$this->config->deleteSystemValue('files_antivirus.' . $key);
		}
		$this->deleteLegacyRows();
	}

	protected function tearDown(): void {
		$this->deleteLegacyRows();
		foreach ($this->systemValuesBefore as $key => $value) {
			if ($value === null) {
				$this->config->deleteSystemValue('files_antivirus.' . $key);
			} else {
				$this->config->setSystemValue('files_antivirus.' . $key, $value);
			}
		}
		parent::tearDown();
	}

	private function deleteLegacyRows(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('files_antivirus')))
			->andWhere($qb->expr()->in('configkey', $qb->createNamedParameter(self::KEYS, IQueryBuilder::PARAM_STR_ARRAY)))
			->execute();
	}

	private function insertLegacyRow(string $key, string $value): void {
		// Direkt in die Tabelle wie in einem eingespielten Dump, am Cache des Kerns vorbei
		$this->connection->insertIfNotExist('*PREFIX*appconfig', [
			'appid' => 'files_antivirus',
			'configkey' => $key,
			'configvalue' => $value,
		], ['appid', 'configkey']);
	}

	/**
	 * @return string[] verbliebene Altschlüssel in oc_appconfig
	 */
	private function legacyRowsInDatabase(): array {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('configkey')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('files_antivirus')))
			->andWhere($qb->expr()->in('configkey', $qb->createNamedParameter(self::KEYS, IQueryBuilder::PARAM_STR_ARRAY)))
			->execute();
		$keys = [];
		while ($row = $result->fetchAssociative()) {
			$keys[] = $row['configkey'];
		}
		$result->free();
		\sort($keys);
		return $keys;
	}

	private function migrate(): void {
		$sqls = (new Version20210413110050())->sql($this->connection);
		foreach ((array) $sqls as $sql) {
			$this->connection->executeQuery($sql);
		}
	}

	public function testLegacyValuesFromDatabaseNeverReachConfigPhp(): void {
		// Ein präparierter Dump: jeder Upload würde als Shell-Skript laufen
		$this->insertLegacyRow('av_path', '/bin/sh');
		$this->insertLegacyRow('av_cmd_options', '-s');

		$this->migrate();

		$this->assertNull($this->config->getSystemValue('files_antivirus.av_path', null));
		$this->assertNull($this->config->getSystemValue('files_antivirus.av_cmd_options', null));
		$this->assertSame([], $this->legacyRowsInDatabase());
	}

	public function testExistingConfigPhpValuesAreNotOverwritten(): void {
		$this->config->setSystemValue('files_antivirus.av_path', '/usr/bin/clamscan');
		$this->config->setSystemValue('files_antivirus.av_cmd_options', '--max-filesize=100M');
		$this->insertLegacyRow('av_path', '/opt/clamav-alt/bin/clamscan');
		$this->insertLegacyRow('av_cmd_options', '--log=/tmp/x');

		$this->migrate();

		$this->assertSame('/usr/bin/clamscan', $this->config->getSystemValue('files_antivirus.av_path', null));
		$this->assertSame('--max-filesize=100M', $this->config->getSystemValue('files_antivirus.av_cmd_options', null));
		$this->assertSame([], $this->legacyRowsInDatabase());
	}

	public function testSecondRunIsNoop(): void {
		$this->insertLegacyRow('av_path', '/opt/clamav-alt/bin/clamscan');
		$this->migrate();

		$this->migrate();

		$this->assertNull($this->config->getSystemValue('files_antivirus.av_path', null));
		$this->assertSame([], $this->legacyRowsInDatabase());
	}

	public function testOtherSettingsOfTheAppStayUntouched(): void {
		$this->config->setAppValue('files_antivirus', 'av_mode', 'daemon');
		$this->insertLegacyRow('av_path', '/opt/clamav-alt/bin/clamscan');

		$this->migrate();

		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('configvalue')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('files_antivirus')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('av_mode')))
			->execute();
		$this->assertSame('daemon', $result->fetchOne());
		$result->free();
		$this->config->deleteAppValue('files_antivirus', 'av_mode');
	}
}
