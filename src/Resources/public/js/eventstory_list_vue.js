"use strict";

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */
class EventStory {
    constructor(elId, opt) {

        // Defaults
        const defaults = {
            'params': {
                'listModuleId': null,
                'readerModuleId': null,
            },
        };

        // Use lodash to merge options and defaults
        let options = _.merge(defaults, opt);

        // Instantiate vue.js application
        new Vue({
            el: elId,
            data: {
                options: options,
                listContent: '',
                readerContent: '',
            },

            created: function created() {
                let self = this;


                self.prepareRequest('/api/module?id=' + self.options.params.listModuleId);
            },

            methods: {
                // Prepare ajax request
                prepareRequest: function prepareRequest(url) {
                    this.fetchList(url);
                },

                /**
                 * Fetch list content
                 * Use dieschittigs/contao-content-api-bundle
                 */
                fetchList: function fetchList(url) {

                    let self = this;

                    fetch(url, {

                            method: "GET",
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        }
                    ).then(function (res) {
                        return res.json();
                    }).then(function (json) {
                        self.listContent = json.compiledHTML;
                    }).then(function () {
                        // trigger same height for item boxes
                        // see: vendor\markocupic\contao-theme-sac-pilatus\src\Resources\contao\files\theme-sac-pilatus\js\theme.js
                        $(window).trigger('vueupdate');

                        let cssSelectorStr = elId + ' .pagination .link,.pagination .first,.pagination .last,.pagination .previous,.pagination .next';
                        $(cssSelectorStr).off("click");
                        $(cssSelectorStr).click(function (e) {
                            e.stopPropagation();
                            e.preventDefault();

                            self.fetchList($(this).prop('href'));
                        });
                    }).then(function () {
                        let cssSelectorStr = elId + ' a.event-reader-link';
                        $(cssSelectorStr).off("click");
                        $(cssSelectorStr).click(function (e) {
                            e.stopPropagation();
                            e.preventDefault();

                            // Get the item id from href
                            let href = e.currentTarget.getAttribute('href');
                            let regex = /^(.*)(\/)([\d]+)/i;
                            let match = regex.exec(href);

                            if (match.length < 4) {
                                console.log('Aborted! Could not load content. No item id found.');
                                return;
                            }
                            let itemId = match[3];

                            if (!options.params.readerModuleId) {
                                console.log('Aborted! Could not load content. No reader module id found.');
                                return;
                            }

                            let url = '/api/module?id=' + self.options.params.readerModuleId + '&items=' + itemId;

                            // Fetch reader content
                            self.fetchReaderContent(url);
                        });
                    });
                },

                /**
                 * Fetch reader/detail content
                 * Use dieschittigs/contao-content-api-bundle
                 * @param url
                 */
                fetchReaderContent: function fetchReaderContent(url) {
                    let self = this;
                    fetch(url, {
                            method: "GET",
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        }
                    ).then(function (res) {
                        return res.json();
                    }).then(function (json) {
                        self.readerContent = json.compiledHTML;
                    }).then(function () {
                        $(elId + ' .modal').first().modal();
                    }).then(function () {
                        self._initLightbox();
                    });
                },
                _initLightbox: function _initLightbox() {
                    jQuery(function ($) {
                        $('a[data-lightbox]').map(function () {
                            $(this).colorbox({
                                // Put custom options here
                                loop: false,
                                rel: $(this).attr('data-lightbox'),
                                maxWidth: '95%',
                                maxHeight: '95%'
                            });
                        });
                    });
                }
            }
        });
    }
}
