"use strict";

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
class VueTourList {

    constructor(elId, params) {

        new Vue({
            el: elId,
            created: function created() {
                var self = this;

                self.prepareRequest(false);
            },

            data: function data() {
                return {

                    // Load x items per request
                    limitPerRequest: params.limitPerRequest,
                    // Limit total results
                    limitTotal: params.limitTotal,
                    // The frontend module id
                    moduleId: params.moduleId,
                    // Calendar ids
                    calendarIds: params.calendarIds,
                    // Image size array
                    imgSize: params.imgSize,
                    // Event types array
                    eventTypes: params.eventTypes,
                    // Filter param array base64 encoded
                    filterParam: params.filterParam,
                    // Endpoint url
                    ajaxEndpoint: params.ajaxEndpoint,
                    // Contao request token
                    requestToken: params.requestToken,
                    // Fields array
                    fields: params.fields,
                    // Result row
                    rows: [],
                    // Requested event ids
                    arrIds: null,
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
                    var self = this;

                    if (self.blnIsBusy === false) {
                        self.blnIsBusy = true;
                        self.getDataByXhr(isPreloadRequest);
                        console.log('Loading events...')
                    }
                },

                // Preload and use the session cache
                preload: function preload() {
                    var self = this;
                    self.prepareRequest(true);
                },

                // Get data by xhr
                getDataByXhr: function getDataByXhr(isPreloadRequest) {
                    var self = this;
                    var counter = 0;
                    var limitPerRequest = self.limitPerRequest;

                    if (self.blnAllEventsLoaded === true) {
                        return;
                    }

                    var data = {
                        'REQUEST_TOKEN': self.requestToken,
                        'offset': self.loadedItems,
                        'limitPerRequest': self.limitPerRequest,
                        'limitTotal': self.limitTotal,
                        'moduleId': self.moduleId,
                        'imgSize': self.imgSize,
                        'calendarIds': self.calendarIds,
                        'eventTypes': self.eventTypes,
                        'ajaxEndpoint': self.ajaxEndpoint,
                        'requestToken': self.requestToken,
                        'fields': self.fields,
                        'arrIds': self.arrIds,
                        'filterParam': self.filterParam,
                        'sessionCacheToken': btoa(window.location.href),
                        'isPreloadRequest': isPreloadRequest,
                    };

                    var xhr = $.ajax({
                        type: 'POST',
                        url: self.ajaxEndpoint,
                        data: data,
                        dataType: 'json',
                    });

                    xhr.done(function (data) {
                        self.blnIsBusy = false;

                        let i = 0;
                        self.itemsFound = data['itemsFound'];
                        data['arrEventData'].forEach(function (row) {
                            i++;
                            self.rows.push(row);
                            self.loadedItems++;
                        });

                        // Get ids to speed up requests
                        self.arrIds = data['arrIds'];

                        if (data['isPreloadRequest'] === false) {
                            if (i === 0 || parseInt(data['itemsFound']) === self.loadedItems) {
                                self.blnAllEventsLoaded = true
                            }
                        }

                        window.setTimeout(function () {
                            $(self.$el).find('[data-toggle="tooltip"]').tooltip();
                        }, 100);

                        if (self.blnAllEventsLoaded === true) {
                            console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
                        } else {
                            if (data['isPreloadRequest'] === false) {
                                // Preload
                                self.preload();
                            }
                        }
                    });
                }
            }
        });
    }
}
