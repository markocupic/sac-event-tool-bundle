# Freigabestufen (Event Release System)

Bundle: `markocupic/sac-event-tool-bundle`

## Ziel

Ein Event (Tour, Kurs, Veranstaltung, Last Minute Tour) wird nicht in einem Schritt veröffentlicht, sondern durchläuft mehrere **Freigabestufen** (FS). Auf jeder Stufe ist festgelegt, wer den Event bearbeiten, löschen, verschieben, hoch- oder herabstufen darf, wer die Anmeldungen verwaltet und ob sich Mitglieder bereits online anmelden können. So bleibt nachvollziehbar, wer wann was an einem Event ändern darf, von der ersten Erfassung durch die Leitenden bis zur Veröffentlichung auf der Website.

Typischer Ablauf mit vier Stufen:

| Stufe | Bedeutung (Beispiel) |
|---|---|
| FS 1 | Der Leiter erfasst den Event und kann ihn frei bearbeiten. |
| FS 2 | Der Event ist zur Prüfung eingereicht, z. B. bei der Touren- oder Kurschefin. |
| FS 3 | Der Event ist geprüft und für das Programm vorgesehen. |
| FS 4 | Der Event ist veröffentlicht und auf der Website sichtbar. |

Anzahl, Namen und Bedeutung der Stufen sind frei konfigurierbar.

## Freigabestufen-Systeme

- Die Stufen werden im Backend-Modul **«Event-Freigabestufen-Tool»** verwaltet. Ein **Freigabestufen-System** fasst mehrere Stufen zusammen.
- Jeder **Event-Art** (Modul «SAC Event-Art-Tool») wird ein Freigabestufen-System zugeordnet. Touren und Kurse können also unterschiedlich viele Stufen und unterschiedliche Rechte haben.
- Ein Event kann nur auf Stufen gesetzt werden, die zum System seiner Event-Art gehören. Das gilt auch für Admins.
- Die Stufen eines Systems beginnen bei 1 und sind lückenlos nummeriert (1, 2, 3, …). Jede Stufe und jeder Titel kommt pro System nur einmal vor. Gelöscht werden kann nur die jeweils höchste Stufe.
- Events ohne Freigabestufe (Event-Art ohne Freigabestufen-System) unterliegen keinen Einschränkungen.

## Einstellungen pro Freigabestufe

| Einstellung | Bedeutung |
|---|---|
| Stufe | Nummer der Stufe (1, 2, 3, …) |
| Titel, Beschreibung | Name und Erklärung der Stufe, z. B. «FS 2 – zur Prüfung eingereicht» |
| Rechte-Regeln | Wer darf auf dieser Stufe was (siehe unten) |
| Online-Anmeldung im Frontend | Ob sich Mitglieder auf dieser Stufe über das Anmeldeformular auf der Website anmelden können |

### Rechte-Regeln

Eine Stufe hat beliebig viele Rechte-Regeln. Eine Regel gewährt die ausgewählten **Rechte** den ausgewählten **Parteien** des Events oder den Mitgliedern der ausgewählten **Benutzergruppen**. Es genügt, wenn eine Regel passt.

Parteien (bezogen auf den einzelnen Event):

| Partei | Wer |
|---|---|
| Event-Autor | Wer den Event erstellt hat |
| Nur Hauptleiter | Der Hauptleiter des Events |
| Alle Event-Leiter | Hauptleiter und alle weiteren Leitenden des Events |
| Anmeldungs-Koordinator | Die Person, die beim Event für die Anmeldungen zuständig ist |

Rechte:

| Recht | Bedeutung |
|---|---|
| Event bearbeiten | Den Event im Backend bearbeiten |
| Event löschen | Den Event löschen (nur möglich, solange keine Anmeldungen vorhanden sind) |
| Event verschieben | Den Event in einen anderen Kalender verschieben |
| Event-Anmeldungen administrieren | Anmeldungen annehmen, ablehnen, auf die Warteliste setzen, manuell erfassen, die Teilnahme bestätigen |
| Freigabestufe hochstufen | Den Event auf die nächste Stufe setzen |
| Freigabestufe herabstufen | Den Event auf die vorherige Stufe zurücksetzen |
| Teilnahme-Historie der Angemeldeten ansehen | Siehe [Teilnahme-Historie](features/participant-event-history.md) |

Beispiel für FS 1: «Alle Event-Leiter» und «Event-Autor» dürfen den Event bearbeiten, löschen und hochstufen; die Gruppe «Tourenchefs» darf zusätzlich herabstufen.

**Admins** haben immer alle Rechte, unabhängig von den Regeln.

### Was alle sehen

Die Teilnehmerliste eines Events können alle Backend-Benutzer mit Zugang zum Kalender ansehen. Ändern können sie nur, wer das Recht «Event-Anmeldungen administrieren» hat. Die Anmeldungen verwalten lässt sich erst, wenn der Anmeldezeitraum des Events begonnen hat (falls einer festgelegt ist).

## Event hoch- und herabstufen

- In der Eventliste stehen dafür zwei Pfeile zur Verfügung. Die Stufe lässt sich auch im Bearbeitungsformular des Events wählen. Dort werden nur die Stufen des passenden Freigabestufen-Systems angeboten.
- Für jeden Schritt braucht es das entsprechende Recht auf der Stufe, auf der der Event gerade steht. Wer im Formular mehrere Stufen auf einmal überspringt, braucht das Recht auf jeder Stufe dazwischen.
- Fehlt das Recht, ist der Pfeil ausgegraut. Beim Darüberfahren erklärt ein Hinweis den Grund.
- **Veröffentlichung:** Erreicht ein Event die höchste Stufe, wird er veröffentlicht. Wird er von der höchsten Stufe herabgestuft, wird er wieder unveröffentlicht.
- **Bearbeitung nach der ersten Stufe:** Sobald ein Event die erste Stufe verlassen hat, können Nicht-Admins die grundlegenden Angaben nicht mehr ändern, z. B. Titel, Event-Art, Leitende, Daten, Schwierigkeit, Teilnehmerzahl und Anmeldezeitraum. Sie werden nur noch angezeigt.

### Zeitregeln des Kalenders

Im Kalender lassen sich zusätzlich zwei Zeitregeln einstellen. Sie gelten nur für Nicht-Admins. Liegt das Startdatum ausserhalb der Zeitspanne, erhalten Admins beim Hochstufen einen Hinweis.

| Einstellung | Wirkung |
|---|---|
| Kalender-Zeitspanne festlegen | Ein Event lässt sich nur über die erste Stufe hinaus hochstufen, wenn sein Startdatum in der festgelegten Zeitspanne liegt (z. B. Jahresprogramm 2027). |
| Hochstufen auf höchste FS ab Datum ermöglichen | Auf die höchste Stufe (Veröffentlichung) darf erst ab einem bestimmten Datum hochgestuft werden. |

### Automatische Veröffentlichung

Pro Kalender kann ein Stichtag gesetzt werden, an dem alle Events von der zweithöchsten auf die höchste Stufe gesetzt und veröffentlicht werden. Siehe [Events automatisch veröffentlichen](features/auto-publish-events.md).

## Benachrichtigungen

- Bei jedem Wechsel der Freigabestufe und bei jeder Veröffentlichung erhalten die im Kalender unter «Benachrichtigen bei Freigabestufen-Änderung» eingetragenen E-Mail-Adressen eine Nachricht.
- Wird ein Event über das Bearbeitungsformular verschoben, wird erst benachrichtigt, wenn die Änderung tatsächlich gespeichert ist.
- Jeder Wechsel wird im Contao-System-Log protokolliert.

## Mehrere Events gleichzeitig bearbeiten

Beim Bearbeiten mehrerer Events auf einmal muss in der Eventliste zuerst nach einer Freigabestufe gefiltert werden. Angezeigt werden nur die Events, die der Benutzer bearbeiten darf.

## Online-Anmeldung

Mitglieder können sich nur über das Anmeldeformular auf der Website anmelden, wenn die Stufe des Events die Online-Anmeldung erlaubt (in der Regel nur die höchste Stufe). Manuelle Anmeldungen im Backend sind davon unabhängig, dafür braucht es das Recht «Event-Anmeldungen administrieren».
