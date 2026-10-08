# Feature: Event Completion Reminder

Bundle: `markocupic/sac-event-tool-bundle`

## Ziel

Leiter (Haupt- und Hilfsleiter) sowie der Anmelde-Koordinator (`registrationGoesTo`), alle aus `tl_user`, werden per Notification Center an offene Aufgaben nach durchgeführten Events erinnert. Pro Empfänger und Kalender eine Benachrichtigung mit ToDo-Liste über alle fälligen Events dieses Kalenders. Keine offenen Aufgaben bedeutet keine Benachrichtigung.

## Fachliche Regeln

### Geprüfte Events (alle Bedingungen müssen gelten)

- Kalender hat `sendEventCompletionReminder = 1` und eine Notification gesetzt
- Eventtyp: nur die im Kalender gewählten Typen (`eventCompletionReminderEventTypes`, Mehrfachauswahl aus `tour`, `lastMinuteTour`, `course`, `generalEvent`). Ist keiner gewählt, wird nichts geprüft. Welche Aufgaben bei einem Typ anfallen, entscheidet zusätzlich jede Task-Klasse über `supports()` (heute: Tourenbericht bei `tour` und `lastMinuteTour`, Teilnahmebestätigung bei allen).
- `published = 1`
- `eventState != 'event_canceled'`
- Verschobene Events (`eventState = 'event_rescheduled'`) nur, wenn `rescheduledEventDate` eingetragen ist. `rescheduledEventDate` ist der neue Starttag; das massgebende Enddatum wird verschoben (siehe «Verschobene Events»)
- Nicht verschobene Events: `endDate > 0`. `endDate` ist der letzte Termin aus `eventDates`, er wird beim Speichern in `DataContainer\CalendarEvents` gesetzt.
- Bearbeitungsfrist abgelaufen: `Enddatum + firstOffset Tage <= heute` (Enddatum = `endDate`, bei verschobenen Events das verschobene Enddatum), gerechnet in ganzen Kalendertagen (Tagesbeginn, Zeitzone wie bei `EventReminderCron`)
- Es werden alle Events des Kalenders geprüft, unabhängig davon, wie lange ihr Ende zurückliegt. Offene Aufgaben werden erinnert, bis sie erledigt sind.

### Verschobene Events

Für verschobene Events gilt `rescheduledEventDate` als neuer Starttag. Die ursprüngliche Dauer in Kalendertagen (Tag von `startDate` bis Tag von `endDate`) wird dazugezählt:

> verschobenes Enddatum = `rescheduledEventDate` + (Tag von `endDate` − Tag von `startDate`)

- Eintägige Tour, verschoben auf den 24.01. → Ende 24.01.
- Sa 10.01.–So 11.01., verschoben auf den 24.01. → Ende 25.01.
- Kurs über zwei Wochenenden 10.01.–18.01. (8 Tage), verschoben auf den 07.02. → Ende 15.02.

Gerechnet wird in ganzen Kalendertagen, die Zeitumstellung hat keinen Einfluss. Ohne eingetragenes Verschiebedatum wird ein verschobenes Event nicht geprüft. In der Benachrichtigung wird das verschobene Enddatum angezeigt. Annahme: Das verschobene Event dauert gleich lange wie das ursprüngliche.

### Aufgaben pro Event

| Eventtyp | Tourenbericht | Teilnahme bestätigt |
|---|---|---|
| `tour`, `lastMinuteTour` | offen, wenn `filledInEventReportForm = 0` | offen, siehe unten |
| `course`, `generalEvent` | - | offen, siehe unten |

Bei `generalEvent` wird nur die Teilnahmebestätigung verlangt: Das Backend bietet den Tourrapport-Button (Teilnehmerliste, Event-Dashboard, «Meine Events») nur bei Touren an.

Für die Teilnahme-Aufgabe zählen nur Anmeldungen in `tl_calendar_events_member` mit `eventId = event.id` und dem Status `subscription-accepted` **oder** `subscription-on-waiting-list` (`EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED`):
- Die Aufgabe existiert nur, wenn es mindestens eine solche Anmeldung gibt.
- Die Aufgabe ist erledigt, sobald mindestens eine dieser Anmeldungen `hasParticipated = 1` hat.
- Auch ein Event, das nur Wartelisten-Anmeldungen hat, löst eine Erinnerung aus, solange keine dieser Anmeldungen `hasParticipated = 1` hat.
- Anmeldungen mit einem anderen Status werden ignoriert. Im Backend lässt sich die Teilnahme bei anderen Status ohnehin nicht setzen.

Jede Spalte dieser Tabelle ist eine eigene Task-Klasse (siehe «Aufgaben-Bausteine»).

### Empfänger

- Alle Leiter aus `tl_calendar_events_instructor` (Haupt- und Hilfsleiter)
- Zusätzlich der Anmelde-Koordinator, wenn `tl_calendar_events.registrationGoesTo > 0` (Logik wie `Feature\EventReminder\PersonProvider::getRegistrationCoordinator()`)
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

Präfix überall: `EventCompletionReminder` bzw. `event_completion_reminder`

Ort im Bundle: `src/Feature/EventCompletionReminder/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventCompletionReminder`), Tests unter `tests/Feature/EventCompletionReminder/`. Konvention: jedes Feature liegt in einem eigenen Ordner unter `src/Feature/`.

## Aufgaben-Bausteine (Task-Klassen)

Jede Aufgabe ist eine eigene Klasse, die `PostEventTaskInterface` implementiert. Eine neue Aufgabe = eine neue Klasse, kein bestehender Code wird angefasst, kein Eintrag in `services.yaml`.

```php
namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Task;

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
- Die Klasse liefert Label und Link, das Layout der Liste bleibt in den Twig-Templates (HTML und Text). Ein optionales HTML-Label kommt aus den Sprachdateien (siehe Templates).
- Die gemeinsamen Filter (Eventtypen des Kalenders, `published`, `eventState`, Enddatum inkl. Verschiebung, Bearbeitungsfrist) gelten für alle Aufgaben und bleiben zentral im `OpenTaskProvider`. Eine Task-Klasse entscheidet nur über Eventtyp (`supports()`) und Status (`isOpen()`).
- Die Prüfungen laufen pro Event. Einfache, lesbare Abfragen gehen vor Optimierung.
- Die Aufgaben gelten für alle Empfänger gleich (Leiter und Koordinator). Das Interface kennt deshalb keine Rolle.

## Datenmodell

### tl_calendar (Legende `event_completion_reminder_legend`, alle Felder `exclude => true`)

| Feld | Typ | Default |
|---|---|---|
| `sendEventCompletionReminder` | Checkbox, `submitOnChange`, Selector der Subpalette | `false` |
| `eventCompletionReminderNotification` | Select auf `tl_nc_notification`, nur Typ `event_completion_reminder` | `0` |
| `eventCompletionReminderEventTypes` | Select, Mehrfachauswahl mit Chosen, Optionen `EventType::ALL`, Pflichtfeld, `blob` (serialisiert) | `tour`, `lastMinuteTour`, `course` (nur bei neuen Kalendern) |
| `eventCompletionReminderFirstOffset` | Select 1–30, Bearbeitungsfrist in Tagen nach dem massgebenden Enddatum | `7` |
| `eventCompletionReminderInterval` | Select 1–30, Tage zwischen Benachrichtigungen | `7` |

### tl_event_completion_reminder_log

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

Unique Index: `(userId, calendarId)`, also genau ein Eintrag pro Empfänger und Kalender (damit die Tabelle nicht wächst). Geschrieben wird mit `INSERT … ON DUPLICATE KEY UPDATE` (`ReminderLog::logNotification()`): beim ersten Versand neu mit `reminderCount = 1`, danach werden die Angaben überschrieben und `reminderCount` um 1 erhöht. Eine Historie der einzelnen Versände gibt es nicht.

Backend-Modul «Reminder für Event-Abschluss» (`sac_event_completion_reminder_log` in `sac_be_modules`), **nur lesen**:

- DCA gesperrt: `closed`, `notCreatable`, `notEditable`, `notDeletable`, `notCopyable`, `notSortable`; keine globalen Operationen, einzige Operation `show`
- Liste nach Versanddatum gruppiert (neueste zuerst), Filter nach Empfänger, Kalender und Zustellung; sortierbar nach Versanddatum, Kalender, Zähler, offenen Aufgaben und Zustellung
- Spalten: Zuletzt versendet am, Empfänger (Name und E-Mail), Kalender, Zähler (`reminderCount`, Anzahl Erinnerungen insgesamt), offene Aufgaben, Events (Titel mit ID), zugestellt; formatiert durch `DataContainer\ReminderLogTable` (Label-Callback). Gelöschte User, Kalender oder Events erscheinen mit ID und «(gelöscht)»
- Sichtbar für Admins; andere Backend-User brauchen das Modul in ihren Rechten

## Klassen

| Klasse | Aufgabe |
|---|---|
| `Feature\EventCompletionReminder\Task\PostEventTaskInterface` | Interface der Aufgaben-Bausteine |
| `Feature\EventCompletionReminder\Task\TourReportTask` | Aufgabe Tourenbericht |
| `Feature\EventCompletionReminder\Task\ParticipationConfirmationTask` | Aufgabe Teilnahmebestätigung |
| `Feature\EventCompletionReminder\OpenTask` | DTO: eventId, title, eventType, startDate (bei verschobenen Events `rescheduledEventDate`), endDate (massgebendes Enddatum, bei verschobenen Events das verschobene), role (`instructor` oder `registration_coordinator`), tasks (Liste aus name, label, url) |
| `Feature\EventCompletionReminder\TaskEvaluator` | erhält alle Tasks per `#[AutowireIterator('sacevt.instructor_post_event_task')]`, liefert die offenen Aufgaben eines Events |
| `Feature\EventCompletionReminder\ReminderSchedule` | reine Logik: Fälligkeit nach Bearbeitungsfrist, Versand fällig? (lastSentAt, interval, now) |
| `Util\EventDateUtil` | reine Logik: massgebendes bzw. verschobenes Start- und Enddatum (`getEffectiveStartDate()`, `getEffectiveEndDate()`, `getRescheduledEndDate()`), auch von der Teilnahme-Historie verwendet |
| `Feature\EventCompletionReminder\OpenTaskProvider` | lädt die in Frage kommenden Events per SQL (`fetchCandidateEvents()`), bestimmt in PHP das massgebende Enddatum und die Fälligkeit (`findDueEvents()`), prüft sie über den `TaskEvaluator` und ordnet sie den Empfängern zu (Leiter ∪ Koordinator). Öffentlich: `getOpenTasksByRecipient(calendar, now)`, `getOpenTasks(userId, calendar, now)`, `getRecipientIdsWithOpenTasks(calendar, now)`, `getRecipient(userId)` (aktiv, mit E-Mail). Nicht readonly (mockbar) |
| `Feature\EventCompletionReminder\TaskItem` | DTO einer offenen Aufgabe: name, label, url |
| `Feature\EventCompletionReminder\DataContainer\ReminderLogTable` | Label-Callback für das Backend-Modul des Logs (nur lesen) |
| `Feature\EventCompletionReminder\DataContainer\Calendar` | tl_calendar-Callback: Notification-Optionen (nur passender Typ) |
| `Feature\EventCompletionReminder\ReminderLog` | `getLastSentAt(userId, calendarId)` (`sentAt`), `countSent(userId, calendarId)` (`reminderCount`), `logNotification(userId, calendarId, notificationId, sentAt, openTaskCount, eventIds)` (Insert bzw. Update mit `reminderCount + 1`, gibt die Log-ID zurück), `markAsDelivered(logId)` |
| `Feature\EventCompletionReminder\Cron\EventCompletionReminderCron` | `45 1,4 * * *` (zweiter Lauf fängt Verpasstes auf, das Intervall verhindert Duplikate), dispatcht Messages. Misst die Laufzeit mit der Symfony Stopwatch und schreibt sie ins Contao-Systemlog (siehe «Laufzeit») |
| `Feature\EventCompletionReminder\Messenger\Message\SendEventCompletionReminderMessage` | userId, calendarId; `LowPriorityMessageInterface` |
| `Feature\EventCompletionReminder\Messenger\MessageHandler\SendEventCompletionReminderHandler` | prüft, loggt, versendet |
| `Feature\EventCompletionReminder\NotificationType\EventCompletionReminderNotificationType` | `NAME = 'event_completion_reminder'` |

Eine Benachrichtigung entspricht genau einer Message. Die Message trägt nur IDs.

## Ordnerstruktur

Alle Klassen des Features liegen in einem Ordner. Nur DCA, Sprachdateien und Templates bleiben an ihren Contao- bzw. Symfony-Pfaden.

```
src/Feature/EventCompletionReminder/
├── Cron/EventCompletionReminderCron.php
├── DataContainer/
│   ├── Calendar.php
│   └── ReminderLogTable.php
├── Messenger/
│   ├── Message/SendEventCompletionReminderMessage.php
│   └── MessageHandler/SendEventCompletionReminderHandler.php
├── NotificationType/EventCompletionReminderNotificationType.php
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

Die Tests liegen spiegelbildlich unter `tests/Feature/EventCompletionReminder/`.

## Laufzeit

Der Cron misst den ganzen Lauf (Suche über alle aktivierten Kalender und Dispatch der Messages) mit der Symfony Stopwatch und schreibt ins Contao-Systemlog, z. B.:

`Event completion reminder cron: checked 4 calendar(s) and dispatched 12 message(s) in 1.83 s.`

Der Mailversand selbst läuft getrennt im Messenger-Worker und ist nicht enthalten. Die Laufzeit wächst mit der Anzahl fälliger Events (pro Event eine Abfrage für die Teilnahmebestätigung und eine für die Leiter). Dazu zählen alle vergangenen Events der gewählten Typen im Kalender, auch solche ohne offene Aufgaben. Der Cron sollte per CLI (`contao:cron`) laufen, dort gilt standardmässig keine `max_execution_time`.

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

`templates/Email/EventCompletionReminder/task_list.html.twig` und `task_list.txt.twig`, gerendert über `@MarkocupicSacEventTool/...`.
Jede Zeile: Datum, bei mehrtägigen Events «von–bis» (z. B. `10.01.2026–11.01.2026`), bei eintägigen nur ein Datum; bei verschobenen Events die verschobenen Daten. Dann Titel, Rolle des Empfängers (z. B. «als Anmelde-Koordinator»), offene Aufgaben mit Label und Direktlink aus der jeweiligen Task-Klasse:
- `TourReportTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events&act=edit&id={id}&call=writeTourReport`
- `ParticipationConfirmationTask::getUrl()`: `contao?do=calendar&table=tl_calendar_events_member&id={id}`

Labels:
- Text-Mail: immer das Label der Task-Klasse (`MSC.instructor_post_event_task.<name>`).
- HTML-Mail: optional ein eigenes Label `MSC.instructor_post_event_task_html.<name>`, das HTML enthalten darf. Fehlt es, wird das Text-Label verwendet.
- HTML-Mail, Linktext: `MSC.instructor_post_event_task_link.<name>` («Zum Tourenbericht», «Zur Teilnehmerliste»). Darstellung: `Label: <a href="…">Linktext</a>`. Fehlt der Linktext, wird das ganze Label verlinkt.
- Die Text-Mail zeigt `- Label: URL`.

Absage-Hinweis bei Kursen und allgemeinen Events:
- Diese Eventtypen haben keinen Tourenbericht. Der Event-Status lässt sich nur im Event selbst setzen.
- Deshalb zeigt die Liste bei diesen Events zusätzlich einen Hinweis mit Link zur Event-Bearbeitung (`contao?do=calendar&table=tl_calendar_events&act=edit&id={id}`): «Wurde der Anlass abgesagt? Dann im Event den Event-Status auf «Event abgesagt» setzen. …»
- Abgesagte Events werden übersprungen, die Erinnerung entfällt damit. Der Hinweis ist keine Aufgabe und zählt nicht in `open_task_count`.
- Die Eventtypen stehen in `SendEventCompletionReminderHandler::EVENT_TYPES_WITH_CANCEL_HINT`, die URLs werden dort erzeugt und als `cancel_hint_urls` (eventId → URL) ans Template übergeben.
- Texte: `MSC.instructor_post_event_task_cancel_hint`, `MSC.instructor_post_event_task_cancel_hint_link` («Zum Event»).
- Damit der Leiter den Status setzen kann, braucht er Schreibrecht auf das Event in der aktuellen Freigabestufe (Recht `can_write_event` in den Rechte-Regeln der Freigabestufe).

URL-Erzeugung wie im `MyEventsDashboardController`. Der Cron läuft per CLI, daher muss der Router-Kontext konfiguriert sein: entweder `framework.router.default_uri` (z. B. `https://www.sac-pilatus.ch`) oder `router.request_context.host`/`scheme` in `parameters.yaml`.

HTML-Mail im Worker: Das Notification Center wandelt im HTML relative URLs mit `Environment::get('base')` um. Ohne Request (Cron, Messenger-Worker) ergibt das `http://:/` und der Versand scheitert («Unable to parse URI»). Im HTML-Text der Notification deshalb keine relativen Links verwenden. TinyMCE kürzt Links auf die eigene Domain ab, interne Seiten darum als Insert-Tag verlinken: `{{link_url::<id>::absolute|urlattr}}`.

## Übrige Dateien

- `contao/dca/tl_calendar.php`, `contao/dca/tl_event_completion_reminder_log.php`
- `contao/config/config.php` (Backend-Modul), `contao/languages/en/modules.php`, `contao/languages/en/tl_event_completion_reminder_log.php`
- `contao/languages/en/...` (deutscher Inhalt; wird per composer-file-copier nach `de` kopiert)
- `config/services.yaml`: kein Eintrag nötig (Autowiring; `$sacevtLocale` ist gebunden)

## Tests (PHPUnit 9.6, `composer unit-tests`)

- `TourReportTaskTest`: `supports()` je Eventtyp, `isOpen()` mit und ohne Bericht
- `ParticipationConfirmationTaskTest`: `supports()` je Eventtyp; keine akzeptierten oder Wartelisten-Anmeldungen, keine Bestätigung, mindestens eine Bestätigung; Abfrage berücksichtigt nur akzeptierte und Wartelisten-Anmeldungen
- `TaskEvaluatorTest`: nur unterstützte und offene Tasks, Reihenfolge nach Priorität (mit Dummy-Tasks)
- `OpenTaskProviderTest`: Zuordnung zu den Empfängern: Leiter, Koordinator ohne Leiterrolle, Koordinator gleichzeitig Leiter (keine Duplikate), deaktivierte User und User ohne E-Mail, Events ohne offene Aufgaben, mehrere Events pro Empfänger, verschobene Events (fällig, noch nicht fällig, ohne Verschiebedatum), Sortierung nach massgebendem Enddatum, Eventtypen des Kalenders (keine gewählt, Übergabe an die Abfrage)
- `ReminderScheduleTest`: Bearbeitungsfrist an den Tagesgrenzen (inkl. Zeitumstellung), erste Mail, Intervall nicht erreicht oder erreicht
- `EventDateUtilTest` (`tests/Util/`): verschobenes Enddatum (eintägig, mehrtägig, zwei Wochenenden, Zeitumstellung, ohne Verschiebedatum)
- `SendEventCompletionReminderHandlerTest` (Mocks): Feature deaktiviert, keine Notification, ungültiger Empfänger, Intervall nicht abgelaufen, alles erledigt, Lock belegt, Log vor Versand (`logNotification()`) und Tokens (inkl. `reminder_count`)
- `EventCompletionReminderCronTest`: eine Message pro fälligem Empfänger und Kalender, Intervall wird beachtet

Nicht durch Unit-Tests abgedeckt: das SQL in `ReminderLog` (u. a. `ON DUPLICATE KEY UPDATE`), die SQL-Abfrage in `OpenTaskProvider::fetchCandidateEvents()` (Eventtyp, veröffentlicht, nicht abgesagt, `endDate`, Verschiebedatum) und die Abfrage in `ParticipationConfirmationTask::isOpen()` gegen eine echte Datenbank. Die Datumsgrenzen dazu prüfen `ReminderScheduleTest` und `EventDateUtilTest`.

## Konfiguration

1. `contao:migrate` (Kalenderfelder, Log-Tabelle)
2. Im Notification Center eine Benachrichtigung vom Typ «Benachrichtigung für Event-Abschluss» anlegen: Empfänger `##recipient_email##`, Text mit `##task_list_text##` bzw. `##task_list_html##`
3. Im Kalender das Feature aktivieren, Benachrichtigung wählen, Event-Typen, Bearbeitungsfrist und Intervall prüfen.
4. Cache leeren (`cache:clear`), damit das Backend-Modul «Reminder für Event-Abschluss» erscheint; Nicht-Admins das Modul in den Rechten zuweisen
5. Router-Kontext setzen (`framework.router.default_uri` oder `router.request_context.host`/`scheme`), damit die Links in den vom Cron versandten Mails auf die richtige Domain zeigen
6. Cron per CLI laufen lassen und Messenger-Worker betreiben (bzw. `messenger:consume`)
7. Kontrolle: Systemlog (Laufzeit, Anzahl Messages) und Backend-Modul «Reminder für Event-Abschluss» (pro Empfänger und Kalender ein Eintrag; «Zuletzt versendet am» und «Zähler» ändern sich höchstens einmal pro Intervall)
