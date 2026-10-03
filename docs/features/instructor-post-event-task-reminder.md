# Feature: Instructor Post-Event Task Reminder

Bundle: `markocupic/sac-event-tool-bundle` (bleibt im Bundle, kein eigenes Bundle)
Status: Spezifikation verabschiedet am 2026-10-01, umgesetzt am 2026-10-01, erweitert am 2026-10-02 (kein Lookback, verschobene Events, Eventtypen im Kalender, Backend-Modul für das Log)
Vorbild: bestehender Event-Reminder (`EventReminderCron`, `SendEventReminderMessage`, `SendEventReminderHandler`, `EventReminderNotificationType`, `EventReminder\PersonProvider`)

## Ziel

Leiter (Haupt- und Hilfsleiter) sowie der Anmelde-Koordinator (`registrationGoesTo`), alle aus `tl_user`, werden per Notification Center an offene Aufgaben nach durchgeführten Events erinnert. Pro Empfänger und Kalender eine Benachrichtigung mit ToDo-Liste über alle fälligen Events dieses Kalenders. Keine offenen Aufgaben bedeutet keine Benachrichtigung.

## Fachliche Regeln

### Geprüfte Events (alle Bedingungen müssen gelten)

- Kalender hat `sendInstructorPostEventTaskReminder = 1` und eine Notification gesetzt
- Eventtyp: nur die im Kalender gewählten Typen (`instructorPostEventTaskReminderEventTypes`, Mehrfachauswahl aus `tour`, `lastMinuteTour`, `course`, `generalEvent`). Ist keiner gewählt, wird nichts geprüft. Welche Aufgaben bei einem Typ anfallen, entscheidet zusätzlich jede Task-Klasse über `supports()` (heute: Tourenbericht bei `tour` und `lastMinuteTour`, Teilnahmebestätigung bei allen).
- `published = 1`
- `eventState != 'event_canceled'`
- Verschobene Events (`eventState = 'event_rescheduled'`) nur, wenn `rescheduledEventDate` eingetragen ist. `rescheduledEventDate` ist der neue Starttag; das massgebende Enddatum wird verschoben (siehe «Verschobene Events»)
- Nicht verschobene Events: `endDate > 0`. `endDate` ist der letzte Termin aus `eventDates`, er wird beim Speichern in `DataContainer\CalendarEvents` gesetzt.
- Bearbeitungsfrist abgelaufen: `Enddatum + firstOffset Tage <= heute` (Enddatum = `endDate`, bei verschobenen Events das verschobene Enddatum), gerechnet in ganzen Kalendertagen (Tagesbeginn, Zeitzone wie bei `EventReminderCron`)
- Kein Lookback: Es werden alle Events des Kalenders geprüft, unabhängig davon, wie lange ihr Ende zurückliegt. Offene Aufgaben werden erinnert, bis sie erledigt sind.

### Verschobene Events

Für verschobene Events gilt `rescheduledEventDate` als neuer Starttag. Die ursprüngliche Dauer in Kalendertagen (Tag von `startDate` bis Tag von `endDate`) wird dazugezählt:

> verschobenes Enddatum = `rescheduledEventDate` + (Tag von `endDate` − Tag von `startDate`)

- Eintägige Tour, verschoben auf den 24.01. → Ende 24.01.
- Sa 10.01.–So 11.01., verschoben auf den 24.01. → Ende 25.01.
- Kurs über zwei Wochenenden 10.01.–18.01. (8 Tage), verschoben auf den 07.02. → Ende 15.02.

Gerechnet wird in ganzen Kalendertagen, die Zeitumstellung hat keinen Einfluss. Ohne eingetragenes Verschiebedatum wird ein verschobenes Event nicht geprüft. In der Benachrichtigung wird das verschobene Enddatum angezeigt. Annahme: Das verschobene Event dauert gleich lange wie das ursprüngliche (entschieden am 2026-10-02, Variante A).

### Aufgaben pro Event

| Eventtyp | Tourenbericht | Teilnahme bestätigt |
|---|---|---|
| `tour`, `lastMinuteTour` | offen, wenn `filledInEventReportForm = 0` | offen, siehe unten |
| `course`, `generalEvent` | - | offen, siehe unten |

Bei `generalEvent` wird nur die Teilnahmebestätigung verlangt (entschieden am 2026-10-02): Das Backend bietet den Tourrapport-Button (Teilnehmerliste, Event-Dashboard, «Meine Events») nur bei Touren an.

Für die Teilnahme-Aufgabe zählen nur Anmeldungen in `tl_calendar_events_member` mit `eventId = event.id` und dem Status `subscription-accepted` **oder** `subscription-on-waiting-list` (`EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED`, geändert am 2026-10-03):
- Die Aufgabe existiert nur, wenn es mindestens eine solche Anmeldung gibt.
- Die Aufgabe ist erledigt, sobald mindestens eine dieser Anmeldungen `hasParticipated = 1` hat.
- Auch ein Event, das nur Wartelisten-Anmeldungen hat, löst eine Erinnerung aus, solange keine dieser Anmeldungen `hasParticipated = 1` hat.
- Anmeldungen mit einem anderen Status werden ignoriert. Im Backend lässt sich die Teilnahme bei anderen Status ohnehin nicht setzen.

Jede Spalte dieser Tabelle ist eine eigene Task-Klasse (siehe «Aufgaben-Bausteine»).

### Empfänger

- Alle Leiter aus `tl_calendar_events_instructor` (Haupt- und Hilfsleiter)
- Zusätzlich der Anmelde-Koordinator, wenn `tl_calendar_events.registrationGoesTo > 0` (Logik wie `EventReminder\PersonProvider::getRegistrationCoordinator()`)
- Der Koordinator sieht dieselben Aufgaben wie die Leiter: Tourenbericht und Teilnahmebestätigung
- Ist der Koordinator auch Leiter desselben Events, erscheint das Event nur einmal in seiner Liste (Rolle `instructor` hat Vorrang)
- Nur `tl_user.disable = 0` mit nicht leerer E-Mail-Adresse
- Eine Benachrichtigung pro (Empfänger, Kalender). Wer Events in mehreren Kalendern leitet oder koordiniert, erhält mehrere Benachrichtigungen.

### Versandzeitpunkt

- Erste Benachrichtigung: mindestens eine offene Aufgabe in einem fälligen Event und noch kein Log-Eintrag für (userId, calendarId)
- Folgebenachrichtigung: weiterhin offene Aufgaben und `sentAt` (letzter Versand) `+ interval Tage <= jetzt`
- Neu fällig gewordene Events durchbrechen das Intervall nicht
- Events in der Bearbeitungsfrist erscheinen nie in einer Benachrichtigung
- Jeder Versand wird geloggt: pro Empfänger und Kalender genau ein Log-Eintrag mit den Angaben zur letzten Erinnerung und der Gesamtzahl der Erinnerungen. Das Log wird nicht gelöscht

## Benennung

Präfix überall: `InstructorPostEventTaskReminder` bzw. `instructor_post_event_task_reminder`

Ort im Bundle: `src/Feature/InstructorPostEventTaskReminder/` (Namespace `Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder`), Tests unter `tests/Feature/InstructorPostEventTaskReminder/`. Konvention: jedes Feature liegt in einem eigenen Ordner unter `src/Feature/`.

## Aufgaben-Bausteine (Task-Klassen)

Jede Aufgabe ist eine eigene Klasse, die `PostEventTaskInterface` implementiert. Eine neue Aufgabe = eine neue Klasse, kein bestehender Code wird angefasst, kein Eintrag in `services.yaml`.

```php
namespace Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task;

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
| `Task\ParticipationConfirmationTask` | `participation_confirmation` | `tour`, `lastMinuteTour`, `course`, `generalEvent` | akzeptierte oder Wartelisten-Anmeldungen vorhanden, aber keine davon mit `hasParticipated = 1` | 10 |

Regeln:

- Reihenfolge in der Mail über `#[AsTaggedItem(priority: …)]`, höhere Priorität zuerst.
- Die Klasse liefert Label und Link, das Layout der Liste bleibt in den Twig-Templates (HTML und Text).
- Die gemeinsamen Filter (Eventtypen des Kalenders, `published`, `eventState`, Enddatum inkl. Verschiebung, Bearbeitungsfrist) gelten für alle Aufgaben und bleiben zentral im `OpenTaskProvider`. Eine Task-Klasse entscheidet nur über Eventtyp (`supports()`) und Status (`isOpen()`).
- Die Prüfungen laufen pro Event. Einfache, lesbare Abfragen gehen vor Optimierung. Falls nötig, kann später eine Vorlade-Methode für mehrere Events ins Interface kommen.
- Die Aufgaben gelten für alle Empfänger gleich (Leiter und Koordinator). Das Interface kennt deshalb keine Rolle.

## Datenmodell

### tl_calendar (Legende `instructor_post_event_task_reminder_legend`, alle Felder `exclude => true`)

| Feld | Typ | Default |
|---|---|---|
| `sendInstructorPostEventTaskReminder` | Checkbox, `submitOnChange`, Selector der Subpalette | `false` |
| `instructorPostEventTaskReminderNotification` | Select auf `tl_nc_notification`, nur Typ `instructor_post_event_task_reminder` | `0` |
| `instructorPostEventTaskReminderEventTypes` | Select, Mehrfachauswahl mit Chosen, Optionen `EventType::ALL`, Pflichtfeld, `blob` (serialisiert) | `tour`, `lastMinuteTour`, `course` (nur bei neuen Kalendern) |
| `instructorPostEventTaskReminderFirstOffset` | Select 1–30, Bearbeitungsfrist in Tagen nach dem massgebenden Enddatum | `7` |
| `instructorPostEventTaskReminderInterval` | Select 1–30, Tage zwischen Benachrichtigungen | `7` |

Ein Feld für den Lookback gibt es bewusst nicht (entfernt am 2026-10-02).

### tl_instructor_post_event_task_reminder_log

| Feld | Beschreibung |
|---|---|
| `id`, `tstamp` | Standard |
| `userId` | `tl_user.id` |
| `calendarId` | `tl_calendar.id` |
| `notificationId` | verwendete Benachrichtigung |
| `sentAt` | Zeitpunkt der letzten Erinnerung |
| `reminderCount` | Anzahl Erinnerungen für (userId, calendarId) insgesamt; wird bei jeder Erinnerung um 1 erhöht (auch wenn nicht zugestellt). Nach dem Versand gleicher Wert wie das Token `##reminder_count##` |
| `openTaskCount` | Anzahl offener Aufgaben bei der letzten Erinnerung |
| `eventIds` | betroffene Events der letzten Erinnerung |
| `delivered` | Ergebnis der letzten Erinnerung laut Notification Center |

Unique Index: `(userId, calendarId)`, also genau ein Eintrag pro Empfänger und Kalender (entschieden am 2026-10-03, damit die Tabelle nicht wächst). Geschrieben wird mit `INSERT … ON DUPLICATE KEY UPDATE` (`ReminderLog::logNotification()`): beim ersten Versand neu mit `reminderCount = 1`, danach werden die Angaben überschrieben und `reminderCount` um 1 erhöht. Eine Historie der einzelnen Versände gibt es damit nicht mehr

Backend-Modul «Log Leiter-Erinnerungen» (`sac_instructor_post_event_task_reminder_log` in `sac_be_modules`), **nur lesen**:

- DCA: Palette und `inputType` sind für alle Felder vorbereitet (falls Bearbeiten einmal freigeschaltet wird), aktuell aber gesperrt: `closed`, `notCreatable`, `notEditable`, `notDeletable`, `notCopyable`, `notSortable`; keine globalen Operationen, einzige Operation `show`
- Liste nach Versanddatum gruppiert (neueste zuerst), Filter nach Empfänger, Kalender und Zustellung; sortierbar nach Versanddatum, Kalender, Zähler, offenen Aufgaben und Zustellung
- Spalten: Zuletzt versendet am, Empfänger (Name und E-Mail), Kalender, Zähler (`reminderCount`, Anzahl Erinnerungen insgesamt), offene Aufgaben, Events (Titel mit ID), zugestellt; formatiert durch `DataContainer\ReminderLogTable` (Label-Callback). Gelöschte User, Kalender oder Events erscheinen mit ID und «(gelöscht)»
- Sichtbar für Admins; andere Backend-User brauchen das Modul in ihren Rechten

## Klassen

| Klasse | Aufgabe |
|---|---|
| `Feature\InstructorPostEventTaskReminder\Task\PostEventTaskInterface` | Interface der Aufgaben-Bausteine |
| `Feature\InstructorPostEventTaskReminder\Task\TourReportTask` | Aufgabe Tourenbericht |
| `Feature\InstructorPostEventTaskReminder\Task\ParticipationConfirmationTask` | Aufgabe Teilnahmebestätigung |
| `Feature\InstructorPostEventTaskReminder\OpenTask` | DTO: eventId, title, eventType, endDate (massgebendes Enddatum, bei verschobenen Events das verschobene), role (`instructor` oder `registration_coordinator`), tasks (Liste aus name, label, url) |
| `Feature\InstructorPostEventTaskReminder\TaskEvaluator` | erhält alle Tasks per `#[AutowireIterator('sacevt.instructor_post_event_task')]`, liefert die offenen Aufgaben eines Events |
| `Feature\InstructorPostEventTaskReminder\ReminderSchedule` | reine Logik: Fälligkeit nach Bearbeitungsfrist, massgebendes bzw. verschobenes Enddatum (`getEffectiveEndDate()`, `getRescheduledEndDate()`), Versand fällig? (lastSentAt, interval, now) |
| `Feature\InstructorPostEventTaskReminder\OpenTaskProvider` | lädt die in Frage kommenden Events per SQL (`fetchCandidateEvents()`), bestimmt in PHP das massgebende Enddatum und die Fälligkeit (`findDueEvents()`), prüft sie über den `TaskEvaluator` und ordnet sie den Empfängern zu (Leiter ∪ Koordinator). Öffentlich: `getOpenTasksByRecipient(calendar, now)`, `getOpenTasks(userId, calendar, now)`, `getRecipientIdsWithOpenTasks(calendar, now)`, `getRecipient(userId)` (aktiv, mit E-Mail). Nicht readonly (mockbar) |
| `Feature\InstructorPostEventTaskReminder\TaskItem` | DTO einer offenen Aufgabe: name, label, url |
| `Feature\InstructorPostEventTaskReminder\DataContainer\ReminderLogTable` | Label-Callback für das Backend-Modul des Logs (nur lesen) |
| `Feature\InstructorPostEventTaskReminder\DataContainer\Calendar` | tl_calendar-Callback: Notification-Optionen (nur passender Typ) |
| `Feature\InstructorPostEventTaskReminder\ReminderLog` | `getLastSentAt(userId, calendarId)` (`sentAt`), `countSent(userId, calendarId)` (`reminderCount`), `logNotification(userId, calendarId, notificationId, sentAt, openTaskCount, eventIds)` (Insert bzw. Update mit `reminderCount + 1`, gibt die Log-ID zurück), `markAsDelivered(logId)` |
| `Feature\InstructorPostEventTaskReminder\Cron\InstructorPostEventTaskReminderCron` | `45 1,4 * * *` (zweiter Lauf fängt Verpasstes auf, das Intervall verhindert Duplikate), dispatcht Messages. Misst die Laufzeit mit der Symfony Stopwatch und schreibt sie ins Contao-Systemlog (siehe «Laufzeit») |
| `Feature\InstructorPostEventTaskReminder\Messenger\Message\SendInstructorPostEventTaskReminderMessage` | userId, calendarId; `LowPriorityMessageInterface` |
| `Feature\InstructorPostEventTaskReminder\Messenger\MessageHandler\SendInstructorPostEventTaskReminderHandler` | prüft, loggt, versendet |
| `Feature\InstructorPostEventTaskReminder\NotificationType\InstructorPostEventTaskReminderNotificationType` | `NAME = 'instructor_post_event_task_reminder'` |

Eine Benachrichtigung entspricht genau einer Message. Die Message trägt nur IDs.

## Ordnerstruktur

Alle Klassen des Features liegen in einem Ordner. Nur DCA, Sprachdateien und Templates bleiben an ihren Contao- bzw. Symfony-Pfaden.

```
src/Feature/InstructorPostEventTaskReminder/
├── Cron/InstructorPostEventTaskReminderCron.php
├── DataContainer/
│   ├── Calendar.php
│   └── ReminderLogTable.php
├── Messenger/
│   ├── Message/SendInstructorPostEventTaskReminderMessage.php
│   └── MessageHandler/SendInstructorPostEventTaskReminderHandler.php
├── NotificationType/InstructorPostEventTaskReminderNotificationType.php
├── Task/
│   ├── PostEventTaskInterface.php
│   ├── ParticipationConfirmationTask.php
│   └── TourReportTask.php
├── OpenTask.php
├── OpenTaskProvider.php
├── ReminderLog.php
├── ReminderSchedule.php
├── TaskEvaluator.php
└── TaskItem.php
```

Die Tests liegen spiegelbildlich unter `tests/Feature/InstructorPostEventTaskReminder/`.

## Laufzeit

Der Cron misst den ganzen Lauf (Suche über alle aktivierten Kalender und Dispatch der Messages) mit der Symfony Stopwatch und schreibt ins Contao-Systemlog, z. B.:

`Instructor post-event task reminder cron: checked 4 calendar(s) and dispatched 12 message(s) in 1.83 s.`

Der Mailversand selbst läuft getrennt im Messenger-Worker und ist nicht enthalten. Die Laufzeit wächst mit der Anzahl fälliger Events (pro Event eine Abfrage für die Teilnahmebestätigung und eine für die Leiter). Da es keinen Lookback gibt, zählen dazu alle vergangenen Events der gewählten Typen im Kalender, auch solche ohne offene Aufgaben. Der Cron sollte per CLI (`contao:cron`) laufen, dort gilt standardmässig keine `max_execution_time`.

## Ablauf Handler

1. Kalender aktiv, Notification gesetzt, Empfänger aktiv mit E-Mail? Sonst return.
2. Symfony-Lock auf `userId-calendarId` holen. Ist er belegt, arbeitet ein anderer Worker daran → return.
3. Intervall erneut gegen das Log prüfen (Schutz vor doppeltem Cron-Lauf). Nicht abgelaufen → return.
4. Aufgaben neu berechnen über `OpenTaskProvider`. Keine Aufgaben → return.
5. Log-Eintrag vor dem Senden schreiben bzw. aktualisieren und `reminderCount` erhöhen (lieber eine Mail zu wenig als doppelt).
6. `NotificationCenter::sendNotification($id, $tokens, $sacevtLocale)`; bei Erfolg `delivered` setzen, sonst Fehler an `contaoErrorLogger`. Lock freigeben.

## Notification-Tokens

- E-Mail: `recipient_email`, `instructor_email`
- Leiter: `instructor_firstname`, `instructor_lastname`, `instructor_name`
- Kalender: `calendar_title`
- Liste: `task_list_html`, `task_list_text`
- Zähler: `open_task_count`, `event_count`
- Einstellungen: `first_offset_days`, `interval_days`
- Versand: `reminder_count`
- Links: `link_my_events_dashboard`

## Templates

`templates/Email/InstructorPostEventTaskReminder/task_list.html.twig` und `task_list.txt.twig`, gerendert über `@MarkocupicSacEventTool/...`.
Jede Zeile: massgebendes Enddatum (bei verschobenen Events das verschobene), Titel, Rolle des Empfängers (z. B. «als Anmelde-Koordinator»), offene Aufgaben mit Label und Direktlink aus der jeweiligen Task-Klasse:
- `TourReportTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events&act=edit&id={id}&call=writeTourReport`
- `ParticipationConfirmationTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events_member&id={id}`

URL-Erzeugung wie im `MyEventsDashboardController`. Der Cron läuft per CLI, daher muss `framework.router.default_uri` gesetzt sein.

## Übrige Dateien

- `contao/dca/tl_calendar.php`, `contao/dca/tl_instructor_post_event_task_reminder_log.php`
- `contao/config/config.php` (Backend-Modul), `contao/languages/en/modules.php`, `contao/languages/en/tl_instructor_post_event_task_reminder_log.php`
- `contao/languages/en/...` (deutscher Inhalt; wird per composer-file-copier nach `de` kopiert)
- `config/services.yaml`: keine Änderung nötig (Autowiring; `$sacevtLocale` ist bereits gebunden)

## Tests (PHPUnit 9.6, `composer unit-tests`)

- `TourReportTaskTest`: `supports()` je Eventtyp, `isOpen()` mit und ohne Bericht
- `ParticipationConfirmationTaskTest`: `supports()` je Eventtyp; keine akzeptierten oder Wartelisten-Anmeldungen, keine Bestätigung, mindestens eine Bestätigung; Abfrage berücksichtigt nur akzeptierte und Wartelisten-Anmeldungen
- `TaskEvaluatorTest`: nur unterstützte und offene Tasks, Reihenfolge nach Priorität (mit Dummy-Tasks)
- `OpenTaskProviderTest`: Zuordnung zu den Empfängern: Leiter, Koordinator ohne Leiterrolle, Koordinator gleichzeitig Leiter (keine Duplikate), deaktivierte User und User ohne E-Mail, Events ohne offene Aufgaben, mehrere Events pro Empfänger, verschobene Events (fällig, noch nicht fällig, ohne Verschiebedatum), Sortierung nach massgebendem Enddatum, Eventtypen des Kalenders (keine gewählt, Übergabe an die Abfrage)
- `ReminderScheduleTest`: Bearbeitungsfrist an den Tagesgrenzen (inkl. Zeitumstellung), verschobenes Enddatum (eintägig, mehrtägig, zwei Wochenenden, Zeitumstellung, ohne Verschiebedatum), erste Mail, Intervall nicht erreicht oder erreicht
- `SendInstructorPostEventTaskReminderHandlerTest` (Mocks): Feature deaktiviert, keine Notification, ungültiger Empfänger, Intervall nicht abgelaufen, alles erledigt, Lock belegt, Log vor Versand (`logNotification()`) und Tokens (inkl. `reminder_count`)
- `InstructorPostEventTaskReminderCronTest`: eine Message pro fälligem Empfänger und Kalender, Intervall wird beachtet

Nicht durch Unit-Tests abgedeckt: das SQL in `ReminderLog` (u. a. `ON DUPLICATE KEY UPDATE`), die SQL-Abfrage in `OpenTaskProvider::fetchCandidateEvents()` (Eventtyp, veröffentlicht, nicht abgesagt, `endDate`, Verschiebedatum) und die Abfrage in `ParticipationConfirmationTask::isOpen()` gegen eine echte Datenbank. Die Datumsgrenzen dazu prüft `ReminderScheduleTest`. Optional: DB-Tests für `OpenTaskProvider` und `ReminderLog` nach dem Muster von `CalendarEventsUtilDatabaseTest`.

## Inbetriebnahme

1. `contao:migrate` (neue Kalenderfelder, Log-Tabelle). Auf Testinstallationen, deren Log noch aus der Zeit vor dem Unique Index `(userId, calendarId)` stammt (mehrere Einträge pro Empfänger und Kalender), die Tabelle vorher leeren (`TRUNCATE tl_instructor_post_event_task_reminder_log`), sonst bricht das Anlegen des Index ab. Wer eine frühe Version mit dem Feld `instructorPostEventTaskReminderLookback` migriert hat: Die Spalte wird nur mit `--with-deletes` bzw. Bestätigung im Install Tool entfernt
2. Im Notification Center eine Benachrichtigung vom Typ «Leiter-Erinnerung an offene Aufgaben nach dem Event» anlegen: Empfänger `##recipient_email##`, Text mit `##task_list_text##` bzw. `##task_list_html##`
3. Im Kalender das Feature aktivieren, Benachrichtigung wählen, Event-Typen, Bearbeitungsfrist und Intervall prüfen. Bei Kalendern, in denen das Feature schon vor dem Feld «Berücksichtigte Event-Typen» aktiviert war, ist das Feld leer: Typen wählen und speichern, sonst wird nichts geprüft
4. Cache leeren (`cache:clear`), damit das Backend-Modul «Log Leiter-Erinnerungen» erscheint; Nicht-Admins das Modul in den Rechten zuweisen
5. `framework.router.default_uri` setzen, damit die Links in den vom Cron versandten Mails auf die richtige Domain zeigen
6. Cron per CLI laufen lassen und Messenger-Worker betreiben (bzw. `messenger:consume`)
7. Kontrolle: Systemlog (Laufzeit, Anzahl Messages) und Backend-Modul «Log Leiter-Erinnerungen» (pro Empfänger und Kalender ein Eintrag; «Zuletzt versendet am» und «Zähler» ändern sich höchstens einmal pro Intervall)
