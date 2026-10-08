# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.3.13] - 2026-10-08

Redesign-Linie. 1.3.12 ist für die Sprachrunde (Zweig fix-sprache-2) vergeben.

### Fixed

- Schmale Fenster: Beschriftungen stehen über ihren Feldern statt in einer
  280 px breiten, rechtsbündigen Spalte. Bei 320–390 px standen die Felder
  rechts außerhalb der Karte, das Formular war 442 px breit und die Seite
  rollte waagerecht; der Hinweis zur Dateigrößenbeschränkung ragte über den
  Kartenrand.
- Das Feld „Socket“ (130 px aus dem Kern) schnitt „/run/clamav/clamd.ctl“ ab;
  Textfelder sind jetzt 24rem (Port und Größen 12rem) breit, höchstens so breit
  wie die Karte.
- „Modus“: eine Auswahl kann nicht umbrechen. Sie wächst mit der gewählten
  Option; ist die breiter als die Karte („McAfee Webgateway / Skyhigh Secure
  Web Gateway (ICAP)“ bei 320 px), rollt nur diese Zeile waagerecht.
- „Fortgeschritten“: die Regeltabelle (1243 px, auch bei 1280 px) sprengte die
  Seite. Sie rollt jetzt in einem eigenen Bereich, Kopf- und Textzellen brechen
  um (bei 1280 px passt sie ganz in die Karte); die Knöpfe „Alles säubern“ und
  „Auf Standard rücksetzen“ ragten 10 px über die Karte.

## [1.3.12] - 2026-10-07

Redesign-Linie: enthält main bis 1.3.11 (Merge), redesign stand vorher bei 1.3.7.

### Fixed

- Sprache: Die Upload-Meldung „The file could not be checked for viruses. Upload cannot be completed, please try again.“ (Scanner antwortet nicht) fehlte in allen deutschen Katalogen.
- Sprache: Regeltabelle in Administration → Sicherheit → Antivirus: Tooltips und Sprachausgabe „Save rule“/„Delete rule“ ergänzt; die 14 Beschreibungen der mitgelieferten Regeln (englisch in der Datenbank) werden zur Anzeige übersetzt. Unverändert gespeichert bleibt der Datenbankwert, eigene Beschreibungen bleiben, wie sie sind.
- Sprache: Der Hinweis „You can change this value in the system configuration (config.php).“ (Modus „ClamAV Executable“) war fest eingetragen und läuft jetzt über den Katalog.
- Anrede: Die ICAP-Meldung bei Fund siezte in de/de_AT/de_CH; de_DE sagte „gelöscht“ statt „abgelehnt“.

## [1.3.11] - 2026-10-02

### Fixed

- Speichern über CalDAV/CardDAV scheiterte mit HTTP 500, wenn files_antivirus aktiv war (PHP 8). Betroffen war jeder neue und jeder geänderte Termin und Kontakt, aus der Kalender-App ebenso wie aus Sync-Clients (iOS, Android/DAVx5, Thunderbird). Ursache: Das Sabre-Plugin der App setzt `$data` in `beforeCreateFile` und `beforeWriteContent` mit `rewind()` zurück. Die CalDAV- und CardDAV-Plugins von Sabre haben den Datenstrom da schon in einen String gewandelt. Unter PHP 7 war `rewind()` auf einem String nur eine Warnung, unter PHP 8 bricht es mit TypeError ab. Zurückgesetzt wird jetzt nur noch ein Datenstrom. Nachgestellt auf SaaS 11.0.21 (Modus socket): Mit 1.3.10 scheiterte schon das Anlegen von Termin und Kontakt mit 500, mit 1.3.11 antworten Anlegen mit 201 und Ändern mit 204.
- Vorsorglich: Kommt ohne Anmeldung ein String an, prüft die App ihn als Inhalt, statt mit TypeError abzubrechen. Datei-Uploads über öffentliche Links laufen unverändert als Datenstrom durch den Scanner, ein infizierter Upload wird weiter mit 403 abgewiesen, beim Anlegen wie beim Überschreiben. Einen anonymen Weg, der einen String liefert, gibt es derzeit nicht: In einen veröffentlichten Kalender darf niemand ohne Anmeldung schreiben, die Rechteprüfung weist das vor dem Plugin ab.
- Termine und Kontakte angemeldeter Nutzer prüft die App wie bisher nicht. Sie liegen in der Datenbank, nicht im Dateispeicher.
- 14 neue Tests, 8 davon rot gegen 1.3.10.

## [1.3.10] - 2026-09-26

### Security

- Umzug von Altbeständen bis 0.16: Dort standen `av_path` und `av_cmd_options` in `oc_appconfig`. Die Migration Version20210413110050 hat sie beim Update ungeprüft in die `config.php` geschrieben und dabei auch vorhandene `config.php`-Werte überschrieben. Im Modus executable startet der Webserver genau dieses Programm mit diesen Argumenten und reicht den Dateiinhalt auf STDIN weiter. Wer die Datenbank liefert (beim Umzug der Kunde), konnte so beliebige Befehle als Nutzer des Webservers ausführen lassen: Mit `av_mode=executable`, `av_path=/bin/sh`, `av_cmd_options=-s` und `installed_version` 0.x lief jeder Upload als Shell-Skript. Nachgestellt auf MariaDB: Mit 1.3.9 legte der Upload eines Skripts nach `occ upgrade` eine Datei als www-data an, mit 1.3.10 nicht. Seit Upstream 1.0.0 lassen sich beide Werte bewusst nicht mehr über die Oberfläche ändern; diese Migration war die verbliebene Brücke von der Datenbank in die `config.php`.
- Jetzt protokolliert die Migration die Altwerte (Warnung „Legacy setting … was not copied to config.php“) und löscht sie aus `oc_appconfig`. In die `config.php` schreibt sie nichts mehr. Ein Pfad vom alten Server ist auf dem neuen ohnehin bedeutungslos.
- **Nach dem Update prüfen:** Wer bis 0.16 einen eigenen `av_path` oder eigene `av_cmd_options` hatte, trägt sie selbst als `files_antivirus.av_path` bzw. `files_antivirus.av_cmd_options` in die `config.php` ein. Sonst gelten `/usr/bin/clamscan` und keine Zusatzoptionen; fehlt clamscan dort, weist die App im Modus executable jeden Upload ab (fail-closed, siehe 1.3.6).
- Vier neue Tests, drei davon rot gegen 1.3.9.

## [1.3.9] - 2026-09-26

### Fixed

- Umzug von Altbeständen: Die Migration Version20170808221437 soll `oc_files_antivirus.fileid` auf bigint heben. Ihre Bedingung war bei der DBAL-3-Umstellung verdreht („ist bigint“ statt „ist noch kein bigint“) und ließ genau die Spalte stehen, die sie umstellen soll. Betroffen sind Datenbanken, in denen diese Migration noch nicht verbucht ist: files_antivirus 0.8.1.0 (Server 8.2.11 und 9.0.x), 0.9.0.1 (Server 9.1.x) und 0.10.0.0 (Server 10.0.0 bis 10.0.2). Alle drei legen die Tabelle noch über `database.xml` an, `fileid` ist dort integer(4); dort blieb die Prüftabelle bei INT UNSIGNED. Nachgestellt auf MariaDB mit dem Stand 0.9.0.1: 1.3.8 lässt `int(10) unsigned` stehen, 1.3.9 hebt auf `bigint(20) unsigned`. Nicht betroffen sind Datenbanken ab 0.10.1.0 (Server 10.0.3), die die Migration mit der damals noch richtigen Bedingung ausgeführt haben, und Neuinstallationen, die die Spalte gleich als bigint anlegen. Vier neue Tests, zwei davon rot gegen 1.3.8.
- Nicht erfasst: Datenbanken aus 0.8.1.0 bis 0.10.0.0, die schon mit 1.3.0 bis 1.3.8 aktualisiert wurden. Dort ist die Migration verbucht und läuft nicht erneut, `fileid` blieb INT. Nachholen mit `occ migrations:execute files_antivirus 20170808221437`; auf MariaDB nachgestellt: danach bigint, vorhandene Einträge bleiben erhalten, ein zweiter Aufruf ändert nichts. Spürbar wird die INT-Spalte erst bei Datei-IDs über 4 294 967 295.

## [1.3.8] - 2026-09-24

### Fixed

- Abschnittsweiser Scan: War ein früherer Abschnitt ohne Ergebnis (ungeprüft) und erst der LETZTE Abschnitt infiziert, meldete der Scanner „ungeprüft“ statt „infiziert“. Der Upload wurde zwar abgewiesen und die Datei gelöscht, aber ohne Virus-Warnung, Aktivität und Protokolleintrag – und mit der Aufforderung, den Upload zu wiederholen. Ursache: Der Befund des letzten Abschnitts wird nie nach `infectedStatus` übernommen (nach ihm kommt kein initScanner mehr) und wurde vom offenen Abschnitt verdeckt. Infiziert zählt jetzt in jeder Position vor ungeprüft. Zwei neue Tests, einer davon rot gegen 1.3.7.

### Hinweise zum Verhalten seit 1.3.6 (fail-closed)

- Objectstore als Primärspeicher (z. B. files_primary_s3): Dieser Speicher kennt keine Teildateien, ein Upload schreibt direkt auf den Endpfad. Beim Überschreiben einer bestehenden Datei ist der alte Inhalt bereits ersetzt, bevor der Scanner sein Urteil abgibt (der innere Strom wird geschlossen und hochgeladen, erst danach läuft der Abschluss des Scans). Liefert der Scan kein Ergebnis, wird deshalb die Zieldatei selbst entfernt – nicht nur eine Teildatei –, samt Cache-Eintrag und ohne Papierkorb; bei einem Objectstore mit Versionierung sind die früheren Fassungen danach in der Anwendung nicht mehr erreichbar. Der Client bekommt 403 mit der Aufforderung, den Upload zu wiederholen; bis dahin fehlt die Datei, andere Sync-Clients sehen sie in dieser Zeit als gelöscht. Bis 1.3.5 blieb in diesem Fall der neue, ungeprüfte Inhalt stehen und der Upload galt als gelungen. Für infizierte Dateien verhält sich die App auf Objectstore seit 0.15.2 genauso. Lokaler Speicher und andere Speicher mit Teildateien sind nicht betroffen: Dort wird nur die Teildatei entfernt, die bestehende Datei bleibt unverändert. Die Alternative (Inhalt behalten, trotzdem 403) ist bewusst nicht umgesetzt: Sie ließe genau die ungeprüfte Datei im Bestand, die 1.3.6 verhindert.
- file_put_contents (Texteditor, vom Kern erzeugte Vorschaubilder, jeder Schreibvorgang über diesen Weg) wird ohne Größengrenze gescannt und schlägt ebenfalls geschlossen fehl: Ohne eindeutiges „sauber“ wird nichts geschrieben (ForbiddenException, wiederholbar). Ein nicht erreichbarer Scanner führte hier schon vor 1.3.6 zum Fehler; neu ist der Fall „Scanner antwortet, aber ohne Urteil“ (Zeitüberschreitung, Überlast). Sichtbare Folge: Solange clamd nicht antwortet, entstehen keine neuen Vorschaubilder, die Vorschau-Anfrage schlägt fehl statt ein Bild zu liefern.

## [1.3.7] - 2026-09-24

### Security

- Große Dateien werden abschnittsweise gescannt (av_stream_max_length). Bisher entschied nur der LETZTE Abschnitt: fiel der Scanner in einem früheren Abschnitt aus, oder brach der Datenstrom mitten im Abschnitt ab (der Neuaufbau schickt nur den letzten Block erneut), galt die Datei trotzdem als sauber. Jetzt bestimmt jeder Abschnitt ohne eindeutiges Ergebnis das Ergebnis der ganzen Datei, der Upload wird abgewiesen. Vier neue Tests (zwei davon rot gegen 1.3.6).
- file_put_contents reicht die Wiederholbarkeit der Ablehnung durch.

### Betrieb: sehr große Dateien (z. B. 44 GB) bei av_max_file_size = -1

- fail-closed weist große Dateien nicht pauschal ab. Mit av_max_file_size = -1 wird jede Datei vollständig gescannt, in Abschnitten von av_stream_max_length (Standard 26214400 Byte = 25 MiB); 44 GB sind rund 1 700–1 800 Abschnitte. Liefert jeder Abschnitt „sauber“, wird die Datei angenommen (Test testBigCleanWriteOverManySegmentsIsAccepted).
- Ein einziger Abschnitt ohne eindeutiges Ergebnis (clamd neu gestartet oder überlastet, ReadTimeout von clamd überschritten, clamscan vom OOM-Killer beendet) lässt den ganzen Upload scheitern. Die Ablehnung kommt erst nach dem vollständigen Upload (bei Chunk-Uploads im abschließenden MOVE), die Teildatei wird gelöscht (auf Objectstore ohne Teildateien die Zieldatei selbst, siehe 1.3.8), der Upload ist wiederholbar (403). Bis 1.3.5 wurde die Datei in diesem Fall ungeprüft angenommen.
- **Vor dem Update prüfen:** av_stream_max_length darf nicht größer sein als StreamMaxLength in clamd.conf (Debian/Ubuntu liefern 25M = 26214400 = Standard der App). Sonst bricht clamd jeden Abschnitt mit „INSTREAM size limit exceeded“ ab. Bis 1.3.5 wurden solche Dateien trotzdem angenommen, obwohl clamd nur den Anfang jedes Abschnitts gesehen hatte; ab 1.3.6 wird jeder Upload abgewiesen, der größer als StreamMaxLength ist (Test testStreamLongerThanDaemonLimitIsRefused). Gemessen gegen clamd 1.5.3 mit StreamMaxLength 25M und 200 MB Zufallsdaten: mit av_stream_max_length 100 MB nimmt 1.3.3 an und 1.3.7 weist ab; mit 25 MiB nehmen beide an.
- Laufzeit (unverändert, nicht durch diese Version verursacht): Der Scan läuft synchron im Upload-Request. Richtwert auf der Testmaschine: 3,5–9 s je 25-MiB-Abschnitt über clamd, also für 44 GB etwa 2–4,5 Stunden zusätzlich im letzten Request. Proxy-, PHP-FPM- und Client-Zeitgrenzen müssen das tragen, sonst scheitert der Upload auch ohne fail-closed. Im Modus executable startet je Abschnitt ein neuer clamscan-Prozess (gemessen 38 s und 1 GB RAM je Abschnitt, für 44 GB rund 19 Stunden) – für solche Dateien ungeeignet. Die Modi icap, fortinet und mawgw halten die ganze Datei im Arbeitsspeicher und scheitern bei 44 GB am memory_limit.
- Wer sehr große Dateien nicht scannen will, setzt av_max_file_size auf eine Grenze. Größere Dateien werden dann gar nicht gescannt und bewusst ungeprüft angenommen.

### Tests

- Die Testattrappe DummyClam antwortet wie ein echter clamd („stream: OK“). Ihre alte Antwort „Scanned OK“ passte auf keine Regel und galt unter fail-closed als ungeprüft (testHealthFilePutContents schlug fehl).

## [1.3.6] - 2026-09-23

### Security

- Upload-Pfad schlägt jetzt geschlossen fehl: Liefert der Scan kein eindeutiges „sauber“ (Scanner mittendrin ausgefallen, clamd oder clamscan abgeschossen, Stream abgebrochen, keine passende Regel), wird der Upload abgewiesen statt die Datei ungeprüft zu übernehmen. Ebenso bei einem unerwarteten Fehler beim Aufsetzen des Scans – vorher kam dann der ungeprüfte Datenstrom zurück.

## [1.3.5] - 2026-09-23

### Fixed

- Socket- und Daemon-Modus: ClamAV 1.x kann den Befehl VERSION abschalten (EnableVersionCommand no, Standard in Ubuntu 26.04) und antwortet mit „COMMAND UNAVAILABLE“. Der Scanner hielt den Dienst dann für nicht erreichbar, jeder Upload scheiterte mit 403. PING belegt die Erreichbarkeit, diese Antwort wird jetzt hingenommen.

## [1.3.4] - 2026-09-23

### Changed

- Verwaltungsseite: Die Hinweise zu Pfad und Kommandozeilenoptionen verweisen nicht mehr auf eine fremde Dokumentationsseite, sondern nennen die Systemkonfiguration (config.php) als Text.

## [1.3.3] - 2026-08-13

### Changed

- README als Betriebsdokumentation neu geschrieben: Installation, Einstellungen,
  Kommandozeile und Fehlersuche; tote und fremde Verweise entfernt.

## [1.3.2] - 2026-08-13

### Changed

- Produktname, Beschreibung und uebersetzte Zeichenketten nennen owncloud.online;
  Verweise auf Fehlerbereich, Repository und Dokumentation zeigen auf das eigene
  Repository. Screenshots aus fremden Repositories entfernt.

## [Unreleased] - XXXXXX


## [1.2.3] - 2025-06-26

### Fixed

- [#554](https://github.com/owncloud/files_antivirus/issues/554) - Fix cron execution with too few arguments
- [#559](https://github.com/owncloud/files_antivirus/issues/559) - Verify ClamAV connection


## [1.2.2] - 2023-06-13

### Fixed

- [#502](https://github.com/owncloud/files_antivirus/issues/502) - McAfee Webgateway causes a 60 seconds delay on each upload
- [#534](https://github.com/owncloud/files_antivirus/issues/534) - Fix enterprise check for ICAP
- [#539](https://github.com/owncloud/files_antivirus/issues/539) - Hide config details about background scan if disabled
- [#540](https://github.com/owncloud/files_antivirus/issues/540) - Fix hostname input validation


## [1.2.1] - 2022-11-21

### Fixed

- [#525](https://github.com/owncloud/files_antivirus/pull/525) - PHP Syntax error when upgrading to files_antivirus 1.2.0 on PHP 7.3 #525
- Translation updates

## [1.2.0] - 2022-11-17

### Added

- [#517](https://github.com/owncloud/files_antivirus/pull/517) - Add setting to enable/disable background scanning

### Fixed

- [#518](https://github.com/owncloud/files_antivirus/pull/518) - In case of missconfiguration a non-tech message is presented
- [#516](https://github.com/owncloud/files_antivirus/pull/516) - Av mode names & fortinet fields
- [#514](https://github.com/owncloud/files_antivirus/pull/514) - Upload cannot be deleted but only denied + use same message in two places
- [#512](https://github.com/owncloud/files_antivirus/pull/512) - Fortinet scanner file name + major code cleanup
- [#496](https://github.com/owncloud/files_antivirus/pull/496) - Daemon and Executable mode are specific to ClamAV
- [#455](https://github.com/owncloud/files_antivirus/pull/455) - Doc link for executable and params point to the wrong doc


## [1.1.0] - 2022-07-26

### Fixed

- [#477](https://github.com/owncloud/files_antivirus/pull/477) - Advanced settings (list of rules) is not displayed
- [#473](https://github.com/owncloud/files_antivirus/pull/473) - ICAP Response Modification Mode is missing
- [#495](https://github.com/owncloud/files_antivirus/pull/495) - Fix wrong offset for the ICAP protocol

### Added

- [#488](https://github.com/owncloud/files_antivirus/pull/488) - Add ICAP Scanner for FortiSandbox
- [#489](https://github.com/owncloud/files_antivirus/pull/489) - Add ICAP Scanner for McAfee Web Gateway 10.x #489


## [1.0.0] - 2021-05-31

### Fixed

- Prevent upload virus file with new public WebDAV API - [#334](https://github.com/owncloud/files_antivirus/pull/334)
- fix: handle McAfee response [#413](https://github.com/owncloud/files_antivirus/pull/413)
- docs: fix icap setup - [#417](https://github.com/owncloud/files_antivirus/pull/417)
- Improve validation pattern to check whether port number is in [1, 65535] range [423](https://github.com/owncloud/files_antivirus/pull/423)
- Prevent from crashing on missing or expired license [#426](https://github.com/owncloud/files_antivirus/pull/426)
- fix: [ICAP] Stop reading the response after headers are read - [#445](https://github.com/owncloud/files_antivirus/pull/445)

### Changed

- Prefer daemon or socket to executable mode if any of those is available [#399](https://github.com/owncloud/files_antivirus/pull/399)
- Do not depend on the sockets PHP extension [#428](https://github.com/owncloud/files_antivirus/pull/428)
- Move executable options into config.php [#442](https://github.com/owncloud/files_antivirus/pull/442)


## [0.16.0] - 2021-02-01

### Added

- Support for external scanner classes for e.g. ICAP integration - [#379](https://github.com/owncloud/files_antivirus/pull/379)

### Changed

- Owncloud 10.3+ required


## [0.15.2] - 2020-07-27

### Fixed

- Delete file infected directly on the physical storage on objectstorage.

## [0.15.1] - 2019-06-24

### Fixed

- correct logging of actions performed by cron job - [#306](https://github.com/owncloud/files_antivirus/issues/306)


## [0.15.0] - 2019-03-14

### Added

- Add a message to background job to help debugging issues - [#260](https://github.com/owncloud/files_antivirus/issues/260)

### Fixed

- Do not scan files if etag hasn't changed - [#288](https://github.com/owncloud/files_antivirus/issues/288)

## [0.14.0] - 2018-11-30

### Added

- Support for PHP 7.2 - [#256](https://github.com/owncloud/files_antivirus/issues/256)

### Changed

- Set max version to 10 because core platform is switching to Semver

## [0.13.0] - 2018-07-11
### Fixed

- Obey file size limits when uploads are chunked [#226](https://github.com/owncloud/files_antivirus/pull/226)
- Don't log exceptions on virus detection [#219](https://github.com/owncloud/files_antivirus/pull/219)

### Changed
- Return HTTP status code `403` on virus detection [#219](https://github.com/owncloud/files_antivirus/pull/219)

## [0.12.0] - 2018-02-08

### Added

 - Ability to disable background scan [213](https://github.com/owncloud/files_antivirus/pull/213)
 - A connection test after saving the settings. Notify admin if this test is failed [195](https://github.com/owncloud/files_antivirus/pull/195)
 - Scanning content in file_put_contents invocation [198](https://github.com/owncloud/files_antivirus/pull/198)

### Changed

 - Ignore calls to fopen in case there is no upload (scan file from the storage 
 wrapper only if it is related to the upload) [196](https://github.com/owncloud/files_antivirus/pull/196)
 - When antivirus is unreachable uploads are rejected [195](https://github.com/owncloud/files_antivirus/pull/195)

### Fixed

 - Proper validation/detection of inputs fields [212](https://github.com/owncloud/files_antivirus/pull/212)
 - Scanning when using public shared links [211](https://github.com/owncloud/files_antivirus/pull/211)
 - Improper size detection for chunking upload [196](https://github.com/owncloud/files_antivirus/pull/196)
 - Don't scan chunks for DAV v1/v2 [196](https://github.com/owncloud/files_antivirus/pull/196)

## [0.11.2] - 2017-09-28

### Added

 - Frontend Validation for config fields [187](https://github.com/owncloud/files_antivirus/pull/187)

## [0.11.1.0] - Unreleased

### Changed

- App description and makefile updated for new marketplace [161](https://github.com/owncloud/files_antivirus/pull/161)

### Fixed

- Oracle: Error when saving a rule  [167](https://github.com/owncloud/files_antivirus/pull/167)

## [0.10.1.0] - 2017-09-15

### Changed 

- DB schema ported from xml to migrations [169](https://github.com/owncloud/files_antivirus/pull/169)
- Do not scan individual chunks for chunked upload [175](https://github.com/owncloud/files_antivirus/pull/175)
- ownCloud 10.0.3+ required


## [0.10.0.2] - Unreleased

### Changed 

- fileid is changed to bigint [165](https://github.com/owncloud/files_antivirus/pull/165)

## [0.10.0.1] - 2017-07-04

### Fixed

- BGscanner query fix [159](https://github.com/owncloud/files_antivirus/pull/159)

## [0.10.0] - 2016-10-10

### Changed 

- Optimized query in a BG scanner [139](https://github.com/owncloud/files_antivirus/pull/139)
- ownCloud 10.0 required

### Fixed

- Always log a warning on uploading infected [132](https://github.com/owncloud/files_antivirus/issues/132)

## [0.9.0.1] - Unreleased

### Changed

- Backport Optimized query in a BG scanner  [174](https://github.com/owncloud/files_antivirus/pull/174)

### Fixed

- Fix Call to a member function getUser() on a non-object at stable9.1 [#156](https://github.com/owncloud/files_antivirus/pull/156/)

## [0.9.0.0] - 2016-03-23

### Changed

- TimedJob is used instead of legacy cron API [100](https://github.com/owncloud/files_antivirus/pull/100)

### Fixed

- Rule is duplicated on edit [111](https://github.com/owncloud/files_antivirus/pull/111)

## [0.8.1.0] - 2016-12-22

### Added

- Add huge files support by scanning them as chunks of size avStreamMaxLength [133](https://github.com/owncloud/files_antivirus/pull/133)

### Changed

- Background scanner scans 10 files per iteration now
- Saving of rules in advanced section

## [0.8.0.1] - 2016-01-31

### Fixed

- AntiVirus 0.7.0.1 crashes cron in OC 8.1 [63](https://github.com/owncloud/files_antivirus/issues/63)
- Change recipient name in notification mail if using user_ldap [66](https://github.com/owncloud/files_antivirus/issues/66)
- Infected file is moved only to trash if "delete file" is activated [68](https://github.com/owncloud/files_antivirus/issues/68)

## [0.8.0] - 2016-01-31

### Changed

- ownCloud 8.2 required

## [0.7.0.2] - 2016-01-31

### Changed

- Skip zero-sized files in background scanner 

## [0.7.0.1] - 2015-07-07

### Changed

- Shipped removed from appinfo
- ownCloud 8.1 required

## [0.7.0] - 2015-07-07

### Added

- Integration with Activity app [37](https://github.com/owncloud/files_antivirus/pull/37)

### Changed

- Refactored to use AppFramework controllers, DB Entities and Mappers 
- Log owner and path for infected files [13](https://github.com/owncloud/files_antivirus/issues/13)
- ownCloud 8.0 required

### Fixed

- Upgrade for sqlite [#6](https://github.com/owncloud/files_antivirus/pull/6)
- If the screen width is not very wide the buttons "Reset to default" and "Clear All" overlap the text. [#23](https://github.com/owncloud/files_antivirus/issues/23)
- Use storage wrapper instead of FS hooks. Fixes [15](https://github.com/owncloud/files_antivirus/issues/15)
- Some issues found by code checker [39](https://github.com/owncloud/files_antivirus/pull/39)
- Debug message missing in executable mode [#44](https://github.com/owncloud/files_antivirus/issues/44)

## [0.6.1] - 2014-11-23

### Added

- App icon [#3](https://github.com/owncloud/files_antivirus/pull/3)
- Manage antivirus statuses from admin
- Extra command line parameters in executable mode
- Routes

### Fixed

- Removed old non-existing background job
- Do not send email to guest users
- Fixed public upload
- Do not execute background job when app is disabled
- Renamed table files_antivirus_status into files_avir_status: key name was too long for Oracle
- Fixed saving rules for Oracle [#1](https://github.com/owncloud/files_antivirus/pull/1)

## [0.6.0] - 2014-04-03

### Added

- Unit tests
- Home storage class support

### Changed

- Do not scan directories and empty files
- Fileid is a primary key


## [0.5.0] - 2014-02-17

### Added

- Namespaces

### Changed

- Updated to use public API
- Socket mode refactored
- Use view to stream file contents to clamav
- Use storage to unlink infected file
- ownCloud 6 required
- Background job scanner updated

### Fixed

- Uploading a file to a read-write shared dir
- Error message in executable mode
- Outdated settings layout

## [0.4.1] - 2013-06-06

### Added

- ClamAV socket mode support

### Changed

- Use displayname in antivirus email
- Loglevel for ClamAV response decreased to debug

## [0.4.0] - 2013-04-09

### Changed

- Updated to new Filesystem API
- Updated to OCP mail functions
- ownCloud 5 required

### Fixed

- Admin check for settings
- Echo replaced with p

## [0.3.0] - 2013-01-18

### Added

- Background scanner
- Configurable action for infected files

## [0.2.0] - 2012-10-17

### Added

- Added onscreen notification for infected files
- Added email notification for infected files

### Fixed

- ClamAV executable mode

## [0.1.0] - 2012-09-19

### Added

- Initial implementation


[Unreleased]: https://github.com/owncloud/files_antivirus/compare/v1.2.2...master
[1.2.2]: https://github.com/owncloud/files_antivirus/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/owncloud/files_antivirus/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/owncloud/files_antivirus/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/owncloud/files_antivirus/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/owncloud/files_antivirus/compare/v0.16.0...v1.0.0
[0.16.0]: https://github.com/owncloud/files_antivirus/compare/v0.15.2...v0.16.0
[0.15.2]: https://github.com/owncloud/files_antivirus/compare/v0.15.1...v0.15.2
[0.15.1]: https://github.com/owncloud/files_antivirus/compare/v0.15.0...v0.15.1
[0.15.0]: https://github.com/owncloud/files_antivirus/compare/v0.14.0...v0.15.0
[0.14.0]: https://github.com/owncloud/files_antivirus/compare/v0.13.0...v0.14.0
[0.13.0]: https://github.com/owncloud/files_antivirus/compare/v0.12.0...v0.13.0
[0.12.0]: https://github.com/owncloud/files_antivirus/compare/v0.11.2...v0.12.0
