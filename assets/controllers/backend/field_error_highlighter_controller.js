import {Controller} from '@hotwired/stimulus';

/*
 * Back end: If a form has been submitted with invalid input, scroll to the first
 * field with an error and focus it. The highlighting is done with CSS
 * (assets/styles/backend/scss/components/_field_errors.scss).
 *
 * Contao generates the edit forms, so the controller adds itself to the <html>
 * element. With Turbo, the <html> element stays and the controller reacts to
 * "turbo:load" instead.
 */
export default class extends Controller {
    static afterLoad(identifier, application) {
        const {controllerAttribute} = application.schema;
        const html = document.documentElement;
        const controllers = (html.getAttribute(controllerAttribute) || '').split(' ').filter(Boolean);

        if (!controllers.includes(identifier)) {
            html.setAttribute(controllerAttribute, [...controllers, identifier].join(' '));
        }
    }

    connect() {
        this.scrollToFirstError = this.scrollToFirstError.bind(this);

        document.addEventListener('turbo:load', this.scrollToFirstError);
        this.scrollToFirstError();
    }

    disconnect() {
        document.removeEventListener('turbo:load', this.scrollToFirstError);
    }

    scrollToFirstError() {
        const error = document.querySelector('.tl_formbody_edit .widget p.tl_error');

        // Only once per page
        if (!error || error.dataset.sacevtScrolled) {
            return;
        }

        error.dataset.sacevtScrolled = 'true';

        const widget = error.closest('.widget');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Contao restores the previous scroll position after a reload. Wait for it,
        // otherwise it would scroll away from the error again.
        window.setTimeout(() => {
            widget.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'center'});

            const input = widget.querySelector('input:not([type="hidden"]), select, textarea');

            if (input && null !== input.offsetParent) {
                input.focus({preventScroll: true});
            }
        }, 200);
    }
}
