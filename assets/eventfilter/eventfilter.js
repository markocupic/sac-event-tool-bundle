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
     * @param opt
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

        // Handle the year and dateStart inputs
        const dateEndInput = document.getElementById('ctrl_dateEnd');
        const datePickerInput = document.getElementById('ctrl_dateStart');
        const getUpcomingInput = document.getElementById('ctrl_getUpcoming');
        const yearInput = document.getElementById('ctrl_year');

        datePickerInput.setAttribute('min', opt.datePicker.minYear + '-01-01');
        datePickerInput.setAttribute('max', (new Date()).getFullYear() + 1 + '-12-31');

        // Set the date pickers start and end date
        if (self.getUrlParam('year') > 0) {
            const date = this.getDateOrNull(self.getUrlParam('dateStart'));
            if (null !== date) {
                yearInput.value = date.getFullYear().toString();
                dateEndInput.value = date.getFullYear().toString() + '-12-31';
                getUpcomingInput.disabled = true;
                getUpcomingInput.value = '';
            } else {
                yearInput.value = '';
                dateEndInput.disabled = true;
                dateEndInput.value = '';
                getUpcomingInput.disabled = false;
                getUpcomingInput.value = '1';
                getUpcomingInput.setAttribute('value', '1');
                datePickerInput.value = '';
            }
        } else {
            const date = this.getDateOrNull(self.getUrlParam('dateStart'));

            if (null !== date) {
                yearInput.value = date.getFullYear().toString();
                dateEndInput.value = date.getFullYear().toString() + '-12-31';
                getUpcomingInput.disabled = true;
                getUpcomingInput.value = '';
            } else {
                yearInput.value = '';
                dateEndInput.disabled = true;
                dateEndInput.value = '';
                getUpcomingInput.disabled = false;
                getUpcomingInput.value = '1';
                getUpcomingInput.setAttribute('value', '1');
                datePickerInput.value = '';
            }
        }

        // Reset date picker if the user changes the year number.
        yearInput.addEventListener('change', (e) => {
            getUpcomingInput.disabled = true;
            getUpcomingInput.value = '';
            datePickerInput.value = e.target.value + '-01-01';
            dateEndInput.disabled = false;
            dateEndInput.value = e.target.value + '-12-31';

            if (e.target.value === '') {
                getUpcomingInput.disabled = false;
                getUpcomingInput.value = '1';
                dateEndInput.disabled = true;
                dateEndInput.value = '';
                getUpcomingInput.setAttribute('value', '1');
                datePickerInput.value = '';
            }
        });

        // Update the year input field if the user changes the date picker.
        datePickerInput.addEventListener('change', (e) => {
            const date = this.getDateOrNull(e.target.value);
            if (null !== date) {
                yearInput.value = date.getFullYear().toString();
                dateEndInput.disabled = false;
                dateEndInput.value = date.getFullYear().toString() + '-12-31';
                getUpcomingInput.disabled = true;
                getUpcomingInput.value = '';
            } else {
                yearInput.value = '';
                dateEndInput.disabled = true;
                dateEndInput.value = '';
                getUpcomingInput.disabled = false;
                getUpcomingInput.value = '1';
                getUpcomingInput.setAttribute('value', '1');
            }
        });

    },
    /**
     * @param strParam
     * @returns {*}
     */
    getUrlParam: function (strParam) {
        const results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
        if (results === null) {
            return 0;
        }

        return results[1] || 0;
    },
    /**
     * Returns null or the Date object
     * if strDate has the format YYYY-MM-DD
     *
     * @param strDate
     * @returns {Date|Date|null}
     */
    getDateOrNull: function (strDate) {
        if (typeof strDate !== "string") {
            return null;
        }

        const regex = /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/;

        if (!regex.test(strDate)) {
            return null;
        }

        const date = new Date(strDate);

        if (strDate.substring(0, 4) !== date.getFullYear().toString()) {
            return null;
        }

        return date;
    },
};
