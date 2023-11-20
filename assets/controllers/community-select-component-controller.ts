import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    static targets = ['select'];

    private selectTarget: HTMLSelectElement;

    public async connect(): Promise<void> {
        new TomSelect(this.selectTarget, {
            create: true,
            maxItems: null,
        });
    }
}
