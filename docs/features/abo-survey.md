# Feature: Abo-Umfrage

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/AboSurvey/` (Namespace `Markocupic\SacEventToolBundle\Feature\AboSurvey`), Tests unter `tests/Feature/AboSurvey/`

## Ziel

Eingeloggte Mitglieder entscheiden sich im Frontend für ein Abo (White Risk oder SchweizMobil) für ein bestimmtes Jahr. Die Eingaben werden in `tl_abo_survey` gespeichert und können im Backend eingesehen, bearbeitet und gelöscht werden.

## Fachliche Regeln

- Das Inhaltselement ist nur für eingeloggte Frontend-User sichtbar; Gäste sehen nichts.
- Teilnehmen dürfen nur Mitglieder, die alle Bedingungen erfüllen (`AboSurveyEligibility`):
  1. `tl_member.sacMemberId` > 0,
  2. es gibt einen aktiven Backend-Benutzer (`tl_user`: `disable = 0` und innerhalb von `start`/`stop`) mit derselben `sacMemberId`,
  3. für diesen Backend-Benutzer trifft mindestens eines zu:
     - er ist im Inhaltselement unter «Zusätzlich berechtigte Benutzer» ausgewählt (`aboSurveyAllowedUsers`),
     - er hat eine Rolle (`tl_user.userRole`), die im Inhaltselement unter «Zusätzlich berechtigte Rollen» ausgewählt ist (`aboSurveyAllowedUserRoles`),
     - er hat eine Rolle, die zum Vorstand gehört (`tl_user_role.belongsToExecutiveBoard`),
     - er ist Hauptleiter (`tl_calendar_events_instructor.isMainInstructor`) bei mindestens einer Tour oder einem Kurs (`eventType` `tour` oder `course`), der im eingestellten Jahr beginnt (`startDate` 1.1. 00:00 bis 31.12. 23:59:59) und mindestens Freigabestufe 3 hat (`tl_event_release_level_policy.level >= 3`).

  Ist eine Bedingung nicht erfüllt, sieht das Mitglied statt des Formulars eine Meldung mit dem Grund (die erste nicht erfüllte Bedingung). Wer die Umfrage bereits abgegeben hat, sieht weiterhin seine Eingaben, auch wenn er die Bedingungen inzwischen nicht mehr erfüllt.
- Das Formular ist vom Startdatum (00:00 Uhr) bis zum Enddatum (23:59:59 Uhr) aktiv. Vorher erscheint ein Hinweis mit dem Startdatum, danach ein Hinweis, dass die Umfrage abgeschlossen ist.
- Pro Mitglied (`sacMemberId`) und Jahr gibt es höchstens einen Eintrag (Unique-Index). Die Umfrage kann nur einmal abgegeben werden: Danach sieht das Mitglied statt des Formulars seine Eingaben (Jahr, Abo, Mitglieder-Nr., E-Mail, Adresse) und den Hinweis, dass es die Umfrage bereits am `dateAdded` (dd.mm.YYYY) ausgefüllt hat. Direkt nach dem Absenden erscheint statt dieses Hinweises einmalig die Erfolgsmeldung; der Hinweis erscheint erst, wenn das Mitglied die Seite später wieder aufruft. Das gilt auch nach Ablauf des Zeitfensters. Korrekturen sind nur im Backend möglich.
- E-Mail und Adresse werden beim Speichern aus dem Mitgliederprofil (`tl_member`) übernommen und sind im Formular nicht editierbar.
- Jede Abgabe wird im Contao-Log protokolliert (`Log::ABO_SURVEY_SUBMIT`).

## Konfiguration

Inhaltselement «Abo-Umfrage» (`abo_survey`, Kategorie «SAC Pilatus Inhalts-Elemente») mit den Feldern:

| Feld | Bedeutung |
|---|---|
| `aboSurveyExplanation` | Nur Anzeige: HTML-Erklärung oben in der Bearbeitungsansicht (ohne Datenbankspalte, Text in `tl_content.php` der Sprachdateien) |
| `aboSurveyYear` | Jahr, für das die Eingaben gelten (z.B. 2027) |
| `aboSurveyStartDate` | Formular aktiv ab (Datum) |
| `aboSurveyEndDate` | Formular aktiv bis (Datum) |
| `aboSurveyAllowedUserRoles` | Zusätzlich berechtigte Rollen (`tl_user_role`, Chosen-Mehrfachauswahl) |
| `aboSurveyAllowedUsers` | Zusätzlich berechtigte Backend-Benutzer (`tl_user`, Chosen-Mehrfachauswahl) |

Backend-Modul «Abo-Umfrage» (`abo_survey`) in der Backend-Menü-Kategorie «SAC-Pilatus Umfragen» (`sac_pilatus_surveys`): Liste mit Filter nach Jahr und Abo, Suche, Bearbeiten und Löschen. Neue Einträge entstehen nur über das Frontend.

### CSV-Export

Die globale Operation «CSV-Export» lädt die Einträge als CSV-Datei herunter (UTF-8 mit BOM, Trennzeichen `;`, öffnet sich direkt in Excel). Ist in der Liste ein Filter nach Jahr und/oder Abo gesetzt, enthält der Export nur diese Einträge, sonst alle. Vorname und Nachname werden über die `sacMemberId` aus `tl_member` ergänzt (leer, wenn das Mitglied nicht mehr existiert). Spalten: Jahr, Abo, SAC-Mitgliedernummer, Vorname, Nachname, E-Mail, Strasse, PLZ, Ort, Eingereicht am. Dateiname: `abo_umfrage_<jahr|alle>_<datum>.csv`. Für den Export braucht der Benutzer Zugriff auf das Backend-Modul.

## Datenmodell `tl_abo_survey`

| Feld | Inhalt |
|---|---|
| `id`, `tstamp` | Standardfelder |
| `dateAdded` | Zeitpunkt der ersten Abgabe |
| `year` | Jahr der Umfrage |
| `aboType` | `whiteRisk` oder `schweizMobil` (`AboSurveyAboType`) |
| `sacMemberId` | SAC-Mitgliedernummer |
| `email`, `street`, `postal`, `city` | Kontaktdaten aus `tl_member` |

## Klassen und Dateien

- `Controller/ContentElement/AboSurveyController.php`: Inhaltselement mit Haste-Formular
- `AboSurveyEligibility.php`: prüft, ob ein Mitglied an der Umfrage teilnehmen darf, und liefert sonst den Grund
- `AboSurveyPeriod.php`: berechnet, ob das Formular noch nicht offen, offen oder geschlossen ist
- `AboSurveyRepository.php`: liest und speichert die Einträge (Doctrine DBAL)
- `AboSurveyAboType.php`: verfügbare Abos
- `AboSurveyCsvExport.php`: erzeugt die CSV-Datei
- `DataContainer/AboSurveyContent.php`: HTML-Erklärung (`aboSurveyExplanation`) und Optionen der Felder `aboSurveyAllowedUserRoles` und `aboSurveyAllowedUsers`
- `DataContainer/AboSurveyTable.php`: onload-Callback, der die Export-Aktion (`action=exportAboSurveyCsv`) abfängt und den Listen-Filter aus der Backend-Session liest
- `contao/dca/tl_abo_survey.php`, Felder in `contao/dca/tl_content.php`
- Template `contao/templates/twig/content_element/abo_survey.html.twig`
- Sprachdateien `contao/languages/{de,en}/tl_abo_survey.php`, Texte in `default.php` (`MSC.abo_survey_*`)

## Tests

- `AboSurveyPeriodTest`: Grenzen des Zeitfensters (Start 00:00, Ende 23:59:59)
- `AboSurveyEligibilityTest`: die Ablehnungsgründe sowie die Berechtigung über Benutzer, Rolle, Vorstand und Hauptleitung
- `AboSurveyCsvExportTest`: Inhalt der CSV-Datei und Anwendung des Filters
