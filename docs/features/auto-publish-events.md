# Feature: Auto Publish Events

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/AutoPublishEvents/` (Namespace `Markocupic\SacEventToolBundle\Feature\AutoPublishEvents`), Tests unter `tests/Feature/AutoPublishEvents/`

## Ziel

Auf Kalenderebene wird ein Datum eingestellt. Ab diesem Zeitpunkt werden alle Events des Kalenders, die auf der **zweithöchsten Freigabestufe** ihres Freigabestufen-Systems stehen, automatisch auf die **höchste Freigabestufe** gesetzt und dabei **veröffentlicht** (`published = 1`).

Typischer Anwendungsfall: Das Jahresprogramm wird während Wochen erfasst und vom Tourenchef bis auf die zweithöchste Stufe freigegeben. Am Stichtag geht das ganze Programm gleichzeitig online, ohne dass jemand jeden Event einzeln hochstufen muss.

## Hintergrund: Freigabestufen-Systeme

- Das Freigabestufen-System hängt nicht am Kalender, sondern am Eventtyp: `tl_calendar_events.eventType` (Alias) → `tl_event_type.levelAccessPermissionPackage` → `tl_event_release_level_policy_package` → Stufen in `tl_event_release_level_policy` (`pid`, `level`).
- Ein Kalender kann also Events mit unterschiedlichen Systemen enthalten (z. B. Touren mit 4 Stufen, Kurse mit 3 Stufen, allgemeine Events mit 2 Stufen).
- Die Stufen eines Systems müssen nicht lückenlos nummeriert sein. «Höchste» und «zweithöchste» Stufe werden deshalb über die Reihenfolge bestimmt, nicht über `level - 1`.
- `eventReleaseLevel = 0` kommt vor, wenn ein Eventtyp kein System hat.

## Fachliche Regeln

### Kalender

Geprüft werden nur Kalender mit
- `autoPublishEvents = 1`
- `autoPublishEventsDate` gesetzt und `<= jetzt` (Datum mit Uhrzeit)
- Für diesen Stichtag noch kein Lauf: `autoPublishEventsExecutedForDate` ist leer oder ungleich `autoPublishEventsDate`

### Events (alle Bedingungen müssen gelten)

- `pid` = Kalender
- `published = 0`
- Eventtyp hat ein Freigabestufen-System (`levelAccessPermissionPackage > 0`) und `eventReleaseLevel > 0`
- Die aktuelle Stufe gehört zum System des Eventtyps (`EventReleaseLevelUtil::hasValidEventReleaseLevel()`). Sonst: überspringen und Warnung ins Log (passiert z. B., wenn der Eventtyp nachträglich geändert wurde)
- Das System hat mindestens zwei Stufen (bei genau zwei Stufen ist die zweithöchste die Anfangsstufe; diese Events werden ebenfalls veröffentlicht)
- Aktuelle Stufe = zweithöchste Stufe des Systems
- Kein Filter auf Eventtyp, `endDate` oder `eventState`: auch vergangene, abgesagte und verschobene Events werden hochgestuft
- Wenn `enableEventStartDateValidation = 1`: `startDate` liegt im gültigen Zeitraum. Der Cron verhält sich hier wie ein Nicht-Admin. Events ausserhalb werden übersprungen und geloggt. Ist `enableEventStartDateValidation = 0`, entfällt diese Prüfung.

Nicht angefasst werden:
- Events auf der höchsten Stufe, die unveröffentlicht sind (wurden bewusst manuell versteckt)
- Events auf tieferen Stufen
- Events ohne Freigabestufen-System

### Aktion pro Event (`EventPublisher`)

1. `Versions::initialize()`: legt die Ausgangsversion an, falls der Event noch keine Version hat
2. `UPDATE tl_calendar_events SET eventReleaseLevel = <höchste Stufe>, published = 1, tstamp = <jetzt> WHERE id = ? AND eventReleaseLevel = <zweithöchste Stufe> AND published = 0`
   Die WHERE-Bedingung schützt vor gleichzeitigen Änderungen im Backend. Wurde keine Zeile geändert, gilt der Event als «in der Zwischenzeit geändert» und wird übersprungen.
3. `Versions::create()` mit Benutzername `Auto Publish Events`, User-ID 0 und Backend-Link auf den Event (im Cron gibt es keinen eingeloggten User und keinen Request)
4. Cache-Tags invalidieren wie beim Speichern im Backend: `contao.db.tl_calendar_events.<id>`, `contao.db.tl_calendar.<pid>` (`EntityCacheTags::invalidateTagsFor()`)
5. Eintrag im Contao-System-Log (`contao.general`, `info`): Event-ID, Titel, alte → neue Stufe. Übersprungene Events mit Grund (`warning`, Fehler als `error`). Keine E-Mail, keine Notification
6. Jeder Event in einem eigenen `try/catch`: Ein fehlerhafter Event bricht den Lauf nicht ab. Ein Fehler in einem Kalender bricht den Cron nicht ab; dieser Kalender wird nicht als ausgeführt markiert und beim nächsten Lauf erneut versucht.

Der `EventPublisher` verwendet bewusst nicht `EventReleaseLevelUtil::shiftEventReleaseLevel()`: Diese Methode und ihre Events (`ChangeEventReleaseLevelEvent`, `PublishEventEvent`) brauchen einen Backend-Request mit eingeloggtem User, den es im Cron nicht gibt. Aus demselben Grund werden die Modell-Methoden `EventReleaseLevelPolicyModel::findMaxLevelByEventId()` usw. nicht verwendet, sie schreiben Backend-Messages. Die Stufen werden in `EventReleaseLevel\EventReleaseLevelPolicyUtil::getLevelsByEventType()` (Rückgabe: `list<EventReleaseLevel\ReleaseLevel>`, höchste Stufe zuerst) mit einer Abfrage über `tl_event_type` → `tl_event_release_level_policy` ermittelt.

### Zeitpunkt

- Der Stichtag hat eine Uhrzeit. Der Cron läuft deshalb alle 15 Minuten: `#[AsCronJob('*/15 * * * *')]`. Der Stichtag wird also höchstens 15 Minuten verspätet ausgeführt. Der Cron selbst ist billig: eine Abfrage auf `tl_calendar`, nur bei fälligen Kalendern folgen Event-Abfragen.
- Kein Symfony Messenger nötig: Die Datenmenge ist klein (ein paar hundert Events pro Jahr und Kalender), die Aktion ist eine Datenbank-Änderung ohne Versand.

### Bezug zu `maxEventReleaseLevelTimeLimit`

Die Einstellung «Hochstufen auf höchste FS ab Datum ermöglichen» erlaubt es Nicht-Admins **ab** einem Datum, selbst hochzustufen. Dieses Feature stuft **am** Datum automatisch hoch. Die beiden Daten sind unabhängig: eigenes Feld `autoPublishEventsDate`. Der Cron prüft `maxEventReleaseLevelTimeLimit` nicht.

### Einmalig pro Stichtag

Der Lauf erfolgt einmal am Stichtag. Danach stufen die Verantwortlichen wieder manuell hoch. Wird das Datum geändert, läuft es erneut.

## Benennung

Präfix überall: `AutoPublishEvents` bzw. `auto_publish_events`

## Konfiguration

Im Kalender (Legende «Events automatisch veröffentlichen»): Funktion aktivieren und Stichtag mit Uhrzeit setzen. Der Status des letzten Laufs wird im Kalender angezeigt. Keine Konfiguration in `config/config.yaml`. Der Contao-Cron muss laufen (per CLI empfohlen).

## Datenmodell

### tl_calendar (Legende `auto_publish_events_legend`)

| Feld | Typ | Default |
|---|---|---|
| `autoPublishEvents` | Checkbox, `submitOnChange`, Selector der Subpalette, `doNotCopy` | `false` |
| `autoPublishEventsDate` | Text, `rgxp => datim` (Datum mit Uhrzeit), Datepicker, Pflichtfeld, `nullIfEmpty`, `doNotCopy`, `bigint(20) unsigned NULL` | `NULL` |
| `autoPublishEventsStatus` | Kein DB-Feld. Nur Anzeige über `input_field_callback` (`DataContainer\Calendar::renderStatus()`): «noch kein Lauf», «ausgeführt am … für den Stichtag …» oder «letzter Lauf für einen anderen Stichtag» | - |
| `autoPublishEventsExecutedAt` | Zeitpunkt des letzten Laufs. Wird nur vom Cron geschrieben, in keiner Palette, `doNotCopy` | `NULL` |
| `autoPublishEventsExecutedForDate` | Stichtag, für den der letzte Lauf erfolgt ist. Wird nur vom Cron geschrieben, in keiner Palette, `doNotCopy` | `NULL` |

Einmaliger Lauf pro Stichtag: Der Kalender ist fällig, solange `autoPublishEventsExecutedForDate` nicht dem aktuellen `autoPublishEventsDate` entspricht. Wird der Stichtag im Backend geändert, ist der Kalender damit automatisch wieder fällig. Es braucht keinen Save-Callback, und ein offenes Backend-Formular kann die Lauf-Felder nicht überschreiben, weil sie in keiner Palette stehen.

`doNotCopy`: Ein kopierter Kalender (inkl. Events) übernimmt die Funktion nicht, sonst würden die kopierten Events beim nächsten Cron-Lauf veröffentlicht.

Sprachdatei `contao/languages/en/tl_calendar.php` (mit deutschem Inhalt; `de` wird vom composer-file-copier-plugin daraus erzeugt).

Keine eigene Tabelle. Protokoll nur über das Contao-System-Log.

## Klassen

```
src/Feature/AutoPublishEvents/
├── Cron/AutoPublishEventsCron.php            # alle 15 Min.: fällige Kalender laufen lassen, Zusammenfassung ins Cron-Log
├── Command/AutoPublishEventsPreviewCommand.php  # sacevt:auto-publish-events:preview (nur Anzeige)
├── DataContainer/Calendar.php                # input_field_callback: Status des letzten Laufs
├── CalendarRunner.php                        # fällige Kalender finden, Lauf für einen Kalender, Log, als ausgeführt markieren
├── CandidateProvider.php                     # Events eines Kalenders auf der zweithöchsten Stufe (inkl. Prüfung Zeitraum)
├── EventPublisher.php                        # stuft einen Event hoch, veröffentlicht, Version, Cache-Tags
├── Candidate.php                             # Wertobjekt: Event mit aktueller und Ziel-Stufe
├── SkippedEvent.php                          # Wertobjekt: übersprungener Event mit Grund (REASON_*)
└── PublishResult.php                         # Ergebnis eines Laufs für einen Kalender
```

Ausserhalb des Feature-Ordners:

- `src/EventReleaseLevel/EventReleaseLevelPolicyUtil.php`: Methode `getLevelsByEventType()`, Stufen des Freigabestufen-Systems eines Eventtyps, höchste zuerst
- `src/EventReleaseLevel/ReleaseLevel.php`: Wertobjekt für eine Stufe (`id`, `level`, `title`)

- `CandidateProvider` lädt die Stufen pro Eventtyp nur einmal pro Lauf.
- `EventPublisher` ist die einzige Klasse, die Events schreibt. Cron und Vorschau laufen beide über `CalendarRunner`, die Vorschau als Dry Run.

### Command (Vorschau)

```
php vendor/bin/contao-console sacevt:auto-publish-events:preview
```

Listet alle Kalender mit aktivierter Funktion und einem Stichtag in der Zukunft, sortiert nach Stichtag (dazu Kalender, deren Stichtag eben erreicht wurde, deren Lauf aber noch aussteht). Pro Kalender wird angezeigt, welche Events am Stichtag hochgestuft werden, gruppiert nach Stufenwechsel, und welche übersprungen werden (mit Grund):

```
Kalender ID 12 "Kurse 2027"
---------------------------

 Am 12.12.2026 18:00 werden folgende Events von FS 3 auf FS 4 hochgestuft und veröffentlicht:
 * Event ID 12 Sidelhorn
 * Event ID 13 Pilatus

 Folgende Events werden nicht hochgestuft:
 * Event ID 14 Titlis: Das Startdatum liegt ausserhalb der Kalender-Zeitspanne.
```

Die Vorschau zeigt den aktuellen Stand der Events, massgebend ist der Stand am Stichtag. Sie ändert nichts, schreibt nichts ins Log und markiert keinen Lauf als ausgeführt (`CalendarRunner::run($id, dryRun: true)`). Veröffentlicht wird ausschliesslich durch den Cron.

## Ablauf Cron

1. `CalendarRunner::findDueCalendarIds()`: `autoPublishEvents = 1`, `autoPublishEventsDate <= jetzt`, `autoPublishEventsExecutedForDate` leer oder ungleich `autoPublishEventsDate`
2. Pro Kalender `CalendarRunner::run()`: Kandidaten ermitteln → pro Kandidat `EventPublisher::publish()` → übersprungene Events loggen
3. `autoPublishEventsExecutedAt = jetzt`, `autoPublishEventsExecutedForDate = Stichtag` setzen, **auch** wenn kein Event betroffen war
4. Zusammenfassung pro Kalender ins Cron-Log: veröffentlicht, übersprungen, davon Fehler

## Tests (`tests/Feature/AutoPublishEvents/`)

- `tests/EventReleaseLevel/EventReleaseLevelPolicyUtilTest`: `getLevelsByEventType()`, Mapping der Stufen, Eventtyp ohne System
- `CandidateProviderTest`: zweithöchste Stufe, andere Stufen ignoriert (auch höchste Stufe unveröffentlicht), Stufen mit Lücken (1, 2, 5), System mit 2 Stufen (Anfangsstufe wird veröffentlicht), System mit 1 Stufe, ungültige Stufe, Kalender mit gemischten Systemen, Stufen nur einmal pro Eventtyp geladen, Zeitraum `validTimePeriod*` (Grenzen, ausgeschaltet), Abfrage ohne Filter auf `eventState`/`endDate`
- `EventPublisherTest`: Stufe, `published`, Version, Cache-Tags; Event in der Zwischenzeit geändert
- `CalendarRunnerTest`: fällige Kalender, Lauf wird markiert (auch ohne Kandidaten), Vorschau (Dry Run) ändert nichts, anstehende Kalender (`findUpcomingCalendarIds()`), geänderter Event, Fehler in einem Event, Log der übersprungenen Events, unbekannter Kalender
- `Cron\AutoPublishEventsCronTest`: alle fälligen Kalender, Fehler in einem Kalender stoppt die anderen nicht
- `Command\AutoPublishEventsPreviewCommandTest`: Ausgabe pro Kalender (gruppiert nach Stufenwechsel, übersprungene Events), kein anstehender Kalender
- `DataContainer\CalendarTest`: drei Status-Texte
