import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";
import {DateHelper} from "../date-helper";

export default class extends Controller {
    static targets = ['timezoneOffset', 'communitySelect', 'languageSelect'];

    private timezoneOffsetTarget: HTMLInputElement;
    private communitySelectTarget: HTMLSelectElement;
    private languageSelectTarget: HTMLSelectElement;

    public connect() {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();

        new TomSelect(this.communitySelectTarget, {
            create: true,
            maxItems: null,
        });
        new TomSelect(this.languageSelectTarget, {
            create: false,
            maxItems: 1,
        });
    }
}
