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
                // The module only lists the favored events of the logged-in member
                'showFavoredEventsOnly': false,
            };

            // Merge options and defaults
            const params = {...defaults, ...opt}

            if (null === params.csrfToken) {
                console.error('No CSRF token has been set.');
            }

            // Used to cancel a pending request, e.g. if the user changes the filter
            let abortController = null;

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
                        // The filter has changed: replace the displayed rows as soon as the new rows have been loaded
                        blnReplaceRows: false,
                        // Flag to prevent loading from indexedDB after back-forward-cache restore
                        blnLoadedFromCache: false,
                    };
                },
                mounted() {
                    const self = this;

                    // Listen for pageshow event to detect back-forward-cache restore
                    window.addEventListener('pageshow', (event) => {
                        if (event.persisted) {
                            // Page was restored from the back-forward-cache
                            self.blnLoadedFromCache = true;
                            console.log('Page restored from back-forward-cache.');
                        }
                    });

                    // Reload the list without page reload if the user changes the event filter
                    document.addEventListener('sacevt::event_filter.change', (event) => {
                        // Tell the filter form that the event has been handled (no page reload needed)
                        event.preventDefault();
                        self.applyFilter(event.detail.params);
                    });

                    // Show the loading spinner while the filter is waiting for the user to stop typing
                    document.addEventListener('sacevt::event_filter.pending', () => {
                        self.blnIsBusy = true;
                    });

                    // The filter has not changed: hide the loading spinner if no request is running
                    document.addEventListener('sacevt::event_filter.unchanged', () => {
                        if (null === abortController) {
                            self.blnIsBusy = false;
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

                    // Apply the filter params (URLSearchParams) of the event filter form and reload the list
                    applyFilter: function applyFilter(filterParams) {
                        const self = this;

                        // Multi selects are transmitted like this: organizers[]=5&organizers[]=7
                        for (const key of ['organizers', 'tourType', 'courseType']) {
                            self.apiParams[key] = [...filterParams.getAll(key + '[]'), ...filterParams.getAll(key)]
                                .map((value) => parseInt(value))
                                .filter((value) => value > 0);
                        }

                        for (const key of ['suitableForBeginners', 'publicTransportEvent', 'favoredEvent', 'getUpcoming', 'dateStart', 'dateEnd', 'textSearch', 'eventId', 'courseId']) {
                            const values = filterParams.getAll(key).filter((value) => value !== '');
                            self.apiParams[key] = values.length ? values[values.length - 1] : '';
                        }

                        if (params.showFavoredEventsOnly) {
                            self.apiParams.favoredEvent = '1';
                        }

                        // Reset the list, but keep displaying the current rows until the new rows have been loaded
                        self.loadedItems = 0;
                        self.blnAllEventsLoaded = false;
                        self.blnReplaceRows = true;
                        self.blnIsBusy = true;

                        self.fetchItems().catch(() => {
                            // The error has already been logged
                        });
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
                        const take = (new URL(window.location.href)).searchParams.get('take_e' + self.modId);

                        return null === take ? null : parseInt(take);
                    },

                    // Load items from the server or the indexedDB cache
                    fetchItems: async function fetchItems() {
                        const self = this;

                        if (self.blnAllEventsLoaded === true) {
                            return;
                        }

                        const formData = new FormData();

                        // Add api parameters to the Form Data object
                        for (const [key, value] of Object.entries(self.apiParams)) {
                            if (key === 'offset') {
                                formData.append('offset', self.loadedItems + parseInt(value.toString()));
                            } else if (Array.isArray(value)) {// Handle arrays correctly
                                for (let i = 0; i < value.length; ++i) {
                                    formData.append(key + '[]', value[i]);
                                }
                            } else {
                                formData.append(key, value);
                            }
                        }

                        // Update when fetching more items
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

                        let urlSearchParams = new URLSearchParams(Array.from(formData)).toString();
                        let url = new URL('/eventApi/events', window.location.origin);
                        url.search = urlSearchParams.toString();

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

                        // Cancel a pending request (its result would belong to the old filter)
                        if (abortController) {
                            abortController.abort();
                        }

                        abortController = new AbortController();

                        try {
                            // Fetch
                            const response = await fetch(url, {
                                signal: abortController.signal,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }

                            const json = await response.json();

                            abortController = null;
                            self.blnIsBusy = false;

                            // Replace the rows of the previous filter
                            if (self.blnReplaceRows) {
                                self.rows = [];
                                self.arrEventIds = [];
                                self.blnReplaceRows = false;
                            }

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

                            let urlSearchParams = (new URL(window.location.href)).searchParams;

                            // Update the URL in the browser without triggering a page reload
                            if (self.loadedItems > self.apiParams.limit) {
                                take = self.loadedItems;

                                const key = 'take_e' + self.modId;
                                urlSearchParams.set(key, take);

                                const nextUrl = new URL(window.location.pathname, window.location.origin);
                                nextUrl.search = urlSearchParams.toString();

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
                            // The request has been replaced by a newer one
                            if (err.name === 'AbortError') {
                                return;
                            }

                            abortController = null;
                            self.blnIsBusy = false;
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
