# Upgrade

## Contao 6 Support

Mit dieser Version läuft das Event-Tool unter Contao 5.3 und ist für Contao 6 vorbereitet. Getestet wird mit Contao 5.3
(PHP 8.4 und 8.5) und Contao 5.7 (PHP 8.4).

### Voraussetzungen

| Paket                                       | Version                  |
|---------------------------------------------|--------------------------|
| PHP                                         | ^8.4                     |
| `contao/core-bundle`, `calendar-bundle`, `newsletter-bundle` | ^5.3 \|\| ^6.0 |
| Symfony-Komponenten                         | ^6.4 \|\| ^7.0 \|\| ^8.0 |

> **Achtung:** Unter Contao 6 lässt sich das Event-Tool noch nicht installieren. Diese Abhängigkeiten unterstützen
> Contao 6 bisher nicht:
>
> - `codefog/contao-haste`
> - `menatwork/contao-multicolumnwizard-bundle`
> - `terminal42/notification_center`
> - `terminal42/contao-mp_forms`
>
> Sobald dafür Contao-6-Versionen veröffentlicht sind, kann das Event-Tool ohne weitere Anpassungen am eigenen Code
> unter Contao 6 installiert werden.

### Eigene Templates: `.html5` wurde zu Twig

Contao 6 kennt keine `.html5`-Templates mehr. Die folgenden Templates des Event-Tools wurden deshalb nach Twig
umgeschrieben. Wer eines davon im Projekt (`templates/`) oder im Theme überschreibt, muss die eigene Kopie ebenfalls
nach Twig portieren. Die `.html5`-Versionen werden nicht mehr ausgeliefert.

| Alt                                                  | Neu                                                       |
|------------------------------------------------------|-----------------------------------------------------------|
| `form_bs_switch.html5`                               | `form_bs_switch.html.twig`                                |
| `be_edit_all_navbar_helper.html5`                    | `be_edit_all_navbar_helper.html.twig`                     |
| `mod_jahresprogramm_export.html5`                    | `mod_jahresprogramm_export.html.twig`                     |
| `mod_jahresprogramm_export_surental.html5`           | `mod_jahresprogramm_export_surental.html.twig`            |
| `mod_pilatus_export.html5`                           | `mod_pilatus_export.html.twig`                            |
| `mod_pilatus_export_events_table_partial.html5`      | `mod_pilatus_export_events_table_partial.html.twig`       |
| `tcpdf_template_sac_kurse.html5`                     | `tcpdf_template_sac_kurse.html.twig`                      |

Hinweise zu einzelnen Templates:

- **`tcpdf_template_sac_kurse`** (Kursbroschüre): Die Textfelder (Teaser, Kursziele, Kursinhalte, Voraussetzungen,
  Kursort, Leistungen, Anmeldung, Material, Treffpunkt, Weiteres) werden nicht mehr in PHP mit `nl2br()` aufbereitet,
  sondern als reiner Text übergeben. Zeilenumbrüche werden im Template mit `|nl2br` umgewandelt.
- **`mod_jahresprogramm_export`**: Die Abschnitte sind in Blöcke unterteilt (`courses`, `tours`,
  `special_users_headline`, `instructors_headline`). Varianten wie `mod_jahresprogramm_export_surental` erweitern
  das Basis-Template und überschreiben nur diese Blöcke.
- **`mod_pilatus_export`**: Der Abschnitt mit den Monatsprogrammen (Variable `events`) wurde entfernt, weil die
  Variable nie befüllt wurde.
- In allen Templates werden Textwerte jetzt escaped. Wer bewusst HTML ausgeben will, muss `|raw` verwenden (nur bei
  vertrauenswürdigen Inhalten).

### Messenger

Contao 6 hat das `Contao\CoreBundle\Messenger\Message\LowPriorityMessageInterface` entfernt. Die Nachrichten des
Event-Tools implementieren nun das eigene Interface
`Markocupic\SacEventToolBundle\Messenger\Message\LowPriorityMessageInterface`. Das Event-Tool leitet es selbst an den
Transport `contao_prio_low` weiter, eine Anpassung der Konfiguration ist nicht nötig.

Wer eigene Nachrichten mit niedriger Priorität hat, kann dieses Interface ebenfalls verwenden. Der Transport
`contao_prio_low` muss vorhanden sein (in der Contao Managed Edition ist er Standard).

### Geänderte Klassen (nur relevant, wenn Code des Event-Tools erweitert wird)

- **`Feature\AutoPublishEvents\EventPublisher`**: Der Konstruktor erwartet statt `EntityCacheTags` jetzt
  `Markocupic\SacEventToolBundle\Cache\CacheTagInvalidator`. Dieser verwendet je nach Contao-Version
  `contao.cache.entity_tags` (5.3) oder `contao.cache.tag_manager` (6).
- **Voter**: `voteOnAttribute()` bzw. `vote()` haben das zusätzliche optionale Argument
  `Symfony\Component\Security\Core\Authorization\Voter\Vote|null $vote = null` (Symfony 7.3+/8). Wer einen Voter des
  Event-Tools erweitert, muss die Signatur anpassen.
- **Routen**: Die Controller verwenden `Symfony\Component\Routing\Attribute\Route` statt der Annotation.

### Backend

- **`Backend.getScrollOffset()`** gibt es in Contao 6 nicht mehr. Die Operationen des Event-Tools verwenden
  `data-action="contao--scroll-offset#store"`. Eigene DCA-Anpassungen, die `Backend.getScrollOffset()` aufrufen,
  sollten ebenfalls umgestellt werden.
- **Turbo**: Das Backend von Contao 6 lädt Seiten mit Turbo Drive, `DOMContentLoaded` wird dabei nur einmal ausgelöst.
  Die Backend-Skripte des Event-Tools initialisieren sich deshalb zusätzlich bei `turbo:load`. Eigene Backend-Skripte
  sollten das ebenso tun.
- Das Feld **`guests`** wurde aus den Paletten der Inhaltselemente und Frontend-Module entfernt (in Contao 6 gibt es
  es nicht mehr).

### Vor dem Wechsel auf Contao 6

- Contao 6 speichert Eingaben nicht mehr HTML-encodiert und castet Model-Werte auf ihren Typ. Die Optionen
  `decodeEntities` und `useRawRequestData` werden ignoriert. Nach dem Wechsel die E-Mail-Benachrichtigungen, die
  Exporte (Jahresprogramm, Pilatus-Export, CSV) und die PDF-Dokumente kontrollieren.
- Das Dateisystem und die Datenbank synchronisieren (`vendor/bin/contao-console contao:filesync`), da Contao 6 einen
  anderen Hash-Algorithmus für die Dateiverwaltung verwendet.
- Die allgemeinen Upgrade-Hinweise von Contao beachten:
  [UPGRADE.md von Contao](https://github.com/contao/contao/blob/6.0/UPGRADE.md).

### Entwicklung

- PHPUnit wird nicht mehr über `tools/phpunit` installiert, sondern ist Teil von `require-dev`. Die Tests laufen mit
  `composer unit-tests` (PHPUnit 9 unter Contao 5.3, PHPUnit 12 ab Contao 5.7).
- Data Provider tragen sowohl die Annotation `@dataProvider` (PHPUnit 9) als auch das Attribut `#[DataProvider]`
  (PHPUnit 12).
- `tools/ecs` benötigt nur noch `markocupic/easy-coding-standard`.
