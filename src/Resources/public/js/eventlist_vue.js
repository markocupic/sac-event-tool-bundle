"use strict";

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
new Vue({
    el: '#event-list',
    created: function created() {
        var self = this;
        self.prepareRequest();
        self.interval = setInterval(function () {
            self.prepareRequest();
        }, 200);
    },
    data: function data() {
        return {
            eventlist: Eventlist,
            rows: [],
            isBusy: false,
            loadedItems: 0,
            interval: null,
            allEventsLoaded: false,
            loadAtOnce: 80,
            loadAllOnSecondRequest: true
        };
    },
    methods: {
        // Prepare ajax request
        prepareRequest: function prepareRequest() {
            var self = this;

            if (self.isBusy === false && self.eventlist.ids.length > self.loadedItems) {
                self.isBusy = true;
                self.getDataByXhr();
            }

            if (self.allEventsLoaded === false && self.eventlist.ids.length === self.loadedItems) {
                self.allEventsLoaded = true;
                clearInterval(self.interval);
                console.log('Finished download process. ' + self.loadedItems + ' events loaded.');
            }
        },
        // Get data by xhr
        getDataByXhr: function getDataByXhr() {
            var self = this;
            var ids = [];
            var counter = 0;
            var itemsPerRequest = 50;

            if (self.loadedItems === 0) {
                itemsPerRequest = self.loadAtOnce;
            }

            if (self.loadedItems > 0 && self.loadAllOnSecondRequest === true) {
                itemsPerRequest = self.eventlist.ids.length;
            }

            for (var i = self.loadedItems; i < self.eventlist.ids.length; i++) {
                ids.push(self.eventlist.ids[i]);
                counter++;

                if (counter === itemsPerRequest) {
                    break;
                }
            }

            var data = {
                'REQUEST_TOKEN': self.eventlist.requestToken,
                'ids': ids,
                'fields': self.eventlist.fields
            };
            var url = self.eventlist.url;
            var xhr = $.ajax({
                type: 'POST',
                url: url,
                data: data,
                dataType: 'json'
            });
            xhr.done(function (data) {
                self.isBusy = false;
                data.forEach(function (row) {
                    self.rows.push(row);
                    self.loadedItems++;
                });
                window.setTimeout(function () {
                    $('#eventList').find('[data-toggle="tooltip"]').tooltip();
                }, 100);
            });
        }
    }
});