# Feature: Workshop Booklet

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/WorkshopBooklet/` (Namespace `Markocupic\SacEventToolBundle\Feature\WorkshopBooklet`)

## Ziel

Erzeugt das Kursprogramm als PDF (TCPDF):

- **Booklet eines Jahres:** alle veröffentlichten Kurse eines Jahres mit Titelseite, einer Seite pro Kurs und Inhaltsverzeichnis.
- **Einzelner Kurs:** eine Seite für einen Kurs, ohne Titelseite und Inhaltsverzeichnis. Verlinkt auf der Detailseite eines Kurses.

Beide Downloads stehen nur eingeloggten Mitgliedern zur Verfügung. Nicht eingeloggte Besucher erhalten «Seite nicht gefunden».

## Regeln (`WorkshopBookletGenerator`)

### Booklet eines Jahres

- Kurse mit `eventType = course`, `published = 1`, Beginn (`startTime`) ab dem 1. Januar des Jahres und Ende (`endTime`) vor dem 1. Januar des Folgejahres
- Sortiert nach Kursart (`courseTypeLevel0`), Titel und Startdatum
- Titelseite: «SAC Sektion Pilatus», «Kursprogramm <Jahr>», Ausgabedatum. Hintergrundbild aus `sacevt.event.course.booklet_cover_image`
- Inhaltsverzeichnis am Ende des Dokuments (Seitenzahlen über die PDF-Lesezeichen)
- Dateiname nach `sacevt.event.course.booklet_filename_pattern`, z. B. `Kursprogramm_2027.pdf`. Die Datei wird zum Herunterladen angeboten und danach im Temp-Verzeichnis gelöscht.
- Jeder Download wird im Contao-Log protokolliert (`Config\Log::DOWNLOAD_WORKSHOP_BOOKLET`)

### Einzelner Kurs

- Nur veröffentlichte Kurse (`published = 1`)
- Dateiname: `<Event-Alias>.pdf`, wird zum Herunterladen angeboten. Die Datei bleibt im Temp-Verzeichnis liegen und wird beim nächsten Download überschrieben.

### Inhalt einer Kursseite

Template `contao/templates/tcpdf/tcpdf_template_sac_kurse.html5` mit: Titel, Daten, Dauer, Kursart und Kursstufe, Organisatoren, Teaser, Kursziele, Kursinhalte, Voraussetzungen, Ort, Leiter, Leistungen, Anmeldung, Material, Treffpunkt, Weiteres.

Hintergrund: das Bild aus dem Event-Feld `singleSRCBroschuere`, sonst `public/images/events/course/booklet/fallback.jpg`. Dazu unten `background.png`. Das Inhaltsverzeichnis hat `toc.jpg` als Hintergrund.

Schrift: Open Sans aus `src/Feature/WorkshopBooklet/fonts/opensans/` (Light, Bold, Light Italic).

Die PDF-Datei wird vor dem Versand in `sacevt.temp_dir` (Standard `system/tmp`) gespeichert.

## Routen (`Controller\WorkshopBookletDownloadController`)

| Route | Name | Was |
|---|---|---|
| `/_download/print_workshop_booklet_as_pdf/{year}` | `sac_event_tool_download_print_workshop_booklet_as_pdf` | Booklet des Jahres. Ohne `{year}` das aktuelle Jahr. |
| `/_download/print_workshop_details_as_pdf/{eventId}` | `sac_event_tool_download_print_workshop_details_as_pdf` | Einzelner Kurs, verlinkt in `contao/templates/modules/calendar/event_reader/event_kurs_detailview_sac.html.twig` |

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
```

- **Titelbild:** eigenes Bild als Pfad relativ zum Projektverzeichnis in `booklet_cover_image`
- **Kursbild:** im Event (Kurs) das Feld `singleSRCBroschuere` setzen, sonst wird `fallback.jpg` verwendet
- **Layout einer Kursseite:** Template `tcpdf_template_sac_kurse.html5` in `templates/` des Projekts überschreiben

## Klassen und Dateien

```
src/Feature/WorkshopBooklet/
├── WorkshopBookletGenerator.php                            # erzeugt das PDF (Booklet oder einzelner Kurs)
├── WorkshopTCPDF.php                                       # TCPDF-Erweiterung: Hintergrundbilder je Seitentyp (cover, eventPage, TOC)
├── Controller/WorkshopBookletDownloadController.php        # die beiden Download-Routen
└── fonts/opensans/                                         # Schriften für das PDF

contao/templates/tcpdf/tcpdf_template_sac_kurse.html5      # Inhalt einer Kursseite
public/images/events/course/booklet/                        # cover.jpg, toc.jpg, fallback.jpg, background.png
```

## Tests

Noch keine.
