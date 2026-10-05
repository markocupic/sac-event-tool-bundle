# Feature: Event Reminder

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventReminder/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventReminder`), Tests unter `tests/Feature/EventReminder/`

## Ziel

Leiter und Teilnehmer werden eine einstellbare Anzahl Tage vor dem Event-Start per Notification Center an den Event erinnert. Pro Event wird genau **eine** E-Mail verschickt (spart E-Mail-Kontingent): Kontaktperson in An/Antwort an, übrige Leiter in CC, Teilnehmer in BCC.

## Fachliche Regeln

### Kalender

- `tl_calendar.sendEventReminder = 1`
- `tl_calendar.eventReminderNotification` gesetzt (Pflichtfeld, nur Typ `event_reminder`)
- `tl_calendar.eventReminderOffset`: Anzahl Tage vor Event-Start (1–365, Default 14)

### Events (alle Bedingungen müssen gelten)

- Event-Start (`startDate`) liegt am Tag «heute + Offset» (ganzer Kalendertag, Zeitzone von PHP/Contao)
- `published = 1`, `title` und `eventType` nicht leer
- Noch nicht erinnert: `eventReminderSentAt = 0`
- Mindestens eine Anmeldung mit Status `subscription-accepted`

### Empfänger (`RecipientResolver`, reine Logik ohne Datenbank)

- **Hauptleiter:** der in `tl_calendar_events_instructor` markierte Hauptleiter, sonst der erste Leiter mit E-Mail-Adresse
- **Co-Leiter:** alle übrigen Leiter
- **Kontaktperson (An/Antwort an, `##recipient_to##`):** der Anmelde-Koordinator (`registrationGoesTo`, aktiv, mit E-Mail), sonst der Hauptleiter
- **CC (`##recipient_cc##`):** alle Leiter ausser der Kontaktperson
- **BCC (`##recipient_bcc##`):** alle akzeptierten Teilnehmer mit E-Mail, ohne Adressen, die schon in An oder CC stehen
- E-Mail-Adressen werden kleingeschrieben, getrimmt und dedupliziert
- Ohne Kontaktperson mit E-Mail-Adresse wird nichts verschickt (Eintrag im Error-Log)

### Versand

- Das Flag `eventReminderSentAt` wird **vor** dem Versand gesetzt (`UPDATE … WHERE eventReminderSentAt = 0`). Lieber eine fehlende als eine doppelte Erinnerung; läuft der Handler doppelt, gewinnt nur einer.
- Zustellfehler werden ins Error-Log geschrieben und nicht wiederholt.

### Zeitpunkt

- Cron `#[AsCronJob('30 3,4 * * *')]`: zweimal pro Nacht. Der zweite Lauf holt nach, was der erste verpasst hat; doppelte Erinnerungen verhindert `eventReminderSentAt`.
- Der Cron verschickt nichts selbst, sondern dispatcht pro Event eine `SendEventReminderMessage` (Symfony Messenger, `LowPriorityMessageInterface`). Den Versand übernimmt `SendEventReminderHandler`.

## Command

```
php vendor/bin/contao-console sacevt:event-reminder                  # wie der Cron: fällige Events, Messages für den Messenger-Worker
php vendor/bin/contao-console sacevt:event-reminder --sync           # fällige Events, sofort versenden (ohne Worker)
php vendor/bin/contao-console sacevt:event-reminder --dry-run        # nur die heute fälligen Events auflisten
php vendor/bin/contao-console sacevt:event-reminder --event=123      # nur Event 123, unabhängig vom Datum, sofort versenden
```

- «Fällig» heisst dasselbe wie im Cron (`EventReminderCron::findDueEventIds()`): Event-Start am Tag «heute + Offset», noch nicht erinnert, akzeptierte Teilnehmer.
- Mit `--sync` und `--event` ruft der Command den `SendEventReminderHandler` direkt auf und zeigt, für welche Events die Erinnerung versendet wurde und für welche nicht (z. B. «bereits versendet am …»).
- `--event` umgeht nur die Datumsprüfung. Alle anderen Bedingungen prüft der Handler weiterhin: bereits versendet (`eventReminderSentAt`), Erinnerung im Kalender eingeschaltet, Benachrichtigung gewählt, akzeptierte Teilnehmer, Kontaktperson mit E-Mail-Adresse. Eine Erinnerung ein zweites Mal versenden geht also nur, wenn `eventReminderSentAt` vorher auf `0` gesetzt wird.

## Konfiguration

1. **Benachrichtigung anlegen:** Notification Center → neue Benachrichtigung vom Typ «Event-Erinnerung vor Event-Start» (`event_reminder`). Als Empfänger `##recipient_to##`, als CC `##recipient_cc##`, als BCC `##recipient_bcc##` und als Antwort-an `##recipient_to##` eintragen. Die verfügbaren Tokens stehen unten.
2. **Kalender einstellen:** Kalender bearbeiten → Legende «Event-Erinnerung-Einstellungen» → «Erinnerung vor Event-Start versenden» aktivieren, Anzahl Tage vor Event-Start wählen und die Benachrichtigung auswählen (Pflichtfeld, es erscheinen nur Benachrichtigungen vom Typ `event_reminder`).
3. **Cron und Messenger:** Der Cron von Contao muss laufen (`contao:cron` oder Web-Cron). Die Messages werden vom Messenger-Worker verarbeitet (`contao_prio_low`). Ohne Worker lässt sich der Versand mit `sacevt:event-reminder --sync` anstossen.

## Datenmodell

### tl_calendar (Legende `event_reminder_legend`)

| Feld | Typ | Default |
|---|---|---|
| `sendEventReminder` | Checkbox, `submitOnChange`, Selector der Subpalette | `false` |
| `eventReminderOffset` | Select 1–365, Tage vor Event-Start | `14` |
| `eventReminderNotification` | Select auf `tl_nc_notification`, nur Typ `event_reminder` (Options-Callback `DataContainer\Calendar`), Pflichtfeld | `0` |

### tl_calendar_events

| Feld | Beschreibung |
|---|---|
| `eventReminderSentAt` | Zeitpunkt des Versands, `0` = noch nicht erinnert. `readonly`, `doNotShow`, `doNotCopy` |

## Tokens (Notification Center, Typ `event_reminder`)

Definiert in `NotificationType\EventReminderNotificationType`, Beschreibungen in `contao/languages/en/nc_tokens.php`. Unter anderem:

- `##event_*##` und `##event_raw_*##`: alle Felder des Events (snake_case), dazu `##event_start_date##`, `##event_end_date##`, `##event_period##`, `##event_duration##`, `##event_meeting_point##`, `##event_leistungen##`, `##event_deregistration_limit##`, `##event_link_detail##`, `##event_event_type_translated##`
- `##main_instructor_name##`, `##main_instructor_email##`, `##main_instructor_phone##`, `##main_instructor_mobile##`
- `##instructors_names##`, `##instructors_email##`, `##co_instructors_names##`, `##co_instructors_email##`
- `##participants_names##`, `##participants_email##`, `##participants_count##`
- `##registration_coordinator_name##`, `##registration_coordinator_email##`
- `##recipient_to##`, `##recipient_cc##`, `##recipient_bcc##`
- `##reminder_offset_days##`

## Klassen

```
src/Feature/EventReminder/
├── Cron/EventReminderCron.php                                # findet die Events (findDueEventIds()), dispatcht pro Event eine Message
├── Command/EventReminderCommand.php                          # sacevt:event-reminder [--sync] [--dry-run] [--event=ID]
├── Messenger/Message/SendEventReminderMessage.php            # Event-ID
├── Messenger/MessageHandler/SendEventReminderHandler.php     # Tokens, Flag setzen, Versand
├── NotificationType/EventReminderNotificationType.php        # Typ "event_reminder" mit Token-Definitionen
├── DataContainer/Calendar.php                                # Options-Callback: nur Benachrichtigungen vom Typ "event_reminder"
├── PersonProvider.php                                        # lädt Leiter, Hauptleiter, Anmelde-Koordinator, Teilnehmer
├── RecipientResolver.php                                     # Empfänger-Regeln (An, CC, BCC), ohne Datenbank
├── Recipients.php                                            # Wertobjekt: An, CC, BCC
├── Person.php                                                # Wertobjekt: Leiter/Koordinator (aus tl_user)
└── Participant.php                                           # Wertobjekt: Teilnehmer (aus tl_calendar_events_member)
```

## Tests (`tests/Feature/EventReminder/`)

- `RecipientResolverTest`: Hauptleiter, Co-Leiter, Kontaktperson, Verteilung auf An/CC/BCC (überschneidungsfrei), Normalisierung und Deduplizierung der Adressen
- `Messenger/MessageHandler/SendEventReminderHandlerTest`: bereits verschickt, Erinnerung im Kalender ausgeschaltet, keine akzeptierten Teilnehmer, fehlende Kontaktperson, anderer Worker war schneller, Flag vor dem Versand und Empfänger-Tokens
- `DataContainer/CalendarTest`: nur Benachrichtigungen vom Typ `event_reminder`
- `Cron/EventReminderCronTest`: eine Message pro fälligem Event
- `Command/EventReminderCommandTest`: wie der Cron, `--sync` mit Ergebnis, `--event` unabhängig vom Datum, `--dry-run`, keine fälligen Events
