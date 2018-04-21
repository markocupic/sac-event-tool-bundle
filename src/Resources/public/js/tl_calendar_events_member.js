/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

window.addEvent('domready', function () {

    var globalTimeout = null;
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
                        if (json['status'] === 'success' && json['sacMemberId'] === sacMemberId) {

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
                                var fields = ['gender', 'firstname', 'lastname', 'street', 'postal', 'city', 'phone', 'email', 'dateOfBirth', 'vegetarian', 'emergencyPhone', 'emergencyPhoneName'];
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