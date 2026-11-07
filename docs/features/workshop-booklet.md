# Feature: Workshop Booklet

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/WorkshopBooklet/` (Namespace `Markocupic\SacEventToolBundle\Feature\WorkshopBooklet`)

## Ziel

Erzeugt das Kursprogramm als PDF mit [Typst](https://typst.app), im Design «Wegweiser» wie [Tour und Kurs als PDF](event-pdf-export.md):

- **Kursheft eines Jahres:** Titelblatt, Inhaltsverzeichnis und alle veröffentlichten Kurse des Jahres, jeder Kurs auf einer neuen Seite.
- **Einzelner Kurs:** dasselbe PDF wie der Button «PDF» auf der Kurs-Detailseite.

Beide Downloads stehen nur eingeloggten Mitgliedern zur Verfügung. Nicht eingeloggte Besucher erhalten «Seite nicht gefunden». Weil nur Mitglieder das Kursheft herunterladen, enthält es die Kontaktangaben der Kursleitung und die Downloads der Kurse.

## Regeln (`WorkshopBookletGenerator`)

### Kursheft eines Jahres

- Kurse mit `eventType = course`, `published = 1`, Beginn (`startTime`) ab dem 1. Januar des Jahres und Ende (`endTime`) vor dem 1. Januar des Folgejahres
- Sortiert nach Kursart (`courseTypeLevel0`), Titel und Startdatum
- **Titelblatt:** Foto aus `sacevt.event.course.booklet_cover_image`, darüber das Logo auf weissem Grund, rotes Wegweiser-Schild mit «SAC Sektion Pilatus» (`sacevt.section_name`), «Kursprogramm <Jahr>», Anzahl Kurse und Ausgabedatum. Darunter die Kursarten.
- **Inhaltsverzeichnis:** nach Kursart gruppiert (Name von `courseTypeLevel0`), pro Kurs Datum, Titel und Seitenzahl. Jede Zeile ist ein Link zur Seite des Kurses. Mehrere einzelne Kurstage erscheinen als «<erster Tag> und weitere». Die Kurse sind zusätzlich als Lesezeichen im PDF hinterlegt.
- **Kurse:** dieselben Seiten wie das PDF eines einzelnen Kurses (rot, mit Kurs-Nr., Kursart, Kursstufe, Kursinhalten, Kurszielen usw.), siehe [Tour und Kurs als PDF](event-pdf-export.md).
- Dateiname nach `sacevt.event.course.booklet_filename_pattern`, z. B. `Kursprogramm_2027.pdf`. Die Datei wird in `sacevt.temp_dir` (Standard `system/tmp`) gespeichert und zum Herunterladen angeboten.
- **Cache:** Das Kursheft wird `sacevt.feature.workshop_booklet.cache_ttl` Sekunden lang wiederverwendet (Standard 3600 = 1 Stunde), damit der Server es nicht bei jedem Download neu erzeugt. Erst danach wird es beim nächsten Download neu erzeugt; Änderungen an Kursen erscheinen also spätestens nach dieser Zeit. Eine Sperrdatei (`Kursprogramm_2027.pdf.lock`) sorgt dafür, dass gleichzeitige Downloads warten und das Heft nur einmal erzeugt wird. Mit `0` wird das Heft bei jedem Download neu erzeugt und danach gelöscht.
- Jeder Download wird im Contao-Log protokolliert (`Config\Log::DOWNLOAD_WORKSHOP_BOOKLET`)

### Einzelner Kurs

- Nur veröffentlichte Kurse (`published = 1`), sonst «Seite nicht gefunden»
- Erzeugt mit `EventPdfGenerator::generate()` (mit Kontaktangaben und Downloads), Dateiname z. B. `Kurs_2027-643_Hochtouren-Grundkurs.pdf`

## Routen (`Controller\WorkshopBookletDownloadController`)

| Route | Name | Was |
|---|---|---|
| `/_download/print_workshop_booklet_as_pdf/{year}` | `sac_event_tool_download_print_workshop_booklet_as_pdf` | Kursheft des Jahres. Ohne `{year}` das aktuelle Jahr. |
| `/_download/print_workshop_details_as_pdf/{eventId}` | `sac_event_tool_download_print_workshop_details_as_pdf` | Einzelner Kurs. Auf der Kurs-Detailseite nicht mehr verlinkt: Dort steht der Button «PDF» aus [Tour und Kurs als PDF](event-pdf-export.md). |

Die Routen der Features werden in `ContaoManager\Plugin::getRouteCollection()` **vor** denen aus `src/Controller/` geladen. Sonst würde die Fallback-Route `/_download/{slug}` aus `Controller\Download\DownloadController` den Aufruf `/_download/print_workshop_booklet_as_pdf` (ohne Jahr) abfangen.

## Konfiguration

```yaml
# config/config.yaml (alles optional, das sind die Standardwerte)
sacevt:
  temp_dir: 'system/tmp'
  event:
    course:
      booklet_cover_image: 'vendor/markocupic/sac-event-tool-bundle/public/images/events/course/booklet/cover.jpg'
      booklet_filename_pattern: 'Kursprogramm_%%s.pdf'
  feature:
    workshop_booklet:
      cache_ttl: 3600     # Sekunden, 0 = ohne Cache
```

Typst, Logo und Schriften: siehe [Tour und Kurs als PDF](event-pdf-export.md) (`sacevt.feature.event_pdf_export`).

- **Titelbild:** eigenes Bild als Pfad relativ zum Projektverzeichnis in `booklet_cover_image`. Fehlt das Bild, ist der obere Teil des Titelblatts nachtblau.
- **Layout:** Titelblatt und Inhaltsverzeichnis in `src/Feature/EventPdfExport/typst/course_booklet.typ`, die Kursseiten in `event_layout.typ`

## Klassen und Dateien

```
src/Feature/WorkshopBooklet/
├── WorkshopBookletGenerator.php                       # wählt die Kurse aus, erzeugt das Kursheft oder einen einzelnen Kurs
└── Controller/WorkshopBookletDownloadController.php   # die beiden Download-Routen

src/Feature/EventPdfExport/
├── EventPdfGenerator.php                              # generateBooklet(): Typst-Aufruf für das Kursheft
└── typst/course_booklet.typ                           # Titelblatt, Inhaltsverzeichnis, Kurse

public/images/events/course/booklet/cover.jpg          # Standard-Titelbild
```

## Tests

Noch keine.
