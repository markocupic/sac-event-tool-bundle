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
 * Autocompletes the member data in the back end (tl_calendar_events_member and tl_user)
 * after entering a SAC member number.
 *
 * The controller element is rendered by the "wizard" callback of the sacMemberId field:
 * <span hidden data-controller="sacevt--backend--member-autocomplete"></span>
 *
 * Works with Contao 5.3 (Chosen, MooTools) and Contao 5.7+ (Choices, Turbo).
 */
export default class extends Controller {
    static values = {
        minLength: {type: Number, default: 6},
        delay: {type: Number, default: 400},
    };

    static FIELDS = ['gender', 'name', 'username', 'firstname', 'lastname', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'dateOfBirth', 'foodHabits', 'emergencyPhone', 'emergencyPhoneName', 'sectionId'];

    connect() {
        this.input = this.element.closest('form')?.querySelector('input[name="sacMemberId"]');

        // Do not run if the input is missing or read only (e.g. synchronized SAC members)
        if (!this.input || this.input.readOnly) {
            this.input = null;
            return;
        }

        this.timeout = null;
        this.onKeyup = this.#onKeyup.bind(this);
        this.input.addEventListener('keyup', this.onKeyup);
    }

    disconnect() {
        clearTimeout(this.timeout);
        this.input?.removeEventListener('keyup', this.onKeyup);
        this.#removeBox();
    }

    #onKeyup() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.#lookup(this.input.value.trim()), this.delayValue);
    }

    async #lookup(sacMemberId) {
        if (sacMemberId.length < this.minLengthValue) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'autocompleterLoadMemberDataFromSacMemberId');
        formData.append('sacMemberId', sacMemberId);
        formData.append('REQUEST_TOKEN', window.Contao?.request_token ?? '');

        let json;

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });

            if (!response.ok) {
                return;
            }

            json = await response.json();
        } catch (error) {
            console.error(error);
            return;
        }

        // Ignore outdated responses (the user kept typing)
        if (json?.status !== 'success' || parseInt(json.sacMemberId, 10) !== parseInt(this.input.value.trim(), 10)) {
            return;
        }

        this.#showBox(sacMemberId, json);
    }

    #showBox(sacMemberId, json) {
        this.#removeBox();

        const box = document.createElement('div');
        box.id = 'acceptAutocompleteBox';

        const info = document.createElement('p');
        info.className = 'autocompleteInfo';
        info.append('In der Datenbank wurde zur Mitgliednummer ');
        info.append(Object.assign(document.createElement('strong'), {textContent: sacMemberId}));
        info.append(' folgendes Mitglied gefunden:');

        const member = document.createElement('p');
        member.className = 'autocompleteInfo';
        member.append(Object.assign(document.createElement('strong'), {
            textContent: `${json.firstname ?? ''} ${json.lastname ?? ''}, ${json.street ?? ''}, ${json.postal ?? ''} ${json.city ?? ''}`,
        }));

        const btnAccept = this.#createButton('Übernehmen', () => {
            this.#fillForm(json);
            this.#removeBox();
        });

        const btnRefuse = this.#createButton('Nein', () => this.#removeBox());

        box.append(info, member, btnAccept, ' ', btnRefuse);

        this.input.before(box);
        this.input.style.display = 'none';
        this.box = box;
    }

    #createButton(label, callback) {
        const button = document.createElement('button');

        // type="button": do not submit the edit form
        button.type = 'button';
        button.className = 'tl_submit autocompleteBtn';
        button.textContent = label;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            callback();
        });

        return button;
    }

    #removeBox() {
        this.box?.remove();
        this.box = null;
        this.input?.style.removeProperty('display');
    }

    async #fillForm(json) {
        for (const fieldName of this.constructor.FIELDS) {
            const field = document.getElementById(`ctrl_${fieldName}`);

            if (!field || !(fieldName in json) || null === json[fieldName]) {
                continue;
            }

            if (field instanceof HTMLSelectElement) {
                const values = Array.isArray(json[fieldName]) ? json[fieldName] : Object.values(json[fieldName] ?? {});
                await this.#updateSelect(field, field.multiple ? values : [json[fieldName]]);
            } else {
                field.value = json[fieldName];
                field.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }
    }

    async #updateSelect(select, values) {
        const selected = values.map(String);
        const id = select.id;

        // Contao 5.7+: the select is enhanced by the Stimulus "contao--choices" controller.
        // Disconnect it, update the native select and connect it again, so that
        // Choices is rebuilt from the current selection.
        const wrapper = select.closest('[data-controller~="contao--choices"]');

        if (wrapper) {
            this.#toggleControllerToken(wrapper, 'contao--choices', false);
            await new Promise((resolve) => requestAnimationFrame(resolve));
            select = document.getElementById(id) ?? select;
        }

        for (const option of select.options) {
            option.selected = selected.includes(option.value);
        }

        select.dispatchEvent(new Event('change', {bubbles: true}));

        if (wrapper) {
            this.#toggleControllerToken(wrapper, 'contao--choices', true);
            return;
        }

        // Contao 5.3: Chosen (MooTools) has to be notified via its own event
        if (select.classList.contains('tl_chosen') && 'function' === typeof select.fireEvent) {
            select.fireEvent('liszt:updated');
        }
    }

    #toggleControllerToken(element, identifier, add) {
        const tokens = (element.dataset.controller ?? '').split(/\s+/).filter((token) => token && token !== identifier);

        if (add) {
            tokens.push(identifier);
        }

        element.dataset.controller = tokens.join(' ');
    }
}
