class TextInputTokenizer {
    /**
     * @param {Element} element - the input element
     * @param {Object} options - Configuration options
     * @param {string[]} options.suggestions - Array of suggestions
     * @param {Function} options.validator - Custom validation function (value) => boolean
     * @param {Function} options.onTokenAdd - Callback when token is added (value, isValid) => void
     * @param {Function} options.onTokenRemove - Callback when token is removed (value) => void
     */
    constructor(element, options = {}) {
        this.input = element;

        if (!this.input) {
            console.error(`TextInputTokenizer: Input element not found`);
            return;
        }

        this.options = {
            suggestions: options.suggestions || [],
            validator: options.validator || null,
            onTokenAdd: options.onTokenAdd || null,
            onTokenRemove: options.onTokenRemove || null
        };

        this.wrapper = null;
        this.tokenContainer = null;
        this.suggestBox = null;

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
        if (!raw) return;

        raw.split(',')
            .map(v => v.trim())
            .filter(v => v.length > 0)
            .forEach(value => this.addToken(value));

        this.input.value = '';
    }

    getTokens() {
        return Array.from(this.tokenContainer.querySelectorAll('.token'))
            .map(t => t.dataset.value);
    }

    addToken(value) {
        value = value.trim();
        if (!value) return;
        if (this.getTokens().includes(value)) return;

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
            if (this.options.onTokenRemove) {
                this.options.onTokenRemove(value);
            }
        });

        token.appendChild(remove);
        this.tokenContainer.appendChild(token);

        this.input.value = '';
        this.input.focus();

        if (this.options.onTokenAdd) {
            this.options.onTokenAdd(value, isValid);
        }
    }

    removeToken(value) {
        const tokens = this.tokenContainer.querySelectorAll('.token');
        tokens.forEach(token => {
            if (token.dataset.value === value) {
                token.remove();
            }
        });
    }

    getRemainingSuggestions(query = '') {
        const selected = this.getTokens();
        return this.options.suggestions.filter(s =>
            !selected.includes(s) &&
            s.toLowerCase().includes(query.toLowerCase())
        ).slice(0, 20);
    }

    showSuggest() {
        const query = this.input.value.trim().toLowerCase();
        const remaining = this.getRemainingSuggestions(query);

        if (remaining.length === 0) {
            this.suggestBox.style.display = 'none';
            return;
        }

        this.suggestBox.innerHTML = '';
        remaining.forEach(suggestion => {
            const item = document.createElement('div');
            item.textContent = suggestion;
            item.addEventListener('click', () => {
                this.addToken(suggestion);
                this.suggestBox.style.display = 'none';
            });
            this.suggestBox.appendChild(item);
        });

        this.suggestBox.style.display = 'block';
    }

    attachEventListeners() {
        this.input.addEventListener('click', () => {
            this.showSuggest();
        });

        // Input event
        this.input.addEventListener('input', () => {
            let val = this.input.value.trim();

            // Add the token if the user enters a comma
            if (val.endsWith(',') || val.endsWith(' ') || val.endsWith('\n') || val.endsWith('\t') || val.endsWith('\r') || val.endsWith(';')) {
                const value = val.slice(0, -1).trim();
                if (value.length > 0) this.addToken(value);
                this.input.value = '';
                return;
            }

            this.showSuggest();
        });

        // Keydown event
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.input.value.trim();
                if (value.length > 0) this.addToken(value);
                this.input.value = '';
                this.suggestBox.style.display = 'none';
            }
        });

        // Form submit event
        this.updateInputValueOnSubmit();

        // Close the suggest box on outside-click
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.addToken(this.input.value.trim());
                this.suggestBox.style.display = 'none';
            }
        });
    }

    updateInputValueOnSubmit() {
        const form = this.input.closest('form');
        if (!form) return;

        form.addEventListener('submit', () => {
            this.input.classList.add('submitting');
            window.setTimeout(() => this.input.classList.remove('submitting'), 5000);

            this.addToken(this.input.value.trim());

            const tokens = this.getTokens();
            this.input.value = tokens.join(', ');
        });
    }

    /**
     * Update suggestions dynamically
     * @param {string[]} newSuggestions
     */
    setSuggestions(newSuggestions) {
        this.options.suggestions = newSuggestions;
    }

    /**
     * Get all current tokens
     * @returns {string[]}
     */
    getValues() {
        return this.getTokens();
    }

    /**
     * Clear all tokens
     */
    clear() {
        this.tokenContainer.innerHTML = '';
    }

    /**
     * Destroy the tokenizer instance
     */
    destroy() {
        if (this.wrapper && this.wrapper.parentNode) {
            this.wrapper.parentNode.insertBefore(this.input, this.wrapper);
            this.wrapper.remove();
        }
    }
}
