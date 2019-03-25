/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
(function ($) {
    $(document).ready(function () {
        var arrDataXhr = [];
        var rt = jQuery('[data-request-token]').first().data('request-token');
        var i=0;
        jQuery('[data-event-lazyload]').each(function () {
            i++;
            var arrData = jQuery(this).data('event-lazyload').split(',');
            var eventId = arrData[0];
            var eventField = arrData[1];
            arrDataXhr.push(arrData);
            if(i%100 == 0){
                var arrXHR = arrDataXhr;
                arrDataXhr = [];
                if (arrXHR.length) {
                    var jqxhr = jQuery.post('ajax', {
                        'REQUEST_TOKEN': rt,
                        'action': 'getEventData',
                        'data': JSON.stringify(arrXHR),
                    }).done(function (json) {
                        if (json.status === 'success') {
                            jQuery.each(json.data, function (key, value) {
                                var element = jQuery('[data-event-lazyload="' + value[0] + ',' + value[1] + '"').first();
                                jQuery(element).append(value[2]);
                            });
                        }
                        console.log('lazyload' + i);
                    }).always(function () {
                        //window.location.reload();
                    });
                }
            }
        });
    });
})(jQuery);
