import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    public async connect(): Promise<void> {
        this.element.textContent = new Date(this.element.textContent.trim()).toLocaleString();
    }
}
