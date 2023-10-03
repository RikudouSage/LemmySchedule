import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";
import {DateHelper} from "../date-helper";
import {useDebounce} from "stimulus-use";

interface TitleExpressionResponse {
    validCount: number;
    invalid: string[];
    title: string;
}

export default class extends Controller {
    static debounces: string[] = ['checkTitleForExpressions'];

    static values = {
        parseTitleUrl: String,
    };

    static targets = [
        'timezoneOffset',
        'timezoneName',
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
        'fileProviderWrapper',
        'fileSelect',
        'titleInput',
        'expressionTitleError',
        'expressionTitleErrorVariables',
        'expressionTitlePreviewWrapper',
        'expressionTitlePreview',
    ];

    private timezoneOffsetTarget: HTMLInputElement;
    private timezoneNameTarget: HTMLInputElement;
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
    private fileProviderWrapperTarget: HTMLDivElement;
    private fileSelectTarget: HTMLInputElement;
    private titleInputTarget: HTMLInputElement;
    private expressionTitleErrorTarget: HTMLElement;
    private expressionTitleErrorVariablesTarget: HTMLSpanElement;
    private expressionTitlePreviewWrapperTarget: HTMLElement;
    private expressionTitlePreviewTarget: HTMLElement;

    private parseTitleUrlValue: string;

    public async connect(): Promise<void> {
        useDebounce(this, {wait: 500});

        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();
        this.timezoneNameTarget.value = DateHelper.getTimezoneName();

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
        await this.toggleFileProvider();
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

    public async toggleFileProvider(): Promise<void> {
        this.fileProviderWrapperTarget.hidden = !this.fileSelectTarget.files.length;
    }

    public async checkTitleForExpressions(): Promise<void> {
        const response = await fetch(this.parseTitleUrlValue, {
            method: 'POST',
            body: JSON.stringify({
                title: this.titleInputTarget.value,
                timezone: this.timezoneNameTarget.value,
            }),
        });
        if (!response.ok) {
            return;
        }

        const body: TitleExpressionResponse = await response.json();

        if (!body.invalid.length) {
            this.expressionTitleErrorTarget.hidden = true;
        }
        if (!body.validCount) {
            this.expressionTitlePreviewWrapperTarget.hidden = true;
        }

        if (body.invalid.length) {
            this.expressionTitleErrorVariablesTarget.innerHTML = body.invalid.map(item => `<code>${item}</code>`).join(', ');
            this.expressionTitleErrorTarget.hidden = false;
        }
        if (body.validCount) {
            this.expressionTitlePreviewTarget.innerText = body.title;
            this.expressionTitlePreviewWrapperTarget.hidden = false;
        }
    }
}
