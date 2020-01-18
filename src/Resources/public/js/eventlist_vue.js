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

                    // If set to false use the button on the bottom of the list to load more items
                    enableAutoloading: false,
                    // Load x items per request
                    limit: params.limit,
                    // Set number of items that are loaded onthe first request
                    limitOnFirstRequest: params.limitOnFirstRequest,
                    // Set offset
                    offset: params.offset,
                    // If set to true all items will be loaded at once on the second request
                    loadAllOnSecondRequest: false,

                    // Don't touch from here
                    params: params,
                    total: params.ids.length,
                    rows: [],
                    isBusy: false,
                    loadedItems: 0,
                    interval: null,
                    allEventsLoaded: false,

                };
            },
            methods: {
                // Prepare ajax request
                prepareRequest: function prepareRequest(isPreloadRequest = false) {
                    var self = this;

                    if (self.isBusy === false && self.params.ids.length > self.loadedItems) {
                        self.isBusy = true;
                        self.getDataByXhr(isPreloadRequest);
                        console.log('Loading events...')
                    }

                    if (self.allEventsLoaded === false && self.params.ids.length === self.loadedItems) {
                        self.allEventsLoaded = true;
                        if (self.enableAutoloading === true && self.interval !== null) {

                            clearInterval(self.interval);
                        }
                    }

                },
                preload: function preload() {
                    var self = this;
                    self.prepareRequest(true);
                },
                // Get data by xhr
                getDataByXhr: function getDataByXhr(isPreloadRequest) {
                    var self = this;
                    var ids = [];
                    var counter = 0;
                    var limit = self.limit;

                    if (self.allEventsLoaded === true) {
                        return;
                    }

                    if (self.loadedItems === 0) {
                        limit = self.limitOnFirstRequest;
                    }

                    if (self.loadedItems > 0 && self.loadAllOnSecondRequest === true) {
                        limit = self.params.ids.length;
                    }

                    for (var i = self.loadedItems; i < self.params.ids.length; i++) {
                        ids.push(self.params.ids[i]);
                        counter++;

                        if (counter === limit) {
                            break;
                        }
                    }

                    var offset = self.offset + self.loadedItems;

                    var data = {
                        'REQUEST_TOKEN': self.params.requestToken,
                        'ids': self.params.ids,
                        'offset': offset,
                        'limit': limit,
                        'fields': self.params.fields,
                        'sessionCacheToken': btoa(window.location.href),
                        'isPreloadRequest': isPreloadRequest,
                    };

                    var url = self.params.ajaxEndpoint;

                    var xhr = $.ajax({
                        type: 'POST',
                        url: url,
                        data: data,
                        dataType: 'json',
                    });

                    xhr.done(function (data) {
                        self.isBusy = false;

                        data['arrEventData'].forEach(function (row) {
                            self.rows.push(row);
                            self.loadedItems++;
                        });

                        window.setTimeout(function () {
                            $(self.$el).find('[data-toggle="tooltip"]').tooltip();
                        }, 100);

                        if (self.allEventsLoaded === false && self.params.ids.length === self.loadedItems) {
                            self.allEventsLoaded = true;
                            console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
                        } else {
                            console.log(data['isPreloadRequest']);
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
