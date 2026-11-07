# Feature: Downloads von Events nur für Mitglieder

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventEnclosureProtection/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventEnclosureProtection`), Tests unter `tests/Feature/EventEnclosureProtection/`

## Ziel

Die Downloads (Anhänge) eines Events, z. B. Topos oder Routenbeschriebe, stehen nur eingeloggten Mitgliedern zur Verfügung. Die Detailseite zeigt die Liste schon nur eingeloggten Mitgliedern an. Dieses Feature sperrt zusätzlich den Download selbst: Wer den Link kennt (z. B. aus einem weitergegebenen [Tour-PDF](event-pdf-export.md)), kann die Datei ohne Login nicht herunterladen.

## Regeln

- Contao liefert einen Anhang aus, wenn die Seite mit `?file=<Pfad>` aufgerufen wird (Event-Leser). Der Listener prüft jeden Frontend-Request mit dem Parameter `file`.
- Ist die Datei ein Anhang eines Events (`tl_calendar_events.addEnclosure = 1`, UUID in `enclosure`) und ist niemand im Frontend angemeldet, wird der Download verweigert (`AccessDeniedException`). Contao zeigt dann die 403-Seite des Seitenbaums (kann auf die Login-Seite weiterleiten).
- Erlaubt sind eingeloggte Mitglieder (`FrontendUser`) und angemeldete Backend-Benutzer (z. B. Vorschau eines Events).
- Andere Dateien mit `?file=` (z. B. Download-Elemente auf normalen Seiten) sind nicht betroffen.

## Klassen und Dateien

```
src/Feature/EventEnclosureProtection/
├── EventEnclosureFinder.php                             # ist eine Datei ein Anhang eines Events?
└── EventListener/EventEnclosureDownloadListener.php     # kernel.request: sperrt ?file= ohne Login
```

## Tests

`tests/Feature/EventEnclosureProtection/EventEnclosureFinderTest.php`: Suche der Datei-UUID in den Anhängen der Events.
