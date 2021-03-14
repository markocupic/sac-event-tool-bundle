"use strict";

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
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
                'eventType': ["tour", "generalEvent", "lastMinuteTour"],
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

        // Use lodash to merge options and defaults
        let params = _.merge(defaults, opt);

        // Instantiate vue.js application
        new Vue({
            el: elId,
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
                        self.getDataByXhr();
                        console.log('Loading events...')
                    }
                },

                // Get data by xhr
                getDataByXhr: function getDataByXhr() {
                    let self = this;

                    if (self.blnAllEventsLoaded === true) {
                        return;
                    }

                    let formData = new FormData();

                    // Handle arrays correctly
                    for (let prop in self.apiParams) {
                        let property = self.apiParams[prop];
                        if (prop === 'offset') {
                            formData.append('offset', parseInt(self.apiParams.offset) + self.loadedItems);
                        } else if (Array.isArray(property)) {
                            for (let i = 0; i < property.length; ++i) {
                                formData.append(prop + '[]', property[i]);
                            }
                        } else {
                            formData.append(prop, property);
                        }
                    }

                    // Handle fields correctly
                    for (let i = 0; i < self.fields.length; ++i) {
                        formData.append('fields[]', self.fields[i]);
                    }

                    let params = new URLSearchParams(Array.from(formData)).toString()

                    // Fetch
                    fetch('eventApi/events?' + params, {
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
                            console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
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
                    if (typeof self.callbacks !== "undefined" && typeof self.callbacks[strCallback] !== "undefined" && typeof self.callbacks[strCallback] === "function") {
                        return true;
                    }
                    return false;
                }
            }
        });
    }
}

