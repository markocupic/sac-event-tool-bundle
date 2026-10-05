# Feature: Event Feedback

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventFeedback/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventFeedback`), Tests unter `tests/Feature/EventFeedback/`

## Ziel

Teilnehmende werten einen Event online aus. Nachdem der Leiter ihre Teilnahme bestätigt hat, erhalten sie per Notification Center eine Aufforderung mit einem persönlichen Link zum Auswertungsformular, bei Bedarf mit Erinnerungen. Leiter sehen die Auswertungen ihres Events anonym zusammengefasst im Backend und können sie als PDF herunterladen. Rückschlüsse auf die antwortende Person sind nicht möglich.

## Fachliche Regeln

### Einrichtung pro Kalender und Event

- Kalender: `enableOnlineEventFeedback = 1` mit Konfiguration (`onlineFeedbackConfiguration`), Benachrichtigung (`onlineFeedbackNotification`, nur Typ `event_feedback_reminder`), Seite mit dem Formular-Modul (`onlineFeedbackPage`) und Formular (`onlineFeedbackForm`, nur Formulare mit `tl_form.isSacEventFeedbackForm = 1`)
- Event: `enableOnlineEventFeedback = 1`. Das Feld erscheint im Event nur, wenn der Kalender vollständig eingerichtet ist (`DataContainer\CalendarEvents`).
- Vollständig eingerichtet heisst: alle Prüfungen in `EventFeedbackHelper::eventHasValidFeedbackConfiguration()` sind erfüllt. Sonst wird nichts geplant und nichts versendet.

### Aufforderungen planen

- Wird bei einer Anmeldung `hasParticipated` gesetzt, werden die Aufforderungen in `tl_event_feedback_reminder` geplant: eine Zeile pro Versandzeitpunkt aus `send_reminder_after_days` (z. B. 0, 14 und 28 Tage).
- Gezählt wird ab der Teilnahmebestätigung, frühestens ab dem Event-Ende (`endDate`). Das Formular kann bis `feedback_expiration_time` Tage nach diesem Startpunkt ausgefüllt werden (`expiration`).
- Wird `hasParticipated` wieder entfernt, werden die geplanten Aufforderungen gelöscht.
- Nichts wird geplant oder gelöscht, wenn der Teilnehmer schon eine Auswertung abgegeben hat oder bereits eine Aufforderung erhalten hat (`countOnlineEventFeedbackNotifications > 0`).
- Wird eine Anmeldung gelöscht, werden ihre Aufforderungen mitgelöscht (`ctable`).

### Versand

- Cron jede Minute (`SendFeedbackRemindersCron`): löscht abgelaufene Aufforderungen, sucht die fälligen und dispatcht pro Aufforderung eine `SendEventFeedbackReminderMessage` (Symfony Messenger, `LowPriorityMessageInterface`).
- Fällig: Versandzeitpunkt plus `send_reminder_execution_delay` Sekunden erreicht. Die Verzögerung gibt dem Leiter Zeit, eine versehentliche Teilnahmebestätigung rückgängig zu machen.
- Vor dem Dispatch wird die Aufforderung belegt (`dispatched = 1`, nur wenn noch nicht belegt). So wird keine Aufforderung doppelt verschickt.
- Der Handler (`SendEventFeedbackReminderHandler`) prüft erneut: Anmeldung und Event vorhanden, noch keine Auswertung abgegeben, Einrichtung vollständig, Secret gesetzt. Dann versendet er die Benachrichtigung, erhöht `countOnlineEventFeedbackNotifications` und schreibt einen Eintrag ins Contao-Systemlog.
- Die Aufforderung wird danach in jedem Fall gelöscht, auch wenn nichts versendet wurde. Belegte Aufforderungen, die der Handler nicht löschen konnte, entfernt der Cron nach einem Tag.

### Auswertung ausfüllen (Frontend-Modul `event_feedback_form`)

- Der Link enthält ein JWT (`FeedbackToken`, Bibliothek `rbdwllr/reallysimplejwt`) mit der ID der Anmeldung, gültig bis `expiration`.
- Nur für eingeloggte Mitglieder. Die Anmeldung muss zum eingeloggten Mitglied gehören (gleiche SAC-Mitgliedernummer, nicht 0).
- Pro Anmeldung eine Auswertung. Ist schon eine vorhanden, erscheint ein Hinweis statt des Formulars.
- Das Formular ist ein Contao-Formular, mehrseitig über `terminal42/contao-mp_forms`. Gespeichert wird in `tl_event_feedback` (`PrepareFormDataListener`, `StoreFormDataListener`). Dabei werden Event, Anmeldung (UUID), Formular und Datum ergänzt und die restlichen Aufforderungen gelöscht.
- Nach dem Absenden erscheint eine Dankesmeldung (Session-Schlüssel `sacevt_event_feedback_last_insert`).
- Antworten können nicht bearbeitet werden.

### Auswertung im Backend

- Im Event-Dashboard erscheint der Button «Event Auswertungen», sobald es mindestens eine Auswertung gibt.
- Berechtigt: Admins sowie Benutzer mit Zugriff auf das Modul «calendar» und Schreibrecht auf das Event oder als Anmelde-Koordinator (wie bei der Teilnehmerliste).
- Zusammenfassung (`FeedbackSummary`): bei Auswahlfeldern (select, radio, checkbox), wie oft jede Option gewählt wurde; bei Textfeldern alle Antworten untereinander.
- PDF: dieselbe Zusammenfassung über eine docx-Vorlage, mit CloudConvert in PDF umgewandelt.

### Löschen

- Wöchentlich (`DeleteOldFeedbacksCron`): Auswertungen und Aufforderungen, die älter als `delete_feedbacks_after` Tage sind.

## Konfiguration

```yaml
# config/config.yaml
sacevt:
  feature:
    event_feedback:
      # Pflicht: mind. 12 Zeichen mit Gross- und Kleinbuchstaben, Zahl und einem Sonderzeichen *&!@%^#$
      secret: '...'
      delete_feedbacks_after: 720   # Tage
      docx_template: 'vendor/markocupic/sac-event-tool-bundle/contao/templates/docx/event_feedback.docx'
      configs:
        default:
          feedback_expiration_time: 60          # Tage
          send_reminder_after_days: [0, 14, 28] # Tage
          send_reminder_execution_delay: 60     # Sekunden
```

- Alle Werte ausser `secret` haben die gezeigten Defaults. Ohne `secret` wird nichts versendet (Eintrag im Error-Log), und bestehende Links werden ungültig, wenn das Secret geändert wird.
- Unter `configs` können weitere Konfigurationen angelegt werden. Sie stehen dann im Kalender zur Auswahl.
- Parameter: `sacevt.feature.event_feedback.secret`, `.delete_feedbacks_after`, `.docx_template`, `.configs`

### Einrichtung

1. `contao:migrate`, `cache:clear`
2. Ein Contao-Formular anlegen, «SAC Event Auswertungsformular» aktivieren und die Felder anlegen (Auswahl- und Textfelder; der Feldname muss einer Spalte von `tl_event_feedback` entsprechen)
3. Ein Frontend-Modul «Event Feedback Formular» anlegen und auf einer geschützten Seite einbinden
4. Im Notification Center eine Benachrichtigung vom Typ «Aufforderung Online Tour-/Kurs-Auswertung» anlegen: Empfänger `##participant_email##`, Link `##feedback_url##`
5. Im Kalender die Online-Auswertung aktivieren, Konfiguration, Benachrichtigung, Seite und Formular wählen
6. Im Event die Online-Auswertung aktivieren
7. Contao-Cron per CLI laufen lassen und Messenger-Worker betreiben. Der Router-Kontext muss gesetzt sein (`framework.router.default_uri` oder `router.request_context.host`/`scheme`), damit `##feedback_url##` auf die richtige Domain zeigt.

## Datenmodell

| Tabelle / Feld | Beschreibung |
|---|---|
| `tl_calendar.enableOnlineEventFeedback`, `onlineFeedbackConfiguration`, `onlineFeedbackNotification`, `onlineFeedbackPage`, `onlineFeedbackForm` | Einrichtung pro Kalender (Legende `sac_event_feedback_legend`) |
| `tl_calendar_events.enableOnlineEventFeedback` | Online-Auswertung für dieses Event |
| `tl_calendar_events_member.countOnlineEventFeedbackNotifications` | Anzahl versendeter Aufforderungen (nur lesen) |
| `tl_form.isSacEventFeedbackForm` | Formular ist ein Auswertungsformular |
| `tl_event_feedback` | Abgegebene Auswertungen: `pid` (Event), `uuid` (Anmeldung, eindeutig), `form`, `dateAdded` und eine Spalte pro Formularfeld |
| `tl_event_feedback_reminder` | Geplante Aufforderungen: `pid` (Anmeldung), `uuid`, `executionDate`, `expiration`, `dispatched`, `dispatchTime`; eindeutig pro (`uuid`, `executionDate`) |

Backend-Module «Event Feedback» und «Event Feedback Reminder» (Gruppe `event_feedback`) sowie die Keys `showEventFeedbacks` und `showEventFeedbacksAsPdf` im Modul «calendar».

## Tokens (Notification Center, Typ `event_feedback_reminder`)

- E-Mail: `participant_email`, `instructor_email` (Hauptleiter), `admin_email` (Root-Seite, sonst Systemeinstellungen)
- Teilnehmer: `participant_firstname`, `participant_lastname`, `participant_uuid`
- Event: `event_title`, `instructor_name`
- Link: `feedback_url` (Seite mit dem Formular-Modul, mit Token)

## Klassen

```
src/Feature/EventFeedback/
├── Command/EventFeedbackRemindersCommand.php                     # sacevt:event-feedback:reminders (anstehende Aufforderungen auflisten)
├── Controller/EventFeedbackBackendController.php                 # Backend-Keys showEventFeedbacks, showEventFeedbacksAsPdf
├── Controller/FrontendModule/EventFeedbackFormController.php     # Frontend-Modul event_feedback_form
├── Cron/SendFeedbackRemindersCron.php                            # jede Minute: fällige Aufforderungen belegen und dispatchen
├── Cron/DeleteOldFeedbacksCron.php                               # wöchentlich: alte Auswertungen und Aufforderungen löschen
├── DataContainer/Calendar.php                                    # Options: Konfigurationen, Benachrichtigungen, Formulare
├── DataContainer/CalendarEvents.php                              # Feld im Event nur bei vollständiger Einrichtung
├── DataContainer/CalendarEventsMember.php                        # hasParticipated: Aufforderungen planen bzw. löschen
├── EventListener/GenerateEventDashboardListener.php              # Button «Event Auswertungen» im Event-Dashboard
├── EventListener/PrepareFormDataListener.php                     # Formular speichert in tl_event_feedback
├── EventListener/StoreFormDataListener.php                       # Auswertung ergänzen, Aufforderungen löschen
├── Messenger/Message/SendEventFeedbackReminderMessage.php        # ID der Aufforderung
├── Messenger/MessageHandler/SendEventFeedbackReminderHandler.php # prüft, versendet, löscht die Aufforderung
├── NotificationType/EventFeedbackReminderNotificationType.php    # Typ event_feedback_reminder
├── EventFeedbackHelper.php                                       # Einrichtung von Kalender und Event lesen und prüfen
├── FeedbackReminder.php                                          # tl_event_feedback_reminder: planen (getSchedule()), belegen, löschen
├── FeedbackSummary.php                                           # anonyme Zusammenfassung der Auswertungen eines Events
└── FeedbackToken.php                                             # JWT für den Link zum Formular
```

Ausserhalb des Feature-Ordners: `src/Model/EventFeedbackModel.php`, `src/Model/EventFeedbackReminderModel.php`, Templates `contao/templates/modules/event_feedback_form/mod_event_feedback_form.html.twig` und `templates/EventFeedback/be_event_feedbacks.html.twig`, docx-Vorlage `contao/templates/docx/event_feedback.docx`, Hintergrundbilder `assets/images/event_feedback/`.

## Tests (`tests/Feature/EventFeedback/`)

- `FeedbackReminderTest`: Versandzeitpunkte und Ablauf (Bestätigung nach bzw. vor dem Event-Ende)
- `Cron/SendFeedbackRemindersCronTest`: nur fällige Aufforderungen (inkl. Verzögerung), belegte werden übersprungen
- `Messenger/MessageHandler/SendEventFeedbackReminderHandlerTest`: Aufforderung fehlt, Auswertung schon abgegeben, Einrichtung ungültig, Versand mit Tokens; Aufforderung wird gelöscht
