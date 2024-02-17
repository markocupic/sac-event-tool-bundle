"use strict";

if (typeof VueTourList !== 'function') {

	/*
	 * This file is part of SAC Event Tool Bundle.
	 *
	 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
	 * @license GPL-3.0-or-later
	 * For the full copyright and license information,
	 * please view the LICENSE file that was distributed with this source code.
	 * @link https://github.com/markocupic/sac-event-tool-bundle
	 */
	window.VueTourList = class {
		constructor(elId, opt) {
			// Defaults
			const defaults = {
				'modId': null,
				'apiParams': {
					'organizers': [],
					'eventType': ["tour", "generalEvent", "lastMinuteTour", "course"],
					'suitableForBeginners': '',
					'publicTransportEvent': '',
					'tourType': '',
					'courseType': '',
					'courseId': '',
					'year': '',
					'dateStart': '',
					'textSearch': '',
					'eventId': '',
					'username': '',
					// Let empty for all published
					'calendarIds': [],
					'limit': '50',
					'offset': '0',
				},
				'fields': [],
				'callbacks': {
					'oninsert': function (vue, json) {
						//
					}
				}
			};

			// Merge options and defaults
			const params = {...defaults, ...opt}

			const {createApp} = Vue

			// Instantiate vue.js application
			const app = createApp({
				data() {
					return {
						// The element CSS ID selector: e.g. #myList
						elId: elId,
						// The module id (used by the take param)
						modId: params.modId,
						// Api params
						apiParams: params.apiParams,
						// Fields array
						fields: (params.fields && Array.isArray(params.fields)) ? params.fields : null,
						// Callbacks
						callbacks: params.callbacks,
						// Result row
						rows: [],
						// Loaded events (ids)
						arrEventIds: [],
						// is busy boolean
						blnIsBusy: false,
						// total found items
						itemsTotal: 0,
						// already loaded items
						loadedItems: 0,
						// all events loades bool
						blnAllEventsLoaded: false,
					};
				},
				mounted() {
					const self = this;
					self.prepareRequest();
				},
				methods: {
					// Prepare ajax request
					prepareRequest: function prepareRequest() {
						const self = this;
						if (self.blnIsBusy === false) {
							self.blnIsBusy = true;
							self.fetchItems();
						}
					},

					getTake: function getTake() {
						const self = this;
						const urlString = window.location.href;
						const url = new URL(urlString);
						const take = url.searchParams.get('take_e' + self.modId);

						return null === take ? null : parseInt(take);
					},

					// Load items from server
					fetchItems: function fetchItems() {
						const self = this;

						// Try to retrieve data from storage
						const storageData = localStorage.getItem(btoa(window.location.href + '&modId=' + self.modId));

						if (storageData) {
							localStorage.removeItem(btoa(window.location.href + '&modId=' + self.modId));
							const storageObject = JSON.parse(storageData);

							// Return if storage data is outdated
							if (storageObject.expiry < Date.now()) {
								return;
							}

							self.rows = storageObject.rows;
							self.arrEventIds = storageObject.arrEventIds;
							self.itemsTotal = storageObject.itemsTotal;
							self.loadedItems = storageObject.loadedItems;
							self.blnAllEventsLoaded = storageObject.blnAllEventsLoaded;
							self.blnIsBusy = false;

							// Trigger on insert callback
							if (self.callbackExists('oninsert')) {
								self.callbacks.oninsert(self, null);
							}

							const url = new URL(window.location.href);
							const urlParams = new URLSearchParams(url.search);
							const href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

							// Remove the "itemId" parameter when the user returns from the detail view
							if (urlParams.has('itemId')) {
								urlParams.delete('itemId');
							}

							// Current URL: https://my-website.ch/demo_a.html?take_e234=200
							const nextURL = href + (urlParams.toString() ? '?' + urlParams.toString() : '');
							const nextTitle = document.title; // keep the same title

							// This will create a new entry in the browser's history, without reloading
							window.history.replaceState({}, nextTitle, nextURL);

							return;
						}

						if (self.blnAllEventsLoaded === true) {

							return;
						}

						const formData = new FormData();

						// Add api parameters to the Form Data object
						for (const [key, value] of Object.entries(self.apiParams)) {
							if (key === 'offset') {
								formData.append('offset', parseInt(value) + self.loadedItems);
							} else if (Array.isArray(value)) {// Handle arrays correctly
								for (let i = 0; i < value.length; ++i) {
									formData.append(key + '[]', value[i]);
								}
							} else {
								formData.append(key, value);
							}
						}

						// Set limit on page load/refresh
						if (self.loadedItems === 0 && self.getTake() > 0) {
							if (formData.has('limit')) {
								formData.set('limit', self.getTake());
							} else {
								formData.append('limit', self.getTake());
							}
						}

						// Handle fields correctly
						for (const prop of self.fields) {
							formData.append('fields[]', prop);
						}

						const urlParams = new URLSearchParams(Array.from(formData)).toString();
						const url = 'eventApi/events?' + urlParams;

						// Fetch
						fetch(url, {
								headers: {
									'x-requested-with': 'XMLHttpRequest'
								},
							}
						).then(function (res) {
							return res.json();
						}).then(function (json) {

							self.blnIsBusy = false;

							let i = 0;
							self.itemsTotal = parseInt(json['meta']['itemsTotal']);
							for (const row of json['data']) {
								i++;
								self.rows.push(row);
								self.loadedItems++;
							}

							// Store all ids of loaded events in self.arrEventIds
							for (const id of json['meta']['arrEventIds']) {
								self.arrEventIds.push(id);
							}

							if (i === 0 || parseInt(json['meta']['itemsTotal']) === self.loadedItems) {
								self.blnAllEventsLoaded = true
							}

							if (self.blnAllEventsLoaded === true) {
								//console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
							}
							return json;
						}).then(function (json) {
							let take = self.getTake();

							const url = new URL(window.location.href);
							const urlParams = new URLSearchParams(url.search);
							const href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

							if (self.loadedItems > self.apiParams['limit']) {
								take = self.loadedItems;
								if (!urlParams.has('take_e' + self.modId)) {
									urlParams.append('take_e' + self.modId, take);
								} else {
									urlParams.set('take_e' + self.modId, take);
								}

								// Current URL: https://my-website.ch/demo_a.html?take_324=200
								const nextURL = href + '?' + urlParams.toString();
								const nextTitle = document.title; // keep the original title

								// This will create a new entry in the browser's history, without reloading
								window.history.replaceState({}, nextTitle, nextURL);
							}

							return json;

						}).then(function (json) {
							// Trigger on insert callback
							if (self.callbackExists('oninsert')) {
								self.callbacks.oninsert(self, json);
							}
							return json;
						});

					},

					// Check if callback exists
					callbackExists: function callbackExists(strCallback) {
						const self = this;
						return typeof self.callbacks !== "undefined" && typeof self.callbacks[strCallback] !== "undefined" && typeof self.callbacks[strCallback] === "function";
					}
				}
			});
			app.config.compilerOptions.delimiters = ['[[ ', ' ]]'];
			app.mount(elId);
		}
	}
}
