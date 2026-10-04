# Feature: Backend User Permission Reset

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/BackendUserPermissionReset/` (Namespace `Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset`)

## Ziel

Backend-User sollen ihre Rechte nur über Benutzergruppen erhalten. Persönlich gesetzte Rechte werden regelmässig zurückgesetzt, damit kein Durcheinander aus Einzelrechten entsteht.

## Regeln (`BackendUserPermissionReset::resetUser()`)

- Betroffen sind nur Backend-User mit `admin = 0` und `inherit = 'extend'` («Gruppenrechte erweitern»), siehe `isResettable()`.
- Zurückgesetzt werden die Contao-Rechtefelder (`modules`, `themes`, `elements`, `frontendModules`, `fields`, `pagemounts`, `alpty`, `filemounts`, `fop`, `forms`, `formp`, `imageSizes`, `amg`) und alle Felder aus `$GLOBALS['TL_PERMISSIONS']` (z. B. `calendars`, `calendarp`, `news`). Felder, die in `tl_user` nicht existieren, werden übersprungen.
- Danach hat der User:
  - die Rechte seiner **aktiven** Gruppen (`disable = 0`, innerhalb von `start`/`stop`), zusammengeführt ohne Duplikate
  - sein [Home-Verzeichnis](backend-user-home-directory.md) als Filemount (`filemounts`), falls es in der Dateiverwaltung existiert
- Admins und User mit einer anderen Rechtevererbung werden nicht verändert.

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Login eines Backend-Users (`EventListener\ResetPermissionsOnLoginListener`, Priorität 0) | Nur der eingeloggte User, nur wenn `reset_permissions_on_login` aktiv ist. Läuft nach dem Anlegen des Home-Verzeichnisses (Priorität 10). |
| Cron `Cron\BackendUserPermissionResetCron` (`#[AsCronJob('45 3 * * *')]`, täglich 03:45) | Alle betroffenen User (`resetAll()`). Läuft nach dem Home-Verzeichnis-Cron (03:15). |
| Backend: Systemwartung → «Benutzerrechte aller Backend Benutzer bereinigen/reseten» (`$GLOBALS['TL_PURGE']['custom']['reset_backend_user_rights']`) | Alle betroffenen User (`resetAll()`). |

## Konfiguration

```yaml
# config/config.yaml
sacevt:
    user:
        backend:
            # Rechte beim Login zurücksetzen (Standard: false)
            reset_permissions_on_login: true
            # Ort der Home-Verzeichnisse (für den Filemount)
            home_dir: 'files/sektion/be_user_home_directories'
```

Der Cron und die Systemwartung funktionieren unabhängig von `reset_permissions_on_login`.

## Klassen

```
src/Feature/BackendUserPermissionReset/
├── BackendUserPermissionReset.php                    # Service: isResettable(), resetUser(), resetAll()
├── EventListener/ResetPermissionsOnLoginListener.php # Login: nur der eingeloggte User
└── Cron/BackendUserPermissionResetCron.php           # täglich 03:45: alle User
```

Weitere Dateien:

- `contao/config/config.php`: Eintrag in `$GLOBALS['TL_PURGE']` (Callback `resetAll`)
- `config/services.yaml`: Service `public: true` (für den TL_PURGE-Callback)
- `contao/languages/en/tl_maintenance.php`: Bezeichnung in der Systemwartung

## Tests (`tests/Feature/BackendUserPermissionReset/`)

- `BackendUserPermissionResetTest`: Rechte aus Home-Verzeichnis und Gruppen, nur existierende Felder, nur aktive Gruppen, unbekannter User, `resetAll()`, `isResettable()`
- `EventListener/ResetPermissionsOnLoginListenerTest`: nur bei aktivierter Option, nur betroffene Backend-User, Frontend-User werden ignoriert
