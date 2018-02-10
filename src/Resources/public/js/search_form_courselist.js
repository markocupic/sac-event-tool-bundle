/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

//Provides methods for filtering the kursliste
var SacCourseFilter = {


    /**
     * globalEventId
     * This is used in self.queueRequest
     */
    globalEventId: 0,

    /**
     * time to wait before launching the xhr request, when making changes to the filter form
     */
    delay: 1000,

    /**
     * FilterBoard Id is used for the session storrage
     */
    filterBoardId: null,

    /**
     * Contao request token for ajax calls
     */
    requestToken: null,

    /**
     * The FontAwesome object
     * https://fontawesome.com/how-to-use/font-awesome-api
     */
    objFontAwesome: null,

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
    $ctrlCourseTypeLevel1: null,

    /**
     * The date start input
     */
    $ctrlDateStart: null,

    /**
     * Hidden date input
     */
    $ctrlDateStartHidden: null,


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
        }, self.delay);
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

        // Add loading icon
        $('.loading-icon-lg').remove();
        // See https://fontawesome.com/how-to-use/font-awesome-api#icon
        var iconDefinition = self.objFontAwesome.findIconDefinition({prefix: 'fal', iconName: 'circle-notch'});
        var icon = self.objFontAwesome.icon(iconDefinition, {
            classes: ['fa-spin', 'fa-3x']
        }).html;
        $('.mod_eventToolCalendarEventlist').append('<div class="loading-icon-lg"><div>' + icon + '</div></div>');
    },

    /**
     * Hide the loading icon
     */
    hideLoadingIcon: function () {
        "use strict";

        // Add loading icon
        $('.loading-icon-lg').remove();
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

        // Kursart
        var idKursart = self.$ctrlCourseTypeLevel1.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_courseTypeLevel1', idKursart);

        // Sektionen
        var arrOrganizers = self.$ctrlOrganizers.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_organizers', JSON.stringify(arrOrganizers));

        // StartDate
        var dateStartHidden = self.$ctrlDateStartHidden;
        var intStartDate = Math.round(dateStartHidden.val()) > 0 ? dateStartHidden.val() : 0;
        intStartDate = Math.round(intStartDate);

        // Textsuche
        var strSearchterm = self.$ctrlSearch.val();
        // Save Input to sessionStorage
        self.sessionStorageSet('ctrl_search', strSearchterm);


        var url = 'ajax';
        var request = $.ajax({
            method: 'post',
            url: url,
            data: {
                action: 'filterCourseList',
                year: self.getUrlParam('year'),
                REQUEST_TOKEN: self.requestToken,
                ids: JSON.stringify(arrIds),
                courseType: idKursart,
                organizers: JSON.stringify(arrOrganizers),
                searchterm: strSearchterm,
                startDate: intStartDate
            },
            dataType: 'json'
        });
        request.done(function (json) {
            if (json) {
                self.hideLoadingIcon();
                $.each(json.filter, function (key, id) {
                    $('.event-item[data-id="' + id + '"]').each(function () {
                        //intFound++;
                        $(this).show();
                        $(this).addClass('visible');
                        itemsFound++;
                    });
                });
                if (itemsFound === 0 && $('.alert-no-results-found').length === 0) {

                    $('.mod_eventToolCalendarEventlist').append('<div class="alert alert-primary alert-no-results-found text-lg" role="alert"><h5><i class="fal fa-meh" aria-hidden="true"></i> Leider wurden zu deiner Suchanfrage keine Events gefunden. &Uuml;berp&uuml;fe bitte die Filtereinstellungen.</h5></div>');
                }
            }
        });
        request.fail(function (jqXHR) {
            self.hideLoadingIcon();
            window.console.log(jqXHR);
            window.alert('Fehler: Die Anfrage konnte nicht bearbeitet werden! Überprüfen Sie die Internetverbindung.');
        });
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
     * Get session storrage value
     * @param key
     * @returns {null}
     */
    sessionStorageGet: function (key) {
        "use strict";
        var self = this;

        try {
            if (typeof sessionStorage !== 'undefined') {
                var value = sessionStorage.getItem(key + '_' + self.filterBoardId);
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
     * Set session storrage value
     * @param key
     * @param value
     * @returns {null}
     */
    sessionStorageSet: function (key, value) {
        "use strict";
        var self = this;

        try {
            if (typeof window.sessionStorage !== 'undefined') {
                sessionStorage.setItem(key + '_' + self.filterBoardId, value);
            }
        } catch (e) {
            window.console.log('Session Storage is disabled or not supported for this browser.');
        }
        return null;
    },


    /**
     * Initialize filter board
     * @param filterBoardId
     * @param requestToken
     * @param FontAwesome
     */
    initialize: function (filterBoardId, requestToken, FontAwesome) {
        "use strict";
        var self = this;

        self.filterBoardId = filterBoardId;
        self.requestToken = requestToken;
        self.objFontAwesome = FontAwesome;

        self.$ctrlOrganizers = $('#ctrl_organizers');
        self.$ctrlSearch = $('#ctrl_search');
        self.$ctrlCourseTypeLevel1 = $('#ctrl_courseTypeLevel1');
        self.$ctrlEventYear = $('#ctrl_eventYear');
        self.$ctrlDateStart = $('#ctrl_dateStart');
        self.$ctrlDateStartHidden = $('#ctrl_dateStartHidden');


        // Check if the request token is set in the template
        if (!self.requestToken) {
            window.alert('Please set the request-token in the template');
        }

        // Check if the request token is set in the template
        if (!self.filterBoardId) {
            window.alert('Please set the filterBoardId in the template');
        }


        // Initialize select2 for the organizer select menu
        // https://select2.org
        self.$ctrlOrganizers.select2();
        self.$ctrlOrganizers.on('select2:unselect', function () {
            self.$ctrlOrganizers.select2('destroy').select2();
        });

        // Trigger on change event and update session storrage item
        self.$ctrlOrganizers.on('change', function () {
            self.sessionStorageSet('ctrl_organizers', JSON.stringify($(this).val()));
            self.queueRequest();
        });

        // Reset organizer filter and reset session storrage item
        $('.reset-select-organizer').click(function (e) {
            e.preventDefault();
            self.$ctrlOrganizers.val([]);
            self.$ctrlOrganizers.trigger('change');
            self.sessionStorageSet('ctrl_organizers', JSON.stringify([]));
            self.queueRequest();
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


        // filter List if there are some values in the browser's sessionStorage
        var blnFilterList = false;
        if (self.sessionStorageGet('ctrl_search') !== null) {
            self.$ctrlSearch.val(self.sessionStorageGet('ctrl_search'));
            blnFilterList = true;
        }
        var arrSektionen = JSON.parse(self.sessionStorageGet('ctrl_organizers'));
        if (arrSektionen !== null) {
            if (arrSektionen.length) {
                self.$ctrlOrganizers.val(arrSektionen);
                self.$ctrlOrganizers.trigger('change');
                blnFilterList = true;
            }
        }

        if (self.sessionStorageGet('ctrl_courseTypeLevel1') !== null) {
            self.$ctrlCourseTypeLevel1.val(self.sessionStorageGet('ctrl_courseTypeLevel1'));
            blnFilterList = true;
        }


        // Filter list, if there are some filter stored in the sessionStorage
        if (blnFilterList) {
            self.resetEventList();
            self.queueRequest();
        }


        /** Trigger Filter **/
        // Redirect to selected year
        self.$ctrlEventYear.on('change', function () {
            var arrUrl = window.location.href.split("?");
            window.location.href = arrUrl[0] + '?year=' + $(this).prop('value');
        });

        self.$ctrlCourseTypeLevel1.on('change', function () {
            self.queueRequest();
        });

        self.$ctrlSearch.on('keyup', function () {
            self.queueRequest();
        });


        // List events starting from a certain date
        var dateStart = self.$ctrlDateStart.val();
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
        }

        $('.filter-board .input-group.date').datepicker(opt).on('changeDate', function () {
            var dateStart = self.$ctrlDateStart.val();
            self.listEventsStartingFromDate(dateStart);
        });


        // Entferne die Suchoptionen im Select-Menu, wenn ohnehin keine Events dazu existieren
        var arrCourseType = [];
        $('.event-item').each(function () {
            if ($(this).attr('data-courseTypeLevel1') !== '') {
                var $arrCourseType = $(this).attr('data-courseTypeLevel1').split(',');
                jQuery.each($arrCourseType, function (i, val) {
                    arrCourseType.push(val);
                });
            }
        });

        arrCourseType = jQuery.unique(arrCourseType);
        self.$ctrlCourseTypeLevel1.find('option').each(function () {
            if ($(this).attr('value') > 0) {
                var id = $(this).attr('value');
                if (jQuery.inArray(id, arrCourseType) < 0) {
                    $(this).remove();
                }
            }
        });
    }
};
