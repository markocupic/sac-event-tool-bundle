# Feature: Teilnahme-Historie

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/ParticipantEventHistory/` (Namespace `Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory`), Tests unter `tests/Feature/ParticipantEventHistory/`

## Ziel

Die Leitenden eines Events sehen bei jeder Anmeldung, an welchen Events das angemeldete Mitglied in den letzten 5 Jahren teilgenommen hat. Damit lässt sich die Eignung für eine Tour oder einen Kurs besser beurteilen. Jeder Zugriff wird im Contao-System-Log protokolliert.

## Fachliche Regeln

### Berücksichtigte Teilnahmen (alle Bedingungen müssen gelten)

- Anmeldung in `tl_calendar_events_member` mit derselben `sacMemberId` wie die Anmeldung, von der aus die Historie geöffnet wird (nicht `contaoMemberId`)
- `tl_calendar_events_member.hasParticipated = 1`. Andere Anmeldungen erscheinen nie in der Liste, unabhängig vom Anmeldestatus.
- Event `published = 1`
- Event nicht abgesagt: `eventState != 'event_canceled'`
- Alle Eventtypen (`EventType::ALL`): Touren, Last Minute Touren, Kurse, Veranstaltungen
- Zeitraum: `startDate >= Stichtag` und `startDate <= jetzt`. Stichtag = heute 00:00, gleiches Kalenderdatum vor `ParticipantEventHistoryQuery::HISTORY_YEARS` (5) Jahren, z. B. am 08.10.2026 der 08.10.2021. Am 29.02. gilt der 01.03.
- Mehrere Anmeldungen zum selben Event zählen einmal.

Nicht berücksichtigt werden Anmeldungen ohne Mitgliedernummer (`sacMemberId = 0`, Gäste oder anonymisierte Anmeldungen) und eine Person mit mehreren Mitgliedernummern: Es zählt nur die Nummer der Anmeldung.

### Backend-Seite

Route `/contao/participant_event_history/{registrationId}`, Titel «Teilgenommene Events der letzten 5 Jahre von Vorname Nachname», darunter Mitgliedernummer und Anzahl Teilnahmen. Tabelle, sortiert nach `startDate` absteigend:

| Spalte | Inhalt |
|---|---|
| Datum | `startDate`, bei mehrtägigen Events `startDate – endDate` (Datumsformat der Systemeinstellungen) |
| Event | `title` mit Link auf die Detailseite im Frontend (neues Fenster). Ohne Detailseite nur der Titel. |
| Eventtyp | Kurzbezeichnung (`MSC.<eventType>_short`) |
| Hauptleiter | Hauptleiter aus `tl_calendar_events_instructor` mit Link auf die Leiter-Profilseite des Kalenders (`tl_calendar.userPortraitJumpTo`, `?getUpcoming=1&username=…`, neues Fenster). Ohne Link, wenn der Benutzer versteckt (`hideUser`) oder deaktiviert ist oder der Kalender keine Profilseite hat. |
| Mit Bergführer | `mountainguide`: «nein», «mit Bergführer/in», «mit Bergführerangebot». Leer bei Veranstaltungen. |
| Schwierigkeit / Kursstufe | Touren und Last Minute Touren: technische Schwierigkeiten (`CalendarEventsUtil::getTourTechDifficultiesAsArray()`, z. B. «WS - ZS, T3»). Kurse: Kursstufe (`CourseLevels`). Sonst leer. |

Ohne Teilnahmen: «Keine Teilnahmen in den letzten 5 Jahren.» Der Zurück-Button führt zur Teilnehmerliste des Events.

### Einstieg: Operation in der Teilnehmerliste

Die Seite ist nur über die Operation `participantEventHistory` pro Anmeldung in `tl_calendar_events_member` erreichbar (Icon `icons/fontawesome/list-check.svg`, ausgegraut `list-check--disabled.svg`, Quelle `assets/icons/fontawesome/`, Kopie nach `public/` beim Build mit `npm run build`). Es gibt keinen Menüeintrag.

- Mit Zugriff verweist die Operation auf die Seite. Die URL enthält nur die ID der Anmeldung, nicht die Mitgliedernummer. Der Zugriff wird bei jedem Aufruf vom Voter geprüft, nicht über die URL.
- Ohne Zugriff ist die Operation ausgegraut. Der Grund steht im Titel des Icons: keine gültige Mitgliedernummer, Einsichtsfrist abgelaufen (mit Datum) oder keine Berechtigung.

### Zugriff

Zugriff auf die Historie einer Anmeldung (`ParticipantEventHistoryVoter`, Attribut `sacevt_can_view_participant_event_history_of_registration`, Subject: ID der Anmeldung):

1. Die Anmeldung existiert und hat eine Mitgliedernummer (`sacMemberId > 0`). Das gilt auch für Admins.
2. Admins haben immer Zugriff, auch nach Ablauf der Einsichtsfrist.
3. Alle anderen brauchen das Recht `can_view_participant_event_history` auf dem Event der Anmeldung, und die Einsichtsfrist darf nicht abgelaufen sein.

Zusätzlich lehnt der Controller Aufrufe von fremden Websites ab (Header `Sec-Fetch-Site` weder `same-origin` noch `none`). Sonst könnte eine fremde Seite die Historie mit der Session eines eingeloggten Users im Hintergrund aufrufen und irreführende Log-Einträge erzeugen. Aufrufe ohne diesen Header sind erlaubt.

#### Recht `can_view_participant_event_history`

- Flag in den Rechte-Regeln der Freigabestufen (`tl_event_release_level_policy.permissionRules`, «Teilnahme-Historie der Angemeldeten ansehen»), Konstante `EventReleaseLevelPermissionRules::FLAG_VIEW_PARTICIPANT_EVENT_HISTORY`
- Ausgewertet von `CalendarEventsVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY` (Subject: Event-ID) auf der Freigabestufe, auf der das Event aktuell steht. Vergeben werden kann das Flag an Parteien (Autor, Hauptleiter, alle Leitenden, Anmelde-Koordinator) oder an Benutzergruppen.
- Events ohne Freigabestufe: Hier gewährt der `CalendarEventsVoter` sonst allen Benutzern alle Rechte. Für dieses Recht nicht: Zugriff haben nur Admins, die Leitenden und der Anmelde-Koordinator (`registrationGoesTo`) des Events.
- Die bestehenden Freigabestufen werden nicht automatisch ergänzt. Solange das Flag nicht gesetzt ist, sehen nur Admins die Historie (bei Events ohne Freigabestufe zusätzlich die Leitenden und der Anmelde-Koordinator).

#### Einsichtsfrist

`ParticipantEventHistoryAccessPeriod::ACCESS_DAYS_AFTER_EVENT_END` (30 Tage, `0` = unbegrenzt):

- Einsicht bis 23:59:59 am Tag Enddatum + 30 Tage. Vor dem Event gibt es keine Einschränkung.
- Enddatum: `endDate`. Bei verschobenen Events (`eventState = 'event_rescheduled'` mit `rescheduledEventDate`) das verschobene Enddatum: `rescheduledEventDate` + (Tag von `endDate` − Tag von `startDate`), siehe `Util\EventDateUtil::getEffectiveEndDate()`. Verschoben, aber noch ohne neues Datum: keine Frist.
- Die Frist wird bei jedem Zugriff neu berechnet, nie gespeichert. Nach einer Verschiebung gilt sofort das neue Enddatum, auch wenn die Frist nach dem alten Datum schon abgelaufen war.
- Beispiele: Tour am 10.01. → bis 09.02. Dieselbe Tour, verschoben auf den 24.01. → bis 23.02. Sa 10.01.–So 11.01., verschoben auf den 24.01. → neues Ende 25.01., bis 24.02.

### Protokollierung

Contao-System-Log (`contao.general`):

- Jeder Aufruf: Aktion `PARTICIPANT_EVENT_HISTORY_ACCESS`, z. B. `User "jdoe" (ID 12) opened the event history of "Jonas Müller" (SAC member 123456) (registration ID 345, event ID 67).`
- Jeder verweigerte Aufruf (keine Berechtigung bzw. Frist abgelaufen, unbekannte Anmeldung, Aufruf von einer fremden Website): Aktion `PARTICIPANT_EVENT_HISTORY_ACCESS_DENIED`. Danach zeigt Contao die Fehlerseite (`AccessDeniedException`).

### Anmeldeformular

Hinweis unter dem Feld «Anmerkungen» (`FORM.evt_reg_ffield_expl_notes`): «Hinweis: Die Leitenden haben Einsicht in die Liste der Touren und Kurse, die du in den letzten %d Jahren mit der %s absolviert hast.» Die Anzahl Jahre kommt aus `ParticipantEventHistoryQuery::HISTORY_YEARS`, der Name der Sektion aus dem Parameter `sacevt.section_name`. Der Text wird in `RegisterStep` übersetzt und dem Template `frontend_module/partials/event_registration/step/register.html.twig` als `notes_explanation` übergeben.

## Beteiligte Klassen und Dateien

| Klasse / Datei | Aufgabe |
|---|---|
| `ParticipantEventHistoryQuery` | Abfrage der Event-IDs, Konstante `HISTORY_YEARS`, Stichtag |
| `ParticipantEventHistoryAccessPeriod` | Einsichtsfrist, Konstante `ACCESS_DAYS_AFTER_EVENT_END` |
| `Security\ParticipantEventHistoryVoter` | Zugriff auf die Historie einer Anmeldung |
| `Controller\ParticipantEventHistoryController` | Backend-Route: prüft Herkunft des Aufrufs und Voter, protokolliert, rendert die Seite |
| `EventListener\ParticipantEventHistoryOperationListener` | Operation in der Teilnehmerliste (URL oder ausgegraut mit Begründung) |
| `Security\Voter\CalendarEventsVoter` | Attribut `CAN_VIEW_PARTICIPANT_EVENT_HISTORY` |
| `EventReleaseLevel\EventReleaseLevelPermissionRules` | Flag `FLAG_VIEW_PARTICIPANT_EVENT_HISTORY` |
| `Util\EventDateUtil` | Enddatum verschobener Events (auch von der Leiter-Erinnerung verwendet) |
| `Config\Log` | Log-Aktionen `PARTICIPANT_EVENT_HISTORY_ACCESS`, `PARTICIPANT_EVENT_HISTORY_ACCESS_DENIED` |
| `templates/ParticipantEventHistory/be_participant_event_history.html.twig` | Backend-Seite |
| `contao/dca/tl_calendar_events_member.php` | Operation `participantEventHistory` |
| `contao/dca/tl_event_release_level_policy.php` | Flag-Option in den Rechte-Regeln |
| `contao/languages/en/default.php` | Texte der Seite (`MSC.participantEventHistory…`) und Hinweis im Anmeldeformular |

## Konfiguration

- Recht `can_view_participant_event_history` in den Rechte-Regeln der Freigabestufen setzen, z. B. für «Alle Event-Leiter» und «Anmeldungs-Koordinator»
- Anzahl Jahre und Einsichtsfrist sind Konstanten (`HISTORY_YEARS`, `ACCESS_DAYS_AFTER_EVENT_END`)

## Tests

- `ParticipantEventHistoryQueryTest`: Stichtag (gleiches Datum vor 5 Jahren, Tagesbeginn, 29.02.), keine Abfrage ohne Mitgliedernummer
- `ParticipantEventHistoryAccessPeriodTest`: Frist bei ein- und mehrtägigen Events, verschobene Events mit und ohne neues Datum, unbegrenzt, Zeitumstellung
- `ParticipantEventHistoryVoterTest`: Admin auch nach Ablauf der Frist, mit Recht innerhalb und ausserhalb der Frist, ohne Recht, ohne Mitgliedernummer, unbekannte Anmeldung, ohne Backend-User
- `tests/Util/EventDateUtilTest`: Enddatum verschobener Events

Nicht durch Unit-Tests abgedeckt: die SQL-Abfrage in `ParticipantEventHistoryQuery::findEventIds()` gegen eine echte Datenbank, der Controller und die Operation.
