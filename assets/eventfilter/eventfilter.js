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

        // Initialize date picker
        const datePicker = document.getElementById('ctrl_dateStart');
        datePicker.setAttribute('min', opt.datePicker.minYear + '-01-01');
        datePicker.setAttribute('max', (new Date()).getFullYear() + 1 + '-12-31');

        // Set the date pickers start and end date
        if (self.getUrlParam('year') > 0) {
            datePicker.value = self.getUrlParam('year') + '-01-01';

            if (self.getUrlParam('dateStart') !== '') {
                datePicker.value = self.getUrlParam('dateStart');
            }
        }

        // Reset date picker if the user changes the year number.
        const yearInput = document.getElementById('ctrl_year');
        yearInput.addEventListener('change', (e) => {
            datePicker.value = '';
        });

        // Update the year input field if the user changes the date picker.
        datePicker.addEventListener('change', (e) => {
            if (!e.target.value) {
                yearInput.value = '';
            } else {
                const date = new Date(e.target.value);
                if (date) {
                    yearInput.value = date.getFullYear().toString();
                } else {
                    yearInput.value = '';
                }
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
