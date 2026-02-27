# SAC Pilatus Event Tool

Dieses Bundle enthält alle Contao-Erweiterungen für das aktuelle SAC Pilatus Event Tool.

**Projektstart**: 2017

**Projektende**: 2018

## Projektkernteam:
Marko Cupic

Christoph Marbach

Dan Straub

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
