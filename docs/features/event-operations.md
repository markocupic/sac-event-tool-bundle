# Feature: Event-Operationen

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/EventOperations/` (Namespace `Markocupic\SacEventToolBundle\Feature\EventOperations`), Tests unter `tests/Feature/EventOperations/`

## Ziel

Die Operationen eines Events (bearbeiten, Eventliste, Vorschau, Teilnehmerliste, E-Mail, Tourrapport, Rapporte drucken) werden an einer Stelle erzeugt und für den angemeldeten Backend-Benutzer geprüft. So zeigen alle Stellen im Backend dieselben Operationen mit denselben Icons, Farben und Berechtigungen:

- Dashboard «Ihre nächsten Events» / «Ihre vergangenen Events» auf der Backend-Startseite (`Controller\Backend\WelcomeScreenDashboardController`)
- Button-Leiste im Event-Formular (`EventListener\GenerateEventDashboardListener`)
- Treffer der Event-Suche auf der Backend-Startseite (`markocupic/contao-backend-event-search`, optional), siehe unten

## Klassen

| Klasse | Aufgabe |
|---|---|
| `EventOperationsBuilder` | `build(CalendarEventsModel $event, string $do = 'calendar')` liefert alle Operationen eines Events in fester Reihenfolge, Schlüssel = Name der Operation. |
| `EventOperation` | Unveränderliches DTO: Name, Zustand, Label, Titel, Font-Awesome-Klassen, Link, primär ja/nein, Farbe, Accesskey, Link-Attribute. `toArray()` liefert die Daten für Twig. |
| `EventOperationState` | `Enabled` (ausführbar), `Denied` (keine Berechtigung, ausgegraut), `NotAvailable` (gibt es für dieses Event nicht). |
| `EventListener\BackendEventSearchOperationsListener` | Hört auf `BuildSearchHitOperationsEvent` der Event-Suche und setzt für jeden Treffer das Operations-Menü. |

## Operationen und Berechtigungen

Grundlage ist der **Lesezugriff auf das Event**: `contao_dc.tl_calendar_events` mit einer `ReadAction` auf den Datensatz, also dieselbe Prüfung wie in der Eventliste eines Kalenders (Zugriff auf das Kalender-Modul und auf den Kalender des Events, `tl_user.calendars`). Die Kalender-Container werden hier nicht geprüft; das Dashboard listet ohnehin nur Events aus den Kalender-Containern des Benutzers. Ohne Lesezugriff sind alle Operationen `Denied`.

| Name | Icon | Enabled, wenn … | NotAvailable, wenn … |
|---|---|---|---|
| `edit-event` | `fa-pen-to-square` | Lesezugriff. Ohne `CAN_WRITE_EVENT` sieht der Benutzer die Felder nur an (`AccessDecision\CalendarEvents`). | – |
| `event-listing` | `fa-list-tree` | Lesezugriff | – |
| `event-preview` | `fa-presentation-screen` | Lesezugriff | der Eventtyp keine Vorschauseite hat |
| `event-registration-list` | `fa-people-group` | Lesezugriff. Bearbeiten dürfen nur Benutzer mit `CAN_ADMINISTER_EVENT_REGISTRATIONS` (`AccessDecision\CalendarEventsMember`). | – |
| `send-email` | `fa-at` | Lesezugriff + `CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS` (wie im `EventParticipantEmailController`) | das Event keine Anmeldungen hat |
| `create-or-edit-report` | `fa-comment-pen`, grün = erfasst, rot = offen | Lesezugriff + `CalendarEventsVoter::CAN_WRITE_EVENT` (wie die globale Operation `writeTourReport` der Teilnehmerliste) | das Event keine Tour / Last Minute Tour ist |
| `print-report` | `fa-print` | Lesezugriff + `CalendarEventsInstructorInvoiceVoter::HAS_ACCESS` (wie die globale Operation `printInstructorInvoice` der Teilnehmerliste) | das Event keine Tour / Last Minute Tour ist |

Der Titel erklärt bei `Denied` und `NotAvailable`, warum die Operation nicht ausführbar ist («…: keine Berechtigung», «… nicht verfügbar»). Labels und Titel stehen in `contao/languages/en/default.php` (`MSC.bhs_dashb_*`).

## Darstellung

- **Dashboard:** alle Operationen ausser `edit-event` als Icons. `edit-event` ist der Link des Event-Titels. `Denied` und `NotAvailable` werden grau und ohne Link angezeigt.
- **Event-Suche:** wie im Dashboard (Template `Backend/EventOperations/operations_menu.html.twig`, `with_portal: true`). Der Event-Titel des Treffers ist wie im Dashboard nur verlinkt, wenn `edit-event` ausführbar ist (`BuildSearchHitOperationsEvent::setEditUrl()`). Ohne installierte Event-Suche wird der Listener nie aufgerufen.
- **Button-Leiste im Event-Formular:** `edit-event`, `event-listing`, `event-preview`, `event-registration-list`, `create-or-edit-report`, `print-report` als Buttons (öffnen in neuem Fenster, mit Accesskey). `Denied` wird als grauer Button ohne Link angezeigt, `NotAvailable` gar nicht. Weitere Buttons können über `GenerateEventDashboardEvent` ergänzt werden (z. B. «Event Auswertungen» im Feature Event Feedback).

Die Icons und das aufklappbare Menü (kleine Bildschirme, Stimulus-Controller `sacevt--backend--operation-dropdown-menu`) kommen aus dem Template `Backend/EventOperations/operations_menu.html.twig`. Das geöffnete Menü wird in die `.dropdown-portal` des umgebenden Elements `.sacevt-op-menu-item` kopiert, damit es nicht von `overflow: hidden` abgeschnitten wird. Im Dashboard ist das der Listeneintrag, in der Suche erzeugt das Template beides selbst (`with_portal: true`). Die Reihenfolge der Icons steht in `EventOperationsBuilder::MENU_OPERATIONS`.

Die Farben sind nicht an eine Seite gebunden und können auch von anderen Bundles verwendet werden (`assets/styles/backend/scss/components/_event_operations.scss`, Build mit `npm run build`):

| Klasse | Farbe |
|---|---|
| `sacevt-op-icon` | Blau `#1a6b99` |
| `sacevt-op-icon--green` | Grün `#589b0f` (Tourrapport erfasst) |
| `sacevt-op-icon--red` | Rot `#d80128` (Tourrapport offen) |
| `sacevt-op-icon--disabled` | Grau `#6f7685` (Denied, NotAvailable) |
| `.event-dashboard .tl_submit.sacevt-op--disabled` | Ausgegrauter Button in der Button-Leiste |
| `.sacevt-op-menu-item` | Element mit Menü und `.dropdown-portal`; darin gelten die Styles des Menüs |

`EventOperation::getIconClasses()` liefert die passenden Klassen inklusive Farbe.

## Verwendung in eigenem Code

```php
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;

foreach ($eventOperationsBuilder->build($event) as $name => $operation) {
    if (!$operation->isAvailable()) {
        continue;
    }

    // $operation->href ist nur gesetzt, wenn $operation->isEnabled()
    // $operation->getIconClasses(), $operation->title, $operation->label
}
```

## Tests

- `EventOperationTest`: Zustände, Link nur bei `Enabled`, Icon-Klassen und Farben, `toArray()`
- `EventListener\BackendEventSearchOperationsListenerTest`: Menü für einen Suchtreffer, unbekanntes Event, fremde Events (wird übersprungen, wenn die Event-Suche nicht installiert ist)
- `EventOperationsBuilderTest`: Reihenfolge, Links, Farbe des Tourrapports, Berechtigungen pro Operation, `NotAvailable` bei Kursen, ohne Anmeldungen und ohne Vorschauseite, Lesezugriff mit dem Datensatz des Events
