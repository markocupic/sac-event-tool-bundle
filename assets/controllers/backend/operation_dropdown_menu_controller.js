/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

import {Controller} from '@hotwired/stimulus';

/**
 * Operations dropdown of an event (small screens), see the template
 * Backend/EventOperations/operations_menu.html.twig. Used by the "My events"
 * dashboard and the back end event search.
 *
 * To avoid overflow-hidden issues, the menu is cloned into the ".dropdown-portal"
 * of the surrounding ".sacevt-op-menu-item" when it is opened. The clone is removed again when the
 * menu is closed, before Turbo caches the page, when the page is restored from
 * the browser's back/forward cache and when the controller disconnects.
 */
export default class extends Controller {
    static targets = ['toggle', 'menu'];

    connect() {
        this.clone = null;

        // Keep references: the targets are not reliably available in disconnect()
        this.toggleEl = this.toggleTarget;
        this.menuEl = this.menuTarget;
        this.portal = this.element.closest('.sacevt-op-menu-item')?.querySelector('.dropdown-portal') ?? null;

        this.onToggleClick = this.toggle.bind(this);
        this.onDocumentClick = this.#onDocumentClick.bind(this);
        this.onDocumentKeydown = this.#onDocumentKeydown.bind(this);
        this.onOtherMenuOpen = this.#onOtherMenuOpen.bind(this);
        this.onPageShow = this.#onPageShow.bind(this);
        this.close = this.close.bind(this);

        this.toggleEl.addEventListener('click', this.onToggleClick);

        document.addEventListener('click', this.onDocumentClick);
        document.addEventListener('keydown', this.onDocumentKeydown);
        document.addEventListener(this.#openEventName, this.onOtherMenuOpen);

        // Contao 5.7+/6 (Turbo): never put an open menu into the page cache
        document.addEventListener('turbo:before-cache', this.close);

        // Page restored from the browser's back/forward cache
        window.addEventListener('pageshow', this.onPageShow);
        window.addEventListener('resize', this.close);
    }

    disconnect() {
        this.toggleEl.removeEventListener('click', this.onToggleClick);

        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onDocumentKeydown);
        document.removeEventListener(this.#openEventName, this.onOtherMenuOpen);
        document.removeEventListener('turbo:before-cache', this.close);

        window.removeEventListener('pageshow', this.onPageShow);
        window.removeEventListener('resize', this.close);

        this.close();
    }

    get isOpen() {
        return !!this.clone?.isConnected;
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        const portal = this.portal;

        if (!portal) {
            return;
        }

        // Close the other open operation menus
        this.dispatch('open', {target: document, detail: {controller: this}});

        const rect = this.toggleEl.getBoundingClientRect();

        // Clone the menu and inject it into the portal to avoid overflow-hidden issues
        const clone = this.menuEl.cloneNode(true);
        clone.removeAttribute(`data-${this.identifier}-target`);
        clone.classList.add('show');
        clone.style.position = 'absolute';
        clone.style.top = `${rect.bottom + window.scrollY - 40}px`;
        clone.style.visibility = 'hidden';
        clone.style.zIndex = '2';

        portal.replaceChildren(clone);
        this.clone = clone;

        requestAnimationFrame(() => {
            if (this.clone !== clone) {
                return;
            }

            clone.style.left = `${rect.right - clone.offsetWidth + window.scrollX}px`;
            clone.style.visibility = 'visible';
        });

        this.toggleEl.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.clone?.remove();
        this.clone = null;

        // Clean up a stale clone, e.g. from a restored page snapshot
        this.portal?.replaceChildren();

        this.menuEl?.classList.remove('show');
        this.toggleEl?.setAttribute('aria-expanded', 'false');
    }

    #onDocumentClick(event) {
        // The toggle button handles its own clicks
        if (this.toggleEl.contains(event.target)) {
            return;
        }

        if (this.clone?.contains(event.target)) {
            // Clicks on a link are not stopped, so that Turbo can handle them. The
            // menu is closed on "turbo:before-cache" (or "pageshow").
            return;
        }

        if (this.isOpen) {
            this.close();
        }
    }

    #onDocumentKeydown(event) {
        if ('Escape' === event.key && this.isOpen) {
            this.close();
            this.toggleEl.focus();
        }
    }

    #onOtherMenuOpen(event) {
        if (event.detail?.controller !== this && this.isOpen) {
            this.close();
        }
    }

    #onPageShow(event) {
        if (event.persisted) {
            this.close();
        }
    }

    get #openEventName() {
        return `${this.identifier}:open`;
    }
}
