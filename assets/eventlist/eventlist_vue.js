/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

"use strict";

if (typeof VueEventList !== 'function') {

    window.VueEventList = class {
        constructor(elId, opt) {
            // Defaults
            const defaults = {
                'modId': null,
                'csrfToken': null,
                'apiParams': {
                    'organizers': [],
                    'eventType': ["tour", "generalEvent", "lastMinuteTour", "course"],
                    'suitableForBeginners': '',
                    'publicTransportEvent': '',
                    'favoredEvent': '',
                    'tourType': '',
                    'courseType': '',
                    'courseId': '',
                    'getUpcoming': '',
                    'dateStart': '',
                    'dateEnd': '',
                    'textSearch': '',
                    'eventId': '',
                    'username': '',
                    // Let empty for all published
                    'calendarIds': [],
                    'limit': '50',
                    'offset': '0',
                },
                'fields': [],
            };

            // Merge options and defaults
            const params = {...defaults, ...opt}

            if (null === params.csrfToken) {
                console.error('No CSRF token has been set.');
            }

            const {createApp} = Vue

            // Instantiate vue.js application
            let app = createApp({
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
                        // all events loaded bool
                        blnAllEventsLoaded: false,
                        // The last (fetch) request url
                        lastRequestUrl: '',
                        // Flag to prevent loading from indexedDB after back-forward-cache restore
                        blnLoadedFromCache: false,
                    };
                },
                mounted() {
                    const self = this;

                    // Listen for pageshow event to detect back-forward-cache restore
                    window.addEventListener('pageshow', (event) => {
                        if (event.persisted) {
                            // Page was restored from back-forward-cache
                            self.blnLoadedFromCache = true;
                            console.log('Page restored from back-forward-cache.');
                        }
                    });

                    self.prepareRequest();
                },

                methods: {
                    // Prepare the ajax request
                    prepareRequest: function prepareRequest() {
                        const self = this;
                        if (self.blnIsBusy === false) {
                            self.blnIsBusy = true;
                            self.fetchItems();
                        }
                    },

                    favorEvent: async function favorEvent(index) {
                        const affectedRow = this.rows[index];
                        const eventId = affectedRow.id;

                        const formData = new FormData();
                        formData.append('eventId', eventId);
                        formData.append('REQUEST_TOKEN', params.csrfToken);

                        try {
                            const response = await fetch(window.location.href, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest',
                                },
                            });

                            if (response.ok) {
                                const json = await response.json();
                                if (json.status === 'success') {
                                    this.rows[index].isFavoredEvent = json.isFavoredEvent;
                                }
                            }
                        } catch (error) {
                            console.error(error.message);
                        }
                    },

                    getTake: function getTake() {
                        const self = this;
                        const urlString = window.location.href;
                        const url = new URL(urlString);
                        const take = url.searchParams.get('take_e' + self.modId);

                        return null === take ? null : parseInt(take);
                    },

                    // Load items from the server or the indexedDB cache
                    fetchItems: async function fetchItems() {
                        const self = this;

                        let skipIndexedDB = false;

                        // Skip indexedDB loading if data already exists from back-forward-cache
                        if (self.blnLoadedFromCache && self.loadedItems > 0) {
                            console.log('Skipping indexedDB load - data already present from back-forward-cache.');
                            skipIndexedDB = true;
                        }

                        // Initialize indexedDB
                        const db = new Dexie('SacEventToolEventListing');
                        db.version(1).stores({
                            eventStore: '++id, path, expires'
                        });

                        // Delete expired data
                        await db.eventStore.where("expires")
                            .below(Math.round(+new Date() / 1000))
                            .delete()
                        ;

                        const eventStoreData = await db.eventStore
                            .where("path")
                            .equals(btoa(window.location.href + '&modId=' + self.modId))
                            .first()
                        ;

                        // Search data in the forward-backwards (indexedDB) cache
                        if (false === skipIndexedDB && eventStoreData && eventStoreData.path && eventStoreData.vueDataSerialized) {

                            // Delete data from indexedDB
                            await db.eventStore.delete(eventStoreData.id);

                            const vueData = JSON.parse(eventStoreData.vueDataSerialized);

                            // Return if storage data is outdated
                            if (vueData.expiry < Date.now()) {
                                return;
                            }

                            console.log('Loaded events from indexedDB cache.');

                            self.rows = vueData.rows;
                            self.lastRequestUrl = vueData.lastRequestUrl;
                            self.arrEventIds = vueData.arrEventIds;
                            self.itemsTotal = vueData.itemsTotal;
                            self.loadedItems = vueData.loadedItems;
                            self.blnAllEventsLoaded = vueData.blnAllEventsLoaded;
                            self.blnIsBusy = false;

                            await self.$nextTick();

                            // Dispatch the sacevt::event_list.indexed_db_load event
                            const event = new CustomEvent('sacevt::event_list.indexed_db_load', {
                                detail: {
                                    elId: self.elId,
                                    storage: vueData,
                                    vueInstance: self,
                                },
                            });

                            document.dispatchEvent(event);

                            // Create the sacevt::event_list.insert event
                            const onInsertEvent = new CustomEvent('sacevt::event_list.insert', {
                                detail: {
                                    elId: self.elId,
                                    vueInstance: self,
                                    json: null,
                                },
                            });

                            // Dispatch the sacevt::event_list.insert event
                            document.querySelector(self.elId).dispatchEvent(onInsertEvent);

                            // Scroll to last mouse click position
                            (() => {
                                const scrollToSelector = eventStoreData.selector;
                                const elScrollTo = document.querySelector('[data-selector="' + scrollToSelector + '"]');

                                if (elScrollTo) {
                                    elScrollTo.scrollIntoView({behavior: "instant"});
                                }
                            })();

                            // Parse current URL
                            const url = new URL(window.location.href);
                            const urlParams = url.searchParams;

                            // Remove the "itemId" parameter when returning from the detail view
                            if (urlParams.has('itemId')) {
                                urlParams.delete('itemId');
                            }

                            // Build a clean base URL (origin + pathname)
                            const baseUrl = new URL(window.location.pathname, window.location.origin);

                            // Apply updated query parameters
                            baseUrl.search = urlParams.toString();

                            // Keep the same title
                            const nextTitle = document.title;

                            // Update browser history without reloading
                            window.history.replaceState({}, nextTitle, baseUrl.toString());

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

                        // Set the limit on page load/refresh
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
                        const url = new URL('/eventApi/events', window.location.origin);
                        url.search = urlParams.toString();

                        self.lastRequestUrl = url;

                        // Dispatch the sacevt::event_list.pre_fetch event
                        const event = new CustomEvent('sacevt::event_list.pre_fetch', {
                            'detail': {
                                url: url,
                                modId: self.modId,
                                instance: self,
                            }
                        });

                        document.dispatchEvent(event);

                        try {
                            // Fetch
                            const response = await fetch(url, {
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }

                            const json = await response.json();

                            self.blnIsBusy = false;

                            // Process loaded records
                            let i = 0;
                            self.itemsTotal = parseInt(json.meta.itemsTotal);

                            for (const row of json.data) {
                                i++;
                                row.selector = self.modId + '-' + row.id;
                                self.rows.push(row);
                                self.loadedItems++;
                            }

                            // Save the event IDS
                            for (const id of json.meta.arrEventIds) {
                                self.arrEventIds.push(id);
                            }

                            // Check if all events are loaded
                            if (i === 0 || self.loadedItems === parseInt(json.meta.itemsTotal)) {
                                self.blnAllEventsLoaded = true;
                            }

                            // Update the URL with the take parameter
                            let take = self.getTake();

                            const urlObj = new URL(window.location.href);
                            const urlParams = urlObj.searchParams;

                            // If more items have been loaded than the API limit, update the take value.
                            if (self.loadedItems > self.apiParams.limit) {
                                take = self.loadedItems;

                                const key = 'take_e' + self.modId;
                                urlParams.set(key, take);

                                const nextUrl = new URL(window.location.pathname, window.location.origin);
                                nextUrl.search = urlParams.toString();

                                window.history.replaceState({}, document.title, nextUrl.toString());
                            }

                            // Dispatch the sacevt::event_list.insert event
                            const onInsertEvent = new CustomEvent('sacevt::event_list.insert', {
                                detail: {
                                    vueInstance: self,
                                    json: json,
                                },
                            });

                            document.querySelector(self.elId).dispatchEvent(onInsertEvent);

                            return json;

                        } catch (err) {
                            console.error('Fetch error:', err);
                            throw err;
                        }
                    },
                }
            });
            app.config.compilerOptions.delimiters = ['[[ ', ' ]]'];
            app.mount(elId);
        }
    }
}
