import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["toggle", "menu"];

    connect() {
        this.toggleTarget.addEventListener("click", this.toggle.bind(this));
        this.menuTarget.addEventListener("click", this.selectItem.bind(this));

        document.addEventListener("click", this.closeAll.bind(this));
        window.addEventListener("resize", this.closeAll.bind(this));
    }

    toggle(event) {
        event.stopPropagation();
        const isOpen = this.menuTarget.classList.contains("show");

        // Close other dropdowns
        document.querySelectorAll(".dropdown-menu.show").forEach(openMenu => {
            if (openMenu !== this.menuTarget) {
                openMenu.classList.remove("show");
                openMenu.closest(".dropdown")
                    .querySelector(".dropdown-toggle")
                    .setAttribute("aria-expanded", "false");
            }
        });

        if (!isOpen) {
            const rect = this.toggleTarget.getBoundingClientRect();
            const portal = this.menuTarget.closest('.my-events--item').querySelector('.dropdown-portal');

            // Clone the menu and inject into the portal to avoid overflow-hidden issues
            const clonedMenu = this.menuTarget.cloneNode(true);

            clonedMenu.classList.add("show");
            clonedMenu.style.position = "absolute";
            clonedMenu.style.top = `${rect.bottom + window.scrollY - 40}px`;
            clonedMenu.style.visibility = "hidden";
            clonedMenu.style.zIndex = "2";

            portal.innerHTML = ""; // Clear previous
            portal.appendChild(clonedMenu);
            window.setTimeout(() => {
                clonedMenu.style.left = `${rect.right - clonedMenu.offsetWidth + window.scrollX}px`;
                clonedMenu.style.visibility = "visible";
            }, 10);

            clonedMenu.addEventListener("click", event => {
                event.stopPropagation();
            });

            this.toggleTarget.setAttribute("aria-expanded", "true");

            // Optional: handle selection inside cloned menu
            //clonedMenu.addEventListener("click", this.selectItem.bind(this));
        } else {
            document.getElementById("dropdown-portal").innerHTML = "";
            this.toggleTarget.setAttribute("aria-expanded", "false");
        }
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
