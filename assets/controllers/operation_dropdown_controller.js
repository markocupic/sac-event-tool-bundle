import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["toggle", "menu"];

    connect() {
        console.log("Dropdown connected:", this.element);
        this.toggleTarget.addEventListener("click", this.toggle.bind(this));
        this.menuTarget.addEventListener("click", this.selectItem.bind(this));

        document.addEventListener("click", this.closeAll.bind(this));
    }

    toggle(event) {
        event.stopPropagation();
        const isOpen = this.menuTarget.classList.contains("show");

        // Close other dropdowns
        document.querySelectorAll(".dropdown-menu.show").forEach(openMenu => {
            if (openMenu !== this.menuTarget) {
                openMenu.classList.remove("show");
                openMenu
                    .closest(".dropdown")
                    .querySelector(".dropdown-toggle")
                    .setAttribute("aria-expanded", "false");
            }
        });

        // Toggle current dropdown
        this.menuTarget.classList.toggle("show", !isOpen);
        this.toggleTarget.setAttribute("aria-expanded", String(!isOpen));
    }

    selectItem(event) {
        if (event.target.matches(".dropdown-item")) {
            this.menuTarget.classList.remove("show");
            this.toggleTarget.setAttribute("aria-expanded", "false");
        }
    }

    closeAll() {
        document.querySelectorAll(".dropdown-menu.show").forEach(menu => {
            menu.classList.remove("show");
            menu.closest(".dropdown")
                .querySelector(".dropdown-toggle")
                .setAttribute("aria-expanded", "false");
        });
    }
}
