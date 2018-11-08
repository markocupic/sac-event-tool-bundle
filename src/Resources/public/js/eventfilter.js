/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

//Provides methods for filtering the event list
var EventFilter = {

    /**
     * Options
     */
    options: null,

    /**
     * globalEventId
     * This is used in self.queueRequest
     */
    globalEventId: 0,

    /**
     * The eventlist
     */
    $eventList: 0,

    /**
     * The filter board
     */
    $filterBoard: null,

    /**
     * The year select
     */
    $ctrlEventYear: null,

    /**
     * The organizer select input
     */
    $ctrlOrganizers: null,

    /**
     * Search input
     */
    $ctrlSearch: null,

    /**
     * Course type level select
     */
    $ctrlEventType: null,

    /**
     * The date start input
     */
    $ctrlDateStart: null,

    /**
     * Hidden date input
     */
    $ctrlDateStartHidden: null,

    /**
     * EventId input
     */
    $ctrlEventId: null,

    /**
     * requestCounter
     */
    $requestCounter: 0,


    /**
     * queueRequest
     */
    queueRequest: function () {
        "use strict";
        var self = this;

        self.showLoadingIcon();
        self.resetEventList();
        self.globalEventId++;
        var eventId = self.globalEventId;
        window.setTimeout(function () {
            if (eventId === self.globalEventId) {
                self.fireXHR();
            }
        }, self.options.delay);
    },

    /**
     * Reset/Remove List
     */
    resetEventList: function () {
        "use strict";

        $('.alert-no-results-found').remove();
        $('.event-item').each(function () {
            $(this).hide();
            $(this).removeClass('visible');
        });
    },

    /**
     * Show the loading icon
     */
    showLoadingIcon: function () {
        "use strict";
        var self = this;

        // Show loading icon
        if (typeof self.options['loadingIcon'] !== 'undefined') {
            $('.loader-icon-container').removeClass('invisible');
        }
    },

    /**
     * Hide the loading icon
     */
    hideLoadingIcon: function () {
        "use strict";
        var self = this;

        // Add loading icon
        if (typeof self.options['loadingIcon'] !== 'undefined') {
            $('.loader-icon-container').addClass('invisible');
        }
    },

    /**
     * List events starting from a certain date
     * @param dateStart
     */
    listEventsStartingFromDate: function (dateStart) {
        "use strict";
        var self = this;

        var regex = /^(.*)-(.*)-(.*)$/g;
        var match = regex.exec(dateStart);
        if (match) {
            // JavaScript counts months from 0 to 11. January is 0. December is 11.
            var date = new Date(match[3], match[2] - 1, match[1]);
            var tstamp = Math.round(date.getTime() / 1000);
            if (!isNaN(tstamp)) {
                self.$ctrlDateStartHidden.val(tstamp);
                self.$ctrlDateStartHidden.attr('value', tstamp);
                self.queueRequest();
                return;
            }
        }
        self.$ctrlDateStartHidden.attr('value', '0');
        self.$ctrlDateStartHidden.val('0');
        self.queueRequest();
    },


    /**
     * filterRequest
     */
    fireXHR: function () {
        "use strict";
        var self = this;

        var itemsFound = 0;

        // Event-items
        var arrIds = [];
        $('.event-item').each(function () {
            arrIds.push($(this).attr('data-id'));
        });

        // Event type
        var eventTypeId = self.$ctrlEventType.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_eventType', eventTypeId);

        // SAC OG's/organizers
        var arrOrganizers = self.$ctrlOrganizers.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_organizers', JSON.stringify(arrOrganizers));

        // StartDate
        var dateStartHidden = self.$ctrlDateStartHidden;
        var intStartDate = Math.round(dateStartHidden.val()) > 0 ? dateStartHidden.val() : 0;
        intStartDate = Math.round(intStartDate);

        // Text search
        var strSearchterm = self.$ctrlSearch.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_search', strSearchterm);

        // eventId
        var strEventId = self.$ctrlEventId.val();
        // Do not save Input to sessionStorage
        // self.sessionStorageSet('ctrl_eventId', strEventId);

        // Get url from options
        var url = self.options.xhr.action.split('?');

        var inputs = {
            action: url[1],
            year: self.getUrlParam('year'),
            REQUEST_TOKEN: self.options.requestToken,
            ids: JSON.stringify(arrIds),
            eventType: eventTypeId,
            organizers: JSON.stringify(arrOrganizers),
            searchterm: strSearchterm,
            startDate: intStartDate,
            eventId: strEventId
        }

        // No ajax request after page is loades and if all inputs are empty
        if (self.$requestCounter == 0 && inputs['year'] == 0 && inputs['eventType'] == 0 && inputs['eventId'] == "" && inputs['searchterm'] == "" && inputs['startDate'] == 0 && arrOrganizers.length < 1) {
            self.hideLoadingIcon();
            $('.event-item').each(function () {
                $(this).show();
                $(this).addClass('visible');
            });
            if (typeof self.options['eventContainer'] !== 'undefined') {
                $(self.options['eventContainer']).removeClass('d-none');
            }
        } else {
            self.$requestCounter++;
            var request = $.ajax({
                method: 'post',
                url: url[0],
                data: inputs,
                dataType: 'json'
            });

            request.done(function (json) {
                if (json) {
                    self.hideLoadingIcon();
                    //window.console.log(json);
                    $.each(json.filter, function (key, id) {
                        $('.event-item[data-id="' + id + '"]').each(function () {
                            $(this).show();
                            $(this).addClass('visible');
                            itemsFound++;
                        });
                    });
                    if (itemsFound === 0 && $('.alert-no-results-found').length === 0) {

                        self.$eventList.first().append(self.options.noResult.html);
                    }

                    if (typeof self.options['eventContainer'] !== 'undefined') {
                        $(self.options['eventContainer']).removeClass('d-none');
                    }

                    $('html, body').animate({
                        scrollTop: (self.$filterBoard.offset().top)
                    }, 500);
                }
            });
            request.fail(function (jqXHR) {
                self.hideLoadingIcon();
                window.console.log(jqXHR);
                //window.alert('Fehler: Die Anfrage konnte nicht bearbeitet werden! Überprüfen Sie die Internetverbindung.');
            });
        }

    },
    /**
     * get url param
     * @param strParam
     * @returns {*|number}
     */
    getUrlParam: function (strParam) {
        "use strict";
        var results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
        if (results === null) {
            return 0;
        }
        return results[1] || 0;
    },

    /**
     * Get session storage value
     * @param key
     * @returns {null}
     */
    sessionStorageGet: function (key) {
        "use strict";
        var self = this;

        try {
            if (typeof sessionStorage !== 'undefined') {
                var value = sessionStorage.getItem(key + '_' + self.options.filterBoardId);
                if (value === '' || value === null || value === 'undefined') {
                    return null;
                }
                return value;
            }
        } catch (e) {
            window.console.log('Session Storage is disabled or not supported for this browser.');
        }
        return null;
    },

    /**
     * Set session storage value
     * @param key
     * @param value
     * @returns {null}
     */
    sessionStorageSet: function (key, value) {
        "use strict";
        var self = this;

        try {
            if (typeof window.sessionStorage !== 'undefined') {
                sessionStorage.setItem(key + '_' + self.options.filterBoardId, value);
            }
        } catch (e) {
            window.console.log('Session Storage is disabled or not supported for this browser.');
        }
        return null;
    },


    /**
     * Reset form
     */
    resetForm: function () {
        "use strict";

        var self = this;
        self.sessionStorageSet('ctrl_organizers', JSON.stringify([]));
        self.sessionStorageSet('ctrl_search', '');
        self.sessionStorageSet('ctrl_eventType', 0);
        self.sessionStorageSet('ctrl_eventYear', 0);
        self.sessionStorageSet('ctrl_dateStart', '');

        // Reload the page
        var arrUrl = window.location.href.split("?");
        window.location.href = arrUrl[0];
    },


    /**
     * Initialize filter board
     * @param eventList
     * @param options
     */
    initialize: function (eventList, options) {
        "use strict";
        var self = this;

        self.$eventList = eventList;
        self.options = options;

        self.$filterBoard = $('#eventFilterBoard');
        self.$ctrlOrganizers = $('#ctrl_organizers');
        self.$ctrlSearch = $('#ctrl_search');
        self.$ctrlEventType = $('#ctrl_eventType');
        self.$ctrlEventYear = $('#ctrl_eventYear');
        self.$ctrlDateStart = $('#ctrl_dateStart');
        self.$ctrlDateStartHidden = $('#ctrl_dateStartHidden');
        self.$ctrlEventId = $('#ctrl_eventId');


        // Reset organizer filter and reset session storage item
        $('.reset-form').click(function (e) {
            e.preventDefault();
            self.resetForm();
        });

        // hide filter board
        $('.close-event-filter-button').click(function () {
            self.$filterBoard.closest('.mod_article').addClass('d-none');
            $('.toggle-filter-btn').removeClass('d-none');
            $('.toggle-filter-btn').addClass('d-block');
            $('html, body').animate({
                scrollTop: (self.$filterBoard.offset().bottom)
            }, 500);
        });

        // Show filter
        $('.toggle-filter-btn').click(function () {
            self.$filterBoard.closest('.mod_article').removeClass('d-none');
            $('.toggle-filter-btn').addClass('d-none');
            $('.toggle-filter-btn').removeClass('d-block');
            $('html, body').animate({
                scrollTop: (self.$filterBoard.offset().top)
            }, 500);
        });


        // You can set the filter by url query param like this:
        // https://somehost.ch/kurse.html?organizer=2
        // https://somehost.ch/kurse.html?organizer=2,3,4
        if (self.getUrlParam('organizer') !== 0) {
            var arrOrganizers = self.getUrlParam('organizer').split(",");
            // Store filter in the sessionStorage and reload page without the query string
            self.sessionStorageSet('ctrl_organizers', JSON.stringify(arrOrganizers));
            var arrUrl = window.location.href.split("?");
            window.location.replace(arrUrl[0]);
            return;
        }


        // Initialize select2 for the organizer select menu
        // https://select2.org
        var select2Options = {
            placeholder: "organisierende Gruppe"
        };
        self.$ctrlOrganizers.select2(select2Options);
        // Close dropdown on deselect
        self.$ctrlOrganizers.on('select2:unselect', function () {
            window.setTimeout(function () {
                // Hacks Hacks Hacks !!!!
                self.$ctrlOrganizers.select2('close');
            }, 100);
        });


        // Disable search field (important if using the plugin with mobile devices)
        // https://select2.org/searching
        self.$ctrlOrganizers.on('select2:opening select2:closing', function (event) {
            // Hacks!!!!!!!
            //var $searchfield = $(this).parent().find('.select2-search__field');
            //$searchfield.prop('disabled', true);
            //if($('#organizers li.select2-selection__choice').length < 1){
            //$('#organizers > span > span.selection > span > ul > li > input').removeAttr('disabled');
            //}
        });


        // Get from session storage
        if (self.sessionStorageGet('ctrl_search') !== null) {
            self.$ctrlSearch.val(self.sessionStorageGet('ctrl_search'));
        }

        // Get from session storage
        var arrSektionen = JSON.parse(self.sessionStorageGet('ctrl_organizers'));
        if (arrSektionen !== null) {
            if (arrSektionen.length) {
                self.$ctrlOrganizers.val(arrSektionen);
                self.$ctrlOrganizers.trigger('change');
            }
        }

        // Get from session storage
        if (self.sessionStorageGet('ctrl_eventType') !== null) {
            self.$ctrlEventType.val(self.sessionStorageGet('ctrl_eventType'));
        }


        // Trigger on change event
        // Redirect to selected year
        self.$ctrlEventYear.on('change', function () {
            var arrUrl = window.location.href.split("?");
            window.location.href = arrUrl[0] + '?year=' + $(this).prop('value');
        });

        // Trigger on change event
        self.$ctrlEventType.on('change', function () {
            self.queueRequest();
        });

        // Trigger on change event
        self.$ctrlOrganizers.on('change', function () {
            self.sessionStorageSet('ctrl_organizers', JSON.stringify($(this).val()));
            self.queueRequest();
        });

        // Trigger on change event
        self.$ctrlSearch.on('keyup', function () {
            self.queueRequest();
        });

        // Trigger on change event
        self.$ctrlEventId.on('keyup', function () {
            self.queueRequest();
        });


        // Datepicker
        // List events starting from a certain date
        var dateStart = self.sessionStorageGet('ctrl_dateStart');
        if (dateStart === null) {
            dateStart = self.$ctrlDateStart.val();
        }
        self.$ctrlDateStart.val(dateStart);
        self.listEventsStartingFromDate(dateStart);


        /** Set Datepicker **/
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

        $('.filter-board .input-group.date').datepicker(opt).on('changeDate', function () {
            var dateStart = self.$ctrlDateStart.val();
            self.listEventsStartingFromDate(dateStart);
            self.sessionStorageSet('ctrl_dateStart', dateStart);
        });


        // Remove options from select if there are no such events
        var arrCourseType = [];
        $('.event-item').each(function () {
            if ($(this).attr('data-event-type') !== '') {
                var $arrCourseType = $(this).attr('data-event-type').split(',');
                jQuery.each($arrCourseType, function (i, val) {
                    arrCourseType.push(val);
                });
            }
        });

        arrCourseType = jQuery.unique(arrCourseType);
        self.$ctrlEventType.find('option').each(function () {
            if ($(this).attr('value') > 0) {
                var id = $(this).attr('value');
                if (jQuery.inArray(id, arrCourseType) < 0) {
                    //$(this).prop('disabled', true);
                    // Remove option from select
                    $(this).remove();
                }
            }
        });


        // Finally reset and send request to apply settings found in the session storage
        self.resetEventList();
        self.queueRequest();
    }
};
