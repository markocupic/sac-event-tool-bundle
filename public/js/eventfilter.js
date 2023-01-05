/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

//Provides methods for filtering the event list
var EventFilter = {

    options: null,

    /**
     * Initialize filter board
     * @param eventList
     * @param options
     */
    initialize: function (opt) {
        var self = this;

        self.options = opt;

        // Initialize Select2 for organizer input
        $('#ctrl_organizers').select2();

        if ($('#ctrl_year')) {
            window.setInterval(() => {
                if ($('.select2-selection__choice').length) {
                    $('.select2-selection').css({
                        'height': 'auto',
                    });
                } else {
                    $('.select2-selection').css({
                        'height': $('#ctrl_year').outerHeight() + 'px',
                    });
                }

            }, 100);
        }

        window.addEventListener('resize', function () {
            $('.select2.select2-container').css({
                'max-width': '100%',
                'width': '100%',
            });
        });

        window.setTimeout(() => {
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
            "locale": self.options.locale,
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
