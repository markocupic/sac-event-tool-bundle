# Feature: Backend User Home Directory

Bundle: `markocupic/sac-event-tool-bundle`
Ort: `src/Feature/BackendUserHomeDirectory/` (Namespace `Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory`)

## Ziel

Jeder Backend-User erhält ein persönliches Verzeichnis in der Dateiverwaltung, auf das er über seinen Filemount zugreifen kann.

## Regeln

### Home-Verzeichnis anlegen (`BackendUserHomeDirectory::create()`)

- Verzeichnis `<home_dir>/<User-ID>` mit den Unterordnern `avatar`, `documents` und `images`. Die Ordner werden über `Contao\Folder` angelegt und damit auch in der Dateiverwaltung (DBAFS) eingetragen.
- Gibt es noch kein `avatar/default.jpg`, wird der Standard-Avatar aus `<home_dir>/new/avatar/default.jpg` kopiert (falls vorhanden).
- Das Verzeichnis wird als Filemount eingetragen und die Rechtevererbung auf `extend` gesetzt. Gespeichert wird der User nur, wenn sich daran etwas ändert.
- Ist das Verzeichnis nicht in der Dateiverwaltung (z. B. weil `home_dir` ausserhalb von `files/` liegt), gibt es keinen Filemount, nur eine Warnung im Contao-Log.

### Verzeichnisse gelöschter User archivieren (`archiveOrphanedDirectories()`)

- Verzeichnisse, deren Name eine User-ID ist, zu der es keinen User mehr gibt, werden in `old__<User-ID>` umbenannt (nicht gelöscht). Der Eintrag in der Dateiverwaltung wird mitgeführt.
- `new`, bereits archivierte `old__…`-Verzeichnisse und alle anderen Namen werden ignoriert.
- Jede Archivierung landet im Contao-Log (`Config\Log::CREATE_USER_HOME_DIRECTORY`).

## Auslöser

| Auslöser | Was passiert |
|---|---|
| Login eines Backend-Users (`EventListener\CreateHomeDirectoryOnLoginListener`, Priorität 10) | Nur für den eingeloggten User. Läuft vor dem [Rechte-Reset](backend-user-permission-reset.md), damit dieser den Filemount setzen kann. |
| Neuer Backend-User (`DataContainer\User::setDefaultsOnCreatingNew()`, `config.oncreate`) | Home-Verzeichnis für den neuen User |
| Cron `Cron\BackendUserHomeDirectoryCron` (`#[AsCronJob('15 3 * * *')]`, täglich 03:15) | Home-Verzeichnisse aller User anlegen (`createForAllUsers()`), danach Verzeichnisse gelöschter User archivieren |

## Konfiguration

```yaml
# config/config.yaml
sacevt:
    user:
        backend:
            home_dir: 'files/sektion/be_user_home_directories' # Standard
```

Damit neue User einen Avatar erhalten, muss `<home_dir>/new/avatar/default.jpg` existieren.

## Klassen

```
src/Feature/BackendUserHomeDirectory/
├── BackendUserHomeDirectory.php                        # Service: create(), createForAllUsers(), findOrphanedDirectories(), archiveOrphanedDirectories()
├── EventListener/CreateHomeDirectoryOnLoginListener.php # Login: nur der eingeloggte User
└── Cron/BackendUserHomeDirectoryCron.php               # täglich 03:15: alle User + Archivierung
```

## Tests (`tests/Feature/BackendUserHomeDirectory/`)

- `BackendUserHomeDirectoryTest`: Verzeichnisse gelöschter User finden (nur numerische Ordner, `new`/`old__…`/Dateien ignoriert), fehlendes `home_dir`
- `EventListener/CreateHomeDirectoryOnLoginListenerTest`: nur der eingeloggte User, Frontend-User werden ignoriert

Das Anlegen und Umbenennen der Ordner (`Contao\Folder`) braucht eine Contao-Installation und ist nicht getestet.
