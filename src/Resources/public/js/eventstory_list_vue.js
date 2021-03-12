"use strict";

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

class ItemWatcher {
    constructor(elId, opt) {

        // Defaults
        const defaults = {
            'params': {
                'listModuleId': null,
                'readerModuleId': null,
                'itemIds': [],
                'perPage': 4,
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
                itemIds: [],
                currentPage: null,
                currentItemIndex: null,
                currentItemId: null,
            },

            created: function created() {
                let self = this;

                self.itemIds = self.options.params.itemIds;

                let page = self.getUrlParam('page_e' + self.options.params.listModuleId, null);
                self.currentPage = page === null ? 1 : parseInt(page);

                document.onkeydown = (function (e) {
                    self._checkKeyPress(e);
                });

                // Handle modal
                window.setTimeout(function () {
                    let modal = document.querySelector(elId + ' .modal');
                    if (modal) {
                        modal.addEventListener('hidden.bs.modal', function (event) {
                            self.currentItemId = null;
                            self.readerContent = '';
                        });
                    }
                }, 1000);


                // Open event story with id 120
                // https://www.sac-pilatus.ch/home.html?showEventStory=120
                let eventStoryId = null;
                if (false !== (eventStoryId = self.getUrlParam('showEventStory', false))) {
                    self.currentItemId = eventStoryId;

                    // Adjust current page
                    self.currentItemIndex = self.getCurrentItemIndex();
                    self.currentPage = self.getCurrentPage();

                    // Fetch detail page
                    self.fetchReaderContent();
                    self.fetchReaderContent();
                }
            },

            watch: {
                currentPage: function (val) {
                    let self = this;
                    self.fetchList();
                },
                currentItemId: function (val) {
                    let self = this;

                    // currentItemId === null do not change page
                    if (self.currentItemId !== null) {
                        // Adjust current page
                        self.currentItemIndex = self.getCurrentItemIndex();
                        self.currentPage = self.getCurrentPage();

                        // Fetch detail page
                        self.fetchReaderContent();
                    }
                },

            },

            methods: {

                /**
                 * Fetch list content
                 * Use dieschittigs/contao-content-api-bundle
                 */
                fetchList: function fetchList() {

                    let self = this;
                    let url = '/api/module?id=' + self.options.params.listModuleId + '&page_e' + self.options.params.listModuleId + '=' + self.currentPage;

                    fetch(url, {

                            method: "GET",
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        }
                    ).then(function (res) {
                        return res.json();
                    }).then(function (json) {
                        $(elId + ' .list-container').css('opacity', 0);
                        self.listContent = json.compiledHTML;
                        $(elId + ' .list-container').fadeTo('slow', 1);
                    }).then(function () {
                        // trigger same height for item boxes
                        // see: vendor\markocupic\contao-theme-sac-pilatus\src\Resources\contao\files\theme-sac-pilatus\js\theme.js
                        $(window).trigger('vueupdate');

                        let cssSelectorStr = elId + ' .pagination .link,.pagination .first,.pagination .last,.pagination .previous,.pagination .next';
                        $(cssSelectorStr).off("click");
                        $(cssSelectorStr).click(function (e) {
                            e.stopPropagation();
                            e.preventDefault();
                            let href = $(this).prop('href');
                            let regexp = new RegExp("page_e" + self.options.params.listModuleId + "=([\\d]+)");
                            let match = regexp.exec(href);
                            let page = match ? match[1] : 1;

                            self.currentPage = parseInt(page);
                        });
                    }).then(function () {
                        let cssSelectorStr = elId + ' a.item-reader-link';
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

                            // Fetch reader content
                            itemId = parseInt(itemId);
                            if (itemId === self.currentItemId) {
                                self.fetchReaderContent();
                                return;
                            }

                            self.currentItemId = parseInt(itemId);
                        });
                    });
                },

                /**
                 * Fetch reader/detail content
                 * Use dieschittigs/contao-content-api-bundle
                 */
                fetchReaderContent: function fetchReaderContent() {
                    let self = this;

                    // Use referer param to generate qrcode in EventStoryReaderController
                    let referer = btoa(window.location.href);

                    let url = '/api/module?id=' + self.options.params.readerModuleId + '&items=' + self.currentItemId + '&referer=' + referer;

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
                        let modal = new bootstrap.Modal(document.querySelector(elId + ' .modal'));
                        if (!self.isModalOpen()) {
                            modal.show();
                        }
                    }).then(function () {
                        self._initLightbox();
                    });
                },

                /**
                 * Check for the text item
                 * @returns {boolean}
                 */
                hasNextItem: function hasNextItem() {
                    let self = this;
                    if (typeof self.itemIds[self.currentItemIndex + 1] !== 'undefined') {
                        return true;
                    }
                    return false;
                },

                /**
                 * Set current ItemId
                 * The watcher will do the rest...
                 */
                goToNextItem: function goToNextItem() {
                    let self = this;
                    self.currentItemId = parseInt(self.itemIds[self.currentItemIndex + 1]);
                },

                /**
                 * Check for the prev item
                 * @returns {boolean}
                 */
                hasPrevItem: function hasPrevItem() {
                    let self = this;
                    if (typeof self.itemIds[self.currentItemIndex - 1] !== 'undefined') {
                        return true;
                    }
                    return false;
                },

                getCurrentItemIndex: function getCurrentItemIndex() {
                    let self = this;
                    return self.itemIds.indexOf(parseInt(self.currentItemId));
                },

                getCurrentPage: function getCurrentPage() {
                    let self = this;
                    return Math.floor(parseInt(self.currentItemIndex) / parseInt(self.options.params.perPage)) + 1;
                },

                /**
                 * Set current ItemId
                 * The watcher will do the rest...
                 */
                goToPrevItem: function goToPrevItem() {
                    let self = this;
                    // Fetch reader content
                    self.currentItemId = parseInt(self.itemIds[self.currentItemIndex - 1]);
                },

                /**
                 * Init Lightbox
                 * @private
                 */
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
                },

                /**
                 * Get url param
                 * @param parameter
                 * @param defaultvalue
                 * @returns {*}
                 * @private
                 */
                getUrlParam: function getUrlParam(parameter, defaultvalue) {
                    let self = this;
                    var urlparameter = defaultvalue;
                    if (window.location.href.indexOf(parameter) > -1) {
                        urlparameter = self._getUrlVars()[parameter];
                    }
                    return urlparameter;
                },

                /**
                 * Helper method for self.getUrlParam
                 * @private
                 */
                _getUrlVars: function _getUrlVars() {
                    var vars = {};
                    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
                        vars[key] = value;
                    });
                    return vars;
                },

                /**
                 * Check key press
                 * @param e
                 * @private
                 */
                _checkKeyPress: function _checkKeyPress(e) {

                    let self = this;
                    e = e || window.event;

                    // Left arrow
                    if (e.keyCode == '37') {
                        if (self.isModalOpen() && self.hasPrevItem()) {
                            self.goToPrevItem();
                        }
                    }
                    // Right arrow
                    else if (e.keyCode == '39') {
                        if (self.isModalOpen() && self.hasNextItem()) {
                            self.goToNextItem();
                        }
                    }

                },

                /**
                 * Check if modal is open
                 * @returns {boolean}
                 */
                isModalOpen: function isModalOpen() {
                    if (document.querySelector(elId + ' .modal.show')) {
                        return true;
                    }
                    return false;
                }
            }
        });
    }
}
