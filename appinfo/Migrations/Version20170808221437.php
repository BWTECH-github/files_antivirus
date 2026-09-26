<?php
/**
 * Files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Viktar Dubiniuk 2018
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/**
 * Updates some fields to bigint if required
 */
class Version20170808221437 implements ISchemaMigration {
	/**
	 * @param Schema $schema
	 * @param array $options
	 *
	 * @return void
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];

		if ($schema->hasTable("{$prefix}files_antivirus")) {
			$table = $schema->getTable("{$prefix}files_antivirus");

			$fileIdColumn = $table->getColumn('fileid');
			// Nur heben, was noch kein bigint ist: Altbestände aus 0.8.1.0 bis
			// 0.10.0.0 (Tabelle aus database.xml) haben integer(4). Die Bedingung
			// war bei der DBAL-3-Umstellung verdreht und ließ genau diese Spalte stehen.
			if ($fileIdColumn // @phpstan-ignore-line
				&& !($fileIdColumn->getType() instanceof BigIntType)
			) {
				$fileIdColumn->setType(Type::getType(Types::BIGINT));
				$fileIdColumn->setOptions(['length' => 20]);
			}
		}
	}
}
