import { Controller } from "@hotwired/stimulus";
import { Tooltip } from "bootstrap";

export default class extends Controller {
    connect() {
        this.tooltip = new Tooltip(this.element, {
            title: this.element.dataset.bsTooltipTitle,
            html: true,
            trigger: "hover",
            placement: this.element.dataset.bsTooltipPlacement ?? "top",
        });
    }

    disconnect() {
        this.tooltip?.dispose();
    }
}
