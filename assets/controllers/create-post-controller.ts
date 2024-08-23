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
    static debounces: string[] = ['checkTitleForExpressions', 'urlChanged'];

    static values = {
        parseTitleUrl: String,
        newCommentBoxUrl: String,
        pageTitleUrl: String,
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
        'checkForDuplicatesWrapper',
        'urlInput',
        'addCommentsWrapper',
        'addCommentsToggle',
        'addCommentButton',
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
    private checkForDuplicatesWrapperTarget: HTMLDivElement;
    private urlInputTarget: HTMLInputElement;
    private addCommentsWrapperTarget: HTMLDivElement;
    private addCommentsToggleTarget: HTMLInputElement;
    private addCommentButtonTarget: HTMLButtonElement;

    private parseTitleUrlValue: string;
    private newCommentBoxUrlValue: string;
    private pageTitleUrlValue: string;

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
        await this.toggleDuplicityCheck();
        await this.toggleCommentsWrapper();
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

    public async toggleDuplicityCheck(): Promise<void> {
        this.checkForDuplicatesWrapperTarget.hidden = !this.urlInputTarget.value.length;
    }

    public async toggleCommentsWrapper(): Promise<void> {
        this.addCommentsWrapperTarget.hidden = !this.addCommentsToggleTarget.checked;
    }

    public async addCommentBox(): Promise<void> {
        const response = await fetch(this.newCommentBoxUrlValue, {
            method: 'POST',
            body: JSON.stringify({
                name: 'comments[]',
                inputId: 'comment' + Math.random(),
            }),
        });
        const html = await response.text();
        const parser = new DOMParser();
        const document = parser.parseFromString(html, 'text/html');

        this.addCommentsWrapperTarget.insertBefore(document.body.firstChild, this.addCommentButtonTarget);
    }

    public async removeComment(event: Event): Promise<void> {
        const target = event.currentTarget as HTMLElement;
        target.parentElement.remove();
        console.log(event);
    }

    public async urlChanged(): Promise<void> {
        if (this.titleInputTarget.value) {
            return;
        }
        try {
            const url = new URL(this.urlInputTarget.value);
            const response = await fetch(this.pageTitleUrlValue, {
                method: 'POST',
                body: JSON.stringify({
                    url: url,
                })
            })
            const json = await response.json() as {title: string | null};
            if (json.title) {
                this.titleInputTarget.value = json.title;
            }
        } catch (e) {
            // ignore
        }
    }
}
