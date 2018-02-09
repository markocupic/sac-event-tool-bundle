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
     * queueRequest
     */
    queueRequest: function () {
        SacCourseFilter.showLoadingIcon();
        SacCourseFilter.resetEventList();
        SacCourseFilter.globalEventId++;
        var eventId = SacCourseFilter.globalEventId;
        window.setTimeout(function () {
            if (eventId == SacCourseFilter.globalEventId) {
                SacCourseFilter.fireXHR();
            }
        }, SacCourseFilter.delay);
    },

    /**
     * Reset/Remove List
     */
    resetEventList: function () {
        $('.alert-no-results-found').remove();
        $('.event-item-course').each(function () {
            $(this).hide();
            $(this).removeClass('visible');
        });
    },


    /**
     * Show the loading icon
     */
    showLoadingIcon: function () {
        // Add loading icon
        $('.loading-icon-lg').remove();
        // See https://fontawesome.com/how-to-use/font-awesome-api#icon
        var iconDefinition = FontAwesome.findIconDefinition({prefix: 'fal', iconName: 'circle-notch'});
        var icon = FontAwesome.icon(iconDefinition, {
            classes: ['fa-spin', 'fa-3x']
        }).html;
        $('.mod_eventToolCalendarEventlist').append('<div class="loading-icon-lg"><div>' + icon + '</div></div>');
    },

    /**
     * Hide the loading icon
     */
    hideLoadingIcon: function () {
        // Add loading icon
        $('.loading-icon-lg').remove();
    },

    /**
     * List events starting from a certain date
     * @param dateStart
     */
    listEventsStartingFromDate: function (dateStart) {
        var regex = /^(.*)-(.*)-(.*)$/g;
        var match = regex.exec(dateStart);
        if (match) {
            // JavaScript counts months from 0 to 11. January is 0. December is 11.
            var date = new Date(match[3], match[2] - 1, match[1]);
            var tstamp = Math.round(date.getTime() / 1000);
            if (!isNaN(tstamp)) {
                $('#ctrl_dateStartHidden').val(tstamp);
                $('#ctrl_dateStartHidden').attr('value', tstamp);
                SacCourseFilter.queueRequest();
                return;
            }
        }
        $('#ctrl_dateStartHidden').attr('value', '0');
        $('#ctrl_dateStartHidden').val('0');
        SacCourseFilter.queueRequest();
    },


    /**
     * filterRequest
     */
    fireXHR: function () {
        var itemsFound = 0;

        // Event-items
        var arrIds = [];
        $('.event-item-course').each(function () {
            arrIds.push($(this).attr('data-id'));
        });

        // Kursart
        var idKursart = $('#ctrl_courseTypeLevel1').val();
        // Save Input to sessionStorage
        try {
            sessionStorage.setItem('ctrl_courseTypeLevel1_' + modEventFilterListId, idKursart);
        }
        catch (e) {
            console.log('Session Storage is disabled or not supported on this browser.')
        }

        // Sektionen
        var arrOrganizers = [];
        $('.ctrl_organizers:checked').each(function () {
            arrOrganizers.push(this.value);
        });

        try {
            // Save Input to sessionStorage
            sessionStorage.setItem('ctrl_organizers_' + modEventFilterListId, JSON.stringify(arrOrganizers));
        }
        catch (e) {
            console.log('Session Storage is disabled or not supported on this browser.')
        }

        // StartDate
        var intStartDate = Math.round($('#ctrl_dateStartHidden').val()) > 0 ? $('#ctrl_dateStartHidden').val() : 0;
        intStartDate = Math.round(intStartDate);

        // Textsuche
        var strSearchterm = $('#ctrl_search').val();
        // Save Input to sessionStorage
        try {
            sessionStorage.setItem('ctrl_search_' + modEventFilterListId, strSearchterm);
        }
        catch (e) {
            console.log('Session Storage is disabled or not supported on this browser.')
        }
        var url = 'ajax';
        var request = $.ajax({
            method: 'post',
            url: url,
            data: {
                action: 'filterCourseList',
                year: SacCourseFilter.getUrlParam('year'),
                REQUEST_TOKEN: request_token,
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
                SacCourseFilter.hideLoadingIcon();
                $.each(json.filter, function (key, id) {
                    $('.event-item-course[data-id="' + id + '"]').each(function () {
                        //intFound++;
                        $(this).show();
                        $(this).addClass('visible');
                        itemsFound++;
                    });
                });
                if (itemsFound == 0 && $('.alert-no-results-found').length == 0) {

                    $('.mod_eventToolCalendarEventlist').append('<div class="alert alert-danger alert-no-results-found text-lg" role="alert"><h4><i class="fal fa-meh" aria-hidden="true"></i> Leider wurden zu deiner Suchanfrage keine Events gefunden. &Uuml;berp&uuml;fe bitte die Filtereinstellungen.</h4></div>');
                }
            }
        });
        request.fail(function (jqXHR, textStatus, errorThrown) {
            SacCourseFilter.hideLoadingIcon();
            console.log(jqXHR);
            alert('Fehler: Die Anfrage konnte nicht bearbeitet werden! Überprüfe Sie die Internetverbindung.');
        });
    },
    /**
     * get url param
     * @param strParam
     * @returns {*|number}
     */
    getUrlParam: function (strParam) {
        var results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
        if (results === null) return 0;
        return results[1] || 0;
    }

}


$().ready(function () {

    if ($('.filter-board[data-event-type="course"]').length < 1) {
        // Add a valid filter board
        return;
    }
    // Check if the request token is set in the template
    if (!request_token) {
        alert('Please set the request-token in the template');
    }

    // Check if the request token is set in the template
    if (!modEventFilterListId) {
        alert('Please set the modEventFilterListId in the template');
    }


    // You can set the filter by url query param like this:
    // https://somehost.ch/kurse.html?organizer=2
    // https://somehost.ch/kurse.html?organizer=2,3,4
    try {
        if (typeof window.sessionStorage !== 'undefined') {
            if (SacCourseFilter.getUrlParam('organizer') !== 0) {
                var arrOrganizers = SacCourseFilter.getUrlParam('organizer').split(",");
                // Store filter in the sessionStorrage and reload page without the query string
                sessionStorage.setItem('ctrl_organizers_' + modEventFilterListId, JSON.stringify(arrOrganizers));
                var arrUrl = window.location.href.split("?");
                window.location.href = arrUrl[0];
                return;
            }
        }
    }catch (e) {
        console.log('Session Storage is disabled or not supported for this browser.');
    }



    // filter List if there are some values in the browser's sessionStorage
    try {
        if (typeof window.sessionStorage !== 'undefined') {
            var blnFilterList = false;
            if (sessionStorage.getItem('ctrl_search_' + modEventFilterListId) !== null) {
                $('#ctrl_search').val(sessionStorage.getItem('ctrl_search_' + modEventFilterListId));
                blnFilterList = true;
            }

            if (sessionStorage.getItem('ctrl_organizers_' + modEventFilterListId) !== null) {
                var arrSektionen = JSON.parse(sessionStorage.getItem('ctrl_organizers_' + modEventFilterListId));
                if (arrSektionen.length) {
                    $('.ctrl_organizers').each(function () {
                        if (jQuery.inArray($(this).attr('value'), arrSektionen) > -1) {
                            $(this).prop('checked', true);
                        } else {
                            $(this).prop('checked', false);
                        }
                    });
                    blnFilterList = true;
                }
            }

            if (sessionStorage.getItem('ctrl_courseTypeLevel1_' + modEventFilterListId) !== null) {
                $('#ctrl_courseTypeLevel1').val(sessionStorage.getItem('ctrl_courseTypeLevel1_' + modEventFilterListId));
                blnFilterList = true;
            }


            // Filter list, if there are some filter stored in the sessionStorage
            if (blnFilterList) {
                SacCourseFilter.resetEventList();
                SacCourseFilter.queueRequest();
            }
        }
    }
    catch (e) {
        console.log('Session Storage is disabled or not supported for this browser.');
    }



    // Select all or unselect all organizers
    $('.select-organizer-all').click(function (e) {
        e.preventDefault();
        var toggler = this;
        var arrOrganizers = [];

        var i = 0;
        var check = true;
        $('.ctrl_organizers').each(function () {
            if ($(this).prop('checked')) {
                i++;
            }
            arrOrganizers.push($(this).prop('value'));
        });
        if (i === $('.ctrl_organizers').length) {
            check = false;
        } else if (i === 0) {
            check = true;
        }

        if (check === true) {
            $('.ctrl_organizers').each(function () {
                $(this).prop('checked', true);
            });
        } else {
            $('.ctrl_organizers').each(function () {
                $(this).prop('checked', false);
            });
        }

        $('.ctrl_organizers').each(function () {
            if (check === true) {
                $(this).prop('checked', true);
                sessionStorage.setItem('ctrl_organizers_' + modEventFilterListId, JSON.stringify(arrOrganizers));
            } else {
                $(this).prop('checked', false);
                sessionStorage.removeItem('ctrl_organizers_' + modEventFilterListId);
            }
        });
        SacCourseFilter.queueRequest();
        return false;
    });


    /** Trigger Filter **/
    // Redirect to selected year
    $('#ctrl_eventYear').on('change', function () {
        var arrUrl = window.location.href.split("?");
        window.location.href = arrUrl[0] + '?year=' + $(this).prop('value');
    });

    $('#ctrl_courseTypeLevel1, .ctrl_organizers').on('change', function () {
        SacCourseFilter.queueRequest();
    });

    $('#ctrl_search').on('keyup', function () {
        SacCourseFilter.queueRequest();
    });

    $('#organizers input').on('ifClicked', function (event) {
        SacCourseFilter.queueRequest();
    });

    // List events starting from a certain date
    var dateStart = $('#ctrl_dateStart').val();
    SacCourseFilter.listEventsStartingFromDate(dateStart);


    /** Set Datepicker **/
    var opt = {
        format: "dd-mm-yyyy",
        autoclose: true,
        maxViewMode: 0,
        language: "de"
    };
    // Set datepickers start and end date
    if (SacCourseFilter.getUrlParam('year') > 0) {
        opt['startDate'] = "01-01-" + SacCourseFilter.getUrlParam('year');
        opt['endDate'] = "31-12-" + SacCourseFilter.getUrlParam('year');
    } else {
        var now = new Date();
        //opt['startDate'] = "01-01-" + now.getFullYear();
        //opt['endDate'] = "31-12-" + now.getFullYear();
    }

    $('.filter-board .input-group.date').datepicker(opt).on('changeDate', function (e) {
        var dateStart = $('#ctrl_dateStart').val();
        SacCourseFilter.listEventsStartingFromDate(dateStart);
    });


    // Entferne die Suchoptionen im Select-Menu, wenn ohnehin keine Events dazu existieren
    var arrCourseType = [];
    $('.event-item-course').each(function () {
        if ($(this).attr('data-courseTypeLevel1') !== '') {
            var $arrCourseType = $(this).attr('data-courseTypeLevel1').split(',');
            jQuery.each($arrCourseType, function (i, val) {
                arrCourseType.push(val);
            });
        }
    });
    arrCourseType = jQuery.unique(arrCourseType);
    $('#ctrl_courseTypeLevel1 option').each(function () {
        if ($(this).attr('value') > 0) {
            var id = $(this).attr('value');
            if (jQuery.inArray(id, arrCourseType) < 0) {
                $(this).remove();
            }
        }
    });
});





