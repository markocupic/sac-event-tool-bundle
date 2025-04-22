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

        const filterBoard = document.querySelector('#event-filter-board-form');

        if (!filterBoard) {
            console.error('Filter board not found!');
            return;
        }

        let self = this;

        self.options = opt;

        const choices = ['#ctrl_organizers', '#ctrl_tourType', '#ctrl_courseType'];

        for (const elementIdSelector of choices) {
            const element = document.querySelector(elementIdSelector);
            if (element) {
                new Choices(element, {
                    removeItems: true,
                    removeItemButton: true,
                });
            }
        }

        window.setTimeout(() => {
            const widgets = document.querySelectorAll('.filter-board-widget');
            for (const widget of widgets) {
                widget.style.visibility = 'visible';
            }
        }, 20);

        // Reset form
        filterBoard.querySelector('.reset-form').addEventListener('click', (e) => {
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
