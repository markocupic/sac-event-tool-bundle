# SAC Pilatus Event Tool

Das SAC Pilatus Event Tool ist ein Bundle für das Content-Management-System [Contao](https://contao.org). Es baut auf der Kalender-Erweiterung von Contao auf und erweitert sie um alles, was eine SAC-Sektion für ihr Touren- und Kursprogramm braucht.

Das Event-Tool ist die Plattform, mit der die [SAC Sektion Pilatus](https://www.sac-pilatus.ch) ihr gesamtes Touren-, Kurs- und Veranstaltungsprogramm organisiert: vom ersten Entwurf eines Leiters über die Prüfung und Veröffentlichung bis zur Anmeldung der Mitglieder, der Durchführung und dem Abschluss mit Tourenbericht und Vergütung.

## Was das Event-Tool kann

### Touren, Kurse und Veranstaltungen ausschreiben

- Leitende erfassen ihre Events selbst: Touren, Last Minute Touren, Kurse und allgemeine Veranstaltungen (z. B. Trainings, Vorträge).
- Zu jedem Event gehören Daten (auch mehrtägig oder über mehrere Wochenenden), Leitende, organisierende Gruppen, Schwierigkeit, Tourentyp, Anforderungen, Ausrüstung, Anreise (z. B. mit öffentlichem Verkehr), Kosten, Treffpunkt, Karte und Höhenprofil.
- Kurse werden nach Kursart und Kursstufe eingeteilt, Touren nach den SAC-Schwierigkeitsgraden.
- Events lassen sich kopieren und ganze Programme um ein Jahr verschieben, damit die Planung für das nächste Jahr schnell geht.

### Freigabe und Veröffentlichung

- Jeder Event durchläuft mehrere Freigabestufen, z. B. «Entwurf», «zur Prüfung eingereicht», «geprüft» und «veröffentlicht».
- Pro Stufe ist festgelegt, wer den Event bearbeiten, löschen, hoch- oder herabstufen darf und ob die Online-Anmeldung offen ist.
- Auf Wunsch werden die Events eines Kalenders an einem Stichtag automatisch veröffentlicht.
- Die zuständigen Personen werden per E-Mail informiert, wenn sich die Freigabestufe ändert.

Mehr dazu: [Freigabestufen](docs/event-release-levels.md).

### Programm auf der Website

- Eventlisten mit Filter (z. B. nach Jahr, Tour- oder Kurstyp, organisierender Gruppe, «für Einsteiger geeignet», «Anreise mit ÖV», Merkliste oder Suchbegriff) und Detailseiten zu jedem Event.
- Porträts der Leitenden mit ihren nächsten Events.
- Karte des Tourengebiets, Erklärung der Schwierigkeitsgrade, Merkliste für Lieblings-Events.
- Tourenliste zum Ausdrucken, Kursprogramm als PDF sowie Exporte für das Jahresprogramm und die Vereinszeitschrift.

### Online-Anmeldung

- Mitglieder melden sich mit ihrem SAC-Login direkt auf der Website an; ihre Personendaten sind bereits ausgefüllt.
- Anmeldefristen, minimale und maximale Teilnehmerzahl, Warteliste und Abmeldefrist werden pro Event festgelegt.
- Ausgebuchte Events werden als solche angezeigt, und Mitglieder können sich innerhalb der Frist selbst wieder abmelden.

### Teilnehmerverwaltung für Leitende

- Leitende sehen alle Anmeldungen ihres Events, nehmen sie an, lehnen sie ab oder setzen sie auf die Warteliste. Die Teilnehmenden werden dabei automatisch per E-Mail benachrichtigt.
- Anmeldungen können auch manuell erfasst werden, z. B. für telefonische Anmeldungen.
- Teilnehmerliste als Word- oder Excel-Datei, E-Mail an alle Teilnehmenden direkt aus der Liste.
- Leitende sehen, an welchen Touren und Kursen ein angemeldetes Mitglied in den letzten Jahren teilgenommen hat. Das hilft bei der Einschätzung, ob eine Tour passt.
- Leitende werden erinnert, wenn Anmeldungen noch nicht bearbeitet sind.

### Nach dem Event

- Leitende bestätigen, wer teilgenommen hat, und füllen den Tourenbericht aus (Durchführung, Verhältnisse, Ausweichtour, besondere Vorkommnisse).
- Das Vergütungsformular für die Leitenden wird direkt aus dem Tourenbericht erstellt und eingereicht.
- Fehlen Tourenbericht oder Teilnahmebestätigung, erinnert das Tool die Leitenden automatisch.
- Teilnehmende können den Event online auswerten. Die Leitenden sehen die Rückmeldungen anonym zusammengefasst.
- Kursteilnehmende erhalten eine Kursbestätigung.

### Mitgliederbereich

- Mitglieder sehen ihre Anmeldungen und ihre absolvierten Touren und Kurse und können Teilnahmebestätigungen herunterladen.
- Sie pflegen ihr Profil und ihr Profilbild, melden sich von Events ab und können ihr Profil löschen.

### Verwaltung

- Die Mitgliederdaten werden täglich mit der Mitgliederdatenbank des SAC Zentralverbands abgeglichen und in die Benutzerkonten und Anmeldungen übernommen.
- Vereinsfunktionen, Sektionen und Ortsgruppen werden zentral verwaltet. Die Rechte der Backend-Benutzer laufen über Benutzergruppen.
- Statistiken zu Events, Leitenden, Anmeldungen und Teilnehmenden der letzten Jahre.
- Erinnerungen an Leitende und Teilnehmende vor dem Event.

**Projektstart**: 2017

**Erste Version**: 2018

## Projektkernteam

- Marko Cupic
- Christoph Marbach
- Dan Straub

## Weiterentwicklung

Jonas Müller und Marko Cupic sind an der Weiterentwicklung des Event-Tools beteiligt.

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

Die Konfiguration des Bundles (Sektionsname, Zugang zur Mitgliederdatenbank, E-Mail-Versand, Basis-URL) und die Erfassung der Sektionen und Ortsgruppen sind in [Einrichtung und Konfiguration](docs/configuration.md) beschrieben.
