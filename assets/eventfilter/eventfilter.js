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

    // The query string of the last applied filter
    lastQueryString: null,

    debounceTimer: null,

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

        // Apply the filter on change without page reload.
        // If disabled, the form is submitted the classic way by the submit button.
        opt.autoApply = opt.autoApply ?? true;

        // Delay in ms before the filter is applied while the user is typing into a text field
        opt.textInputDelay = opt.textInputDelay ?? 3000;

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
            if (e.target.value === '') {
                getUpcomingInput.disabled = false;
                getUpcomingInput.value = '1';
                dateEndInput.disabled = true;
                dateEndInput.value = '';
                getUpcomingInput.setAttribute('value', '1');
                datePickerInput.value = '';

                return;
            }

            getUpcomingInput.disabled = true;
            getUpcomingInput.value = '';
            datePickerInput.value = e.target.value + '-01-01';
            dateEndInput.disabled = false;
            dateEndInput.value = e.target.value + '-12-31';
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

        if (!opt.autoApply) {
            return;
        }

        // Apply the filter as soon as the user changes a filter value (no submit button needed).
        // The listener is registered on the form, so it runs after the year/date listeners above
        // have updated the hidden fields.
        const form = filterBoard.querySelector('form');

        form.addEventListener('change', (e) => {
            const input = e.target;

            if (!self.isFilterInput(input)) {
                return;
            }

            // Do not apply the filter while the user is still typing an incomplete date (e.g. year "202")
            if (input.name === 'dateStart' && input.value !== '' && (!input.checkValidity() || null === self.getDateOrNull(input.value))) {
                return;
            }

            self.applyFilter(form);
        });

        // Apply the filter while the user is typing into a text field (debounced)
        form.addEventListener('input', (e) => {
            const input = e.target;

            if (!self.isFilterInput(input) || input.name === 'dateStart' || (input.type !== 'text' && input.type !== 'search')) {
                return;
            }

            window.clearTimeout(self.debounceTimer);
            self.debounceTimer = window.setTimeout(() => self.applyFilter(form), self.options.textInputDelay);

            // Inform the event lists, so they can show the loading spinner while waiting
            document.dispatchEvent(new CustomEvent('sacevt::event_filter.pending'));
        });

        // Without a submit button, the enter key no longer submits the form (e.g. in the text search field)
        form.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') {
                return;
            }

            // Let Choices.js handle the enter key in its search input
            if (e.target.classList.contains('choices__input--cloned')) {
                return;
            }

            e.preventDefault();
            self.applyFilter(form);
        });

        // The initial filter state has been rendered by the server
        self.lastQueryString = self.getQueryString(form);

    },
    /**
     * Returns false for elements that are not part of the filter,
     * e.g. the search input of the Choices.js widgets.
     *
     * @param input
     * @returns {boolean}
     */
    isFilterInput: function (input) {
        return !!input.name && !input.classList.contains('choices__input--cloned');
    },
    /**
     * Build the (sorted) query string from the filter form.
     * Empty values are skipped, e.g. the hidden empty field of the checkbox widgets.
     *
     * @param form
     * @returns {string}
     */
    getQueryString: function (form) {
        const params = new URLSearchParams();

        for (const [key, value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') {
                params.append(key, value.trim());
            }
        }

        params.sort();

        return params.toString();
    },
    /**
     * Update the URL without reloading the page and inform the event lists
     * by dispatching the cancelable "sacevt::event_filter.change" event.
     * If no event list handles the event, the form is submitted the classic way.
     *
     * @param form
     */
    applyFilter: function (form) {
        window.clearTimeout(this.debounceTimer);

        const queryString = this.getQueryString(form);

        // Nothing has changed (e.g. "change" fired after the debounced "input")
        if (queryString === this.lastQueryString) {
            // Inform the event lists, so they can hide the loading spinner
            document.dispatchEvent(new CustomEvent('sacevt::event_filter.unchanged'));

            return;
        }

        this.lastQueryString = queryString;

        // Update the URL first: the event list reads the "take" parameter from the URL
        const url = new URL(window.location.pathname, window.location.origin);
        url.search = queryString;
        window.history.replaceState({}, document.title, url.toString());

        const event = new CustomEvent('sacevt::event_filter.change', {
            cancelable: true,
            detail: {
                params: new URLSearchParams(queryString),
            },
        });

        document.dispatchEvent(event);

        // No event list on this page has handled the event: reload the page
        if (!event.defaultPrevented) {
            form.submit();
        }
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
