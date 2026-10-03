# Feature: Member Database Sync

Bundle: `markocupic/sac-event-tool-bundle` (bleibt im Bundle, kein eigenes Bundle)
Ort: `src/Feature/MemberDatabaseSync/` (Namespace `Markocupic\SacEventToolBundle\Feature\MemberDatabaseSync`), Tests unter `tests/Feature/MemberDatabaseSync/`

## Ziel

Die Mitgliederdaten der Sektion (und ihrer OGs) werden aus der Mitgliederdatenbank des SAC Zentralverbands (Bern) in die Contao-Tabelle `tl_member` übernommen. Einweg-Sync: Zentralverband → `tl_member`. Neue Mitglieder werden angelegt, bestehende aktualisiert, Ausgetretene deaktiviert.

## Ablauf (`SyncMemberDatabase::run()`)

1. **Lock:** Der ganze Sync läuft unter einem Symfony-Lock, es läuft also nie mehr als ein Sync gleichzeitig.
2. **Sektionen:** Synchronisiert werden alle Sektions-IDs aus `tl_sac_section` (z. B. 4250 SAC Pilatus, 4251 Surental).
3. **CSV-Dateien holen (`CsvFileProvider`):** Pro Sektion wird die CSV-Datei vom FTP-Server des Zentralverbands geholt (`FtpCsvFileFinder`, Zugangsdaten aus `sacevt.member_sync_credentials`) und nach `system/tmp/Adressen_<Sektions-ID>.csv` kopiert. Jede Datei muss mit `* * * Dateiende * * *` enden, sonst gilt sie als unvollständig und der Sync bricht ab. Fehlt die Datei einer Sektion, bricht der Sync ebenfalls ab.
4. **Temporäre Tabelle (`TempMemberTableManager`):** Die Zeilen der CSV-Dateien (`CsvMemberReader` → `CsvMemberDto`) werden in `tl_member_sync_temp` geschrieben. Ist jemand in mehreren Sektionen Mitglied, werden die Sektions-IDs zusammengeführt.
5. **Schreiben in `tl_member` (`ContaoMemberWriter`), alles in einer Transaktion:**
   - neue Mitglieder einfügen, bestehende aktualisieren
   - Mitglieder, die nicht mehr in der CSV stehen, deaktivieren. **Sicherung:** Würden mehr als 7 % aller Mitglieder deaktiviert, bricht der Sync ab (Schutz vor einem kaputten oder unvollständigen Export).
   - für bis zu 20 Mitglieder ohne Passwort ein zufälliges Passwort setzen
6. Bei einem Fehler wird die Transaktion zurückgerollt. Die temporäre Tabelle wird in jedem Fall gelöscht.
7. **Protokoll (`SyncLogger`):** Anzahl verarbeitete, neue, aktualisierte und deaktivierte Mitglieder, Dauer, Fehler. `run()` wirft selbst keine Exception, Fehler stehen im `SyncLogger`.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Cron `MemberDatabaseSyncCron` (`#[AsCronJob('1 5 * * *')]`, täglich 05:01) | Sync wie oben. Ergebnis und jede Änderung im Contao-Log (`Config\Log::MEMBER_DATABASE_SYNC_*`), Fehler im Error-Log. |
| Command `sacevt:member-database:sync` | Sync wie oben, Zusammenfassung als Tabelle, mit `-v` alle Änderungen. |
| Backend: Systemwartung → «Synchronisation SAC Mitgliederdatenbank Zentralverband -> Contao Mitglieder Datenbank», Button «Mitglieder-Datenbank Synchronisierung starten» (`ContaoBackendMaintenance\MemberDatabaseSync`, Template `templates/Maintenance/member_database_sync.html.twig`) | Sync per AJAX, Ergebnis als JSON. |

```
php vendor/bin/contao-console sacevt:member-database:sync
php vendor/bin/contao-console sacevt:member-database:sync -v
```

## Konfiguration

1. **Sektionen:** Im Backend unter den SAC-Sektionen (`tl_sac_section`) alle Sektionen und OGs eintragen, deren Mitglieder übernommen werden sollen (4-stellige Sektions-ID vom Zentralverband).
2. **FTP-Zugang** zum Server des Zentralverbands:

```yaml
# config/config.yaml
sacevt:
  member_sync_credentials:
    hostname: ftpserver.sac-cas.ch
    username: ****
    password: ******
```

3. **Cron:** Der Cron von Contao muss laufen (`contao:cron` oder Web-Cron).

`RemoteCsvFileFinderInterface` ist in `config/services.yaml` auf `FtpCsvFileFinder` gemappt. Für Tests oder eine andere Quelle lässt sich dort eine andere Implementierung eintragen.

## Klassen

```
src/Feature/MemberDatabaseSync/
├── SyncMemberDatabase.php                            # Ablauf: Lock, CSV holen, temporäre Tabelle, Transaktion
├── CsvFileProvider.php                               # holt und prüft die CSV-Datei jeder Sektion
├── RemoteCsvFileFinderInterface.php                  # Quelle der CSV-Dateien
├── FtpCsvFileFinder.php                              # Implementierung: FTP-Server des Zentralverbands
├── CsvMemberReader.php                               # liest eine CSV-Datei, liefert CsvMemberDto
├── CsvMemberDto.php                                  # eine CSV-Zeile, normalisiert (u. a. Telefonnummern)
├── TempMemberTableManager.php                        # temporäre Tabelle tl_member_sync_temp
├── ContaoMemberWriter.php                            # schreibt in tl_member: einfügen, aktualisieren, deaktivieren, Passwörter
├── SyncLogger.php                                    # Protokoll eines Laufs
├── Cron/MemberDatabaseSyncCron.php                   # täglich 05:01
├── Command/SyncMemberDatabaseCommand.php             # sacevt:member-database:sync
└── ContaoBackendMaintenance/MemberDatabaseSync.php   # Modul in der Systemwartung
```

Nicht Teil dieses Features, aber eng verwandt: der Abgleich Mitglied → Backend-User, siehe [member-to-user-sync.md](member-to-user-sync.md). Er läuft täglich um 06:01, also eine Stunde nach diesem Sync, damit die Mitgliederdaten dann aktuell sind.

## Tests (`tests/Feature/MemberDatabaseSync/`)

`SyncMemberDatabaseTest`, `CsvFileProviderTest`, `FtpCsvFileFinderTest`, `CsvMemberReaderTest`, `CsvMemberDtoTest`, `TempMemberTableManagerTest`, `ContaoMemberWriterTest`, `SyncLoggerTest`
