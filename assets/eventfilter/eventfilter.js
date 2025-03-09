/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

"use strict";

const EventListFilter = {

    options: null,

    /**
     * Initialize filter board
     * @param eventList
     * @param options
     */
    initialize: function (opt) {
        if (typeof window.jQuery === 'undefined') {
            console.error('EventListFilter requires jQuery, but jQuery is not loaded.');
            return;
        }

        let self = this;

        self.options = opt;

        // Initialize Select2 for organizer input
        if (document.getElementById('ctrl_organizers')) {
            $('#ctrl_organizers').select2();
        }

        // Initialize Select2 for tourType input
        if (document.getElementById('ctrl_tourType')) {
            $('#ctrl_tourType').select2();
        }

        // Initialize Select2 for courseType input
        if (document.getElementById('ctrl_courseType')) {
            $('#ctrl_courseType').select2();
        }

        $('#ctrl_tourType').select2();
        $('#ctrl_courseType').select2();

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
                'max-width': '100%', 'width': '100%',
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
        const datePickerOpt = {
            dateFormat: self.options.dateFormat, "locale": self.options.locale,
        }

        const today = new Date();
        const mm = today.getMonth() + 1;
        const dd = today.getDate();
        const YYYY = today.getFullYear();


        const minYYYY = 2016;
        const maxYYYY = today.getFullYear() + 1

        datePickerOpt.minDate = minYYYY + '-01-01';
        datePickerOpt.maxDate = maxYYYY + '-12-31';

        // Set datepickers start and end date
        if (self.getUrlParam('year') > 0) {
            datePickerOpt.defaultDate = self.getUrlParam('year') + '-01-01';

            if (self.getUrlParam('dateStart') != '') {
                datePickerOpt.defaultDate = self.getUrlParam('dateStart');
            }
        }

        // Instantiate the flatpicker calendar plugin
        const calendar = flatpickr("#ctrl_dateStart", datePickerOpt);

        // Update the calendar if the year dropdown has been changed
        document.getElementById('year').addEventListener('change', (e) => {
            if (!e.target.value) {
                calendar.clear();
            } else {
                calendar.setDate(e.target.value + '-01-01');
            }
        });

    }, /**
     * @param strParam
     * @returns {*}
     */
    getUrlParam: function (strParam) {
        "use strict";
        const results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
        if (results === null) {
            return 0;
        }

        return results[1] || 0;
    }
};
