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
 * Turns a text input into a token input (e.g. for a comma separated list of email addresses).
 *
 * Used by the Stimulus controller "sacevt--backend--text-input-tokenizer".
 * The styles live in assets/text_input_tokenizer/css/style.css.
 */
export default class TextInputTokenizer {
    /**
     * @param {HTMLInputElement} element - the input element
     * @param {Object} options - Configuration options
     * @param {string[]} options.suggestions - Array of suggestions
     * @param {Function} options.validator - Custom validation function (value) => boolean
     * @param {Function} options.onTokenAdd - Callback when token is added (value, isValid) => void
     * @param {Function} options.onTokenRemove - Callback when token is removed (value) => void
     */
    constructor(element, options = {}) {
        this.input = element;

        if (!this.input) {
            console.error('TextInputTokenizer: Input element not found');
            return;
        }

        this.options = {
            suggestions: options.suggestions || [],
            validator: options.validator || null,
            onTokenAdd: options.onTokenAdd || null,
            onTokenRemove: options.onTokenRemove || null,
        };

        this.wrapper = null;
        this.tokenContainer = null;
        this.suggestBox = null;
        this.form = null;

        this.onClick = () => this.showSuggest();
        this.onInput = this.#onInput.bind(this);
        this.onKeydown = this.#onKeydown.bind(this);
        this.onSubmit = this.#onSubmit.bind(this);
        this.onDocumentClick = this.#onDocumentClick.bind(this);

        this.init();
    }

    init() {
        this.input.classList.add('text-input-tokenizer');
        this.createWrapper();
        this.createSuggestBox();
        this.initializeTokensFromInput();
        this.attachEventListeners();
    }

    createWrapper() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'tokenizer';

        this.tokenContainer = document.createElement('div');
        this.tokenContainer.className = 'tokens';

        this.input.parentNode.insertBefore(this.wrapper, this.input);
        this.wrapper.appendChild(this.tokenContainer);
        this.wrapper.appendChild(this.input);
    }

    createSuggestBox() {
        this.suggestBox = document.createElement('div');
        this.suggestBox.className = 'suggest-box';
        this.suggestBox.style.display = 'none';
        this.wrapper.appendChild(this.suggestBox);
    }

    initializeTokensFromInput() {
        const raw = this.input.value.trim();

        if (!raw) {
            return;
        }

        raw.split(',')
            .map((v) => v.trim())
            .filter((v) => v.length > 0)
            .forEach((value) => this.addToken(value, false));

        this.input.value = '';
    }

    getTokens() {
        return Array.from(this.tokenContainer.querySelectorAll('.token')).map((t) => t.dataset.value);
    }

    addToken(value, focus = true) {
        value = (value ?? '').trim();

        if (!value || this.getTokens().includes(value)) {
            return;
        }

        const token = document.createElement('span');
        token.className = 'token';
        token.dataset.value = value;
        token.textContent = value;

        const isValid = this.options.validator ? this.options.validator(value) : true;

        if (!isValid) {
            token.classList.add('invalid');
        }

        const remove = document.createElement('span');
        remove.className = 'remove';
        remove.setAttribute('role', 'button');
        remove.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg"><path d="M5 5L19 19M5 19L19 5" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        remove.addEventListener('click', () => {
            token.remove();
            this.options.onTokenRemove?.(value);
        });

        token.appendChild(remove);
        this.tokenContainer.appendChild(token);

        this.input.value = '';

        if (focus) {
            this.input.focus();
        }

        this.options.onTokenAdd?.(value, isValid);
    }

    removeToken(value) {
        this.tokenContainer.querySelectorAll('.token').forEach((token) => {
            if (token.dataset.value === value) {
                token.remove();
            }
        });
    }

    getRemainingSuggestions(query = '') {
        const selected = this.getTokens();

        return this.options.suggestions
            .filter((s) => !selected.includes(s) && s.toLowerCase().includes(query.toLowerCase()))
            .slice(0, 20);
    }

    showSuggest() {
        const query = this.input.value.trim().toLowerCase();
        const remaining = this.getRemainingSuggestions(query);

        if (remaining.length === 0) {
            this.hideSuggest();
            return;
        }

        this.suggestBox.replaceChildren();

        remaining.forEach((suggestion) => {
            const item = document.createElement('div');
            item.textContent = suggestion;
            item.addEventListener('click', () => {
                this.addToken(suggestion);
                this.hideSuggest();
            });
            this.suggestBox.appendChild(item);
        });

        this.suggestBox.style.display = 'block';
    }

    hideSuggest() {
        this.suggestBox.style.display = 'none';
    }

    attachEventListeners() {
        this.input.addEventListener('click', this.onClick);
        this.input.addEventListener('input', this.onInput);
        this.input.addEventListener('keydown', this.onKeydown);

        // Write the tokens back into the input before the form is submitted
        this.form = this.input.closest('form');
        this.form?.addEventListener('submit', this.onSubmit);

        // Close the suggest box on outside-click
        document.addEventListener('click', this.onDocumentClick);
    }

    #onInput() {
        const val = this.input.value.trim();

        // Add the token if the user enters a comma, a semicolon or whitespace
        if (/[,;\s]$/.test(this.input.value) && !/^[,;\s]*$/.test(this.input.value)) {
            const value = val.replace(/[,;]$/, '').trim();

            if (value.length > 0) {
                this.addToken(value);
            }

            this.input.value = '';
            return;
        }

        this.showSuggest();
    }

    #onKeydown(e) {
        if ('Enter' === e.key || 'Space' === e.code) {
            e.preventDefault();
            this.addToken(this.input.value);
            this.input.value = '';
            this.hideSuggest();
        }
    }

    #onSubmit() {
        this.input.classList.add('submitting');
        window.setTimeout(() => this.input.classList.remove('submitting'), 5000);

        this.addToken(this.input.value, false);
        this.input.value = this.getTokens().join(', ');
    }

    #onDocumentClick(e) {
        if (!this.wrapper.contains(e.target)) {
            this.addToken(this.input.value, false);
            this.hideSuggest();
        }
    }

    /**
     * Update suggestions dynamically
     * @param {string[]} newSuggestions
     */
    setSuggestions(newSuggestions) {
        this.options.suggestions = newSuggestions;
    }

    /**
     * @returns {string[]}
     */
    getValues() {
        return this.getTokens();
    }

    clear() {
        this.tokenContainer.replaceChildren();
    }

    /**
     * Remove the tokenizer and write the tokens back into the input, so that the
     * value survives e.g. the Turbo page cache.
     */
    destroy() {
        if (!this.wrapper) {
            return;
        }

        // Tokens plus whatever is still in the input (it may already hold the tokens after a submit)
        const values = [...new Set([...this.getTokens(), ...this.input.value.split(',').map((v) => v.trim())].filter(Boolean))];

        this.input.removeEventListener('click', this.onClick);
        this.input.removeEventListener('input', this.onInput);
        this.input.removeEventListener('keydown', this.onKeydown);
        this.form?.removeEventListener('submit', this.onSubmit);
        document.removeEventListener('click', this.onDocumentClick);

        this.wrapper.parentNode?.insertBefore(this.input, this.wrapper);
        this.wrapper.remove();
        this.wrapper = null;

        this.input.classList.remove('text-input-tokenizer', 'submitting');
        this.input.value = values.join(', ');
    }
}
