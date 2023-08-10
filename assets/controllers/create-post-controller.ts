import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";
import {DateHelper} from "../date-helper";

export default class extends Controller {
    static targets = ['timezoneOffset', 'communitySelect'];

    private timezoneOffsetTarget: HTMLInputElement;
    private communitySelectTarget: HTMLSelectElement;

    public connect() {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();

        new TomSelect(this.communitySelectTarget, {
            create: true,
            maxItems: null,
        });
    }
}
