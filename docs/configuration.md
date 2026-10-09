# Einrichtung und Konfiguration

## Symfony Friendly Configuration
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

## SAC Sektionen und OG
Im Contao Backend die Sektionen und OGs eintragen, für die die Webseite erstellt wird.
Die 4-stellige Sektions ID bekommt man in Bern.

```
4250 -> SAC PILATUS,
4251 -> SAC PILATUS SURENTAL,
etc.
```
