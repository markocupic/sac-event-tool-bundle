"use strict";

/**
 * Selected checkboxes can be saved in the users session,
 * when using the Contao backend in the "editAll" or "overrideAll" mode.
 */
document.addEventListener('DOMContentLoaded', () => {
	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('do') && urlParams.has('act')) {
		if (urlParams.get('act') === 'overrideAll' || urlParams.get('act') === 'editAll') {
			new EditAllNavbarHelper();
		}
	}
});

class EditAllNavbarHelper {

	#itemsChecked = {};

	constructor() {
		this.#initialize();
	}

	/**
	 * initialize
	 */
	#initialize() {
		const self = this;

		const formData = new FormData();
		formData.append('action', 'editAllNavbarHandler');
		formData.append('subaction', 'loadNavbar');
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
			if (json['status'] === 'success') {
				// Append the button markup to the body
				document.querySelector('body').insertAdjacentHTML('afterend', json['navbar']);

				// Add event listener to the get button
				document.querySelector('#editAllNavbarHelperGetSettings').addEventListener('click', () => {
					self.#getSessionData();
				});

				// Add event listener to the set button
				document.querySelector('#editAllNavbarHelperSaveSettings').addEventListener('click', () => {
					self.#saveSessionData();
				});
			}
		}).catch(error => {
			console.error(error.message);
		})
	}

	/**
	 * Retrieve data from session
	 */
	#getSessionData() {
		const self = this;

		const formData = new FormData();
		formData.append('action', 'editAllNavbarHandler');
		formData.append('subaction', 'getSessionData');
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
			if (json['status'] === 'success') {
				self.#itemsChecked = JSON.parse(json['itemsChecked']);

				// First unselect all checkboxes
				const nodeList = document.querySelectorAll('#check_all, .tl_checkbox_container input[name="all_fields[]"]');
				for (const cboxItem of nodeList) {
					cboxItem.checked = false;
				}

				// Select checkboxes from session
				for (const cboxId of self.#itemsChecked) {
					if (document.getElementById(cboxId)) {
						document.getElementById(cboxId).checked = true;
					}
				}
			}
		}).catch(error => {
			console.error(error.message);
		})
	}

	/**
	 * Write checked checkbox to the session
	 */
	#saveSessionData() {
		const self = this;

		const nodeList = document.querySelectorAll('.tl_checkbox_container input[name="all_fields[]"]');
		let checkedItems = [];
		for (const cbox of nodeList) {
			if (cbox.checked) {
				checkedItems.push(cbox.id);
			}
		}

		const formData = new FormData();
		formData.append('action', 'editAllNavbarHandler');
		formData.append('subaction', 'saveSessionData');
		formData.append('checkedItems', JSON.stringify(checkedItems));
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
			if (json['status'] === 'success') {
				self.#itemsChecked = JSON.parse(json['itemsChecked']);
			}
		}).catch(error => {
			console.error(error.message);
		})
	}
}

