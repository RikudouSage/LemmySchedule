import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    static targets = ['instanceSelect'];

    private instanceSelectTarget: HTMLSelectElement;

    public async connect() {
        new TomSelect(this.instanceSelectTarget, {
            create: true,
            maxItems: 1,
        });
    }
}
