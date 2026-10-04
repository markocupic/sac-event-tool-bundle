# SAC Pilatus Event Tool

Dieses Bundle enthält alle Contao-Erweiterungen für das aktuelle SAC Pilatus Event Tool.

**Projektstart**: 2017

**Projektende**: 2018

## Projektkernteam:
Marko Cupic

Christoph Marbach

Dan Straub

## Features

Ausführliche Beschreibungen (Regeln, Datenmodell, Klassen, Tests) liegen unter `docs/features/`:

- [Event-Erinnerung vor Event-Start](docs/features/event-reminder.md): erinnert Leiter und Teilnehmer x Tage vor dem Event-Start mit einer E-Mail pro Event (An: Kontaktperson, CC: Leiter, BCC: Teilnehmer).
- [Leiter-Erinnerung an offene Aufgaben nach dem Event](docs/features/instructor-post-event-task-reminder.md): erinnert Leiter und Anmelde-Koordinatoren per Benachrichtigung an fehlende Tourenberichte und Teilnahmebestätigungen.
- [Events automatisch veröffentlichen](docs/features/auto-publish-events.md): setzt an einem Stichtag pro Kalender die Events von der zweithöchsten auf die höchste Freigabestufe und veröffentlicht sie.
- [Mitglieder-Sync mit dem Zentralverband](docs/features/member-database-sync.md): übernimmt täglich die Mitgliederdaten aus der Mitgliederdatenbank des SAC Zentralverbands in `tl_member`.
- [Mitgliederdaten in die Backend-User übernehmen](docs/features/member-to-user-sync.md): überträgt täglich die Personendaten aus `tl_member` in die Backend-User (`tl_user`) mit SAC-Mitgliedernummer.
- [Mitgliederdaten in die Event-Anmeldungen übernehmen](docs/features/event-registration-database-sync.md): überträgt täglich die aktuellen Personendaten aus `tl_member` in die Anmeldungen (`tl_calendar_events_member`).
- [Kursprogramm als PDF](docs/features/workshop-booklet.md): erzeugt das Kursprogramm eines Jahres oder einen einzelnen Kurs als PDF zum Herunterladen (nur für eingeloggte Mitglieder).
- [Home-Verzeichnis für Backend-User](docs/features/backend-user-home-directory.md): legt für jeden Backend-User ein persönliches Verzeichnis mit Filemount an und archiviert die Verzeichnisse gelöschter User.
- [Rechte-Reset für Backend-User](docs/features/backend-user-permission-reset.md): setzt die persönlichen Rechte von Backend-Usern mit Gruppenrechten zurück, damit sie ihre Rechte nur über Gruppen erhalten.
- [Mitgliederprofil löschen](docs/features/member-profile-deletion.md): löscht ein Mitglied samt Avatar-Ordner und anonymisiert seine Anmeldungen zu vergangenen Events; verweigert die Löschung, solange das Mitglied auf einer Buchungsliste steht.
- [Bereinigung der Event-Anmeldungen](docs/features/event-registration-cleanup.md): löscht täglich die Anmeldungen zu gelöschten Events und anonymisiert die Anmeldungen gelöschter Mitglieder.

## Einrichtung und Konfiguration

### Symfony Friendly Configuration
```yaml
# config/config.yml
# see src/DependencyInjection/Configuration.php for more options
sacevt:
  locale: 'de'
  section_name: 'SAC Sektion Pilatus'
  member_sync_credentials:
    hostname: ftpserver.sac-cas.ch
    username: ****
    password: ******
  mailer_transports:
    system_admin:
      transport_name: 'system_admin'
      sender_name: 'Web-Administrator SAC Sektion Pilatus'
      sender_email: 'internet@sac-pilatus.ch'
    event_admin:
      transport_name: 'touren_und_kursadministration'
      sender_name: 'Touren- und Kursadministration SAC Sektion Pilatus'
      sender_email: 'touren-und-kurs-administration@sac-pilatus.ch'
```

### SAC Sektionen und OG
Im Contao Backend die Sektionen und OGs eintragen, für die die Webseite erstellt wird.
Die 4-stellige Sektions ID bekommt man in Bern.

```
4250 -> SAC PILATUS,
4251 -> SAC PILATUS SURENTAL,
etc.
```
