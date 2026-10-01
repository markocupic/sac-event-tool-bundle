# Feature: Instructor Post-Event Task Reminder

Bundle: `markocupic/sac-event-tool-bundle` (bleibt im Bundle, kein eigenes Bundle)
Status: Spezifikation verabschiedet am 2026-10-01, umgesetzt am 2026-10-01
Vorbild: bestehender Event-Reminder (`EventReminderCron`, `SendEventReminderMessage`, `SendEventReminderHandler`, `EventReminderNotificationType`, `EventReminder\PersonProvider`)

## Ziel

Leiter (Haupt- und Hilfsleiter) sowie der Anmelde-Koordinator (`registrationGoesTo`), alle aus `tl_user`, werden per Notification Center an offene Aufgaben nach durchgeführten Events erinnert. Pro Leiter und Kalender eine Benachrichtigung mit ToDo-Liste über alle fälligen Events dieses Kalenders. Keine offenen Aufgaben bedeutet keine Benachrichtigung.

## Fachliche Regeln

### Geprüfte Events (alle Bedingungen müssen gelten)

- Kalender hat `sendInstructorPostEventTaskReminder = 1` und eine Notification gesetzt
- Eventtyp: entscheidet jede Task-Klasse über `supports()` (heute `tour`, `lastMinuteTour`, `course`; `generalEvent` hat keine Aufgaben). Bewusst kein Typ-Filter im SQL, damit eine neue Aufgabe nichts zentral ändern muss.
- `published = 1`
- `eventState NOT IN ('event_canceled', 'event_rescheduled')`
- `endDate > 0`. `endDate` ist der letzte Termin aus `eventDates`, er wird beim Speichern in `DataContainer\CalendarEvents` gesetzt.
- Bearbeitungsfrist abgelaufen: `endDate + firstOffset Tage <= heute`, gerechnet in ganzen Kalendertagen (Tagesbeginn, Zeitzone wie bei `EventReminderCron`)
- Lookback: `endDate >= heute - lookback Tage`

### Aufgaben pro Event

| Eventtyp | Tourenbericht | Teilnahme bestätigt |
|---|---|---|
| `tour`, `lastMinuteTour` | offen, wenn `filledInEventReportForm = 0` | offen, siehe unten |
| `course` | - | offen, siehe unten |

Die Teilnahme-Aufgabe existiert nur, wenn mindestens eine Anmeldung in `tl_calendar_events_member` mit `eventId = event.id` und `stateOfSubscription = 'subscription-accepted'` (`EventSubscriptionState::SUBSCRIPTION_ACCEPTED`) vorhanden ist.
Sie ist erledigt, sobald mindestens eine akzeptierte Anmeldung `hasParticipated = 1` hat.
`hasParticipated = 1` bei nicht akzeptierten Anmeldungen (z. B. Warteliste) zählt nicht. Das ist gewollt, damit der Leiter den Anmeldestatus nachführt.

Jede Spalte dieser Tabelle ist eine eigene Task-Klasse (siehe «Aufgaben-Bausteine»).

### Empfänger

- Alle Leiter aus `tl_calendar_events_instructor` (Haupt- und Hilfsleiter)
- Zusätzlich der Anmelde-Koordinator, wenn `tl_calendar_events.registrationGoesTo > 0` (Logik wie `EventReminder\PersonProvider::getRegistrationCoordinator()`)
- Der Koordinator sieht dieselben Aufgaben wie die Leiter: Tourenbericht und Teilnahmebestätigung
- Ist der Koordinator auch Leiter desselben Events, erscheint das Event nur einmal in seiner Liste (Rolle `instructor` hat Vorrang)
- Nur `tl_user.disable = 0` mit nicht leerer E-Mail-Adresse
- Eine Benachrichtigung pro (Leiter, Kalender). Leiter mit Events in mehreren Kalendern erhalten mehrere Benachrichtigungen.

### Versandzeitpunkt

- Erste Benachrichtigung: mindestens eine offene Aufgabe in einem fälligen Event und noch kein Log-Eintrag für (userId, calendarId)
- Folgebenachrichtigung: weiterhin offene Aufgaben und `MAX(sentAt) + interval Tage <= jetzt`
- Neu fällig gewordene Events durchbrechen das Intervall nicht
- Events in der Bearbeitungsfrist erscheinen nie in einer Benachrichtigung
- Jeder Versand wird geloggt, das Log wird nicht gelöscht

## Benennung

Präfix überall: `InstructorPostEventTaskReminder` bzw. `instructor_post_event_task_reminder`

## Aufgaben-Bausteine (Task-Klassen)

Jede Aufgabe ist eine eigene Klasse, die `PostEventTaskInterface` implementiert. Eine neue Aufgabe = eine neue Klasse, kein bestehender Code wird angefasst, kein Eintrag in `services.yaml`.

```php
namespace Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\Task;

#[AutoconfigureTag('sacevt.instructor_post_event_task')]
interface PostEventTaskInterface
{
    // Stabiler Schlüssel, z. B. 'tour_report'
    public function getName(): string;

    // Gilt die Aufgabe für dieses Event? (Eventtyp)
    public function supports(CalendarEventsModel $event): bool;

    // Ist die Aufgabe noch offen?
    public function isOpen(CalendarEventsModel $event): bool;

    // Text für die ToDo-Liste (über den Translator), z. B. «Tourenbericht ausfüllen»
    public function getLabel(): string;

    // Direktlink ins Backend
    public function getUrl(CalendarEventsModel $event): string;
}
```

| Klasse | `getName()` | `supports()` | `isOpen()` | Priorität |
|---|---|---|---|---|
| `Task\TourReportTask` | `tour_report` | `tour`, `lastMinuteTour` | `filledInEventReportForm = 0` | 20 |
| `Task\ParticipationConfirmationTask` | `participation_confirmation` | `tour`, `lastMinuteTour`, `course` | akzeptierte Anmeldungen vorhanden, aber keine mit `hasParticipated = 1` | 10 |

Regeln:

- Reihenfolge in der Mail über `#[AsTaggedItem(priority: …)]`, höhere Priorität zuerst.
- Die Klasse liefert Label und Link, das Layout der Liste bleibt in den Twig-Templates (HTML und Text).
- Die gemeinsamen Filter (`published`, `eventState`, `endDate`, Bearbeitungsfrist, Lookback) gelten für alle Aufgaben und bleiben zentral im `OpenTaskProvider`. Eine Task-Klasse entscheidet nur über Eventtyp (`supports()`) und Status (`isOpen()`).
- Die Prüfungen laufen pro Event. Einfache, lesbare Abfragen gehen vor Optimierung. Falls nötig, kann später eine Vorlade-Methode für mehrere Events ins Interface kommen.
- Die Aufgaben gelten für alle Empfänger gleich (Leiter und Koordinator). Das Interface kennt deshalb keine Rolle.

## Datenmodell

### tl_calendar (Legende `instructor_post_event_task_reminder_legend`, alle Felder `exclude => true`)

| Feld | Typ | Default |
|---|---|---|
| `sendInstructorPostEventTaskReminder` | Checkbox, `submitOnChange`, Selector der Subpalette | `false` |
| `instructorPostEventTaskReminderNotification` | Select auf `tl_nc_notification`, nur Typ `instructor_post_event_task_reminder` | `0` |
| `instructorPostEventTaskReminderFirstOffset` | Integer, Bearbeitungsfrist in Tagen nach `endDate` | `7` |
| `instructorPostEventTaskReminderInterval` | Integer, Tage zwischen Benachrichtigungen | `7` |
| `instructorPostEventTaskReminderLookback` | Integer, Tage rückwirkend ab `endDate` | `365` |

Validierung: `rgxp natural`, Minimum 1, `lookback > firstOffset`.

### tl_instructor_post_event_task_reminder_log (nur SQL-DCA, kein Backend-Modul)

| Feld | Beschreibung |
|---|---|
| `id`, `tstamp` | Standard |
| `userId` | `tl_user.id` |
| `calendarId` | `tl_calendar.id` |
| `notificationId` | verwendete Benachrichtigung |
| `sentAt` | Versandzeitpunkt |
| `openTaskCount` | Anzahl offener Aufgaben beim Versand |
| `eventIds` | betroffene Events |
| `delivered` | Ergebnis laut Notification Center |

Index: `(userId, calendarId, sentAt)`

## Klassen

| Klasse | Aufgabe |
|---|---|
| `InstructorPostEventTaskReminder\Task\PostEventTaskInterface` | Interface der Aufgaben-Bausteine |
| `InstructorPostEventTaskReminder\Task\TourReportTask` | Aufgabe Tourenbericht |
| `InstructorPostEventTaskReminder\Task\ParticipationConfirmationTask` | Aufgabe Teilnahmebestätigung |
| `InstructorPostEventTaskReminder\OpenTask` | DTO: eventId, title, eventType, endDate, role (`instructor` oder `registration_coordinator`), tasks (Liste aus name, label, url) |
| `InstructorPostEventTaskReminder\TaskEvaluator` | erhält alle Tasks per `#[AutowireIterator('sacevt.instructor_post_event_task')]`, liefert die offenen Aufgaben eines Events |
| `InstructorPostEventTaskReminder\ReminderSchedule` | reine Logik: Versand fällig? (lastSentAt, interval, now) |
| `InstructorPostEventTaskReminder\OpenTaskProvider` | lädt die in Frage kommenden Events per SQL (gemeinsame Filter), prüft sie über den `TaskEvaluator`; `getOpenTasks(userId, calendar)`, `getUserIdsWithOpenTasks(calendar)`; Empfänger = Leiter ∪ Koordinator; nicht readonly (mockbar) |
| `InstructorPostEventTaskReminder\TaskItem` | DTO einer offenen Aufgabe: name, label, url |
| `InstructorPostEventTaskReminder\DataContainer\Calendar` | tl_calendar-Callbacks: Notification-Optionen (nur passender Typ), Validierung Lookback > Bearbeitungsfrist |
| `InstructorPostEventTaskReminder\ReminderLog` | `getLastSentAt(userId, calendarId)`, Log schreiben |
| `Cron\InstructorPostEventTaskReminderCron` | `45 3,4 * * *` (zweiter Lauf fängt Verpasstes auf, das Intervall verhindert Duplikate), dispatcht Messages |
| `Messenger\Message\SendInstructorPostEventTaskReminderMessage` | userId, calendarId; `LowPriorityMessageInterface` |
| `Messenger\MessageHandler\SendInstructorPostEventTaskReminderHandler` | prüft, loggt, versendet |
| `NotificationType\InstructorPostEventTaskReminderNotificationType` | `NAME = 'instructor_post_event_task_reminder'` |

Eine Benachrichtigung entspricht genau einer Message. Die Message trägt nur IDs.

## Ablauf Handler

1. Kalender aktiv, Notification gesetzt, Leiter aktiv mit E-Mail? Sonst return.
2. Aufgaben neu berechnen über `OpenTaskProvider`. Keine Aufgaben → return.
3. Intervall erneut gegen das Log prüfen (Schutz vor doppeltem Cron-Lauf).
4. Symfony-Lock auf `userId-calendarId` rund um Prüfen, Loggen und Senden.
5. Log-Eintrag vor dem Senden schreiben (lieber eine Mail zu wenig als doppelt).
6. `NotificationCenter::sendNotification($id, $tokens, $sacevtLocale)`, `delivered` nachführen, Fehler an `contaoErrorLogger`.

## Notification-Tokens

- E-Mail: `recipient_email`, `instructor_email`
- Leiter: `instructor_firstname`, `instructor_lastname`, `instructor_name`
- Kalender: `calendar_title`
- Liste: `task_list_html`, `task_list_text`
- Zähler: `open_task_count`, `event_count`
- Einstellungen: `first_offset_days`, `interval_days`, `lookback_days`
- Versand: `reminder_count`
- Links: `link_my_events_dashboard`

## Templates

`templates/Email/InstructorPostEventTaskReminder/task_list.html.twig` und `task_list.txt.twig`, gerendert über `@MarkocupicSacEventTool/...`.
Jede Zeile: Datum, Titel, Rolle des Empfängers (z. B. «als Anmelde-Koordinator»), offene Aufgaben mit Label und Direktlink aus der jeweiligen Task-Klasse:
- `TourReportTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events&act=edit&id={id}&call=writeTourReport`
- `ParticipationConfirmationTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events_member&id={id}`

URL-Erzeugung wie im `MyEventsDashboardController`. Der Cron läuft per CLI, daher muss `framework.router.default_uri` gesetzt sein.

## Übrige Dateien

- `contao/dca/tl_calendar.php`, `contao/dca/tl_instructor_post_event_task_reminder_log.php`
- `contao/languages/en/...` (deutscher Inhalt; wird per composer-file-copier nach `de` kopiert)
- `config/services.yaml`: Autowiring, falls nötig `$sacevtLocale` binden

## Tests (PHPUnit 9.6, `composer unit-tests`)

- `TourReportTaskTest`: `supports()` je Eventtyp, `isOpen()` mit und ohne Bericht
- `ParticipationConfirmationTaskTest`: `supports()` je Eventtyp; keine Teilnehmer, keine Bestätigung, mindestens eine Bestätigung, hasParticipated nur bei nicht akzeptierter Anmeldung
- `TaskEvaluatorTest`: nur unterstützte und offene Tasks, Reihenfolge nach Priorität (mit Dummy-Tasks)
- `OpenTaskProvider`: gemeinsame Filter (abgesagt, verschoben, unveröffentlicht, Bearbeitungsfrist, Lookback, `endDate = 0`), Koordinator ohne Leiterrolle, Koordinator gleichzeitig Leiter (keine Duplikate), Koordinator deaktiviert oder ohne E-Mail
- `ReminderScheduleTest`: erste Mail, Intervall nicht erreicht oder erreicht, Grenzen am Tageswechsel
- `SendInstructorPostEventTaskReminderHandlerTest` mit Mocks
- optional DB-Tests für `OpenTaskProvider` und `ReminderLog`

## Umsetzungsreihenfolge

1. `PostEventTaskInterface`, `TourReportTask`, `ParticipationConfirmationTask`, `TaskEvaluator`, `ReminderSchedule` mit Tests
2. DCA `tl_calendar`, Log-Tabelle, Sprachdateien
3. `OpenTaskProvider`, `ReminderLog`
4. Notification-Typ, Twig-Templates
5. Message, Handler und Tests
6. Cron
7. Manueller Test auf sac-pilatus (`contao:cron`, Worker, Log prüfen)
