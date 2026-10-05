# Feature: Event Registration Database Sync

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventRegistrationDatabaseSync/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync`), Tests unter `tests/Feature/EventRegistrationDatabaseSync/`

## Ziel

Die Anmeldungen (`tl_calendar_events_member`) enthalten eine Kopie der Personendaten zum Zeitpunkt der Anmeldung. Dieses Feature überträgt die aktuellen Daten aus `tl_member` in die Anmeldungen, damit Leiter z. B. die aktuelle Adresse oder Telefonnummer sehen.

## Regeln (`SyncEventRegistrationDatabase`)

- Berücksichtigt werden alle Anmeldungen mit `contaoMemberId` eines bestehenden Mitglieds und `anonymized = 0`.
- **Immer übernommen:** `gender`, `firstname`, `lastname`, `street`, `postal`, `city`, `dateOfBirth`, `phone`
- **Nur wenn im Mitglied nicht leer:** `email`, `mobile`
- **Nur wenn das Mitglied eine SAC-Mitgliedernummer hat** (`> 0`): `sacMemberId`. Korrigiert von Hand eingetragene Werte wie `00167400` oder `370883 SAC Pilatus`.
- **Nur bei kommenden Events** (`startDate` in der Zukunft) und nur wenn im Mitglied nicht leer: `emergencyPhone` und `emergencyPhoneName` (nur zusammen), `foodHabits`
- Die Anmeldungen werden zusammen mit den Mitgliederdaten in **einer** Abfrage gelesen (nur die benötigten Spalten) und Zeile für Zeile verarbeitet. Eine Anmeldung wird nur geschrieben, wenn sich mindestens ein Feld unterscheidet, und dann nur die geänderten Felder.
- Alle Mitglieder laufen in **einer** Transaktion. Bei einem Fehler wird zurückgerollt, der Fehler steht im Protokoll (`with_error`, `exceptions`) und im Error-Log.

### Protokoll (Rückgabe von `run()`)

```php
[
    'processed_registrations' => int,
    'processed_members' => int,
    'updates' => int,
    'log' => list<string>,      // eine Zeile pro geänderte Anmeldung
    'duration' => int|float,    // Sekunden
    'with_error' => bool,
    'exceptions' => list<string>,
]
```

Jeder Lauf beginnt mit einem leeren Protokoll.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Cron `EventRegistrationDatabaseSyncCron` (`#[AsCronJob('40 4 * * *')]`, täglich 04:40) | `run()` für alle Mitglieder, Zusammenfassung im Contao-Log |
| Backend: Systemwartung → «Synchronisation Mitglieder -> Event Registrierungen» (`ContaoBackendMaintenance\EventRegistrationSync`, Template `templates/Maintenance/event_registration_sync.html.twig`) | `run()` per AJAX, Protokoll als JSON |
| Command `sacevt:event-registration-database:sync` (`Command\EventRegistrationDatabaseSyncCommand`) | `run()` für alle Mitglieder, Zusammenfassung als Tabelle, mit `-v` alle Änderungen. Bei einem Fehler Exit-Code 1. |
| Event-Anmeldung (`Controller\FrontendModule\EventRegistration\StepHandler\RegisterStep`) und Profil bearbeiten (`Controller\FrontendModule\MemberDashboardEditProfileController`) | `syncMember($memberId)`: nur die Anmeldungen dieses Mitglieds. Diese beiden Aufrufer gehören nicht zum Feature. |

## Command

```
php vendor/bin/contao-console sacevt:event-registration-database:sync
php vendor/bin/contao-console sacevt:event-registration-database:sync -v
```

## Konfiguration

Keine Einstellungen nötig. Voraussetzungen:

- Der Cron von Contao muss laufen (`contao:cron` oder Web-Cron).

## Klassen

```
src/Feature/EventRegistrationDatabaseSync/
├── SyncEventRegistrationDatabase.php                       # Service: run() für alle, syncMember() für ein Mitglied
├── Cron/EventRegistrationDatabaseSyncCron.php              # täglich 04:40
├── ContaoBackendMaintenance/EventRegistrationSync.php      # Modul in der Systemwartung
└── Command/EventRegistrationDatabaseSyncCommand.php        # sacevt:event-registration-database:sync
```

## Tests (`tests/Feature/EventRegistrationDatabaseSync/`)

- `SyncEventRegistrationDatabaseTest`: nur geänderte Felder, leere E-Mail/Mobile überschreiben nicht, Notfallkontakt und Essgewohnheiten nur bei kommenden Events, Protokoll, jeder Lauf beginnt leer, `syncMember()`, Rollback bei Fehler
- `Command/EventRegistrationDatabaseSyncCommandTest`: Zusammenfassung und Log-Zeilen, Exit-Code bei einem Fehler

