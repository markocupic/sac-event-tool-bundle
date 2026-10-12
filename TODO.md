# TODO

## Umstieg auf Contao 5.7 (Support für Contao 5.3 entfernen)

Sobald alle Installationen auf Contao 5.7 laufen, die Kompatibilitätsschichten für
Contao 5.3 entfernen und alle Operationen konsequent mit `DataContainerOperation`
umsetzen. Die alte Signatur (`$href, $label, $title, $class, $attributes, …`) ist seit
Contao 5.6 deprecated und funktioniert in Contao 6 nicht mehr.

### Button-Callbacks

- [ ] **sac-event-tool-bundle** – `src/DataContainer/CalendarEventsMember.php`
  - `config.onload`-Callback `registerGlobalOperationButtonCallbacks()` entfernen.
  - `legacyBackToEventSettingsButton()`, `legacySendEmailButton()` und `renderGlobalOperation()` entfernen.
  - `backToEventSettingsButton()` und `sendEmailButton()` wieder direkt per
    `#[AsCallback(table: 'tl_calendar_events_member', target: 'list.global_operations.<name>.button')]` registrieren.
- [ ] **contao-backend-column-toggle** – `src/EventListener/DataContainer/ColumnToggleListener.php`
  - In `getButtonCallback()` die Weiche `class_exists(DataContainerGlobalOperationsBuilder::class)` und den Legacy-Rückgabewert entfernen.
- [ ] **contao-umfrage-jahresprogramm** – `UmfrageJahresprogramm.php`
  - Globale Operation `export` (`generateSendEmailButton()`) noch mit alter Signatur → auf `DataContainerOperation` umstellen (`$operation->setUrl(…)`).
- [x] Operationen der Datensätze im sac-event-tool-bundle (`CalendarEvents`, `CalendarEventsMember`, `Calendar`, `CalendarContainer`, `CalendarEventsInstructorInvoice`, `ParticipantEventHistoryOperationListener`) – bereits auf `DataContainerOperation`.
- [x] **rotate_image** – `RotateImageListener` – bereits auf `DataContainerOperation`.

### contao-custom-global-operation

- [ ] Deaktivierte Operationen unterstützen: `$operation->disable()` rendert in Contao 5.7+ kein `<a>`, sondern ein `<li>` mit ausgegrautem Icon.
  - `data-customglobop` zusätzlich über `listAttributes` ans `<li>` hängen (`LoadDataContainerListener`).
  - Im `ParseBackendTemplateListener` das ganze `<li>` verschieben statt nur den Link.
  - Für deaktivierte Icons jeweils eine `…--disabled.svg` bereitstellen (wie `square-check--disabled.svg`).
- [ ] Contao-5.3-Teile entfernen: SCSS-Regel für das Icon als `background-image` (Contao 5.3) streichen, nur noch die `<img>`-Variante behalten.
- [ ] `composer.json`: `contao/core-bundle` auf `^5.7 || ^6.0` setzen.

### Weitere Prüfungen für Contao 6 (noch nicht erfasst)

- [ ] `label_callback`: String-Rückgaben werden in Contao 6 HTML-encodiert → bei HTML-Ausgabe `RecordLabel` mit `htmlLabel` zurückgeben.
- [ ] `child_record_callback` (in Contao 6 entfernt) → `label_callback` verwenden.
- [ ] `decodeEntities` / `useRawRequestData` in den DCAs entfernen (in Contao 6 entfernt, Eingaben werden roh gespeichert).
- [ ] Ausgaben ohne Twig (Hooks, Callbacks, die HTML zurückgeben) auf korrektes Escaping prüfen: In Contao 6 werden Eingaben nicht mehr encodiert gespeichert.
- [ ] Twig: `|insert_tag_raw` → `|insert_tag_html` (Reihenfolge `foo|raw|insert_tag_html`), Twig-Variable `request_token` → `contao.request_token`.
- [ ] Twig: `{{ insert_tag('insert_module::…')|raw }}` → `{{ frontend_module(…) }}`.
- [ ] Verbleibende `.html5`-Templates in Twig umwandeln.
- [ ] `composer.json`: `contao/core-bundle` auf `^5.7 || ^6.0` setzen.

## Sonstiges

- [ ] `assets/js/old___slidebox.js` löschen (wird nirgends verwendet), danach `npm run build`.
- [ ] `src/DataContainer/CalendarEvents.php` (`validateEventDates()`): `new Date(…)->tstamp` ohne Klammern benötigt PHP 8.4 – mit dem Umstieg auf Contao 5.7/PHP 8.4+ kein Problem mehr, sonst `(new Date(…))->tstamp`.
