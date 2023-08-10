import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    public async dismiss(): Promise<void> {
        this.element.remove();
    }
}
