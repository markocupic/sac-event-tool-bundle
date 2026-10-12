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
 * Field selection helper for the "editAll" and "overrideAll" mode in the back end:
 * the checked fields can be saved in the user's session ("Save") and restored
 * later ("Get").
 *
 * The markup (be_edit_all_navbar_helper.html.twig) is added to the page by the
 * BackendAssetsSubscriber, only on the field selection page.
 */
export default class extends Controller {
    static FIELD_SELECTOR = '.tl_checkbox_container input[name="all_fields[]"]';

    async load() {
        const json = await this.#post({subaction: 'getSessionData'});

        if (json?.status !== 'success') {
            return;
        }

        const itemsChecked = this.#parseItems(json.itemsChecked);

        // First uncheck all checkboxes, then check the ones from the session
        for (const checkbox of document.querySelectorAll(`#check_all, ${this.constructor.FIELD_SELECTOR}`)) {
            checkbox.checked = false;
        }

        for (const id of itemsChecked) {
            const checkbox = document.getElementById(id);

            if (checkbox) {
                checkbox.checked = true;
            }
        }
    }

    async save() {
        const checkedItems = [...document.querySelectorAll(this.constructor.FIELD_SELECTOR)]
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.id);

        await this.#post({subaction: 'saveSessionData', checkedItems: JSON.stringify(checkedItems)});
    }

    async #post(data) {
        const formData = new FormData();
        formData.append('action', 'editAllNavbarHandler');
        formData.append('REQUEST_TOKEN', window.Contao?.request_token ?? '');

        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });

            return response.ok ? await response.json() : null;
        } catch (error) {
            console.error(error);

            return null;
        }
    }

    /**
     * The session holds a JSON string (or an empty array if nothing was saved yet).
     */
    #parseItems(items) {
        if (Array.isArray(items)) {
            return items;
        }

        try {
            const parsed = JSON.parse(items || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
}
