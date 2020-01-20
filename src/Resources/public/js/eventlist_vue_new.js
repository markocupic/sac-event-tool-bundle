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
                self.prepareRequest();
                if (self.enableAutoloading === true) {
                    self.interval = setInterval(function () {
                        self.prepareRequest();
                    }, 200);
                }
            },
            data: function data() {
                return {

                    // Load x items per request
                    limitPerRequest: params.limitPerRequest,
                    limitTotal: params.limitTotal,
                    moduleId: params.moduleId,
                    calendarIds: params.calendarIds,
                    eventTypes: params.eventTypes,
                    filterParam: params.filterParam,
                    ajaxEndpoint: params.ajaxEndpoint,
                    requestToken: params.requestToken,
                    fields: params.fields,
                    rows: [],
                    isBusy: false,
                    // total found items
                    itemsFound: 0,
                    // already loaded items
                    loadedItems: 0,

                    interval: null,
                    blnAllEventsLoaded: false,
                };
            },
            methods: {
                // Prepare ajax request
                prepareRequest: function prepareRequest(isPreloadRequest = false) {
                    var self = this;

                    if (self.isBusy === false) {
                        self.isBusy = true;
                        self.getDataByXhr(isPreloadRequest);
                        console.log('Loading events...')
                    }

                    if (self.blnAllEventsLoaded === true) {
                        clearInterval(self.interval);
                    }

                },

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
                        'calendarIds': self.calendarIds,
                        'eventTypes': self.eventTypes,
                        'ajaxEndpoint': self.ajaxEndpoint,
                        'requestToken': self.requestToken,
                        'fields': self.fields,
                        'filterParam': self.filterParam,
                        'sessionCacheToken': btoa(window.location.href),
                        'isPreloadRequest': isPreloadRequest,
                    };

                    var url = self.ajaxEndpoint;

                    var xhr = $.ajax({
                        type: 'POST',
                        url: url,
                        data: data,
                        dataType: 'json',
                    });

                    xhr.done(function (data) {
                        self.isBusy = false;

                        let i = 0;
                        self.itemsFound = data['itemsFound'];
                        data['arrEventData'].forEach(function (row) {
                            i++;
                            self.rows.push(row);
                            self.loadedItems++;
                        });

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
