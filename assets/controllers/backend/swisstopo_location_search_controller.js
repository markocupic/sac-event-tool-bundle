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
 * Location search for the coordinate field of tl_calendar_events (coordsCH1903).
 *
 * Clicking into the coordinate field opens a small panel underneath it. The
 * editor types a place name, picks one of the suggestions delivered by the
 * swisstopo search API and the LV95 coordinates are written into the field,
 * replacing whatever was in there before.
 *
 * The controller element is rendered by the "wizard" callback of the field:
 * <span hidden data-controller="sacevt--backend--swisstopo-location-search"></span>
 *
 * The styles live in assets/styles/backend/scss/components/_swisstopo_location_search.scss.
 */
export default class extends Controller {
    // https://api3.geo.admin.ch/services/sdiservices.html#search
    static API_URL = 'https://api3.geo.admin.ch/rest/services/api/SearchServer';
    static SPATIAL_REFERENCE = 2056;
    static LIMIT = 10;

    // Written into the coordinate field: 2'620'000, 1'200'000.
    static THOUSANDS_SEPARATOR = "'";

    // Wait for the typing to calm down before asking the API.
    static DEBOUNCE = 250;
    static MIN_CHARS = 2;

    static LABEL = {
        title: 'Ort suchen',
        placeholder: 'Ortsname, Flurname, Adresse …',
        hint: 'Ort auswählen, um die Koordinaten zu übernehmen.',
        empty: 'Keine Treffer.',
        error: 'Die Ortssuche ist momentan nicht erreichbar.',
        searching: 'Suche …',
        close: 'Schliessen',
    };

    connect() {
        // The wizard is rendered directly after the input (also in the editAll mode)
        this.field = this.element.closest('.widget')?.querySelector('input[name^="coordsCH1903"]');

        if (!this.field || this.field.readOnly || this.field.disabled) {
            this.field = null;
            return;
        }

        this.panel = null;
        this.results = [];
        this.activeIndex = -1;
        this.timeout = null;
        this.abortController = null;

        // Focusing the field reopens the panel - not wanted right after closing.
        this.reopenBlocked = false;

        this.open = this.open.bind(this);
        this.onDocumentMouseDown = this.onDocumentMouseDown.bind(this);
        this.onDocumentKeyDown = this.onDocumentKeyDown.bind(this);

        this.field.addEventListener('focus', this.open);
        this.field.addEventListener('click', this.open);
    }

    disconnect() {
        this.close();
        this.field?.removeEventListener('focus', this.open);
        this.field?.removeEventListener('click', this.open);
    }

    /**
     * Opens the panel, whether the field already holds a coordinate or not.
     * Picking a location overwrites the current value.
     */
    open() {
        if (this.reopenBlocked || this.panel) {
            return;
        }

        const label = this.constructor.LABEL;

        const panel = document.createElement('div');
        panel.className = 'swisstopo-location-search';

        const head = document.createElement('div');
        head.className = 'swisstopo-location-search__head';
        head.append(Object.assign(document.createElement('strong'), {textContent: label.title}));

        const closeButton = Object.assign(document.createElement('button'), {
            type: 'button',
            className: 'swisstopo-location-search__close',
            title: label.close,
            textContent: '×',
        });
        closeButton.addEventListener('click', () => this.close());
        head.append(closeButton);

        this.input = Object.assign(document.createElement('input'), {
            type: 'text',
            className: 'tl_text swisstopo-location-search__input',
            autocomplete: 'off',
            placeholder: label.placeholder,
        });
        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('keydown', (event) => this.onKeyDown(event));

        this.status = Object.assign(document.createElement('div'), {className: 'swisstopo-location-search__status'});
        this.list = Object.assign(document.createElement('ul'), {className: 'swisstopo-location-search__list'});

        panel.append(head, this.input, this.status, this.list);
        this.setStatus(label.hint);

        this.field.insertAdjacentElement('afterend', panel);
        this.panel = panel;

        document.addEventListener('mousedown', this.onDocumentMouseDown);
        document.addEventListener('keydown', this.onDocumentKeyDown);

        this.input.focus();
    }

    close() {
        if (!this.panel) {
            return;
        }

        this.abort();
        clearTimeout(this.timeout);

        document.removeEventListener('mousedown', this.onDocumentMouseDown);
        document.removeEventListener('keydown', this.onDocumentKeyDown);

        this.panel.remove();

        this.panel = null;
        this.input = null;
        this.list = null;
        this.status = null;
        this.results = [];
        this.activeIndex = -1;
    }

    onDocumentMouseDown(event) {
        if (this.panel && !this.panel.contains(event.target) && event.target !== this.field) {
            this.close();
        }
    }

    onDocumentKeyDown(event) {
        if ('Escape' === event.key) {
            this.closeAndFocus();
        }
    }

    /**
     * Closes the panel and puts the cursor back into the coordinate field,
     * without the focus opening the panel again straight away.
     */
    closeAndFocus() {
        this.close();

        this.reopenBlocked = true;
        this.field.focus();

        setTimeout(() => {
            this.reopenBlocked = false;
        }, 0);
    }

    onInput() {
        clearTimeout(this.timeout);

        const searchText = this.input.value.trim();

        if (searchText.length < this.constructor.MIN_CHARS) {
            this.abort();
            this.renderResults([]);
            this.setStatus(this.constructor.LABEL.hint);

            return;
        }

        this.timeout = setTimeout(() => this.search(searchText), this.constructor.DEBOUNCE);
    }

    onKeyDown(event) {
        if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
            event.preventDefault();
            this.moveActive('ArrowDown' === event.key ? 1 : -1);

            return;
        }

        if ('Enter' === event.key) {
            // Never submit the Contao form from within the panel.
            event.preventDefault();

            if (this.results[this.activeIndex]) {
                this.apply(this.results[this.activeIndex]);
            }
        }
    }

    async search(searchText) {
        this.abort();

        const abortController = new AbortController();
        this.abortController = abortController;

        const url = new URL(this.constructor.API_URL);
        url.searchParams.set('searchText', searchText);
        url.searchParams.set('type', 'locations');
        url.searchParams.set('sr', String(this.constructor.SPATIAL_REFERENCE));
        url.searchParams.set('limit', String(this.constructor.LIMIT));

        this.setStatus(this.constructor.LABEL.searching);

        try {
            const response = await fetch(url, {signal: abortController.signal});

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const json = await response.json();

            // A late answer to an outdated search is dropped.
            if (!this.input || this.input.value.trim() !== searchText) {
                return;
            }

            this.renderResults(this.constructor.parseResults(json));
        } catch (error) {
            if ('AbortError' === error.name || !this.panel) {
                return;
            }

            this.renderResults([]);
            this.setStatus(this.constructor.LABEL.error);

            console.error(error.message);
        }
    }

    abort() {
        this.abortController?.abort();
        this.abortController = null;
    }

    renderResults(items) {
        this.results = items;
        this.activeIndex = items.length ? 0 : -1;

        this.list.replaceChildren();

        items.forEach((item, index) => {
            const entry = document.createElement('li');
            entry.className = 'swisstopo-location-search__item' + (index === this.activeIndex ? ' is-active' : '');

            entry.append(
                Object.assign(document.createElement('span'), {className: 'swisstopo-location-search__label', textContent: item.label}),
                Object.assign(document.createElement('span'), {className: 'swisstopo-location-search__coords', textContent: this.constructor.format(item.east) + ', ' + this.constructor.format(item.north)}),
            );

            entry.addEventListener('mousedown', (event) => {
                // mousedown, so the outside click does not close us first.
                event.preventDefault();
                this.apply(item);
            });

            entry.addEventListener('mouseenter', () => this.setActive(index));

            this.list.appendChild(entry);
        });

        if (items.length) {
            this.setStatus('');
        } else if (this.input.value.trim().length >= this.constructor.MIN_CHARS) {
            this.setStatus(this.constructor.LABEL.empty);
        }
    }

    moveActive(offset) {
        if (!this.results.length) {
            return;
        }

        this.setActive((this.activeIndex + offset + this.results.length) % this.results.length);
        this.list.children[this.activeIndex].scrollIntoView({block: 'nearest'});
    }

    setActive(index) {
        [...this.list.children].forEach((entry, i) => entry.classList.toggle('is-active', i === index));
        this.activeIndex = index;
    }

    setStatus(text) {
        this.status.textContent = text;
        this.status.hidden = '' === text;
    }

    /**
     * Writes the coordinates into the field, in the format the help text of
     * the field asks for: 2'620'000, 1'200'000.
     */
    apply(item) {
        this.field.value = this.constructor.format(item.east) + ', ' + this.constructor.format(item.north);

        // Let Contao (and anything else listening) know about the change.
        this.field.dispatchEvent(new Event('input', {bubbles: true}));
        this.field.dispatchEvent(new Event('change', {bubbles: true}));

        this.closeAndFocus();
    }

    /**
     * Turns the API answer into a flat list. With sr=2056 the API delivers the
     * LV95 coordinates in attrs.y (easting) and attrs.x (northing); should that
     * ever be swapped, the values are told apart by their magnitude.
     */
    static parseResults(json) {
        const results = Array.isArray(json?.results) ? json.results : [];

        return results
            .map((result) => {
                const attrs = result?.attrs;

                if (!attrs) {
                    return null;
                }

                const first = Math.round(Number(attrs.y));
                const second = Math.round(Number(attrs.x));

                if (!Number.isFinite(first) || !Number.isFinite(second)) {
                    return null;
                }

                // The easting is the larger one (2 million vs 1 million).
                return {
                    label: this.stripTags(attrs.label ?? attrs.detail ?? ''),
                    east: Math.max(first, second),
                    north: Math.min(first, second),
                };
            })
            .filter(Boolean);
    }

    /**
     * The API marks the matching part of a label with <b>, which would end up
     * as markup in the list.
     */
    static stripTags(value) {
        const doc = new DOMParser().parseFromString(String(value), 'text/html');

        return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * 2695353 => 2'695'353 - the format of the field and of its help text.
     */
    static format(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, this.THOUSANDS_SEPARATOR);
    }
}
