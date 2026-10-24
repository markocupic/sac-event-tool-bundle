# Feature: Abo-Umfrage

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/AboSurvey/` (Namespace `Markocupic\SacEventToolBundle\Feature\AboSurvey`), Tests unter `tests/Feature/AboSurvey/`

## Ziel

Eingeloggte Mitglieder geben im Frontend an, welches Abo sie bisher hatten, und entscheiden sich für ein Abo für ein bestimmtes Jahr (jeweils «Kein Abo», «White Risk» oder «SchweizMobil»). Die Eingaben werden in `tl_abo_survey` gespeichert und können im Backend eingesehen, bearbeitet und gelöscht werden.

## Fachliche Regeln

- Das Inhaltselement ist nur für eingeloggte Frontend-User sichtbar; Gäste sehen nichts.
- Teilnehmen dürfen nur Mitglieder, die alle Bedingungen erfüllen (`AboSurveyEligibility`):
  1. `tl_member.sacMemberId` > 0,
  2. es gibt einen aktiven Backend-Benutzer (`tl_user`: `disable = 0` und innerhalb von `start`/`stop`) mit derselben `sacMemberId`,
  3. dieser Backend-Benutzer ist kein Bergführer (`tl_user.leiterQualifikation` enthält nicht `TourguideQualification::MOUNTAIN_GUIDE`). Bergführer sind immer ausgeschlossen, auch wenn sie unter 4. ausgewählt sind oder zum Vorstand gehören,
  4. für diesen Backend-Benutzer trifft mindestens eines zu:
     - er ist im Inhaltselement unter «Zusätzlich berechtigte Benutzer» ausgewählt (`aboSurveyAllowedUsers`),
     - er hat eine Rolle (`tl_user.userRole`), die im Inhaltselement unter «Zusätzlich berechtigte Rollen» ausgewählt ist (`aboSurveyAllowedUserRoles`),
     - er hat eine Rolle, die zum Vorstand gehört (`tl_user_role.belongsToExecutiveBoard`),
     - er ist als Leiter (Haupt- oder Co-Leiter, `tl_calendar_events_instructor`) bei mindestens einer Tour oder einem Kurs (`eventType` `tour` oder `course`), der im eingestellten Jahr beginnt (`startDate` 1.1. 00:00 bis 31.12. 23:59:59) und mindestens Freigabestufe 3 hat (`tl_event_release_level_policy.level >= 3`).

  Ist eine Bedingung nicht erfüllt, sieht das Mitglied statt des Formulars eine Meldung mit dem Grund (die erste nicht erfüllte Bedingung). Wer die Umfrage bereits abgegeben hat, sieht weiterhin seine Eingaben, auch wenn er die Bedingungen inzwischen nicht mehr erfüllt.
- Das Formular ist vom Startdatum (00:00 Uhr) bis zum Enddatum (23:59:59 Uhr) aktiv. Vorher erscheint ein Hinweis mit dem Startdatum, danach ein Hinweis, dass die Umfrage abgeschlossen ist.
- Pro Mitglied (`sacMemberId`) und Jahr gibt es höchstens einen Eintrag (Unique-Index). Die Umfrage kann nur einmal abgegeben werden: Danach sieht das Mitglied statt des Formulars seine Eingaben (Jahr, Gruppe, bisheriges Abo, Abo, Name, Mitglieder-Nr., E-Mail, Adresse, Kommentar falls vorhanden) und den Hinweis, dass es die Umfrage bereits am `dateAdded` (dd.mm.YYYY) ausgefüllt hat. Direkt nach dem Absenden erscheint statt dieses Hinweises einmalig die Erfolgsmeldung; der Hinweis erscheint erst, wenn das Mitglied die Seite später wieder aufruft. Das gilt auch nach Ablauf des Zeitfensters. Korrekturen sind nur im Backend möglich.
- Vorname, Nachname, E-Mail und Adresse werden beim Speichern aus dem Mitgliederprofil (`tl_member`) übernommen und sind im Formular nicht editierbar.
- Das Formular enthält das Pflicht-Auswahlfeld «Gruppenzugehörigkeit» (`eventOrganizer`, Optionen: alle `tl_event_organizer` in der Backend-Sortierung; gespeichert wird `tl_event_organizer.title`, nicht die ID), die zwei Pflicht-Auswahlfelder (Select) «Bisheriges Abo» (`previousAboType`) und «Abo» (`aboType`) mit den Optionen «Kein Abo», «White Risk» und «SchweizMobil» (`AboSurveyAboType`) sowie ein optionales Kommentarfeld (`notice`, Textarea, max. 2000 Zeichen).
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

Backend-Modul «Abo-Umfrage» (`abo_survey`) in der Backend-Menü-Kategorie «SAC-Pilatus Umfragen» (`sac_pilatus_surveys`): Liste mit Filter nach Jahr, Gruppe, bisherigem Abo und Abo, Suche, Bearbeiten und Löschen. Neue Einträge entstehen nur über das Frontend.

### CSV-Export

Die globale Operation «CSV-Export» lädt die Einträge als CSV-Datei herunter (UTF-8 mit BOM, Trennzeichen `;`, öffnet sich direkt in Excel). Der Export enthält immer alle Einträge (alle Jahre); die Filter der Liste werden nicht berücksichtigt. Spalten: Jahr, Gruppenzugehörigkeit, Bisheriges Abo, Abo, SAC-Mitgliedernummer, Vorname, Nachname, E-Mail, Strasse, PLZ, Ort, Kommentar, Eingereicht am. Dateiname: `abo_umfrage_<datum>.csv`. Für den Export braucht der Benutzer Zugriff auf das Backend-Modul.

## Datenmodell `tl_abo_survey`

| Feld | Inhalt |
|---|---|
| `id`, `tstamp` | Standardfelder |
| `dateAdded` | Zeitpunkt der ersten Abgabe |
| `year` | Jahr der Umfrage |
| `eventOrganizer` | Gruppenzugehörigkeit: `tl_event_organizer.title` (Titel, nicht ID; wird ein Titel später geändert, bleibt der gespeicherte Wert erhalten) |
| `previousAboType` | Bisheriges Abo: `noAbo`, `whiteRisk` oder `schweizMobil` (`AboSurveyAboType`) |
| `aboType` | Gewähltes Abo: `noAbo`, `whiteRisk` oder `schweizMobil` (`AboSurveyAboType`) |
| `sacMemberId` | SAC-Mitgliedernummer |
| `firstname`, `lastname` | Name aus `tl_member` |
| `email`, `street`, `postal`, `city` | Kontaktdaten aus `tl_member` |
| `notice` | Kommentar des Mitglieds (optional, max. 2000 Zeichen) |

## Klassen und Dateien

- `Controller/ContentElement/AboSurveyController.php`: Inhaltselement mit Haste-Formular
- `AboSurveyEligibility.php`: prüft, ob ein Mitglied an der Umfrage teilnehmen darf, und liefert sonst den Grund
- `AboSurveyPeriod.php`: berechnet, ob das Formular noch nicht offen, offen oder geschlossen ist
- `AboSurveyRepository.php`: liest und speichert die Einträge (Doctrine DBAL)
- `AboSurveyAboType.php`: verfügbare Abos
- `AboSurveyCsvExport.php`: erzeugt die CSV-Datei
- `DataContainer/AboSurveyContent.php`: HTML-Erklärung (`aboSurveyExplanation`) und Optionen der Felder `aboSurveyAllowedUserRoles` und `aboSurveyAllowedUsers`
- `DataContainer/AboSurveyTable.php`: Optionen des Felds `eventOrganizer` und onload-Callback, der die Export-Aktion (`action=exportAboSurveyCsv`) abfängt und den Export auslöst
- `contao/dca/tl_abo_survey.php`, Felder in `contao/dca/tl_content.php`
- Template `contao/templates/twig/content_element/abo_survey.html.twig`
- Sprachdateien `contao/languages/{de,en}/tl_abo_survey.php`, Texte in `default.php` (`MSC.abo_survey_*`)

## Tests

- `AboSurveyPeriodTest`: Grenzen des Zeitfensters (Start 00:00, Ende 23:59:59)
- `AboSurveyEligibilityTest`: die Ablehnungsgründe (inkl. Bergführer) sowie die Berechtigung über Benutzer, Rolle, Vorstand und Leitung
- `AboSurveyCsvExportTest`: Inhalt der CSV-Datei, immer alle Einträge
