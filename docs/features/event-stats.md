# Feature: Event-Statistik

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventStats/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventStats`), Tests unter `tests/Feature/EventStats/`

## Ziel

Eine Backend-Seite zeigt Kennzahlen zu Events, Leitenden und Teilnehmenden. Die Zahlen stehen für die zwei vorangehenden Jahre, das aktuelle und das nächste Jahr nebeneinander: `J-2 | J-1 | J | J+1` (J = aktuelles Jahr). Die Jahre verschieben sich jeweils am 1. Januar automatisch.

## Fachliche Regeln

### Allgemein

- Massgebend ist das Jahr des Event-Starts (`tl_calendar_events.startDate`, 1. Januar 00:00 bis 31. Dezember 23:59:59).
- Ausgeschrieben heisst: Freigabestufe FS3 oder FS4 (`tl_event_release_level_policy.level`). Nur die Tabelle «Bestätigte Teilnehmende» zählt nur Events auf FS4.
- Last Minute Touren und allgemeine Veranstaltungen werden nie gezählt.

### Tabellen

| Tabelle | Event-Arten | Inhalt |
|---|---|---|
| Ausgeschriebene Touren | Tour | Total sowie nach `mountainguide`: mit Bergführer-Angebot, mit Bergführer, ohne Bergführer |
| Ausgeschriebene Kurse | Kurs | wie Touren |
| Ausgeschriebene Events nach Gruppe | Tour, Kurs | Total und pro organisierender Gruppe (`tl_event_organizer`, sortiert wie im Backend). Ein Event mit mehreren Gruppen wird bei jeder Gruppe gezählt. |
| Leitende | Tour, Kurs | Anzahl verschiedener Leitender (`tl_calendar_events_instructor`, auch deaktivierte Benutzer), davon Bergführer (Qualifikation `TourguideQualification::MOUNTAIN_GUIDE` in `tl_user.leiterQualifikation`) und Nicht-Bergführer |
| Anmeldungen | Tour, Kurs | Total und pro Anmeldestatus (`EventSubscriptionState::ALL`) |
| Bestätigte Teilnehmende (FS4) | Tour, Kurs | Anmeldungen mit `hasParticipated = 1`: Total, Geschlecht (`female` weiblich, `male` männlich, `other` divers/keine Angabe) und Altersgruppe (0–20, 21–30, 31–40, 41–60, 61–80, 81+, unbekannt). Alter = Event-Jahr minus Geburtsjahr; ohne Geburtsdatum «unbekannt». |
| Event-Status | Tour | siehe unten |

### Event-Status (nur Touren)

| Zeile | Bedingung |
|---|---|
| Events stattgefunden | `eventState` leer und `executionState` gesetzt (Tourrapport vorhanden) |
| Events ausgebucht / verschoben / abgesagt | `eventState` = `event_fully_booked` / `event_rescheduled` / `event_canceled` |
| Unbekannt (kein Tourrapport) | `eventState` und `executionState` leer |
| Wie ausgeschrieben durchgeführt: Ja / Nein | `executionState` = `event_executed_like_predicted` / `event_not_executed_like_predicted` |
| Wie ausgeschrieben durchgeführt: Unbekannt | `executionState` leer |

### Zugriff

- Admins sowie Benutzer, die das Backend-Modul `sac_pilatus_event_stats` («SAC Pilatus Event Statistiken») in ihren Rechten oder über eine Benutzergruppe haben.
- Der Menüeintrag erscheint in der Gruppe «SAC Module» nur für berechtigte Benutzer.

## Konfiguration

Keine. Für Benutzer ohne Admin-Rechte in der Benutzergruppe bzw. beim Benutzer das Modul «SAC Pilatus Event Statistiken» freigeben.

## Klassen

```
src/Feature/EventStats/
├── Controller/EventStatsController.php       # Backend-Route /contao/sac_pilatus_event_stats, stellt die Tabellen zusammen
├── EventListener/BackendMenuListener.php     # Menüeintrag in «SAC Module»
└── EventStatsQuery.php                       # Abfragen: Events, Touren nach Status, Leitende, Anmeldungen, Teilnehmende
```

Ausserhalb des Feature-Ordners: Template `templates/EventStats/be_event_stats.html.twig`, Modul-Eintrag in `contao/config/config.php` (`hideInNavigation`), Label in `contao/languages/en/modules.php`.

## Tests (`tests/Feature/EventStats/`)

- `EventStatsQueryTest`: Zuordnung des Alters zur Altersgruppe
