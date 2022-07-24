"use strict";

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
class VueTourList {
    constructor(elId, opt) {

        // Defaults
        const defaults = {
            'apiParams': {
                'organizers': [],
                'eventType': ["tour", "generalEvent", "lastMinuteTour", "course"],
                'suitableForBeginners': '',
                'tourType': '',
                'courseType': '',
                'courseId': '',
                'year': '',
                'dateStart': '',
                'textsearch': '',
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

        // Instantiate vue.js application
        new Vue({
            el: elId,
            delimiters: ["<%", "%>"],
            created: function created() {
                let self = this;

                self.prepareRequest();
            },

            data: function data() {
                return {
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
                    // is busy bool
                    blnIsBusy: false,
                    // total found items
                    itemsTotal: 0,
                    // already loaded items
                    loadedItems: 0,
                    // all events loades bool
                    blnAllEventsLoaded: false,
                };
            },
            methods: {
                // Prepare ajax request
                prepareRequest: function prepareRequest() {
                    let self = this;
                    if (self.blnIsBusy === false) {
                        self.blnIsBusy = true;
                        self.fetchItems();
                    }
                },

                getTake: function getTake() {
                    let urlString = window.location.href;
                    let url = new URL(urlString);
                    let take = url.searchParams.get('take');

                    return null === take ? null : parseInt(take);
                },

                // Load items from server
                fetchItems: function fetchItems() {
                    let self = this;

                    // Try to retrieve data from storage
                    let storageData = localStorage.getItem(btoa(window.location.href));
                    if (storageData) {
                        localStorage.removeItem(btoa(window.location.href));
                        let storageObject = JSON.parse(storageData);

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

                        // Trigger oninsert callback
                        if (self.callbackExists('oninsert')) {
                            self.callbacks.oninsert(self, null);
                        }

                        let url = new URL(window.location.href);
                        let urlParams = new URLSearchParams(url.search);
                        let href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                        // Remove the "itemId" parameter when the user returns from the detail view
                        if (urlParams.has('itemId')) {
                            urlParams.delete('itemId');
                        }

                        // Current URL: https://my-website.ch/demo_a.html?take=200
                        const nextURL = href + (urlParams.toString() ? '?' + urlParams.toString() : '');
                        const nextTitle = document.title; // keep the same title

                        // This will create a new entry in the browser's history, without reloading
                        window.history.replaceState({}, nextTitle, nextURL);

                        return;
                    }

                    if (self.blnAllEventsLoaded === true) {

                        return;
                    }

                    let formData = new FormData();

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
                    self.fields.forEach((value, key) => {
                        formData.append('fields[]', value);
                    });

                    let urlParams = new URLSearchParams(Array.from(formData)).toString();
                    let url = 'eventApi/events?' + urlParams;

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
                        json['data'].forEach(function (row) {
                            i++;
                            self.rows.push(row);
                            self.loadedItems++;
                        });

                        // Store all ids of loaded events in self.arrEventIds
                        json['meta']['arrEventIds'].forEach(function (id) {
                            self.arrEventIds.push(id);
                        });

                        if (i === 0 || parseInt(json['meta']['itemsTotal']) === self.loadedItems) {
                            self.blnAllEventsLoaded = true
                        }

                        if (self.blnAllEventsLoaded === true) {
                            //console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
                        }
                        return json;
                    }).then(function (json) {
                        let take = self.getTake();

                        let url = new URL(window.location.href);
                        let urlParams = new URLSearchParams(url.search);
                        let href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                        if (self.loadedItems > self.apiParams['limit']) {
                            take = self.loadedItems;
                            if (!urlParams.has('take')) {
                                urlParams.append('take', take);
                            } else {
                                urlParams.set('take', take);
                            }

                            // Current URL: https://my-website.ch/demo_a.html?take=200
                            const nextURL = href + '?' + urlParams.toString();
                            const nextTitle = document.title; // keep the original title

                            // This will create a new entry in the browser's history, without reloading
                            window.history.replaceState({}, nextTitle, nextURL);
                        }

                        return json;

                    }).then(function (json) {
                        // Trigger oninsert callback
                        if (self.callbackExists('oninsert')) {
                            self.callbacks.oninsert(self, json);
                        }
                        return json;
                    });

                },

                // Check if callback exists
                callbackExists: function callbackExists(strCallback) {
                    let self = this;
                    return typeof self.callbacks !== "undefined" && typeof self.callbacks[strCallback] !== "undefined" && typeof self.callbacks[strCallback] === "function";

                }
            }
        });
    }
}


