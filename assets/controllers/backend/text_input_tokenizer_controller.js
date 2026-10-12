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
import TextInputTokenizer from '../../text_input_tokenizer/js/TextInputTokenizer';

const VALIDATORS = {
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()),
};

/**
 * Turns a text input into a token input with suggestions.
 *
 * <div data-controller="sacevt--backend--text-input-tokenizer"
 *      data-sacevt--backend--text-input-tokenizer-input-value="#ctrl_recipientsCc"
 *      data-sacevt--backend--text-input-tokenizer-suggestions-value='["a@example.com"]'
 *      data-sacevt--backend--text-input-tokenizer-validator-value="email">
 *     …form with the input…
 * </div>
 *
 * The styles have to be loaded separately (text_input_tokenizer/css/style.css).
 */
export default class extends Controller {
    static values = {
        input: {type: String, default: 'input[type="text"]'},
        suggestions: {type: Array, default: []},
        validator: {type: String, default: ''},
    };

    connect() {
        const input = this.element.querySelector(this.inputValue);

        if (!input || input.readOnly || input.disabled) {
            return;
        }

        this.tokenizer = new TextInputTokenizer(input, {
            suggestions: this.suggestionsValue.map(String),
            validator: VALIDATORS[this.validatorValue] ?? null,
        });
    }

    disconnect() {
        this.tokenizer?.destroy();
        this.tokenizer = null;
    }
}
