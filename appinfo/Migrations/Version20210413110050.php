<?php
/**
 * Files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author David Christofas <dchristofas@owncloud.com>
 *
 * @copyright David Christofas 2021
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus\Migrations;

use OCP\IDBConnection;
use OCP\Migration\ISqlMigration;

/**
 * Entfernt av_path und av_cmd_options (bis 0.16 in oc_appconfig) aus der Datenbank.
 *
 * Beide Werte bestimmen, welches Programm der Webserver mit welchen Argumenten
 * startet (Modus executable). Seit 1.0.0 stehen sie deshalb nur noch in der
 * config.php. Früher hat diese Migration die Datenbankwerte dorthin kopiert.
 * Beim Umzug stammt die Datenbank aber vom Kunden: Mit av_path=/bin/sh und
 * av_cmd_options=-s liefe jeder Upload als Shell-Skript. Außerdem ist ein Pfad
 * vom alten Server auf dem neuen bedeutungslos. Die Werte werden deshalb nur
 * noch protokolliert und gelöscht; wer sie braucht, trägt sie selbst in die
 * config.php ein. Vorhandene config.php-Werte bleiben unangetastet.
 */
class Version20210413110050 implements ISqlMigration {
	public function sql(IDBConnection $conn) {
		$query = 'SELECT `configkey`, `configvalue` FROM `*PREFIX*appconfig` WHERE `appid` = \'files_antivirus\' AND (`configkey` = \'av_path\' OR `configkey` = \'av_cmd_options\')';
		$result = $conn->executeQuery($query);
		$logger = \OC::$server->getLogger();
		while ($row = $result->fetchAssociative()) {
			$logger->warning(
				\sprintf(
					'Legacy setting %s = %s found in the database was not copied to config.php. If it is still needed, set \'files_antivirus.%s\' in config.php manually.',
					$row['configkey'],
					\json_encode($row['configvalue'], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
					$row['configkey']
				),
				['app' => 'files_antivirus']
			);
		}
		$result->free();

		$query = 'DELETE FROM `*PREFIX*appconfig` WHERE `appid` = \'files_antivirus\' AND (`configkey` = \'av_path\' OR `configkey` = \'av_cmd_options\')';
		$conn->executeStatement($query);

		return [];
	}
}
