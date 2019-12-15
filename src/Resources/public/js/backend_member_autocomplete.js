/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

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
            sacMemberId = $$('input[name="sacMemberId"]')[0].get('value');
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
                            acceptAutocomplete.appendHTML(' <button id="btnAcceptAutocomplete" class="tl_submit autocomleteBtn">&Uuml;bernehmen</button>&nbsp;&nbsp;&nbsp;&nbsp;<button id="btnRefuseAutocomplete" class="tl_submit autocomleteBtn">Nein</button>');

                            // Autofill form inputs
                            $('btnAcceptAutocomplete').addEvent('click', function (event) {
                                var fields = ['gender', 'name', 'username', 'firstname', 'lastname', 'street', 'postal', 'city', 'mobile', 'phone', 'email', 'dateOfBirth', 'foodHabits', 'emergencyPhone', 'emergencyPhoneName'];
                                fields.each(function (field) {
                                    if ($('ctrl_' + field)) {
                                        if (json[field] !== null) {
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
