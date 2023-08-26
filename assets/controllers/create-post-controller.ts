import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";
import {DateHelper} from "../date-helper";

export default class extends Controller {
    static targets = [
        'timezoneOffset',
        'communitySelect',
        'languageSelect',
        'recurringScheduleSwitch',
        'oneTimeSchedule',
        'recurringSchedule',
    ];

    private timezoneOffsetTarget: HTMLInputElement;
    private communitySelectTarget: HTMLSelectElement;
    private languageSelectTarget: HTMLSelectElement;
    private recurringScheduleSwitchTarget: HTMLInputElement;
    private oneTimeScheduleTarget: HTMLDivElement;
    private recurringScheduleTarget: HTMLDivElement;

    public async connect(): Promise<void> {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();

        new TomSelect(this.communitySelectTarget, {
            create: true,
            maxItems: null,
        });
        new TomSelect(this.languageSelectTarget, {
            create: false,
            maxItems: 1,
        });

        await this.toggleRecurring();
    }

    public async toggleRecurring(): Promise<void> {
        if (this.recurringScheduleSwitchTarget.checked) {
            this.oneTimeScheduleTarget.hidden = true;
            this.recurringScheduleTarget.hidden = false;
        } else {
            this.oneTimeScheduleTarget.hidden = false;
            this.recurringScheduleTarget.hidden = true;
        }
    }
}
