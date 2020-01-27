"use strict";

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
class VueTourList {

    constructor(elId, opt) {

        // Defaults
        const defaults = {
            'apiParams': {
                'organizers': [],
                'eventType': ["tour","generalEvent","lastMinuteTour"],
                'tourType': '',
                'courseType': '',
                'courseId': '',
                'year': '',
                'dateStart': '',
                'searchterm': '',
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

                self.prepareRequest(false);
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
                    // is busy bool
                    blnIsBusy: false,
                    // total found items
                    itemsFound: 0,
                    // already loaded items
                    loadedItems: 0,
                    // all events loades bool
                    blnAllEventsLoaded: false,
                };
            },
            methods: {
                // Prepare ajax request
                prepareRequest: function prepareRequest(isPreloadRequest = false) {
                    let self = this;

                    if (self.blnIsBusy === false) {
                        self.blnIsBusy = true;
                        self.getDataByXhr(isPreloadRequest);
                        console.log('Loading events...')
                    }
                },

                // Preload and use the session cache
                preload: function preload() {
                    let self = this;
                    self.prepareRequest(true);
                },

                // Get data by xhr
                getDataByXhr: function getDataByXhr(isPreloadRequest) {
                    let self = this;

                    if (self.blnAllEventsLoaded === true) {
                        return;
                    }

                    let data = new FormData();
                    data.append('requestToken', self.requestToken);
                    data.append('sessionCacheToken', btoa(window.location.href));
                    data.append('isPreloadRequest', isPreloadRequest);

                    // Handle arrays correctly
                    for (let prop in self.apiParams) {
                        let property = self.apiParams[prop];
                        if (prop === 'offset') {
                            data.append('offset', parseInt(self.apiParams.offset) + self.loadedItems);
                        }
                        else if (Array.isArray(property)) {
                            for (let i = 0; i < property.length; ++i) {
                                data.append(prop + '[]', property[i]);
                            }
                        }
                        else {
                            data.append(prop, property);
                        }
                    }

                    // Handle fields correctly
                    for (let i = 0; i < self.fields.length; ++i) {
                        data.append('fields[]', self.fields[i]);
                    }

                    // Fetch
                    fetch('eventApi/getEventList', {

                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        }
                    ).then(function (res) {
                        return res.json();
                    }).then(function (json) {

                        self.blnIsBusy = false;

                        let i = 0;
                        self.itemsFound = json['itemsFound'];
                        json['arrEventData'].forEach(function (row) {
                            i++;
                            self.rows.push(row);
                            self.loadedItems++;
                        });


                        if (json['isPreloadRequest'] === false) {
                            if (i === 0 || parseInt(json['itemsFound']) === self.loadedItems) {
                                self.blnAllEventsLoaded = true
                            }
                        }

                        if (self.blnAllEventsLoaded === true) {
                            console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
                        } else {
                            if (json['isPreloadRequest'] === false) {
                                // Preload
                                self.preload();
                            }
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
