# Feature: Event Registration Reminder

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventRegistrationReminder/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder`), Tests unter `tests/Feature/EventRegistrationReminder/`

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

### Einrichtung

1. `contao:migrate` (Kalenderfelder, Log-Tabelle), `cache:clear`
2. Im Notification Center eine Benachrichtigung vom Typ «Reminder für unbearbeitete Event-Anmeldungen» anlegen: Empfänger `##instructor_email##`, Rohtext mit `##registrations##`, HTML-Version mit `##registrations_html##`. Interne Links im HTML-Text als Insert-Tag mit `absolute` setzen (z. B. `{{link_url::123::absolute|urlattr}}`) bzw. `##link_event_tool##` verwenden; relative Links lassen den Versand im Worker scheitern («Unable to parse URI»).
3. Im Kalender den Reminder aktivieren, Frist, Intervall und Benachrichtigung wählen
4. Der Router-Kontext muss gesetzt sein (`framework.router.default_uri` oder `router.request_context.host`/`scheme`), damit die Links auf die richtige Domain zeigen
5. Contao-Cron per CLI laufen lassen und Messenger-Worker betreiben
6. Kontrolle: Systemlog (Anzahl Messages, Laufzeit) und Backend-Modul «Reminder für Event-Anmeldungen»

## Datenmodell

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

Backend-Modul «Reminder für Event-Anmeldungen» (`event_registration_reminder_notification` in `sac_be_modules`), **nur lesen**: Liste und Detailansicht, kein Erstellen, Bearbeiten oder Löschen.

## Tokens (Notification Center, Typ `event_registration_reminder`)

- E-Mail: `instructor_email` (Empfänger), `admin_email`
- Empfänger: `instructor_firstname`, `instructor_lastname`, `instructor_name`
- Liste: `registrations` (Rohtext, Template `registrations.txt.twig`) und `registrations_html` (HTML, Template `registrations.html.twig`), beide unter `templates/Email/EventRegistrationReminder/`, gruppiert nach Event. Pro Event ein Link zur Teilnehmerliste (`contao?do=calendar&table=tl_calendar_events_member&id={id}`), Linktext `MSC.serr_link_member_list` («Zur Teilnehmerliste»). Die URLs erzeugt der Handler (`getMemberListUrls()`).
- Einstellungen: `send_first_reminder_after` (Frist bis zum ersten Reminder), `send_reminder_each` (Intervall)
- Link: `link_event_tool` (absoluter Link zum Contao-Backend). Im HTML-Text als `href="##link_event_tool##"` verwenden, damit TinyMCE keinen relativen Link daraus macht.

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

## Laufzeit

Der Cron misst seine Laufzeit mit der Symfony Stopwatch und schreibt sie ins Contao-Systemlog, z. B. «Event registration reminder cron: checked 74 calendar(s) and dispatched 0 message(s) in 0.02 s.». Der Versand im Messenger-Worker ist nicht enthalten.
