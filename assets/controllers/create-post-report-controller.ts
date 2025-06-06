import {Controller} from "@hotwired/stimulus";
import {DateHelper} from "../date-helper";
import TomSelect from "tom-select";

export default class extends Controller {
    static targets: string[] = [
        'communitySelect',
        'recurringScheduleSwitch',
        'oneTimeSchedule',
        'recurringSchedule',
        'timezoneOffset',
        'timezoneName',
    ];

    private communitySelectTarget: HTMLSelectElement;
    private recurringScheduleSwitchTarget: HTMLInputElement;
    private oneTimeScheduleTarget: HTMLDivElement;
    private recurringScheduleTarget: HTMLDivElement;
    private timezoneOffsetTarget: HTMLInputElement;
    private timezoneNameTarget: HTMLInputElement;

    public async connect(): Promise<void> {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();
        this.timezoneNameTarget.value = DateHelper.getTimezoneName();

        new TomSelect(this.communitySelectTarget, {
            create: true,
            maxItems: null,
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
