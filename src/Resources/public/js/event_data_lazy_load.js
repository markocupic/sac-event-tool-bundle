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
        var rt = $('[data-request-token]').first().data('request-token');
        $('[data-event-lazyload]').each(function () {
            var arrData = $(this).data('event-lazyload').split(',');
            var eventId = arrData[0];
            var eventField = arrData[1];
            arrDataXhr.push(arrData);
        });

        if (arrDataXhr.length) {
            var jqxhr = $.post('ajax', {
                'REQUEST_TOKEN': rt,
                'action': 'getEventData',
                'data': JSON.stringify(arrDataXhr),
            }).done(function (json) {
                if (json.status === 'success') {
                    jQuery.each(json.data, function (key, value) {
                        var element = $('[data-event-lazyload="' + value[0] + ',' + value[1] + '"').first();
                        $(element).append(value[2]);
                    });
                }
            }).always(function () {
                //window.location.reload();
            });
        }
    });
})(jQuery);
