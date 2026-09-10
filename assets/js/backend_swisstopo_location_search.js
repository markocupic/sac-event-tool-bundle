/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Location search for the coordinate field of tl_calendar_events.
 *
 * Clicking into the coordinate field opens a small panel underneath it. The
 * editor types a place name, picks one of the suggestions delivered by the
 * swisstopo search API and the LV95 coordinates are written into the field,
 * replacing whatever was in there before.
 */

"use strict";

document.addEventListener('DOMContentLoaded', () => {

  // Every input the panel is attached to.
  const FIELD_SELECTOR = 'input[name^="coordsCH1903"]';

  // https://api3.geo.admin.ch/services/sdiservices.html#search
  const API_URL = 'https://api3.geo.admin.ch/rest/services/api/SearchServer';
  const SPATIAL_REFERENCE = 2056;
  const LIMIT = 10;

  // Written into the coordinate field: 2'620'000, 1'200'000.
  const THOUSANDS_SEPARATOR = "'";

  // Wait for the typing to calm down before asking the API.
  const DEBOUNCE = 250;
  const MIN_CHARS = 2;

  const LABEL = {
    title: 'Ort suchen',
    placeholder: 'Ortsname, Flurname, Adresse …',
    hint: 'Ort auswählen, um die Koordinaten zu übernehmen.',
    empty: 'Keine Treffer.',
    error: 'Die Ortssuche ist momentan nicht erreichbar.',
    searching: 'Suche …',
    close: 'Schliessen',
  };

  const fields = document.querySelectorAll(FIELD_SELECTOR);

  if (!fields.length) {
    return;
  }

  injectStyles();

  fields.forEach(field => {
    if (field.readOnly || field.disabled) {
      return;
    }

    new LocationSearch(field);
  });

  /**
   * The panel of one coordinate field.
   */
  function LocationSearch(field) {
    let panel = null;
    let input = null;
    let list = null;
    let status = null;
    let timeout = null;
    let controller = null;
    let results = [];
    let activeIndex = -1;
    // Focusing the field reopens the panel - not wanted right after closing.
    let reopenBlocked = false;

    field.addEventListener('focus', open);
    field.addEventListener('click', open);

    /**
     * Opens the panel, whether the field already holds a coordinate or not.
     * Picking a location overwrites the current value.
     */
    function open() {
      if (reopenBlocked || panel) {
        return;
      }

      panel = document.createElement('div');
      panel.className = 'swisstopo-location-search';
      panel.innerHTML = '<div class="swisstopo-location-search__head">'
          + '<strong>' + escapeHtml(LABEL.title) + '</strong>'
          + '<button type="button" class="swisstopo-location-search__close" title="'
          + escapeHtml(LABEL.close) + '">&times;</button>'
          + '</div>'
          + '<input type="text" class="tl_text swisstopo-location-search__input" autocomplete="off"'
          + ' placeholder="' + escapeHtml(LABEL.placeholder) + '">'
          + '<div class="swisstopo-location-search__status">' + escapeHtml(LABEL.hint) + '</div>'
          + '<ul class="swisstopo-location-search__list"></ul>';

      field.insertAdjacentElement('afterend', panel);

      input = panel.querySelector('.swisstopo-location-search__input');
      list = panel.querySelector('.swisstopo-location-search__list');
      status = panel.querySelector('.swisstopo-location-search__status');

      panel.querySelector('.swisstopo-location-search__close').addEventListener('click', close);
      input.addEventListener('input', onInput);
      input.addEventListener('keydown', onKeyDown);

      document.addEventListener('mousedown', onDocumentMouseDown);
      document.addEventListener('keydown', onDocumentKeyDown);

      input.focus();
    }

    function close() {
      if (!panel) {
        return;
      }

      abort();
      window.clearTimeout(timeout);

      document.removeEventListener('mousedown', onDocumentMouseDown);
      document.removeEventListener('keydown', onDocumentKeyDown);

      panel.remove();

      panel = null;
      input = null;
      list = null;
      status = null;
      results = [];
      activeIndex = -1;
    }

    function onDocumentMouseDown(event) {
      if (!panel.contains(event.target) && event.target !== field) {
        close();
      }
    }

    function onDocumentKeyDown(event) {
      if ('Escape' === event.key) {
        closeAndFocus();
      }
    }

    /**
     * Closes the panel and puts the cursor back into the coordinate field,
     * without the focus opening the panel again straight away.
     */
    function closeAndFocus() {
      close();

      reopenBlocked = true;
      field.focus();

      window.setTimeout(() => {
        reopenBlocked = false;
      }, 0);
    }

    function onInput() {
      window.clearTimeout(timeout);

      const searchText = input.value.trim();

      if (searchText.length < MIN_CHARS) {
        abort();
        renderResults([]);
        setStatus(LABEL.hint);

        return;
      }

      timeout = window.setTimeout(() => search(searchText), DEBOUNCE);
    }

    function onKeyDown(event) {
      if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
        event.preventDefault();
        moveActive('ArrowDown' === event.key ? 1 : -1);

        return;
      }

      if ('Enter' === event.key) {
        // Never submit the Contao form from within the panel.
        event.preventDefault();

        if (results[activeIndex]) {
          apply(results[activeIndex]);
        }
      }
    }

    function search(searchText) {
      abort();

      controller = new AbortController();

      const url = API_URL
          + '?searchText=' + encodeURIComponent(searchText)
          + '&type=locations'
          + '&sr=' + SPATIAL_REFERENCE
          + '&limit=' + LIMIT;

      setStatus(LABEL.searching);

      fetch(url, {signal: controller.signal})
          .then(response => {
            if (!response.ok) {
              throw new Error('HTTP ' + response.status);
            }

            return response.json();
          })
          .then(json => {
            // A late answer to an outdated search is dropped.
            if (input.value.trim() !== searchText) {
              return;
            }

            renderResults(parseResults(json));
          })
          .catch(error => {
            if ('AbortError' === error.name) {
              return;
            }

            renderResults([]);
            setStatus(LABEL.error);

            console.error(error.message);
          });
    }

    function abort() {
      if (controller) {
        controller.abort();
        controller = null;
      }
    }

    function renderResults(items) {
      results = items;
      activeIndex = items.length ? 0 : -1;

      list.innerHTML = '';

      items.forEach((item, index) => {
        const entry = document.createElement('li');

        entry.className = 'swisstopo-location-search__item'
            + (index === activeIndex ? ' is-active' : '');
        entry.innerHTML = '<span class="swisstopo-location-search__label">'
            + escapeHtml(item.label) + '</span>'
            + '<span class="swisstopo-location-search__coords">'
            + escapeHtml(format(item.east) + ', ' + format(item.north)) + '</span>';

        entry.addEventListener('mousedown', event => {
          // mousedown, so the outside click does not close us first.
          event.preventDefault();
          apply(item);
        });

        entry.addEventListener('mouseenter', () => setActive(index));

        list.appendChild(entry);
      });

      if (items.length) {
        setStatus('');
      } else if (input.value.trim().length >= MIN_CHARS) {
        setStatus(LABEL.empty);
      }
    }

    function moveActive(offset) {
      if (!results.length) {
        return;
      }

      setActive((activeIndex + offset + results.length) % results.length);
      list.children[activeIndex].scrollIntoView({block: 'nearest'});
    }

    function setActive(index) {
      [...list.children].forEach((entry, i) => entry.classList.toggle('is-active', i === index));
      activeIndex = index;
    }

    function setStatus(text) {
      status.textContent = text;
      status.hidden = '' === text;
    }

    /**
     * Writes the coordinates into the field, in the format the help text of
     * the field asks for: 2'620'000, 1'200'000.
     */
    function apply(item) {
      field.value = format(item.east) + ', ' + format(item.north);

      // Let Contao (and anything else listening) know about the change.
      field.dispatchEvent(new Event('input', {bubbles: true}));
      field.dispatchEvent(new Event('change', {bubbles: true}));

      closeAndFocus();
    }
  }

  /**
   * Turns the API answer into a flat list. With sr=2056 the API delivers the
   * LV95 coordinates in attrs.y (easting) and attrs.x (northing); should that
   * ever be swapped, the values are told apart by their magnitude.
   */
  function parseResults(json) {
    const results = Array.isArray(json?.results) ? json.results : [];

    return results
        .map(result => {
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
          const east = Math.max(first, second);
          const north = Math.min(first, second);

          return {
            label: stripTags(attrs.label ?? attrs.detail ?? ''),
            east: east,
            north: north,
          };
        })
        .filter(Boolean);
  }

  /**
   * The API marks the matching part of a label with <b>, which would end up
   * as markup in the list.
   */
  function stripTags(value) {
    const element = document.createElement('div');

    element.innerHTML = String(value);

    return (element.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
  }

  /**
   * 2695353 => 2'695'353 - the format of the field and of its help text.
   */
  function format(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, THOUSANDS_SEPARATOR);
  }

  function injectStyles() {
    if (document.getElementById('swisstopoLocationSearchStyles')) {
      return;
    }

    const style = document.createElement('style');

    style.id = 'swisstopoLocationSearchStyles';
    style.textContent = `
.swisstopo-location-search {
	/* Contao provides these in both colour schemes; the fallbacks are only
	   used if the backend stylesheet ever drops them. */
	--sls-bg: var(--content-bg, #fff);
	--sls-border: var(--content-border, #ddd);
	--sls-text: var(--text, #1d1d1d);
	--sls-hover: var(--panel-bg, #eef3fb);
	--sls-shadow: rgba(0, 0, 0, .12);

	position: relative;
	margin-top: 6px;
	margin-bottom: 4px;
	padding: 10px;
	border: 1px solid var(--sls-border);
	border-radius: 3px;
	background: var(--sls-bg);
	color: var(--sls-text);
	box-shadow: 0 2px 6px var(--sls-shadow);
}
/* Fallbacks for a dark backend without the Contao variables. */
@media (prefers-color-scheme: dark) {
	html:not([data-color-scheme="light"]) .swisstopo-location-search {
		--sls-bg: var(--content-bg, #23262b);
		--sls-border: var(--content-border, #3a3f47);
		--sls-text: var(--text, #e6e6e6);
		--sls-hover: var(--panel-bg, #2f343c);
		--sls-shadow: rgba(0, 0, 0, .5);
	}
}
html[data-color-scheme="dark"] .swisstopo-location-search {
	--sls-bg: var(--content-bg, #23262b);
	--sls-border: var(--content-border, #3a3f47);
	--sls-text: var(--text, #e6e6e6);
	--sls-hover: var(--panel-bg, #2f343c);
	--sls-shadow: rgba(0, 0, 0, .5);
}
.swisstopo-location-search__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 6px;
	font-size: 12px;
	color: var(--sls-text);
}
.swisstopo-location-search__close {
	padding: 0 4px;
	border: 0;
	background: none;
	color: var(--sls-text);
	font-size: 18px;
	line-height: 1;
	cursor: pointer;
	opacity: .6;
}
.swisstopo-location-search__close:hover {
	opacity: 1;
}
.swisstopo-location-search__input {
	width: 100%;
	box-sizing: border-box;
}
.swisstopo-location-search__status {
	margin-top: 6px;
	color: var(--sls-text);
	font-size: 11px;
	opacity: .7;
}
.swisstopo-location-search__list {
	margin: 6px 0 0;
	padding: 0;
	max-height: 260px;
	overflow-y: auto;
	list-style: none;
}
.swisstopo-location-search__item {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 12px;
	padding: 5px 6px;
	border-radius: 2px;
	color: var(--sls-text);
	font-size: 12px;
	cursor: pointer;
}
.swisstopo-location-search__item.is-active {
	background: var(--sls-hover);
}
.swisstopo-location-search__coords {
	flex: 0 0 auto;
	color: var(--sls-text);
	font-size: 11px;
	white-space: nowrap;
	opacity: .7;
}
`;

    document.head.appendChild(style);
  }
});
