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
 * Disables the submit button(s) of a form while it is being submitted, so that
 * e.g. an email is not sent twice.
 *
 * <div data-controller="sacevt--backend--prevent-double-submit">…form…</div>
 *
 * Works with and without Turbo (Contao 5.3, 5.7+): the buttons are enabled again
 * if Turbo reports a failed submission (e.g. validation errors), before Turbo
 * caches the page, if the page is restored from the browser's back/forward cache
 * and when the controller disconnects.
 */
export default class extends Controller {
    connect() {
        this.onSubmit = this.#onSubmit.bind(this);
        this.onSubmitEnd = this.#onSubmitEnd.bind(this);
        this.onPageShow = this.#onPageShow.bind(this);
        this.enable = this.enable.bind(this);

        this.element.addEventListener('submit', this.onSubmit);
        this.element.addEventListener('turbo:submit-end', this.onSubmitEnd);
        document.addEventListener('turbo:before-cache', this.enable);
        window.addEventListener('pageshow', this.onPageShow);
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
        this.element.removeEventListener('turbo:submit-end', this.onSubmitEnd);
        document.removeEventListener('turbo:before-cache', this.enable);
        window.removeEventListener('pageshow', this.onPageShow);

        this.enable();
    }

    enable() {
        for (const button of this.#getButtons()) {
            if (button.dataset.preventDoubleSubmitDisabled) {
                button.disabled = false;
                delete button.dataset.preventDoubleSubmitDisabled;
            }
        }
    }

    #onSubmit(event) {
        // Another listener may have cancelled the submission
        if (event.defaultPrevented) {
            return;
        }

        // Disable the buttons after the submit event, so that the form data
        // (incl. the submitter) is collected first.
        setTimeout(() => {
            for (const button of this.#getButtons()) {
                if (!button.disabled) {
                    button.disabled = true;
                    button.dataset.preventDoubleSubmitDisabled = '1';
                }
            }
        }, 0);
    }

    #onSubmitEnd(event) {
        if (!event.detail?.success) {
            this.enable();
        }
    }

    #onPageShow(event) {
        if (event.persisted) {
            this.enable();
        }
    }

    #getButtons() {
        return this.element.querySelectorAll('button[type="submit"], input[type="submit"]');
    }
}
