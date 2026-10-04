# Feature: Event Registration Cleanup

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventRegistrationCleanup/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup`)

## Ziel

Räumt die Event-Anmeldungen (`tl_calendar_events_member`) einmal täglich auf: Anmeldungen zu gelöschten Events werden gelöscht, Anmeldungen gelöschter Mitglieder werden anonymisiert.

## Regeln (`EventRegistrationCleanup::run()`)

### 1. Anmeldungen zu gelöschten Events löschen

Ausgewählt werden alle Anmeldungen (`findRegistrationsOfDeletedEvents()`), die gespeichert sind (`tstamp > 0`) und deren Event (`eventId`) nicht mehr in `tl_calendar_events` existiert, **mit oder ohne SAC-Nummer**.

Grund: Wird ein Event gelöscht, gehören auch seine Anmeldungen weg. Die Tour-History im Mitglieder-Dashboard zeigt Anmeldungen zu gelöschten Events ohnehin nicht mehr an (`CalendarEventsMemberModel::findEventsByMemberId()` lädt die Events aus `tl_calendar_events`).

Die Anmeldungen und ihre **Versionen** (`tl_version`, enthalten die Personendaten) werden mit `Feature\MemberProfileDeletion\EventRegistrationRemover` in einer Transaktion gelöscht. Das Contao-Log enthält nur die Anzahl und die IDs, keine Personendaten.

Im Backend kann ein Event nur gelöscht werden, wenn es keine Anmeldungen mehr hat (einzeln und über «Mehrere bearbeiten», `DataContainer\AccessDecision\CalendarEvents`, Meldung `ERR.deleteEventMembersBeforeDeleteEvent`). Dieser Schritt ist darum ein Sicherheitsnetz für Altdaten und für Events, die ausserhalb des Backends gelöscht wurden.

### 2. Anmeldungen gelöschter Mitglieder anonymisieren

Ausgewählt werden Anmeldungen (`findRegistrationsOfDeletedMembers()`), die alle Bedingungen erfüllen:

- noch nicht anonymisiert (`anonymized = 0`) und gespeichert (`tstamp > 0`)
- gehört zu einem Mitglied: `contaoMemberId > 0` **oder** `sacMemberId > 0` (Gäste ohne beides sind nicht betroffen)
- **kein** Mitglied in `tl_member` mit dieser `contaoMemberId`
- **und**, falls eine SAC-Nummer gesetzt ist, **kein** Mitglied mit dieser SAC-Nummer
- das Event existiert noch (Anmeldungen zu gelöschten Events werden in Schritt 1 gelöscht)

Die Prüfung über die `contaoMemberId` schützt Anmeldungen, deren Mitglied vom Zentralverband eine neue SAC-Nummer erhalten hat, bevor der [Sync der Event-Anmeldungen](event-registration-database-sync.md) die neue Nummer übernommen hat. Deaktivierte Mitglieder (`disable = 1`) existieren weiterhin und sind nicht betroffen.

Die Anmeldungen werden mit `Feature\MemberProfileDeletion\EventRegistrationAnonymizer` anonymisiert (inkl. Löschen der Versionen), siehe [Mitgliederprofil löschen](member-profile-deletion.md). Sie bleiben für Statistik und Abrechnung erhalten.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Cron `Cron\EventRegistrationCleanupCron` (`#[AsCronJob('30 6 * * *')]`, täglich 06:30) | `run()`. Läuft nach dem Sync der Event-Anmeldungen (04:40) und dem Mitglieder-Sync (05:01). |
| Command `sacevt:event-registration:cleanup` | `run()`, mit `--dry-run` nur Auflistung ohne Änderung |

## Command

```
php vendor/bin/contao-console sacevt:event-registration:cleanup --dry-run   # nur anzeigen
php vendor/bin/contao-console sacevt:event-registration:cleanup             # wie der Cron
```

Die Ausgabe listet pro Schritt (zuerst Löschen, dann Anonymisieren) die betroffenen Anmeldungen mit ID, Event, Name, SAC-Nummer und Contao-Mitglied.

## Konfiguration

Keine. Der Cron von Contao muss laufen (`contao:cron` oder Web-Cron).

## Klassen

```
src/Feature/EventRegistrationCleanup/
├── EventRegistrationCleanup.php                 # run(), findRegistrationsOfDeletedMembers(), findRegistrationsOfDeletedEvents()
├── Cron/EventRegistrationCleanupCron.php        # täglich 06:30
└── Command/EventRegistrationCleanupCommand.php  # sacevt:event-registration:cleanup [--dry-run]
```

## Tests (`tests/Feature/EventRegistrationCleanup/`)

- `EventRegistrationCleanupTest`: Dry-Run ändert nichts, löschen (über `EventRegistrationRemover`) und anonymisieren, Abfragebedingungen (alle Anmeldungen zu gelöschten Events; SAC-Nummer **und** Contao-Mitglied fehlen, Event existiert)
- `Command/EventRegistrationCleanupCommandTest`: Ausgabe im Dry-Run
