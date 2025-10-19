import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message']

    connect() {
        this.element.addEventListener('turbo:submit-end', this.onSubmitEnd.bind(this))
    }

    onSubmitEnd(event) {
        if (event.detail.success) {
            this.messageTarget.textContent = 'Danke für deine Nachricht!'
        } else {
            this.messageTarget.textContent = 'Fehler beim Absenden.'
        }
    }
}

