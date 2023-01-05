/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

"use strict";

window.addEvent('domready', function () {

  var globalTimeout = null;
  if (!$$('input[name="sacMemberId"]')[0]) {
    return;
  }

  $$('input[name="sacMemberId"]')[0].addEvent('keyup', function (event) {
    if (globalTimeout != null) {
      clearTimeout(globalTimeout);
    }
    globalTimeout = setTimeout(function () {
      globalTimeout = null;
      // Get value from input field
      let sacMemberId = $$('input[name="sacMemberId"]')[0].get('value');
      if (sacMemberId.length > 5) {
        new Request.JSON({
          url: window.location.href,
          onSuccess: function (json, txt) {
            if (json['status'] === 'success' && json['sacMemberId'] == sacMemberId) {

              if ($('acceptAutocompleteBox') !== null) {
                $('acceptAutocompleteBox').destroy();
              }

              if ($('ctrl_sacMemberId') !== null) {
                $('ctrl_sacMemberId').hide();
              }

              // Inject html
              var acceptAutocomplete = new Element('div', {id: 'acceptAutocompleteBox'});
              acceptAutocomplete.inject($('ctrl_sacMemberId'), 'before');
              acceptAutocomplete.appendHTML(' <p class="autocompleteInfo">In der Datenbank wurde zur Mitgliednummer <strong>' + sacMemberId + '</strong> folgendes Mitglied gefunden:</p>');
              acceptAutocomplete.appendHTML(' <p class="autocompleteInfo"><strong>' + json['firstname'] + ' ' + json['lastname'] + ', ' + json['street'] + ', ' + json['postal'] + ' ' + json['city'] + '</strong></p>');
              acceptAutocomplete.appendHTML(' <button id="btnAcceptAutocomplete" class="tl_submit autocomleteBtn">Übernehmen</button>&nbsp;&nbsp;&nbsp;&nbsp;<button id="btnRefuseAutocomplete" class="tl_submit autocomleteBtn">Nein</button>');

              // Autofill form inputs
              $('btnAcceptAutocomplete').addEvent('click', function (event) {
                var fields = ['gender', 'name', 'username', 'firstname', 'lastname', 'street', 'postal', 'city', 'mobile', 'phone', 'email', 'dateOfBirth', 'foodHabits', 'emergencyPhone', 'emergencyPhoneName', 'sectionId'];
                fields.each(function (field) {
                  if ($('ctrl_' + field)) {
                    // Special handling for arrays
                    if (field === 'sectionId') {
                      let arrSections = json[field];
                      let options = document.querySelectorAll('select#ctrl_' + field + ' option');
                      let i;
                      if (options) {
                        // First reset select field
                        for (i = 0; i < options.length; i++) {
                          options[i].selected = false;
                        }
                      }

                      // Then add new entries
                      if (arrSections.length) {
                        let i;
                        for (i = 0; i < arrSections.length; i++) {
                          let sectionId = arrSections[i];
                          let option = document.querySelector('select#ctrl_' + field + ' option[value="' + sectionId + '"]')
                          if (option) {
                            option.selected = true;
                          }
                        }
                        // Update chosen
                        if (document.querySelector('#ctrl_' + field + '_chzn')) {
                          // Mootools
                          $('ctrl_' + field).fireEvent("liszt:updated");
                        }
                      }
                    } else if (json[field] !== null) {
                      $('ctrl_' + field).set('value', json[field]);
                    }
                  }
                });
                acceptAutocomplete.destroy();
                $('ctrl_sacMemberId').show();
              });
              $('btnRefuseAutocomplete').addEvent('click', function (event) {
                acceptAutocomplete.destroy();
                $('ctrl_sacMemberId').show();
              });
            }
          }
        }).post({
          'action': 'autocompleterLoadMemberDataFromSacMemberId',
          'sacMemberId': sacMemberId,
          'REQUEST_TOKEN': Contao.request_token
        });
      }
    }, 400);
  });
});
