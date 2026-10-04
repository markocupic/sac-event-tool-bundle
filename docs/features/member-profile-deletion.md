# Feature: Member Profile Deletion

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/MemberProfileDeletion/` (Namespace `Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion`)

## Ziel

Wird ein Mitglied gelöscht (vom Mitglied selbst im Frontend oder von einem Admin im Backend), verschwinden seine Personendaten. Seine Anmeldungen zu vergangenen Events bleiben für Statistik und Abrechnung erhalten, lassen sich aber keiner Person mehr zuordnen.

## Regeln

### Profil bereinigen (`MemberProfileDeletion::clearMemberProfile()`)

- **Verweigert**, wenn das Mitglied bei einem kommenden Event auf der Buchungsliste steht (jeder Status ausser «abgelehnt»). Pro Event wird eine Fehlermeldung als Contao-Message ausgegeben. Mit `$force = true` wird diese Prüfung übersprungen.
- Die Anmeldungen des Mitglieds werden über die `contaoMemberId` **oder** die `sacMemberId` gefunden und so behandelt:

  | Anmeldung | Was passiert |
  |---|---|
  | Event existiert nicht mehr | **gelöscht**, samt Versionen (`EventRegistrationRemover`). Ohne Event gibt es nichts zu behalten, die Tour-History zeigt nur existierende Events. |
  | Event existiert (vergangen, abgemeldet, abgelehnt) | **anonymisiert** (`EventRegistrationAnonymizer`), bleibt für Statistik und Abrechnung |
  | Event existiert, kommend und aktiv angemeldet | **blockiert** die Löschung vorher (ausser mit `$force`, dann anonymisiert) |
- Der Avatar-Ordner `<avatar_dir>/<Mitglied-ID>` wird gelöscht (auch in der Dateiverwaltung).
- Das Mitglied selbst wird nicht gelöscht.

### Mitglied löschen (`deleteMember()`)

- Bereinigt zuerst das Profil. Klappt das nicht, bleibt das Mitglied bestehen.
- Danach wird das Mitglied gelöscht und im Contao-Log protokolliert (`Config\Log::DELETE_FRONTEND_USER`).

### Anmeldungen löschen (`EventRegistrationRemover::remove()`)

Löscht die Anmeldungen und ihre Versionen (`tl_version`) in einer Transaktion. Das Contao-Log enthält nur die Anzahl und die IDs. Wird auch von der [Bereinigung der Event-Anmeldungen](event-registration-cleanup.md) verwendet.

### Anmeldung anonymisieren (`EventRegistrationAnonymizer::anonymize()`)

Nur Anmeldungen, die noch nicht anonymisiert sind (`anonymized = 0`).

| Feld | Neuer Wert |
|---|---|
| `firstname`, `lastname`, `street`, `city` | «Vorname/Nachname/Adresse/Ort [anonymisiert]» |
| `postal` | `0` |
| `email`, `phone`, `mobile`, `gender`, `dateOfBirth`, `ahvNumber`, `foodHabits`, `instructorNotes` | leer |
| `sacMemberId` | `0` (kein SAC-Mitglied) |
| `sectionId` | `NULL` |
| `contaoMemberId` | `0` |
| `emergencyPhone` / `emergencyPhoneName` | `999 99 99` / «[anonymisiert]» |
| `deregistrationCause` | «[anonymisiert]», falls ein Grund angegeben war |
| `notes` | «Benutzerdaten anonymisiert am TT.MM.JJJJ» |
| `anonymized` | `1` |

Unverändert bleiben z. B. Event, Status, Teilnahme, Bezahlung und `uuid` (zufällige ID der Anmeldung).

Im selben Schritt (in einer Transaktion) werden die **Versionen** der Anmeldung gelöscht (`tl_version` mit `fromTable = 'tl_calendar_events_member'`), weil sie die alten Personendaten enthalten.

Jede Anonymisierung landet im Contao-Log (`Config\Log::ANONYMIZE_EVENT_REGISTRATION`), nur mit der ID der Anmeldung, ohne Name oder SAC-Nummer.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Frontend-Modul «Profil löschen» (`Controller\FrontendModule\MemberDashboardDeleteProfileController`) | `deleteMember()` für das eingeloggte Mitglied, danach Weiterleitung auf die Startseite |
| Backend: Mitglied löschen (`DataContainer\Member::clearMemberProfile()`, `config.ondelete`) | `clearMemberProfile()`. Bei einem Fehler wird das Löschen abgebrochen. Das Mitglied löscht Contao selbst. |
| [Bereinigung der Event-Anmeldungen](event-registration-cleanup.md) (Cron, täglich 06:30) | Anmeldungen gelöschter Mitglieder werden mit `EventRegistrationAnonymizer` anonymisiert. |

## Konfiguration

```yaml
# config/config.yaml
sacevt:
    user:
        frontend:
            avatar_dir: 'files/sektion/fe_user_home_directories/avatars' # Standard
```

## Klassen

```
src/Feature/MemberProfileDeletion/
├── MemberProfileDeletion.php         # clearMemberProfile(), deleteMember(), deleteAvatarDirectory(), findRegistrationIdsOfExistingEvents(), findRegistrationIdsOfDeletedEvents(), getUpcomingEventErrors()
├── EventRegistrationAnonymizer.php   # anonymize(), getAnonymizedData()
└── EventRegistrationRemover.php      # remove(): Anmeldungen samt Versionen löschen
```

## Tests (`tests/Feature/MemberProfileDeletion/`)

- `MemberProfileDeletionTest`: unbekanntes Mitglied, Anmeldungen existierender Events anonymisiert und gelöschter Events gelöscht, Suche über Contao- oder SAC-Nummer, nichts geändert bei Verweigerung, Verweigerung bei kommenden Events, abgelehnte Anmeldung blockiert nicht, `$force`, `deleteMember()`
- `EventRegistrationRemoverTest`: Anmeldungen und Versionen gelöscht, Log ohne Personendaten, leere Liste
- `EventRegistrationAnonymizerTest`: keine Personendaten mehr, Versionen gelöscht, Log ohne Personendaten, bereits anonymisierte Anmeldungen bleiben unverändert, Abmeldegrund

Das Löschen des Avatar-Ordners (`Contao\Folder`) braucht eine Contao-Installation und ist nicht getestet.
