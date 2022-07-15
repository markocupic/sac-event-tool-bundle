"use strict";

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

document.addEventListener("DOMContentLoaded", function (event) {
    //window.FontAwesome.dom.watch();
});

class EventBlogList {
    constructor(elId, opt) {

        // Defaults
        const defaults = {
            'params': {
                'listModuleId': null,
                'apiKey': null,
                'readerModuleId': null,
                'itemIds': [],
                'perPage': 4,
                'language': 'en',
            },
        };

        // merge options and defaults
        let options = {...defaults, ...opt}

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

            created: async function created() {
                let self = this;

                self.itemIds = self.options.params.itemIds;

                let page = await self.getUrlParam('page_e' + self.options.params.listModuleId, null);
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

                // Open event blog with id 120
                // https://www.sac-pilatus.ch/home.html?show_event_blog=120
                let eventBlogId = null;
                if (false !== (eventBlogId = await self.getUrlParam('show_event_blog', false))) {
                    self.currentItemId = eventBlogId;

                    // Adjust current page
                    self.currentItemIndex = self.getCurrentItemIndex();
                    self.currentPage = self.getCurrentPage();

                    // Fetch detail page
                    self.fetchReaderContent();
                    self.fetchReaderContent();
                }

                // Set self.currentPage if user goes back/forward in the browser history
                window.onpopstate = async function (event) {
                    self.currentPage = await self.getUrlParam('page_e' + self.options.params.listModuleId, 1);
                };

            },

            watch: {
                currentPage: async function (val) {
                    let self = this;

                    // Add the current page to the url without reloading the page
                    let nextURL = await (function () {
                        if (self.currentPage < 2) {
                            return self.removeUrlParam('page_e' + self.options.params.listModuleId);
                        } else {
                            return self.setUrlParam('page_e' + self.options.params.listModuleId, self.currentPage);
                        }
                    })();

                    if (nextURL !== window.location.href) {
                        window.history.pushState({}, document.title, nextURL);
                    }

                    // Fetch items from server
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
                 * Fetch items from server
                 * Use markocupic/contao-content-api
                 */
                fetchList: function fetchList() {

                    let self = this;

                    let url = window.location.protocol + '//' + window.location.hostname + '/_mc_cc_api/' + self.options.params.apiKey + '/show?id=' + self.options.params.listModuleId
                        + '&page_e' + self.options.params.listModuleId + '=' + self.currentPage
                        + '&_locale=' + self.options.params.language
                    ;

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

                        let cssSelectorStr = elId + ' .pagination .link, ' + elId + ' .pagination .first, ' + elId + ' .pagination .last, ' + elId + ' .pagination .previous, ' + elId + ' .pagination .next';
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
                 * Use markocupic/contao-content-api
                 */
                fetchReaderContent: function fetchReaderContent() {
                    let self = this;

                    // Use referer param to generate qrcode in EventBlogReaderController
                    let referer = btoa(window.location.href);

                    let url = '/_mc_cc_api/' + self.options.params.apiKey + '/show?id=' + self.options.params.readerModuleId
                        + '&items=' + self.currentItemId
                        + '&referer=' + referer
                        + '&_locale=' + self.options.params.language
                    ;

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
                        let elModal = document.querySelector(elId + ' .modal');
                        let modal = bootstrap.Modal.getOrCreateInstance(elModal);
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
                    // GLightbox support
                    if ('undefined' !== typeof GLightbox) {
                        (function () {
                            'use strict';
                            document.querySelectorAll('a[data-lightbox]').forEach((element) => {
                                if (!!element.dataset.lightbox) {
                                    element.setAttribute('data-gallery', element.dataset.lightbox);
                                }
                            });
                            GLightbox({
                                selector: 'a[data-lightbox]'
                            });
                        })();
                    } else {
                        // Colorbox support
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
                },

                /**
                 * Get an url parameter from the search query string
                 * @param parameter
                 * @param defaultvalue
                 * @returns {Promise<string>}
                 */
                getUrlParam: function getUrlParam(parameter, defaultvalue) {

                    return new Promise(resolve => {
                        let params = new URLSearchParams(document.location.search);
                        if (params.has(parameter)) {
                            resolve(params.get(parameter));
                        } else {
                            resolve(defaultvalue);
                        }
                    });
                },

                /**
                 * Set an url parameter and return the new url
                 * @param parameter
                 * @param value
                 * @param href
                 * @returns {Promise<string>}
                 */
                setUrlParam: function setUrlParam(parameter, value, href = null) {

                    return new Promise(resolve => {
                        if (null === href) {
                            href = window.location.href;
                        }

                        let url = new URL(href);
                        let urlParams = new URLSearchParams(url.search);

                        if (urlParams.has(parameter)) {
                            urlParams.set(parameter, value);
                        } else {
                            urlParams.append(parameter, value);
                        }

                        href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                        resolve(href + (urlParams.toString() ? '?' + urlParams.toString() : ''));
                    });

                },

                /**
                 * Remove an url parameter and return the new url
                 * @param parameter
                 * @param href
                 * @returns {Promise<string>}
                 */
                removeUrlParam: async function removeUrlParam(parameter, href = null) {

                    return new Promise(resolve => {
                        if (null === href) {
                            href = window.location.href;
                        }

                        let url = new URL(href);
                        let urlParams = new URLSearchParams(url.search);

                        if (urlParams.has(parameter)) {
                            urlParams.delete(parameter)
                        }

                        href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                        resolve(href + (urlParams.toString() ? '?' + urlParams.toString() : ''));
                    });

                },

                /**
                 *
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
