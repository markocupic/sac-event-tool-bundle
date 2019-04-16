/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
new Vue({
    el: '#eventList',
    created() {
        var self = this;

        self.fetchData();
        self.interval = setInterval(
            function () {
                self.fetchData();
            }, 200);
    },
    data: {
        eventlist: Eventlist,
        rows: [],
        isBusy: false,
        loadedItems: 0,
        interval: null,
        allEventsLoaded: false,
        loadAtOnce: 50,
        loadAllOnSecondRequest: true
    },
    methods: {
        fetchData() {
            var self = this;
            if (this.isBusy === false && self.eventlist.ids.length > this.loadedItems) {
                this.isBusy = true;
                this.xhr();
            }
            if (self.allEventsLoaded === false && self.eventlist.ids.length == this.loadedItems) {
                self.allEventsLoaded = true;
                clearInterval(self.interval);
                console.log('Finished download process. ' + this.loadedItems + ' items loaded.');
            }
        },

        xhr() {
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

            for (let i = self.loadedItems; i < self.eventlist.ids.length; i++) {

                ids.push(self.eventlist.ids[i]);
                counter++;
                if (counter == itemsPerRequest) {
                    break;
                }
            }

            var data = {
                'REQUEST_TOKEN': self.eventlist.requestToken,
                'ids': ids,
                'fields': self.eventlist.fields
            };

            var self = this;
            var url = self.eventlist.url;
            var xhr = $.ajax({
                type: 'POST',
                url: url,
                data: data,
                dataType: 'json'
            });

            xhr.done(function (data, textStatus, jqXHR) {
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
