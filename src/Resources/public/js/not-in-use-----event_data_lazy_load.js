/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

(function ($) {
    /**
     * Lazyload fields in eventlists after domready
     */
    $(document).ready(function () {
        let arrDataXhr = [];
        let rt = jQuery('[data-request-token]').first().data('request-token');
        if (typeof rt === 'undefined') {
            console.log('Request token is missing.');
        }
        let i = 0;
        let loadNumberOfItemsAtOnce = 100;
        let lazyLoadItems = jQuery('[data-event-lazyload]');
        if (lazyLoadItems.length) {
            jQuery(lazyLoadItems).each(function () {
                i++;
                let arrData = jQuery(this).data('event-lazyload').split(',');
                arrDataXhr.push(arrData);
                if (i % loadNumberOfItemsAtOnce === 0 || i === lazyLoadItems.length) {
                    loadNumberOfItemsAtOnce = lazyLoadItems.length - loadNumberOfItemsAtOnce + 1;
                    let arrXHR = arrDataXhr;
                    arrDataXhr = [];
                    if (arrXHR.length) {
                        jQuery.post('ajaxEventLazyLoad/getEventData', {
                            'REQUEST_TOKEN': rt,
                            'data': JSON.stringify(arrXHR),
                        }).done(function (json) {
                            if (json.status === 'success') {
                                jQuery.each(json.data, function (key, value) {
                                    let element = jQuery('[data-event-lazyload="' + value[0] + ',' + value[1] + '"').first();
                                    jQuery(element).append(value[2]);
                                });
                            }
                        }).always(function () {
                            //
                        });
                    }
                }
            });
        }
    });
})(jQuery);
