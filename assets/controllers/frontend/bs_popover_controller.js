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
import {Popover} from 'bootstrap';

// Only one popover is open at a time
let currentPopover = null;

/**
 * Bootstrap popover with a close button. Clicking outside or pressing ESC closes it.
 *
 * <span data-controller="sacevt--frontend--bs-popover" role="button"
 *       data-bs-title="Title[br]Second line" data-bs-content="Content">…</span>
 *
 * "[br]" in the title or content is turned into a line break.
 */
export default class extends Controller {
    static values = {
        placement: {type: String, default: 'top'},
        container: {type: String, default: '.tour-difficulties'},
    };

    static CLOSE_BUTTON = '<button type="button" class="btn-close position-absolute top-0 end-0 mt-1 me-1" aria-label="Close"></button>';

    connect() {
        this.onShow = this.#onShow.bind(this);
        this.onShown = this.#onShown.bind(this);
        this.onHidden = this.#onHidden.bind(this);
        this.onDocumentClick = this.#onDocumentClick.bind(this);
        this.onDocumentKeydown = this.#onDocumentKeydown.bind(this);

        this.popover = new Popover(this.element, {
            container: this.element.closest(this.containerValue) ?? 'body',
            html: true,
            sanitize: true,
            allowList: {...Popover.Default.allowList, button: ['type', 'aria-label']},
            placement: this.placementValue,
            trigger: 'click',
            title: () => this.#format(this.element.dataset.bsTitle) + this.constructor.CLOSE_BUTTON,
            content: () => this.#format(this.element.dataset.bsContent),
        });

        this.element.addEventListener('show.bs.popover', this.onShow);
        this.element.addEventListener('shown.bs.popover', this.onShown);
        this.element.addEventListener('hidden.bs.popover', this.onHidden);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.popover', this.onShow);
        this.element.removeEventListener('shown.bs.popover', this.onShown);
        this.element.removeEventListener('hidden.bs.popover', this.onHidden);
        this.#removeDocumentListeners();

        if (currentPopover === this.popover) {
            currentPopover = null;
        }

        this.popover?.dispose();
        this.popover = null;
    }

    #onShow() {
        if (currentPopover && currentPopover !== this.popover) {
            currentPopover.hide();
        }

        currentPopover = this.popover;
    }

    #onShown() {
        this.#getTip()?.querySelector('.btn-close')?.addEventListener('click', () => this.popover?.hide(), {once: true});

        // Add the listeners with a small delay to avoid immediate triggering
        setTimeout(() => {
            document.addEventListener('click', this.onDocumentClick);
            document.addEventListener('keydown', this.onDocumentKeydown);
        }, 0);
    }

    #onHidden() {
        this.#removeDocumentListeners();

        if (currentPopover === this.popover) {
            currentPopover = null;
        }
    }

    #onDocumentClick(event) {
        const tip = this.#getTip();

        if (!this.element.contains(event.target) && !tip?.contains(event.target)) {
            this.popover?.hide();
        }
    }

    #onDocumentKeydown(event) {
        if ('Escape' === event.key) {
            this.popover?.hide();
        }
    }

    #removeDocumentListeners() {
        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onDocumentKeydown);
    }

    #getTip() {
        const id = this.element.getAttribute('aria-describedby');

        return id ? document.getElementById(id) : null;
    }

    /**
     * Escape the text (it is plain text from the database) and turn [br] into <br>.
     */
    #format(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';

        return div.innerHTML.replaceAll('[br]', '<br>');
    }
}
