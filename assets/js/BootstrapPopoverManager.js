/**
 * Manages the lifecycle and behavior of Bootstrap popovers triggered by specified elements.
 * Usage:
 *
 * Minimalistic:
 * const popover = new BootstrapPopoverManager();
 * popover.init();
 *
 * Customized:
 * const popover = new BootstrapPopoverManager('.bs-popover-trigger', {
 *     container: '.tour-difficulties',
 *     html: true,
 *     sanitize: false,
 *     placement: 'top',
 *     trigger: 'click',
 *     closeButtonHtml: '<button type="button" class="btn-close" aria-label="Close"></button>',
 *     closeOnClickOutside: true,
 *     closeOnEscape: true,
 * });
 * popover.init();
 */
class BootstrapPopoverManager {
    #triggerSelector;
    #currentPopover = null;
    #closeOnClickOutside;
    #closeOnEscape;
    #defaultOptions;

    constructor(triggerSelector = '.bs-popover-trigger', options = {}) {

        this.#defaultOptions = {
            container: '.tour-difficulties',
            html: true,
            sanitize: false,
            placement: 'top',
            trigger: 'click',
            closeButtonHtml: '<button type="button" class="btn-close position-absolute top-0 end-0 mt-1 me-1" aria-label="Close"></button>',
            ...options,
        };

        this.#triggerSelector = triggerSelector;
        this.#closeOnClickOutside = options.closeOnClickOutside ?? true;
        this.#closeOnEscape = options.closeOnEscape ?? true;
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.#initializePopovers();
        });
    }


    destroy() {
        const elements = document.querySelectorAll(this.#triggerSelector);
        for (const element of elements) {
            const instance = bootstrap.Popover.getInstance(element);
            if (instance) {
                instance.dispose();
            }
        }
        this.#currentPopover = null;
    }

    #initializePopovers() {
        const elements = document.querySelectorAll(this.#triggerSelector);

        for (const element of elements) {
            this.#setupPopover(element);
        }
    }

    #setupPopover(element) {
        // Listen for popover show event
        element.addEventListener('show.bs.popover', () => {
            this.#handlePopoverShow(element);
        });

        // Create popover instance
        const popover = new bootstrap.Popover(element, {
            ...this.#defaultOptions,
            title: () => this.#getPopoverTitle(element),
            content: () => this.#getPopoverContent(element),
        });

        // Handle close button after popover is shown
        element.addEventListener('shown.bs.popover', () => {
            this.#handlePopoverShown(popover);
        });
    }

    #handlePopoverShow(element) {
        const instance = bootstrap.Popover.getOrCreateInstance(element);

        if (this.#currentPopover !== null && this.#currentPopover !== instance) {
            this.#currentPopover.hide();
        }

        this.#currentPopover = instance;
    }

    #getPopoverTitle(element) {
        // Replace [br] with <br> and append a close button to the title
        const title = (element.dataset.bsTitle ?? '').replaceAll('[br]', '<br>');
        return title + this.#defaultOptions.closeButtonHtml;
    }

    #getPopoverContent(element) {
        // Replace [br] with <br>
        const content = element.dataset.bsContent ?? '';
        return content.replaceAll('[br]', '<br>');
    }

    #handlePopoverShown(popover) {
        const closeBtn = document.querySelector('.popover .btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                popover.hide();
            }, {once: true});
        }

        let cleanupCalled = false;
        let listenersAdded = false;

        // Cleanup function to remove all event listeners
        const cleanup = () => {
            if (cleanupCalled || !listenersAdded) {
                return;
            }
            cleanupCalled = true;

            if (this.#closeOnClickOutside) {
                document.removeEventListener('click', handleClickOutside);
            }
            if (this.#closeOnEscape) {
                document.removeEventListener('keydown', handleEscapeKey);
            }
        };

        // Close popover when clicking outside
        const handleClickOutside = (event) => {
            const popoverElement = document.querySelector('.popover');
            const triggerElement = popover._element;

            if (popoverElement && !popoverElement.contains(event.target) && !triggerElement.contains(event.target)) {
                popover.hide();
            }
        };

        // Close popover when pressing ESC key
        const handleEscapeKey = (event) => {
            if (event.key === 'Escape' || event.keyCode === 27) {
                popover.hide();
            }
        };

        // Add event listeners with a small delay to avoid immediate triggering
        setTimeout(() => {
            if (this.#closeOnClickOutside) {
                document.addEventListener('click', handleClickOutside);
            }
            if (this.#closeOnEscape) {
                document.addEventListener('keydown', handleEscapeKey);
            }
            listenersAdded = true;
        }, 0);

        // Clean up listeners when popover is hidden
        popover._element.addEventListener('hidden.bs.popover', () => {
            cleanup();
        }, {once: true});
    }

}
