<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus\Tests\unit\Scanner;

use OCA\Files_Antivirus\AppConfig;
use OCA\Files_Antivirus\Scanner\AbstractScanner;
use OCA\Files_Antivirus\Status;
use OCP\IL10N;
use OCP\ILogger;
use Test\TestCase;

/**
 * Große Dateien werden abschnittsweise gescannt. Ein Abschnitt ohne
 * eindeutiges Ergebnis oder ein abgebrochener Datenstrom muss das Ergebnis
 * der ganzen Datei bestimmen - nicht nur der letzte Abschnitt.
 *
 * @group DB
 */
class SegmentStatusTest extends TestCase {
	/**
	 * @param int[] $ergebnisse Ergebnis je Abschnitt (in Reihenfolge)
	 * @param int $fehlschreiben Nummer des writeRaw-Aufrufs, der scheitert (0 = keiner)
	 */
	private function scanner(array $ergebnisse, int $fehlschreiben = 0): AbstractScanner {
		$config = $this->getMockBuilder(AppConfig::class)
			->disableOriginalConstructor()
			->addMethods(['getAvStreamMaxLength'])
			->getMock();
		$config->method('getAvStreamMaxLength')->willReturn(10);

		$scanner = new class($config, $this->createMock(ILogger::class), $this->createMock(IL10N::class)) extends AbstractScanner {
			public array $ergebnisse = [];
			public int $fehlschreiben = 0;
			private int $schreibzaehler = 0;

			public function initScanner(string $fileName): void {
				parent::initScanner($fileName);
				$this->writeHandle = \fopen('php://memory', 'w+');
			}

			public function shutdownScanner(): void {
				$wert = \array_shift($this->ergebnisse);
				if ($wert !== null) {
					$feld = new \ReflectionProperty(Status::class, 'numericStatus');
					$feld->setAccessible(true);
					$feld->setValue($this->status, $wert);
				}
			}

			protected function writeRaw(string $data): bool {
				$this->schreibzaehler++;
				if ($this->schreibzaehler === $this->fehlschreiben) {
					return false;
				}
				return parent::writeRaw($data);
			}
		};
		$scanner->ergebnisse = $ergebnisse;
		$scanner->fehlschreiben = $fehlschreiben;
		return $scanner;
	}

	private function scanne(AbstractScanner $scanner, array $bloecke): int {
		$scanner->initScanner('probe');
		foreach ($bloecke as $block) {
			$scanner->onAsyncData($block);
		}
		return $scanner->completeAsyncScan()->getNumericStatus();
	}

	public function testUncheckedFirstSegmentDecidesAlthoughLastIsClean(): void {
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_UNCHECKED, Status::SCANRESULT_CLEAN]),
			['0123456789', 'abcdef']
		);
		self::assertSame(Status::SCANRESULT_UNCHECKED, $ergebnis);
	}

	public function testAllSegmentsCleanStaysClean(): void {
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_CLEAN, Status::SCANRESULT_CLEAN]),
			['0123456789', 'abcdef']
		);
		self::assertSame(Status::SCANRESULT_CLEAN, $ergebnis);
	}

	public function testInfectedSegmentWinsOverUnchecked(): void {
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_INFECTED, Status::SCANRESULT_UNCHECKED, Status::SCANRESULT_CLEAN]),
			['0123456789', 'abcdefghij', 'xyz']
		);
		self::assertSame(Status::SCANRESULT_INFECTED, $ergebnis);
	}

	public function testInfectedLastSegmentWinsOverEarlierUnchecked(): void {
		// Der letzte Abschnitt wird nie nach $infectedStatus geklont (nach ihm
		// gibt es kein initScanner mehr). Sein Befund darf nicht hinter dem
		// offenen Abschnitt zurückstehen - sonst gibt es „bitte erneut
		// versuchen“ statt der Virus-Meldung (Nacharbeit 24.09.2026).
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_UNCHECKED, Status::SCANRESULT_INFECTED]),
			['0123456789', 'abcdef']
		);
		self::assertSame(Status::SCANRESULT_INFECTED, $ergebnis);
	}

	public function testInfectedMiddleSegmentWinsOverEarlierUnchecked(): void {
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_UNCHECKED, Status::SCANRESULT_INFECTED, Status::SCANRESULT_CLEAN]),
			['0123456789', 'abcdefghij', 'xyz']
		);
		self::assertSame(Status::SCANRESULT_INFECTED, $ergebnis);
	}

	public function testBrokenStreamMidSegmentIsUnchecked(): void {
		// Zweiter Schreibvorgang scheitert: was vorher in den Strom ging, bekommt
		// nie ein Urteil, auch wenn der letzte Abschnitt sauber zurückkommt.
		$ergebnis = $this->scanne(
			$this->scanner([Status::SCANRESULT_CLEAN], 2),
			['abc', 'def']
		);
		self::assertSame(Status::SCANRESULT_UNCHECKED, $ergebnis);
	}
}
