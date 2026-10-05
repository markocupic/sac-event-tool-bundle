# Feature: Event Registration Reminder

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventRegistrationReminder/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder`), Tests unter `tests/Feature/EventRegistrationReminder/`
Herkunft: übernommen aus der Extension `markocupic/sac-event-registration-reminder` und an die Feature-Konventionen angepasst (Cron + Messenger, wie `EventCompletionReminder`)

## Ziel

Anmelde-Koordinator bzw. Hauptleiter werden per Notification Center daran erinnert, dass sie Event-Anmeldungen noch nicht bearbeitet haben (nicht angenommen, nicht abgelehnt und nicht auf die Warteliste gesetzt). Pro Empfänger und Kalender wird **eine** Benachrichtigung mit allen betroffenen Events verschickt.

## Fachliche Regeln

### Kalender

- `enableInstructorReminderNotification = 1`
- `sendFirstReminderAfter`: Anzahl Tage seit der Anmeldung, nach denen eine unbearbeitete Anmeldung den Reminder auslöst (1–30)
- `sendReminderEach`: Intervall in Tagen zwischen zwei Remindern an denselben Empfänger für diesen Kalender (1–30)
- `sendReminderNotification`: Benachrichtigung (Pflichtfeld, nur Typ `event_registration_reminder`)

### Events

- Event des Kalenders, `published = 1`, hat noch nicht begonnen (`startDate > jetzt`)
- Empfänger: der Anmelde-Koordinator (`registrationGoesTo`), falls gesetzt, sonst der Hauptleiter (`tl_calendar_events_instructor.isMainInstructor = 1`)
- Der Empfänger muss aktiv sein und eine E-Mail-Adresse haben

### Anmeldungen

- `stateOfSubscription = subscription-not-confirmed`
- Nur Anmeldungen mit vollständigen Personalien (Vorname, Nachname, Geschlecht, Strasse, PLZ, Ort)
- **Überfällig:** angemeldet vor mindestens `sendFirstReminderAfter` Tagen. Nur überfällige Anmeldungen lösen den Reminder aus.
- **Neu:** die übrigen unbearbeiteten Anmeldungen desselben Events. Sie werden im Reminder zusätzlich aufgeführt («ebenfalls noch hängig»), lösen ihn aber nicht aus.

### Intervall

- Reminder fällig, wenn noch keiner verschickt wurde oder der letzte mindestens `sendReminderEach` Tage zurückliegt (60 Sekunden Toleranz für den Startzeitpunkt des Crons), siehe `ReminderLog::isReminderDue()`
- Geprüft im Cron und nochmals im Handler (Schutz vor doppeltem Cron-Lauf)

### Versand

- Cron: Zeitplan aus `sacevt.feature.event_registration_reminder.cron_schedule` (Default `30 4,5 * * *`, zweimal am Morgen). Der zweite Lauf holt nach, was der erste verpasst hat; das Intervall verhindert Duplikate. Der Tag `contao.cronjob` wird in `MarkocupicSacEventToolExtension` gesetzt, nicht per `#[AsCronJob]`.
- Der Cron dispatcht pro (Empfänger, Kalender) eine `SendEventRegistrationReminderMessage` (Symfony Messenger, `LowPriorityMessageInterface`). Den Versand übernimmt `SendEventRegistrationReminderHandler`.
- Der Handler prüft alles erneut, nimmt einen Lock pro (Empfänger, Kalender), schreibt den Log-Eintrag **vor** dem Versand (lieber ein fehlender als ein doppelter Reminder) und versendet in der Sprache des Benutzers (`tl_user.language`, sonst `sacevt.locale`).
- Zustellfehler werden ins Error-Log geschrieben und nicht wiederholt.

## Konfiguration

```yaml
# config/config.yaml
sacevt:
  feature:
    event_registration_reminder:
      disable: false                 # true: Reminder global aus, unabhängig von den Kalendereinstellungen
      cron_schedule: '30 4,5 * * *'  # Zeitplan des Crons
```

Parameter: `sacevt.feature.event_registration_reminder.disable`, `sacevt.feature.event_registration_reminder.cron_schedule`. Beide sind optional. `disable` wird im Cron geprüft: Es werden keine neuen Messages mehr dispatcht.

Gegenüber der früheren Extension weggefallen: `sid` und `allow_web_scope` (keine Web-Route mehr), `notification_limit_per_request` (Versand einzeln über den Messenger), `default_locale` (ersetzt durch `sacevt.locale`).

## Datenmodell

Die Namen der Felder, der Log-Tabelle, des Backend-Moduls und des Notification-Typs stammen aus der früheren Extension und wurden bewusst beibehalten. So bleiben Einstellungen, Log und Benachrichtigungen bestehender Installationen erhalten, eine Migration ist nicht nötig.

### tl_calendar (Legende `event_registration_reminder_legend`)

| Feld | Typ | Default |
|---|---|---|
| `enableInstructorReminderNotification` | Checkbox, `submitOnChange`, Selector der Subpalette | `false` |
| `sendFirstReminderAfter` | Select 1–30, `smallint` | `0` |
| `sendReminderEach` | Select 1–30, `smallint` | `0` |
| `sendReminderNotification` | Select auf `tl_nc_notification`, nur Typ `event_registration_reminder` (Options-Callback `DataContainer\Calendar`), `varchar(64)` | `''` |

### tl_event_registration_reminder_notification (Log)

Eine Zeile pro (Empfänger, Kalender) mit dem letzten Reminder:

| Feld | Beschreibung |
|---|---|
| `user`, `calendar` | Empfänger (`tl_user`) und Kalender |
| `dateAdded` | Zeitpunkt des letzten Reminders (massgebend für das Intervall) |
| `prevReminderTstamp` | Zeitpunkt des Reminders davor |
| `title` | z. B. «Sent a reminder to Anna Muster (last time 01.10.2026).»; der Zusatz erscheint, wenn der vorherige Reminder weniger als zwei Intervalle zurückliegt |
| `history` | die letzten 10 Reminder |

Backend-Modul «Reminder für unbearbeitete Anmeldungen» (`event_registration_reminder_notification` in `sac_be_modules`), **nur lesen**: Liste und Detailansicht, kein Erstellen, Bearbeiten oder Löschen.

## Tokens (Notification Center, Typ `event_registration_reminder`)

- E-Mail: `instructor_email` (Empfänger), `admin_email`
- Empfänger: `instructor_firstname`, `instructor_lastname`, `instructor_name`
- Liste: `registrations` (Text, gruppiert nach Event, Template `templates/Email/EventRegistrationReminder/registrations.txt.twig`)
- Einstellung: `send_reminder_each`

## Klassen

```
src/Feature/EventRegistrationReminder/
├── Cron/EventRegistrationReminderCron.php                                     # dispatcht pro (Empfänger, Kalender) eine Message
├── DataContainer/Calendar.php                                                 # Options-Callback: nur Benachrichtigungen vom Typ "event_registration_reminder"
├── Messenger/Message/SendEventRegistrationReminderMessage.php                 # userId, calendarId
├── Messenger/MessageHandler/SendEventRegistrationReminderHandler.php          # prüft, loggt, versendet
├── NotificationType/EventRegistrationReminderNotificationType.php             # Typ "event_registration_reminder"
├── PendingEvent.php                                                           # DTO: Event mit überfälligen und neuen Anmeldungen
├── PendingRegistration.php                                                    # DTO: Vorname, Nachname, Geschlecht, Mitgliedernummer, Tage seit Anmeldung
├── PendingRegistrationProvider.php                                            # lädt Events und Anmeldungen, ordnet sie den Empfängern zu
└── ReminderLog.php                                                            # Log lesen/schreiben, isReminderDue()
```

## Tests (`tests/Feature/EventRegistrationReminder/`)

- `ReminderLogTest`: Intervall (nie versendet, genau abgelaufen, Toleranz, zweiter Cron-Lauf)
- `PendingRegistrationProviderTest`: Zuordnung Anmelde-Koordinator bzw. Hauptleiter, inaktive Empfänger, überfällige und neue Anmeldungen
- `Cron/EventRegistrationReminderCronTest`: eine Message pro fälligem (Empfänger, Kalender), nichts bei `disable`
- `Messenger/MessageHandler/SendEventRegistrationReminderHandlerTest`: Abbruchfälle, Log vor dem Versand, Tokens, Sprache des Benutzers

## Inbetriebnahme

1. Die Extension `markocupic/sac-event-registration-reminder` deinstallieren (`composer remove`) und ihre Konfiguration `sac_evt_reg_reminder` aus `config/config.yaml` entfernen; `disable` und `cron_schedule` bei Bedarf unter `sacevt.feature.event_registration_reminder` übernehmen. Beide gleichzeitig installiert führt zu doppelten Definitionen.
2. Falls ein externer Cronjob die alte Route `/_event_registration_reminder/{sid}` aufruft: entfernen. Der Reminder läuft jetzt über den Contao-Cron und den Messenger-Worker.
3. `contao:migrate` (keine Änderungen erwartet), `cache:clear`
4. Im Kalender prüfen, ob die gewählte Benachrichtigung vom Typ «Reminder für unbearbeitete Event-Anmeldungen» ist. Andere Typen werden nicht mehr angeboten.
5. Kontrolle: Systemlog (Anzahl Messages) und Backend-Modul «Reminder für unbearbeitete Anmeldungen»
