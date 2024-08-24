import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    public async connect(): Promise<void> {
        if (typeof Intl.NumberFormat === "undefined") {
            return;
        }
        this.element.textContent = String(new Intl.NumberFormat().format(Number(this.element.textContent)));
    }
}
