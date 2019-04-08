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
            format: "dd-mm-yyyy",
            autoclose: true,
            maxViewMode: 0,
            language: "de"
        };
        // Set datepickers start and end date
        if (self.getUrlParam('year') > 0) {
            opt.startDate = "01-01-" + self.getUrlParam('year');
            opt.endDate = "31-12-" + self.getUrlParam('year');
        } else {
            var today = new Date();
            var mm = today.getMonth() + 1;
            var dd = today.getDate();
            var YYYY = today.getFullYear();
            opt.startDate = dd + '-' + mm + '-' + YYYY;
            YYYY = YYYY + 2;
            opt.endDate = dd + '-' + mm + '-' + YYYY;
        }
        $('#ctrl_dateStart').datepicker(opt);

        $('#dateStart button').click(function (e) {
            $('#ctrl_dateStart').datepicker('show');
        });

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
