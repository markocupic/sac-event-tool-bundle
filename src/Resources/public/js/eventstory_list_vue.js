"use strict";

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
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
                $(elId).on('hidden.bs.modal', '.modal', function () {
                    self.currentItemId = null;
                    self.readerContent = '';
                });
            },

            watch: {
                currentPage: function (val) {
                    let self = this;
                    self.fetchList();
                },
                currentItemId: function (val) {
                    let self = this;

                    // currentItemId === null do not change page
                    if(self.currentItemId !== null)
                    {
                        // Adjust current page
                        self.currentItemIndex = self.itemIds.indexOf(parseInt(self.currentItemId));
                        self.currentPage = Math.floor(parseInt(self.currentItemIndex) / parseInt(self.options.params.perPage)) + 1;

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
                    let url = '/api/module?id=' + self.options.params.readerModuleId + '&items=' + self.currentItemId;

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
