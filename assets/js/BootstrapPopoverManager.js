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
 * });
 * popover.init();
 */
class BootstrapPopoverManager {
    constructor(triggerSelector = '.bs-popover-trigger', options = {}) {
        this.triggerSelector = triggerSelector;
        this.currentPopover = null;
        this.defaultOptions = {
            container: '.tour-difficulties',
            html: true,
            sanitize: false,
            placement: 'top',
            trigger: 'click',
            closeButtonHtml: '<button type="button" class="btn-close position-absolute top-0 end-0 mt-1 me-1" aria-label="Close"></button>',
            ...options
        };
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initializePopovers();
        });
    }

    initializePopovers() {
        const elements = document.querySelectorAll(this.triggerSelector);

        for (const element of elements) {
            this.setupPopover(element);
        }
    }

    setupPopover(element) {
        // Listen for popover show event
        element.addEventListener('show.bs.popover', () => {
            this.handlePopoverShow(element);
        });

        // Create popover instance
        const popover = new bootstrap.Popover(element, {
            ...this.defaultOptions,
            title: () => this.getPopoverTitle(element),
            content: element.dataset.bsContent,
        });

        // Handle close button after popover is shown
        element.addEventListener('shown.bs.popover', () => {
            this.handlePopoverShown(popover);
        });
    }

    handlePopoverShow(element) {
        const instance = bootstrap.Popover.getOrCreateInstance(element);

        if (this.currentPopover !== null && this.currentPopover !== instance) {
            this.currentPopover.hide();
        }

        this.currentPopover = instance;
    }

    getPopoverTitle(element) {
        // Replace [br] with <br> and append a close button to the title
        return element.dataset.bsTitle.replace('[br]', '<br>') + this.defaultOptions.closeButtonHtml;
    }

    handlePopoverShown(popover) {
        const closeBtn = document.querySelector('.popover .btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                popover.hide();
            }, {once: true});
        }
    }

    destroy() {
        const elements = document.querySelectorAll(this.triggerSelector);
        for (const element of elements) {
            const instance = bootstrap.Popover.getInstance(element);
            if (instance) {
                instance.dispose();
            }
        }
        this.currentPopover = null;
    }
}
