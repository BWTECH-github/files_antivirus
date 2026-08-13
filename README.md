# Anti-Virus (files_antivirus)

Die App prüft Dateien beim Hochladen auf Schadsoftware, bevor sie in den
Speicher geschrieben werden, und kann den vorhandenen Bestand in einem
Hintergrundlauf nachprüfen. Geprüft wird nicht von owncloud.online selbst,
sondern von einem externen Scanner: ClamAV als Programmaufruf oder als Dienst
(clamd), oder ein Scanner, der über ICAP angesprochen wird. Die App ist ein
PHP-8.4-Fork für owncloud.online.

## Was die App tut

* Sie hängt einen Speicher-Wrapper (`oc_avir`) in alle Speicher ein. Alles, was
  schreibend geöffnet oder geschrieben wird, läuft parallel zum Schreiben durch
  den Scanner.
* Sie prüft Uploads über öffentliche Freigabelinks: ein Sabre-Plugin greift
  `beforeCreateFile` und `beforeWriteContent` ab, wenn keine Sitzung angemeldet
  ist.
* Sie prüft im Hintergrund Dateien, die noch nie geprüft wurden oder deren ETag
  sich seit der letzten Prüfung geändert hat.
* Jeder Fund erzeugt einen Eintrag im Log der App (`files_antivirus`). Funde im
  Hintergrundlauf und beim Hochladen durch angemeldete Nutzer erzeugen
  zusätzlich einen Aktivitätseintrag vom Typ `virus_detected`.

Nicht geprüft werden: Verzeichnisse, leere Dateien, die einzelnen Teilstücke
eines Chunk-Uploads (geprüft wird die fertig zusammengesetzte Datei) und beim
Hochladen Dateien oberhalb der Größengrenze (siehe unten).

## Betriebsarten

Die Betriebsart steht im Schlüssel `av_mode`. Die drei ICAP-Betriebsarten
setzen eine gültige Lizenz voraus (siehe „Fehlersuche").

| `av_mode`    | Beschriftung im Panel                             | Verbindung zum Scanner                                |
|--------------|---------------------------------------------------|-------------------------------------------------------|
| `executable` | ClamAV ausführbar                                 | startet `clamscan` als Prozess, Daten über STDIN      |
| `daemon`     | ClamAV Daemon (TCP Socket)                        | TCP-Verbindung zu clamd, Kommando `INSTREAM`          |
| `socket`     | ClamAV Daemon (Unix Socket)                       | Unix-Socket zu clamd, Kommando `INSTREAM`             |
| `icap`       | ClamAV & Kaspersky (ICAP)                         | ICAP, Methode REQMOD                                  |
| `fortinet`   | Fortinet (ICAP)                                   | ICAP, Methode RESPMOD                                 |
| `mawgw`      | McAfee Webgateway / Skyhigh Secure Web Gateway (ICAP) | ICAP, Methode RESPMOD                             |

In den Betriebsarten `daemon` und `socket` sendet die App vor jeder Prüfung
`PING` und `VERSION` und bricht ab, wenn die Antwort nicht `PONG` lautet
beziehungsweise nicht mit `ClamAV` beginnt. Diese beiden Betriebsarten setzen
also einen ClamAV-Dienst voraus, keinen anderen Scanner.

In `executable` und `daemon`/`socket` wird die Antwort des Scanners über
Regeln ausgewertet (Abschnitt „Fortgeschritten" im Panel, Tabelle
`files_avir_status`): entweder über den Rückgabewert des Programms
(Betriebsart `executable`) oder über einen regulären Ausdruck auf die Ausgabe
des Dienstes. Die ICAP-Betriebsarten verwenden diese Regeln nicht, sondern den
Antwort-Header des ICAP-Servers.

Bei der Erstinstallation probiert die App der Reihe nach `daemon` und `socket`
mit einer EICAR-Testzeichenkette durch und fällt auf `executable` zurück, wenn
keine Verbindung zustande kommt. Dass diese Suche gelaufen ist, wird im
Schlüssel `autoprobe` vermerkt.

## Voraussetzungen

* owncloud.online 11.x, PHP 8.4.
* Ein erreichbarer Scanner:
  * ClamAV als Programm (Standardpfad `/usr/bin/clamscan`), les- und
    ausführbar für den Nutzer des Webservers, oder
  * ein laufender clamd über TCP oder Unix-Socket (der Socket muss für den
    Nutzer des Webservers zugreifbar sein), oder
  * ein ICAP-Server.
* Für die Hintergrundprüfung: ein funktionierender Cron-Lauf von
  owncloud.online.

## Installation

Der einfachere Weg ist die Installation über den Markt. Manuell:

    cd /var/www/owncloud.online/apps
    git clone https://github.com/BWTECH-github/files_antivirus.git
    cd files_antivirus
    composer install --no-dev
    chown -R www-data:www-data .
    sudo -u www-data php8.4 ../../occ app:enable files_antivirus

## Einstellungen

Das Panel „Antivirus-Konfiguration" wird als Administrationsbereich mit der
Kennung `security` registriert. Beim Speichern führt die App sofort eine
Testprüfung mit einer EICAR-Testzeichenkette aus und meldet, wenn diese nicht
als Fund erkannt wird.

Diese Schlüssel werden als App-Werte der App `files_antivirus` abgelegt:

| Schlüssel              | Standard                     | Bedeutung                                                             |
|------------------------|------------------------------|-----------------------------------------------------------------------|
| `av_mode`              | `executable`                 | Betriebsart, siehe Tabelle oben                                       |
| `av_host`              | `localhost`                  | Host des Scanners (`daemon` und alle ICAP-Betriebsarten)              |
| `av_port`              | `3310`                       | Port des Scanners (`daemon` und alle ICAP-Betriebsarten)              |
| `av_socket`            | `/var/run/clamav/clamd.ctl`  | Pfad zum Unix-Socket (Betriebsart `socket`)                           |
| `av_stream_max_length` | `26214400`                   | Bytes je Verbindung; danach wird neu verbunden                        |
| `av_max_file_size`     | `-1`                         | Größengrenze in Bytes, `-1` bedeutet keine Grenze                     |
| `av_scan_background`   | `true`                       | Hintergrundprüfung ein- oder ausschalten                              |
| `av_infected_action`   | `only_log`                   | Fund im Hintergrundlauf: `only_log` oder `delete`                     |
| `av_request_service`   | `avscan`                     | ICAP-Dienstname                                                       |
| `av_response_header`   | `X-Infection-Found`          | ICAP-Antwort-Header, der den Fund meldet                              |
| `autoprobe`            | –                            | interne Marke, dass die Suche nach der Betriebsart gelaufen ist       |

Übliche Werte für die beiden ICAP-Schlüssel, so wie das Panel sie beschriftet:

| Betriebsart | `av_request_service`         | `av_response_header`               |
|-------------|------------------------------|------------------------------------|
| `icap`      | `avscan` (ClamAV), `req` (Kaspersky ScanEngine) | `X-Infection-Found` oder `X-Virus-ID` |
| `fortinet`  | `respmod`                    | `X-Virus-ID`                       |
| `mawgw`     | `respmod`                    | `X-Virus-Name`                     |

Zwei Werte lassen sich bewusst nicht über die Oberfläche ändern, sie werden
aus der `config.php` gelesen und dort auch gepflegt:

```php
'files_antivirus.av_path' => '/usr/bin/clamscan',
'files_antivirus.av_cmd_options' => '',
```

`files_antivirus.av_cmd_options` nimmt zusätzliche Kommandozeilenoptionen
kommagetrennt entgegen; jede Option wird einzeln maskiert an `clamscan`
übergeben.

## Was bei einem Fund passiert

Beim Hochladen durch angemeldete Nutzer (Speicher-Wrapper):

* Der Upload wird abgelehnt. Der Nutzer sieht „Der Virus %s wurde in der Datei
  gefunden. Das Hochladen konnte nicht abgeschlossen werden."
* Die bereits geschriebenen Teile der Datei werden entfernt. Die App löscht
  dabei direkt auf dem darunterliegenden Speicher, damit der Vorgang auch bei
  Objektspeichern gelingt.
* Es entstehen ein Warneintrag im Log (mit Name des Fundes, Eigentümer und
  Pfad) und ein Aktivitätseintrag „Datei %s ist infiziert mit %s".
* `av_infected_action` gilt hier nicht: beim Hochladen wird immer abgelehnt.

Beim Hochladen über einen öffentlichen Freigabelink (Sabre-Plugin):

* Der Upload wird mit derselben Meldung abgelehnt, und zwar bevor etwas
  geschrieben wird; es ist also nichts zu löschen.
* Es entsteht ein Warneintrag im Log (mit Name des Fundes und Pfad), aber kein
  Aktivitätseintrag.

Im Hintergrundlauf entscheidet `av_infected_action`:

* `only_log` (Standard): Es wird nur protokolliert, die Datei bleibt liegen.
* `delete`: Die Datei wird gelöscht.

In beiden Fällen entsteht ein Aktivitätseintrag („Datei %s ist infiziert mit
%s", beim Löschen zusätzlich „Sie wird gelöscht"). Kann eine Datei nicht
eindeutig bewertet werden (Status „ungeprüft"), wird das nur protokolliert;
die Datei bleibt unverändert.

## Größengrenze und Geschwindigkeit

`av_max_file_size` wirkt an zwei Stellen unterschiedlich:

* Beim Hochladen wird die Datei bei Überschreitung **gar nicht** geprüft; der
  Upload läuft ungeprüft durch. Im Debug-Log steht dann die ermittelte Größe,
  das Limit und „Scanning is skipped".
* Im Hintergrundlauf und bei öffentlichen Uploads wird der Datenstrom nach der
  eingestellten Byte-Zahl abgeschnitten und nur dieser Anfang geprüft. Der
  Hintergrundlauf wählt ohnehin nur Dateien unterhalb der Grenze aus.

Zur Geschwindigkeit:

* Die Prüfung läuft parallel zum Schreiben; jeder Upload kostet zusätzlich die
  Zeit des Scanners. Läuft der Scanner auf
  derselben Maschine, konkurriert er mit dem Webserver um CPU und
  Arbeitsspeicher. Ein eigener Scanner-Host oder ein ICAP-Server entlastet
  die owncloud.online-Instanz.
* `av_stream_max_length` begrenzt, wie viele Bytes über eine Verbindung
  geschickt werden. Ist der Wert erreicht, beendet die App die Verbindung und
  baut eine neue auf. Der Wert muss zur Einstellung `StreamMaxLength` des
  ClamAV-Dienstes passen; ist er zu groß, bricht die Übertragung ab und wird
  wiederholt, was Uploads spürbar verlangsamt.
* Der Hintergrundjob läuft höchstens alle 15 Minuten und prüft je Lauf bis zu
  10 Dateien. Ein großer, noch nie geprüfter Bestand braucht daher entsprechend
  lange.

## Fehlersuche

| Symptom                                                                                   | Ursache                                                                                                                | Abhilfe                                                                                                             |
|-------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| Nach dem Speichern erscheint „Test war nicht erfolgreich. Bitte überprüfe die Antivirus-Einstellungen" | Die Testprüfung mit der EICAR-Zeichenkette lieferte keinen Fund: Scanner nicht erreichbar oder Signaturen fehlen        | Host, Port beziehungsweise Socket prüfen; läuft clamd? Sind die Signaturen geladen?                                    |
| Upload bricht ab mit „Die owncloud.online antivirus App ist entweder falsch konfiguriert, oder der externe Virenscanner Dienst ist nicht erreichbar" | Der Scanner ließ sich nicht starten oder nicht erreichen                                                                | Log der App `files_antivirus` lesen, die genaue Ursache steht dort                                                     |
| Log: `The antivirus executable could not be found at path ...`                            | `files_antivirus.av_path` zeigt ins Leere                                                                              | Pfad in der `config.php` korrigieren; die Datei muss für den Nutzer des Webservers ausführbar sein                     |
| Log: `Unexpected response to ping: ...` oder `Unexpected response to version: ...`        | Auf der angegebenen Adresse antwortet kein ClamAV-Dienst                                                                | Host/Port beziehungsweise Socket-Pfad korrigieren, oder eine ICAP-Betriebsart verwenden                                |
| Log: `Could not connect to host ...` / `Could not connect to socket ...`                  | clamd läuft nicht, oder der Nutzer des Webservers darf den Socket nicht öffnen                                          | Dienst starten; Rechte auf der Socket-Datei prüfen                                                                     |
| Log: `Failed to write a chunk. Check if Stream Length matches StreamMaxLength in ClamAV daemon settings` | `av_stream_max_length` ist größer als `StreamMaxLength` in der `clamd.conf`                                            | Beide Werte angleichen                                                                                                 |
| Log: `No matching rules. Please check antivirus rules.` oder `No matching rule for exit code ...` | Die Regeltabelle ist leer oder unvollständig, das Ergebnis bleibt „ungeprüft"                                          | Im Panel unter „Fortgeschritten" auf „Auf Standard rücksetzen" klicken                                                 |
| Log: `No valid license found for icap scanner, resetting mode to executable`; Speichern schlägt fehl | Die Betriebsarten `icap`, `fortinet` und `mawgw` sind an eine gültige Lizenz gebunden                                   | Lizenz einspielen oder eine ClamAV-Betriebsart wählen. Die App bleibt aktiv, sie fällt auf `executable` zurück          |
| Log: `ICAP response unusable: ...`                                                        | Der ICAP-Server antwortet mit einem unerwarteten Code oder ohne den erwarteten Header                                   | `av_request_service` und `av_response_header` an den Server anpassen (Tabelle oben); `Allow: 204` muss erlaubt sein     |
| Dateien werden beim Hochladen nicht geprüft, Debug-Log: `Scanning is skipped`             | Die Datei ist größer als `av_max_file_size`, oder die Upload-Größe ist nicht bestimmbar (einzelne Chunks)               | Grenze anheben oder `-1` setzen; Chunks werden erst als zusammengesetzte Datei geprüft                                  |
| Der Bestand wird nicht nachgeprüft                                                        | `av_scan_background` steht auf `false`, oder der Cron-Lauf von owncloud.online arbeitet nicht                            | Hintergrundprüfung einschalten und den Cron-Lauf prüfen; der Job startet frühestens alle 15 Minuten                     |

Sauber geprüfte Dateien vermerkt der Hintergrundlauf in der Tabelle
`files_antivirus` (Datei-ID, Zeitpunkt, ETag); Funde und unklare Ergebnisse
werden dort nicht abgelegt. Wird ein Eintrag entfernt oder ändert sich die
Datei, prüft der nächste Lauf sie erneut.

## Herkunft

Die App geht auf `files_antivirus` der ownCloud GmbH zurück (AGPL-3.0).
Ursprüngliche Autoren: Manuel Delgado, Bart Visscher, thinksilicon.de,
Viktar Dubiniuk. Dieser Fork wird von der BW-Tech GmbH für owncloud.online
gepflegt und auf PHP 8.4 gehalten.

* Quelltext und Fehlerberichte:
  https://github.com/BWTECH-github/files_antivirus
* Dokumentation: https://docs.owncloud.online
* Produkt: https://owncloud.online
