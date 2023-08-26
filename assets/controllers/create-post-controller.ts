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
        'scheduleUnpinSwitchWrapper',
        'pinToCommunitySwitch',
        'pinToInstanceSwitch',
        'scheduleUnpinSwitch',
        'scheduleUnpinWrapper',
    ];

    private timezoneOffsetTarget: HTMLInputElement;
    private communitySelectTarget: HTMLSelectElement;
    private languageSelectTarget: HTMLSelectElement;
    private recurringScheduleSwitchTarget: HTMLInputElement;
    private oneTimeScheduleTarget: HTMLDivElement;
    private recurringScheduleTarget: HTMLDivElement;
    private scheduleUnpinSwitchWrapperTarget: HTMLDivElement;
    private pinToCommunitySwitchTarget: HTMLInputElement;
    private pinToInstanceSwitchTarget: HTMLInputElement;
    private scheduleUnpinSwitchTarget: HTMLInputElement;
    private scheduleUnpinWrapperTarget: HTMLDivElement;

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
        await this.toggleScheduleUnpinSwitch();
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

    public async toggleScheduleUnpinSwitch(): Promise<void> {
        this.scheduleUnpinSwitchWrapperTarget.hidden = !(this.pinToCommunitySwitchTarget.checked || this.pinToInstanceSwitchTarget.checked);
        this.scheduleUnpinWrapperTarget.hidden = !(!this.scheduleUnpinSwitchWrapperTarget.hidden && this.scheduleUnpinSwitchTarget.checked);
    }
}
