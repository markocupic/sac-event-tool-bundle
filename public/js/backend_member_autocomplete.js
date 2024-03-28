/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

"use strict";

document.addEventListener('DOMContentLoaded', () => {

	let globalTimeout = null;
	if (!document.querySelector('input[name="sacMemberId"]')) {
		return;
	}

	document.querySelector('input[name="sacMemberId"]').addEventListener('keyup', () => {
		if (globalTimeout !== null) {
			clearTimeout(globalTimeout);
		}

		globalTimeout = setTimeout(() => {
			globalTimeout = null;
			// Get the value from input field
			let sacMemberId = document.querySelector('input[name="sacMemberId"]').value;

			if (sacMemberId.length > 5) {

				const formData = new FormData();
				formData.append('action', 'autocompleterLoadMemberDataFromSacMemberId');
				formData.append('sacMemberId', sacMemberId);
				formData.append('REQUEST_TOKEN', Contao.request_token);

				fetch(window.location.href, {
					method: 'POST',
					body: formData,
					headers: {
						'x-requested-with': 'XMLHttpRequest',
					},
				}).then(response => {
					if (response.ok) {
						return response.json();
					}
				}).then(json => {
					if (json['status'] === 'success' && parseInt(json['sacMemberId']) === parseInt(sacMemberId)) {
						// Append the button markup to the body
						if (document.getElementById('acceptAutocompleteBox')) {
							document.getElementById('acceptAutocompleteBox').remove();
						}

						if (document.getElementById('ctrl_sacMemberId')) {
							document.getElementById('ctrl_sacMemberId').style.display = 'none';
						}

						// Inject html
						let acceptAutocomplete = document.createElement('div');
						acceptAutocomplete.setAttribute('id', 'acceptAutocompleteBox');
						document.getElementById('ctrl_sacMemberId').before(acceptAutocomplete);

						const firstname = json['firstname'];
						const lastname = json['lastname'];
						const street = json['street'];
						const postal = json['postal'];
						const city = json['city'];
						console.log(json)

						let markup = [];
						markup.push(` <p class="autocompleteInfo">In der Datenbank wurde zur Mitgliednummer <strong>${sacMemberId}</strong> folgendes Mitglied gefunden:</p>`);
						markup.push(` <p class="autocompleteInfo"><strong>${firstname} ${lastname}, ${street}, ${postal} ${city}</strong><br></p>`);
						markup.push(` <button id="btnAcceptAutocomplete" class="tl_submit autocompleteBtn">Übernehmen</button>`);
						markup.push(` <button id="btnRefuseAutocomplete" class="tl_submit autocompleteBtn">Nein</button>`);
						markup = markup.join('');

						// Inject autocompleter markup
						acceptAutocomplete.insertAdjacentHTML('afterbegin', markup);

						// Autofill form inputs
						acceptAutocomplete.addEventListener('click', () => {
							const fields = ['gender', 'name', 'username', 'firstname', 'lastname', 'street', 'postal', 'city', 'mobile', 'phone', 'email', 'dateOfBirth', 'foodHabits', 'emergencyPhone', 'emergencyPhoneName', 'sectionId'];
							for (const fieldname of fields) {

								const currField = document.getElementById('ctrl_' + fieldname);
								if (currField) {
									// Special handling for arrays (select inputs)
									if (fieldname === 'sectionId') {
										const arrSections = json[fieldname];

										const options = document.querySelectorAll('select#ctrl_' + fieldname + ' option');

										// First reset select field
										for (const option of options) {
											option.selected = false;
										}

										// Then add new entries
										if (arrSections) {
											for (const [key, sectionId] of Object.entries(arrSections)) {

												const option = document.querySelector('select#ctrl_' + fieldname + ' option[value="' + sectionId + '"]')
												if (option) {
													option.selected = true;
												}
											}
										}
									} else if (json[fieldname] !== null) {
										document.getElementById('ctrl_' + fieldname).value = json[fieldname];
									}

									// Update chosen
									if (currField.classList.contains('tl_chosen')) {
										// Element.fireEvent() is the Mootools implementation for Element.dispatchEvent()
										currField.fireEvent("liszt:updated");
										// currField.dispatchEvent(new Event("liszt:updated"));
										// Vanilla Script only implementation won't work! Why? I don't know :-(
									}
								}
							}

							acceptAutocomplete.remove();
							document.getElementById('ctrl_sacMemberId').removeAttribute('style');
						});

						document.getElementById('btnRefuseAutocomplete').addEventListener('click', () => {
							acceptAutocomplete.remove();
							document.getElementById('ctrl_sacMemberId').removeAttribute('style');
						});
					}
				}).catch(error => {
					console.error(error.message);
				})
			}
		}, 400);
	});
});
