/**
 * This replaces jQuery slideUp/Down.
 *
 * Add this CSS snippet to your stylesheet or template:
 *
 *  <style>
 *      .slide-box {
 *          overflow: hidden;
 *          height: 0;
 *          transition: height 300ms ease;
 *      }
 *
 *      .slide-box.open {
 *          height: auto; // This won't animate, so we handle it via JS
 *      }
 *  </style>
 */
class SlideBox {
    constructor(element, once = false, duration = 300) {
        this.once = once;
        this.counter = 0;
        this.el = element;
        this.duration = duration;
        this.isOpen = false;

        this.el.style.overflow = 'hidden';
        this.el.style.transition = `height ${this.duration}ms ease`;
        this.el.style.height = '0';
    }

    slideDown() {
        this.counter++;
        if (this.counter > 1 && true === this.once) {
            return;
        }

        this.el.style.display = 'block';
        const height = this.el.scrollHeight + 'px';
        this.el.style.height = height;

        this.el.addEventListener('transitionend', () => {
            this.el.style.height = 'auto';
            this.el.classList.add('open');
        }, {once: true});

        this.isOpen = true;
    }

    slideUp() {
        this.counter++;
        if (this.counter > 1 && true === this.once) {
            return;
        }

        this.el.style.height = this.el.scrollHeight + 'px';
        requestAnimationFrame(() => {
            this.el.style.height = '0';
        });

        this.isOpen = false;
    }

    toggle() {
        this.counter++;
        if (this.counter > 1 && true === this.once) {
            return;
        }
        this.isOpen ? this.slideUp() : this.slideDown();
    }
}
