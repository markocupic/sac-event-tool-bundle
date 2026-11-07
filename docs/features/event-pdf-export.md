# Feature: Tour und Kurs als PDF

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventPdfExport/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventPdfExport`), Tests unter `tests/Feature/EventPdfExport/`

## Ziel

Jede veröffentlichte Tour kann als PDF (A4) angezeigt und heruntergeladen werden. Der Button «PDF» steht auf der Detailseite einer Tour neben «iCal download». Das PDF enthält dieselben Angaben wie die Detailseite im Design «Wegweiser»: blaues Schild mit Titel, Eckdaten in der Seitenleiste rechts, nummerierte Abschnitte und ein QR-Code, der zur Tour auf der Website führt.

Das PDF wird mit [Typst](https://typst.app) erzeugt, einem Satzprogramm, das als einzelne ausführbare Datei läuft. Es braucht weder CloudConvert noch einen Browser und erzeugt ein PDF in wenigen hundert Millisekunden.

## Regeln

- Nur veröffentlichte Events (`published = 1`) vom Typ Tour, Last-Minute-Tour, allgemeine Veranstaltung oder Kurs (`EventPdfDataProvider::EVENT_TYPES`). Für alle anderen: «Seite nicht gefunden».
- **Kurse** (`variant: course`) folgen der Kurs-Detailseite: Schild, Seitenleiste und Akzente in SAC-Rot (`signal`, wie im Design System), Kurs-Nr. (`courseId`) statt Eventnummer, in der Seitenleiste «Kursdaten», «Kursart» (Kursart: Unterkurs) und «Kursstufe», Abschnitte «Kursinhalte» (`issues`) und «Kursziele» (`terms`) vor den gemeinsamen Abschnitten, «Kursleitung» statt «Leitung». Als Link nur die Karte. Dateiname `Kurs_…pdf`. Der Button «PDF» auf der Kurs-Detailseite (`event_kurs_detailview_sac.html.twig`) ersetzt den früheren Button «Detailinfos» (Einzelkurs aus dem [Kursprogramm als PDF](workshop-booklet.md)).
- Wie auf der Detailseite: Schwierigkeit, «Für Anfänger geeignet», Profil und «Route/Details» nur bei Touren (`EventPdfDataProvider::TOUR_TYPES`). Allgemeine Veranstaltungen zeigen stattdessen «Details» (`generalEventDetailText`). Dateiname dann `Event_…pdf` statt `Tour_…pdf`.
- Das PDF ist öffentlich, wie die Detailseite. Leitende mit `hideUser` werden weggelassen (wie bei den Kontaktboxen der Detailseite).
- Die Kontaktangaben der Leitenden (Telefon, Mobile, E-Mail) und die automatisch erzeugten Kontaktdaten des Hauptleiters in «Anmeldung und Abmeldung» (`generateMainInstructorContactDataFromDb`) stehen nur im PDF für eingeloggte Mitglieder (`FrontendUser`). Ohne Login steht unter den Namen der Hinweis `EventPdfDataProvider::CONTACT_HINT`. Was die Leitenden selbst ins Feld `bookingDetails` schreiben, erscheint immer.
- Unter «Leitung» steht neben jedem Namen ein rundes Portrait: das Avatar des Benutzers (`Avatar::getAvatarResourcePath()`), zugeschnitten auf 250 × 250 px mit dem Contao-Bilddienst (`Studio`, Modus `crop`) wie in den Kontaktboxen der Detailseite. Dabei gilt der «wichtige Bereich» aus der Dateiverwaltung; die verkleinerten Bilder liegen im normalen Bild-Cache `assets/images/`. Ohne eigenes Foto (Standard-Avatar aus `sacevt.avatar.*`) stehen die Initialen auf Nachtblau. Die Portraits erscheinen auch ohne Login, wie in den Kontaktboxen der Detailseite.
- Links wie auf der Detailseite, mit QR-Code und anklickbarem Titel: «Karte auf map.geo.admin.ch» (nur mit Koordinaten `coordsCH1903`; URL aus `sacevt.event.geo_link`, statt der Einbettung `embed.html` die normale Kartenansicht `index.html`), «Route auf SAC-Tourenportal» (`linkSacRoutePortal`, geprüft mit `CalendarEventsUtil::getSacRoutePortalLink()`) und «Ausrüstungsempfehlung» (veröffentlichte Seite mit dem Alias `ausruestungslisten`, `EventPdfDataProvider::EQUIPMENT_PAGE_ALIAS`). Fehlt eine URL, wird der Link weggelassen.
- Downloads (`addEnclosure`/`enclosure`): wie auf der Detailseite nur für eingeloggte Mitglieder, als Abschnitt «Downloads / Weitere Informationen» mit Titel (Metadaten, sonst Dateiname), Dateityp, Grösse, Link und QR-Code. Der Link ist der Download-Link der Detailseite (`<Event-URL>?file=<Pfad>`); nur Dateien mit erlaubtem Typ (`allowedDownload`). Der Download selbst funktioniert nur mit Login, siehe [Downloads von Events nur für Mitglieder](event-enclosure-protection.md). Ohne Login steht dort nur der Hinweis `EventPdfDataProvider::DOWNLOADS_HINT`.
- Nicht im PDF: Galerie und die Karte.
- Abgesagte und verschobene Events (`EventState::STATE_CANCELED`, `STATE_RESCHEDULED`) zeigen den Status als roten Hinweis unter dem Titel. Andere Status (z. B. «Anmeldung möglich», «ausgebucht») und die Anzahl Plätze stehen bewusst nicht im PDF.
- Die Abschnitte haben dieselben Titel wie auf der Detailseite. Leere Felder werden weggelassen, die Nummerierung passt sich an.
- Der Abschnitt «Profil» ist eine Tabelle mit einer Zeile pro Tag: «Tag 1», ↑ Aufstieg (Hm, Zeit), ↓ Abstieg (Hm, Zeit). Bei eintägigen Touren ohne Tagesangabe. Reine Zahlen mit Apostroph (`EventPdfDataProvider::formatProfileRows()`).
- Aufstiegs- und Abstiegszeit gesamt in der Seitenleiste unter «Profil gesamt», je unter den Höhenmetern: Summe der Zeiten aller Tage, z. B. «8:45» oder «17» (`EventPdfDataProvider::sumHours()`); steht bei einem Tag keine Zahl, wird die jeweilige Summe nicht angezeigt.
- Aufstieg und Abstieg gesamt: Summe der Meter aller Tage aus `tourProfile`. Steht bei einem Tag keine reine Zahl (z. B. «ca. 900»), wird die Summe nicht angezeigt.
- Längere Texte fliessen auf eine zweite Seite. Ein nummerierter Abschnitt wird dabei nicht getrennt: Passt er nicht mehr auf die Seite, beginnt er auf der nächsten. Nur Abschnitte, die höher als `max-unbroken` (500 pt, rund zwei Drittel einer Seite) sind, dürfen umbrechen (Template `typst/event_layout.typ`, Funktion `keep`). Die Fusszeile (Abschnitt, URL, Stand, Seitenzahl) steht auf jeder Seite.

## Ablauf

1. `EventPdfExportController` prüft das Event und ruft `EventPdfGenerator::generate()` auf.
2. `EventPdfDataProvider::getData()` sammelt alle Angaben als reinen Text (ohne HTML, Insert-Tags und Entities).
3. Ohne Cache (Standard, `cache: false`) erzeugt der Generator das PDF bei jedem Aufruf neu in `<cache_dir>/tmp/`; der Controller löscht die Datei nach dem Senden. Mit Cache (`cache: true`) berechnet der Generator aus den Daten, dem Template und dem Logo einen Hash. Gibt es `<cache_dir>/<Event-ID>/<public|members>/<Hash>.pdf` schon, wird diese Datei ausgeliefert. Die Variante ohne (`public`) und mit Kontaktangaben (`members`) wird getrennt zwischengespeichert.
4. Sonst legt er ein Arbeitsverzeichnis an mit `event_pdf.typ` und `event_layout.typ`, dem Logo, den Portraits, dem QR-Code (SVG, `chillerlan/php-qrcode`) und `data.json`, ruft Typst auf und speichert das PDF (mit Cache: im Cache, ältere PDFs desselben Events werden gelöscht; ohne Cache: als temporäre Datei).

Mit Cache wird das PDF beim nächsten Aufruf automatisch neu erzeugt, sobald sich ein Feld der Tour ändert. Das Datum «Stand» ist nicht Teil des Hashes: Es zeigt, wann die aktuelle Version erzeugt wurde.

Die Texte werden in Typst als Strings eingesetzt, nie als Markup. Zeichen wie `#`, `*`, `_` oder `$` in einem Tourenbeschrieb erscheinen deshalb unverändert im PDF.

Schlägt Typst fehl, wird der Fehler im Contao-Log protokolliert und eine Fehlermeldung (HTTP 500) angezeigt.

## Route (`Controller\EventPdfExportController`)

| Route | Name | Was |
|---|---|---|
| `/_download/event_pdf/{eventId}` | `sac_event_tool_download_event_pdf` | Zeigt das PDF im Browser an (`inline`). Dateiname z. B. `Tour_2027-1342_Sustenhorn-3503-m.pdf`. |

Verlinkt in `contao/templates/modules/calendar/event_reader/event_tour_detailview_sac.html.twig` (Touren, allgemeine Veranstaltungen) und `event_kurs_detailview_sac.html.twig` (Kurse).

## Einrichtung

### Typst installieren

Typst ist eine einzelne Datei ohne Abhängigkeiten. Auf dem Server (z. B. cyon, per SSH):

```bash
mkdir -p ~/bin && cd ~/bin
curl -L -o typst.tar.xz https://github.com/typst/typst/releases/latest/download/typst-x86_64-unknown-linux-musl.tar.xz
tar -xf typst.tar.xz --strip-components=1 && chmod +x typst
./typst --version
```

Lokal unter Windows: `typst-x86_64-pc-windows-msvc.zip` von den [Typst-Releases](https://github.com/typst/typst/releases) herunterladen und `typst.exe` z. B. nach `C:\tools\typst\` entpacken.

PHP muss `proc_open()` ausführen dürfen (nicht in `disable_functions`).

### Konfiguration

```yaml
# config/config.yaml
sacevt:
  feature:
    event_pdf_export:
      # Absoluter Pfad, unter PHP-FPM ist $HOME oft nicht gesetzt (Standard: "typst" aus dem PATH)
      typst_binary: '/home/sacpilat/bin/typst'
      # optional, das sind die Standardwerte (relativ zum Projektverzeichnis)
      logo: 'vendor/markocupic/sac-event-tool-bundle/src/Feature/EventPdfExport/typst/logo.svg'
      cache: false        # true: PDFs pro Event zwischenspeichern
      cache_dir: 'var/sacevt/event_pdf_export'
```

Lokal z. B. in `config/config_dev.yaml`: `typst_binary: 'C:\tools\typst\typst.exe'`.

## Layout anpassen

Das Layout einer Event-Seite steht in `typst/event_layout.typ` (Funktion `event-page`). `typst/event_pdf.typ` erzeugt damit das PDF eines Events, `typst/course_booklet.typ` das [Kursheft](workshop-booklet.md) (`EventPdfGenerator::generateBooklet()`). Die Farben kommen aus dem Design System «SAC Pilatus» und stehen am Anfang der Datei. Zum Ausprobieren ohne Contao: `data.json` aus einem Arbeitsverzeichnis (siehe Ablauf) neben das Template legen und

```bash
typst watch --root . --font-path fonts --ignore-system-fonts event_pdf.typ
```

Jede Änderung am Template ändert den Hash, die PDFs im Cache werden beim nächsten Aufruf neu erzeugt.

## Klassen und Dateien

```
src/Feature/EventPdfExport/
├── EventPdfDataProvider.php                 # sammelt die Angaben einer Tour als reinen Text
├── EventPdfGenerator.php                    # Cache, QR-Code, Aufruf von Typst, Dateiname
├── Controller/EventPdfExportController.php  # Route /_download/event_pdf/{eventId}
└── typst/
    ├── event_layout.typ                     # Layout einer Event-Seite (Design «Wegweiser»)
    ├── event_pdf.typ                        # PDF eines Events
    ├── course_booklet.typ                   # Kursheft: Titelblatt, Inhaltsverzeichnis, Kurse
    ├── logo.svg                             # Logo der Sektion
    └── fonts/                               # Archivo SemiCondensed, Source Sans 3 (SIL Open Font License)
```

## Tests

`tests/Feature/EventPdfExport/EventPdfDataProviderTest.php`: Profil-Tabelle, Summe der Höhenmeter, Zahlenformat mit Apostroph, Schwierigkeiten (einzeln und als Bereich).
