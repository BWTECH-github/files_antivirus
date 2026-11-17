<?php
/**
 * Files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Viktar Dubiniuk 2021
 * @license AGPL-3.0
 */

$config = \OC::$server->getConfig();
$passed = $config->getAppValue('files_antivirus', 'autoprobe', false);
// Nur proben, wenn noch kein Modus eingestellt ist: Eine vorhandene Einstellung (z. B. socket aus der
// Bereitstellung oder einer früheren Installation) darf die automatische Erkennung nicht überschreiben
if ($passed === false && $config->getAppValue('files_antivirus', 'av_mode', false) === false) {
	$app = new \OCA\Files_Antivirus\AppInfo\Application();
	$app->autoProbe();
}
