/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

//Provides methods for filtering the event list
var EventFilter = {

    /**
     * Initialize filter board
     * @param eventList
     * @param options
     */
    initialize: function () {
        var self = this;

        // Initialize Select2 for organizer input
        $('#ctrl_organizers').select2();
        window.setTimeout(function () {
            $('.filter-board-widget').css('visibility', 'visible');
        }, 20);

        // Reset form
        $('.filter-board .reset-form').click(function (e) {
            e.stopPropagation();
            e.preventDefault();
            window.location.href = location.href.replace(location.search, '');
        });


        //Set Datepicker
        var opt = {
            dateFormat: 'Y-m-d',
            "locale": 'de',
        }

        var today = new Date();
        var mm = today.getMonth() + 1;
        var dd = today.getDate();
        var YYYY = today.getFullYear();

        // Set datepickers start and end date
        if (self.getUrlParam('year') > 0) {
            opt.minDate = self.getUrlParam('year') + '-01-01';
            opt.maxDate = self.getUrlParam('year') + '-12-31';
            opt.defaultDate = '';
            if (self.getUrlParam('dateStart') != '') {
                opt.defaultDate = self.getUrlParam('dateStart');
            }
        } else {
            var today = new Date();
            var mm = today.getMonth() + 1;
            var dd = today.getDate();
            var YYYY = today.getFullYear();
            opt.minDate = YYYY + '-' + mm + '-' + dd;
            YYYY = YYYY + 2;
            opt.maxDate = YYYY + '-' + mm + '-' + dd;
        }

        flatpickr("#ctrl_dateStart", opt);

    },
    /**
     * @param strParam
     * @returns {*}
     */
    getUrlParam: function (strParam) {
        "use strict";
        var results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
        if (results === null) {
            return 0;
        }
        return results[1] || 0;
    }
};
