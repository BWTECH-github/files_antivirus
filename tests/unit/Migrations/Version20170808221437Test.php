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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Types;
use OCA\Files_Antivirus\Migrations\Version20170808221437;
use PHPUnit\Framework\TestCase;

// Migrationsklassen lädt sonst erst der MigrationService des Kerns
require_once __DIR__ . '/../../../appinfo/Migrations/Version20170808221437.php';

/**
 * Altbestand aus ownCloud 9.x (files_antivirus 0.9/0.10, database.xml):
 * oc_files_antivirus.fileid ist dort integer(4). Diese Migration muss die
 * Spalte beim Umzug auf bigint heben, sonst passen Datei-IDs jenseits von
 * 2^32 nicht mehr in die Prüftabelle.
 */
class Version20170808221437Test extends TestCase {
	private const PREFIX = 'oc_';

	private function schemaWithFileIdType(string $type): Schema {
		$schema = new Schema();
		$table = $schema->createTable(self::PREFIX . 'files_antivirus');
		$table->addColumn('fileid', $type, ['unsigned' => true, 'notnull' => true, 'length' => 4]);
		$table->addColumn('check_time', Types::INTEGER, ['unsigned' => true, 'notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['fileid']);
		return $schema;
	}

	private function migrate(Schema $schema): void {
		(new Version20170808221437())->changeSchema($schema, ['tablePrefix' => self::PREFIX]);
	}

	public function testIntegerFileIdFromOwncloud9IsWidenedToBigint(): void {
		$schema = $this->schemaWithFileIdType(Types::INTEGER);
		$this->assertInstanceOf(IntegerType::class, $schema->getTable('oc_files_antivirus')->getColumn('fileid')->getType());

		$this->migrate($schema);

		$column = $schema->getTable('oc_files_antivirus')->getColumn('fileid');
		$this->assertInstanceOf(BigIntType::class, $column->getType());
		$this->assertSame(20, $column->getLength());
	}

	public function testExistingRowsSurviveBecauseOnlyTheColumnTypeChanges(): void {
		$schema = $this->schemaWithFileIdType(Types::INTEGER);

		$this->migrate($schema);

		// Keine Spalte fällt weg, der Primärschlüssel bleibt auf fileid
		$table = $schema->getTable('oc_files_antivirus');
		$this->assertSame(['fileid', 'check_time'], \array_keys($table->getColumns()));
		$this->assertSame(['fileid'], $table->getPrimaryKey()->getColumns());
	}

	public function testBigintFileIdIsLeftAloneAndSecondRunIsNoop(): void {
		$schema = $this->schemaWithFileIdType(Types::BIGINT);
		$before = clone $schema;

		$this->migrate($schema);
		$this->migrate($schema);

		$this->assertEquals(
			$before->getTable('oc_files_antivirus')->getColumn('fileid')->toArray(),
			$schema->getTable('oc_files_antivirus')->getColumn('fileid')->toArray()
		);
	}

	public function testMissingTableIsNoop(): void {
		$schema = new Schema();

		$this->migrate($schema);

		$this->assertFalse($schema->hasTable('oc_files_antivirus'));
	}
}
