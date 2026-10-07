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

namespace OCA\Files_Antivirus\Tests\unit\Dav;

use OCA\Files_Antivirus\AppInfo\Application;
use OCA\Files_Antivirus\Dav\AntivirusPlugin;
use OCA\Files_Antivirus\IScannable;
use OCA\Files_Antivirus\Scanner\IScanner;
use OCA\Files_Antivirus\ScannerFactory;
use OCA\Files_Antivirus\Status;
use OCA\Files_Antivirus\Tests\unit\TestBase;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\INode;

/**
 * Sabre ruft beforeCreateFile (neue Datei) und beforeWriteContent (vorhandene
 * Datei) mit $data als Datenstrom auf. Die CalDAV- und CardDAV-Plugins von
 * Sabre laufen vorher und machen daraus einen String (validateICalendar,
 * validateVCard). Unter PHP 8 brach rewind() auf diesem String mit TypeError
 * ab: jeder Termin und jeder Kontakt scheiterte mit HTTP 500, sobald
 * files_antivirus eingeschaltet war.
 */
class AntivirusPluginTest extends TestBase {
	private const SIGNATURE = 'does the job';

	/** @var string|null was der Scanner zu sehen bekam */
	private $scannedContent;

	public function providesHooks(): array {
		return [
			'neu (beforeCreateFile)' => ['beforeCreateFile'],
			'ändern (beforeWriteContent)' => ['beforeWriteContent'],
		];
	}

	/**
	 * @dataProvider providesHooks
	 */
	public function testCalDavStringIsAcceptedForLoggedInUser(string $hook): void {
		$data = $this->ics();

		$result = $this->callHook($this->createPlugin(true), $hook, 'calendars/admin/personal/probe.ics', $data);

		self::assertTrue($result);
		self::assertSame($this->ics(), $data);
	}

	/**
	 * @dataProvider providesHooks
	 */
	public function testCardDavStringIsAcceptedForLoggedInUser(string $hook): void {
		$vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:probe\r\nFN:Probe\r\nN:Probe;;;;\r\nEND:VCARD\r\n";
		$data = $vcard;

		$result = $this->callHook($this->createPlugin(true), $hook, 'addressbooks/users/admin/contacts/probe.vcf', $data);

		self::assertTrue($result);
		self::assertSame($vcard, $data);
	}

	/**
	 * Unverändert: ein Datenstrom geht vom Anfang an weiter.
	 *
	 * @dataProvider providesHooks
	 */
	public function testStreamIsHandedOnFromStartForLoggedInUser(string $hook): void {
		$data = $this->createStream('file content');
		\fseek($data, 0, SEEK_END);

		$result = $this->callHook($this->createPlugin(true), $hook, 'files/admin/probe.txt', $data);

		self::assertTrue($result);
		self::assertSame('file content', \stream_get_contents($data));
	}

	/**
	 * Öffentlicher Upload: ganze Datei geprüft, danach vom Anfang an weitergereicht.
	 *
	 * @dataProvider providesHooks
	 */
	public function testCleanPublicStreamIsScannedAndHandedOnFromStart(string $hook): void {
		$content = $this->longerThanOneChunk('clean');
		$data = $this->createStream($content);

		$result = $this->callHook($this->createPlugin(false), $hook, 'public-files/token/probe.txt', $data);

		self::assertTrue($result);
		self::assertSame($content, $this->scannedContent);
		self::assertSame($content, \stream_get_contents($data));
	}

	/**
	 * Die Virenprüfung öffentlicher Uploads muss unverändert greifen, auch
	 * wenn die Signatur erst hinter dem ersten Abschnitt steht.
	 *
	 * @dataProvider providesHooks
	 */
	public function testInfectedPublicStreamIsRefused(string $hook): void {
		$data = $this->createStream($this->longerThanOneChunk(self::SIGNATURE));

		$this->expectException(Forbidden::class);
		$this->callHook($this->createPlugin(false), $hook, 'public-files/token/probe.txt', $data);
	}

	/**
	 * Kommt ohne Anmeldung ein String an, wird er geprüft statt mit TypeError
	 * abzubrechen.
	 *
	 * @dataProvider providesHooks
	 */
	public function testCleanPublicStringIsScanned(string $hook): void {
		$content = $this->longerThanOneChunk('clean');
		$data = $content;

		$result = $this->callHook($this->createPlugin(false), $hook, 'public-calendars/token/probe.ics', $data);

		self::assertTrue($result);
		self::assertSame($content, $this->scannedContent);
		self::assertSame($content, $data);
	}

	/**
	 * @dataProvider providesHooks
	 */
	public function testInfectedPublicStringIsRefused(string $hook): void {
		$data = $this->longerThanOneChunk(self::SIGNATURE);

		$this->expectException(Forbidden::class);
		$this->callHook($this->createPlugin(false), $hook, 'public-calendars/token/probe.ics', $data);
	}

	/**
	 * Aufruf wie in Sabre\DAV\Server::createFile() bzw. updateFile()
	 *
	 * @param resource|string $data
	 * @return bool|null
	 */
	private function callHook(AntivirusPlugin $plugin, string $hook, string $path, &$data) {
		$node = $this->createMock(INode::class);
		$modified = false;
		if ($hook === 'beforeCreateFile') {
			return $plugin->beforeCreateFile($path, $data, $node, $modified);
		}
		return $plugin->beforeWriteContent($path, $node, $data, $modified);
	}

	private function createPlugin(bool $loggedIn): AntivirusPlugin {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($loggedIn ? $this->createMock(IUser::class) : null);

		// Liest wie AbstractScanner::scan() abschnittsweise bis zum Ende
		$scanner = $this->createMock(IScanner::class);
		$scanner->method('scan')->willReturnCallback(function (IScannable $item): Status {
			$content = '';
			while (($chunk = $item->fread()) !== false) {
				$content .= $chunk;
			}
			$this->scannedContent = $content;
			if (\strpos($content, self::SIGNATURE) !== false) {
				return Status::create(Status::SCANRESULT_INFECTED, 'Probe.Signatur');
			}
			return Status::create(Status::SCANRESULT_CLEAN);
		});
		$scannerFactory = $this->createMock(ScannerFactory::class);
		$scannerFactory->method('getScanner')->willReturn($scanner);

		$application = new Application();
		$container = $application->getContainer();
		$container->registerService('ScannerFactory', function () use ($scannerFactory) {
			return $scannerFactory;
		});
		$container->registerService('AppConfig', function () {
			return $this->config;
		});
		$container->registerService('L10N', function () {
			return $this->l10n;
		});

		return new AntivirusPlugin($application, $userSession, $this->createMock(ILogger::class));
	}

	private function ics(): string {
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Probe//AV//DE\r\n"
			. "BEGIN:VEVENT\r\nUID:probe\r\nDTSTAMP:20261002T100000Z\r\n"
			. "DTSTART:20261014T100000Z\r\nDTEND:20261014T110000Z\r\nSUMMARY:Probe\r\n"
			. "END:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	/**
	 * Inhalt, dessen Ende erst im zweiten Abschnitt liegt (av_chunk_size im
	 * TestBase: 8192 Byte)
	 */
	private function longerThanOneChunk(string $tail): string {
		return \str_repeat('0', 10000) . $tail;
	}

	/**
	 * @return resource
	 */
	private function createStream(string $content) {
		$stream = \fopen('php://temp', 'r+');
		\fwrite($stream, $content);
		\rewind($stream);
		return $stream;
	}
}
