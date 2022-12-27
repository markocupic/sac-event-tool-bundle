<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['new'] = ['Neues Vergütungsformular mit Tourenrapport', 'Neues Vergütungsformular mit Tourenrapport hinzufügen.'];

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['edit'] = ['Vergütungsformular bearbeiten', 'Vergütungsformular mit ID %s bearbeiten.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['delete'] = ['Vergütungsformular löschen', 'Vergütungsformular mit ID %s löschen.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['copy'] = ['Vergütungsformular kopieren', 'Vergütungsformular mit ID %s kopieren.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoicePdf'] = ['Vergütungsformular drucken PDF', 'PDF Vergütungsformular mit ID %s ausdrucken.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoiceDocx'] = ['Vergütungsformular drucken MsWord', 'MsWord Vergütungsformular mit ID %s ausdrucken.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportPdf'] = ['Tourenrapport drucken PDF', 'Tourenrapport als PDF mit ID %s ausdrucken.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportDocx'] = ['Tourenrapport drucken MsWord', 'Tourenrapport als MsWord mit ID %s ausdrucken.'];

// Legends
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['user_legend'] = 'Begünstigter';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['iban_legend'] = 'Bankverbindung';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['expenses_legend'] = 'Allgemeine Auslagen/Kosten für Übernachtung';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['transport_legend'] = 'Auslagen für Reise/Transport';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['phone_costs_legend'] = 'Weiteres';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['event_legend'] = 'Angaben zum Event';
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['notice_legend'] = 'Weiteres';

// Fields
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['userPid'] = ['Begünstigter', 'Wählen Sie einen Namen aus der Liste aus.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxes'] = ['Auslagen für Übernachtung in CHF', 'Geben Sie die übernachtungsauslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxesText'] = ['Auslagen für Übernachtung Beschreibung', 'Geben Sie eine Beschreibung für die Auslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxes'] = ['Sonstige Auslagen in CHF', 'Geben Sie eine Beschreibung für die Auslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxesText'] = ['Sonstige Auslagen Beschreibung', 'Geben Sie eine Beschreibung für die Auslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['privateArrival'] = ['Anzahl privat angereister Personen', 'Privat angereiste Teilnehmende und Leitende werden bei der Spesenberechnung nicht berücksichtigt.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxes'] = ['ÖV-Kosten in CHF (Basis Halbtax)', 'Geben Sie die Kosten für die Benutzung des öffentlichen Verkehrs ein (Basis Halbtax).'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxesText'] = ['ÖV-Kosten Beschreibung', 'Geben Sie eine Beschreibung für die Auslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxes'] = ['Kosten für Privat-/Bergbahnen in CHF', 'Geben Sie die Kosten für die Benutzung von Privat-/Bergbahnen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxesText'] = ['Kosten für Privat-/Bergbahnen Beschreibung', 'Geben Sie eine Beschreibung für die Auslagen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['roadTaxes'] = ['Park-/Strassen-/Tunnelgebühren pro Auto in CHF', 'Geben Sie die Park-/Strassen-/Tunnelgebühren pro Auto ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['carTaxesKm'] = ['Anzahl mit PW zurückgelegte Kilometer pro Fahrzeug', 'Geben Sie die Anzahl Kilometer ein, welche pro Fahrzeug zurückgelegt wurde. Wird für die Kilometerabrechnung benötigt.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['countCars'] = ['Anzahl PW', "Geben Sie die Anzahl PW's ein. Wird für die Kilometerabrechnung benötigt."];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['phoneTaxes'] = ['Telefon/Porti in CHF (pauschal CHF 10.00/Tag)', 'Geben Sie die Telefon-/Portokosten ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['iban'] = ['IBAN Nummer', 'Geben Sie Ihre IBAN Nummer ein. Wird für die Überweisung auf Ihr Bankkonto benötigt.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['notice'] = ['Weitere Anmerkungen', 'Geben Sie weitere Anmerkungen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['eventDuration'] = ['Event Dauer in Tagen', 'Geben Sie die Event-Dauer in Tagen an.'];
