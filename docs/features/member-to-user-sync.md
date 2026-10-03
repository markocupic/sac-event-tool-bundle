# Feature: Member To User Sync

Bundle: `markocupic/sac-event-tool-bundle` (bleibt im Bundle, kein eigenes Bundle)
Ort: `src/Feature/MemberToUserSync/` (Namespace `Markocupic\SacEventToolBundle\Feature\MemberToUserSync`)

## Ziel

Backend-User (`tl_user`), die zugleich Sektionsmitglied sind, erhalten ihre Personendaten aus `tl_member`. Einweg-Sync: `tl_member` → `tl_user`. Verbunden werden die beiden Tabellen über die SAC-Mitgliedernummer (`sacMemberId`).

Zusammen mit dem [Mitglieder-Sync](member-database-sync.md) ergibt sich die Kette: Zentralverband → `tl_member` (05:01) → `tl_user` (06:01).

## Regeln (`MemberToUserSync::run()`)

- Geprüft werden alle Backend-User mit `sacMemberId > 0`. User und Mitgliederdaten werden in **einer** Abfrage gelesen (`tl_user LEFT JOIN tl_member` über `sacMemberId`) und zeilenweise verarbeitet (`iterateAssociative()`).
- Gibt es mehrere Mitglieder mit derselben `sacMemberId`, zählt das mit der tiefsten ID.
- **Mitglied gefunden** (`tl_member.sacMemberId` gleich): Folgende Felder werden übernommen: `firstname`, `lastname`, `name` («Nachname Vorname»), `sectionId`, `dateOfBirth`, `email`, `street`, `postal`, `city`, `country`, `gender`, `phone`, `mobile`.
  - Hat das Mitglied keine E-Mail-Adresse, bekommt der User den Platzhalter `invalid_<username>_<sacMemberId>@noemail.ch`.
  - Geschrieben werden nur die Felder, die sich tatsächlich unterscheiden (`getChangedFields()`). Ist alles gleich, gibt es kein UPDATE und keinen Log-Eintrag.
- **Kein Mitglied gefunden** (ausgetreten): `tl_user.sacMemberId` wird auf `0` gesetzt. Der User selbst wird nicht deaktiviert oder gelöscht.
- Alles läuft in **einer** Transaktion. Bei einem Fehler wird zurückgerollt, der Fehler steht im Protokoll (`with_error`, `exception`).
- Jede Änderung und die Zusammenfassung landen im Contao-Log (`Config\Log::MEMBER_WITH_USER_SYNC_SUCCESS`).

### Protokoll (`getSyncLog()`)

```php
[
    'log' => list<string>,   // eine Zeile pro geändertem User
    'processed' => int,
    'updates' => int,
    'disabled' => int,       // User, bei denen sacMemberId auf 0 gesetzt wurde
    'duration' => float,     // Sekunden
    'with_error' => bool,
    'exception' => string,
]
```

Jeder Lauf beginnt mit einem leeren Protokoll.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Cron `MemberToUserSyncCron` (`#[AsCronJob('1 6 * * *')]`, täglich 06:01) | Sync für alle User, eine Stunde nach dem Mitglieder-Sync |
| Command `sacevt:member-to-user:sync` (`Command\MemberToUserSyncCommand`) | Sync für alle User, Zusammenfassung als Tabelle, mit `-v` alle Änderungen. Bei einem Fehler Exit-Code 1. |

## Konfiguration

Keine Einstellungen nötig. Voraussetzungen:

- Im Backend-User muss die SAC-Mitgliedernummer (`tl_user.sacMemberId`) eingetragen sein, sonst wird er nicht berücksichtigt.
- Der Cron von Contao muss laufen (`contao:cron` oder Web-Cron).

## Klassen

```
src/Feature/MemberToUserSync/
├── MemberToUserSync.php                          # Service: Abgleich tl_member -> tl_user, Protokoll
├── Cron/MemberToUserSyncCron.php                 # täglich 06:01
└── Command/MemberToUserSyncCommand.php           # sacevt:member-to-user:sync
```

## Command

```
php vendor/bin/contao-console sacevt:member-to-user:sync
php vendor/bin/contao-console sacevt:member-to-user:sync -v
```

## Tests (`tests/Feature/MemberToUserSync/`)

- `MemberToUserSyncTest`: nur geänderte Felder, E-Mail-Platzhalter, ausgetretene Mitglieder, doppelte `sacMemberId`, leeres Protokoll pro Lauf, Rollback bei einem Fehler
- `Command/MemberToUserSyncCommandTest`: Zusammenfassung und Log-Zeilen, Exit-Code bei einem Fehler
