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
- [Leiter-Erinnerung an offene Aufgaben nach dem Event](docs/features/event-completion-reminder.md): erinnert Leiter und Anmelde-Koordinatoren per Benachrichtigung an fehlende Tourenberichte und Teilnahmebestätigungen.
- [Reminder für unbearbeitete Event-Anmeldungen](docs/features/event-registration-reminder.md): erinnert Anmelde-Koordinator bzw. Hauptleiter an Anmeldungen, die noch nicht angenommen, abgelehnt oder auf die Warteliste gesetzt wurden.
- [Teilnahme-Historie](docs/features/participant-event-history.md): Leitende sehen in der Teilnehmerliste, an welchen Events ein angemeldetes Mitglied in den letzten 5 Jahren teilgenommen hat; jeder Zugriff wird protokolliert.
- [Event Feedback](docs/features/event-feedback.md): Teilnehmende werten einen Event nach der Teilnahme online aus; Leiter sehen die Auswertungen anonym zusammengefasst im Backend.
- [Event-Statistik](docs/features/event-stats.md): Backend-Seite mit Kennzahlen zu ausgeschriebenen Events, Leitenden, Anmeldungen und Teilnehmenden für das aktuelle und die zwei vorangehenden Jahre.
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

framework:
  router:
    # Pflicht für Cron-Jobs und Message Handler (kein Request auf der Kommandozeile):
    # - Basis-URL für absolute Links in E-Mails
    # - ohne diesen Eintrag scheitert der Versand von HTML-Mails im Notification Center ("Unable to parse URI")
    # Mit "www", da sac-pilatus.ch auf www.sac-pilatus.ch umleitet.
    default_uri: 'https://www.sac-pilatus.ch'
```

```yaml
#config/parameters.yaml

parameters:
    # Used for command line requests see: https://docs.contao.org/dev/framework/cron/
    # or
    # Symfony messenger
    # We redirect https://sac-pilatus.ch to https://www.sac-pilatus.ch (Cyon domain settings)
    # Because of this be sure to add the www in front of the hostname (router.request_context.host: 'www.sac-pilatus.ch')
    # otherwise the router would generate an absolute url without the www
    # and UrlSigner::checkRequest() would fail.
    router.request_context.host: 'www.sac-pilatus.ch'
    router.request_context.scheme: 'https'
```

### SAC Sektionen und OG
Im Contao Backend die Sektionen und OGs eintragen, für die die Webseite erstellt wird.
Die 4-stellige Sektions ID bekommt man in Bern.

```
4250 -> SAC PILATUS,
4251 -> SAC PILATUS SURENTAL,
etc.
```
