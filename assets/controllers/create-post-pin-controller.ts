import {Controller} from "@hotwired/stimulus";
import {Notification} from "../notification";
import {DateHelper} from "../date-helper";

interface Post {
    post: {
        name: string;
        featuredCommunity: boolean;
        featuredLocal: boolean;
        body: string | null;
        url: string | null;
    },
    community: {
        name: string;
        title: string | null;
        actorId: string;
    },
}

export default class extends Controller {
    private readonly notification = new Notification();

    static targets = [
        'postIdInput',
        'messageHolder',
        'detailsTable',
        'titleCell',
        'urlCell',
        'textCell',
        'pinnedCell',
        'communityCell',
        'restOfTheForm',
        'pinCommunityRadio',
        'unpinCommunityRadio',
        'pinInstanceRadio',
        'unpinInstanceRadio',
        'timezoneOffset',
        'timezoneName',
    ];
    static values = {
        fetchPostUrl: String,
        emptyInputError: String,
        badRequestError: String,
        convertingUrlToIdError: String,
        notFoundError: String,
        genericError: String,
        yesCommunity: String,
        yesInstance: String,
        yesBoth: String,
        no: String,
    };

    private postIdInputTarget: HTMLInputElement;
    private messageHolderTarget: HTMLDivElement;
    private detailsTableTarget: HTMLTableElement;
    private titleCellTarget: HTMLTableCellElement;
    private urlCellTarget: HTMLTableCellElement;
    private textCellTarget: HTMLTableCellElement;
    private pinnedCellTarget: HTMLTableCellElement;
    private communityCellTarget: HTMLTableCellElement;
    private restOfTheFormTarget: HTMLDivElement;
    private pinCommunityRadioTarget: HTMLInputElement;
    private unpinCommunityRadioTarget: HTMLInputElement;
    private pinInstanceRadioTarget: HTMLInputElement;
    private unpinInstanceRadioTarget: HTMLInputElement;
    private timezoneOffsetTarget: HTMLInputElement;
    private timezoneNameTarget: HTMLInputElement;

    private fetchPostUrlValue: string;
    private emptyInputErrorValue: string;
    private badRequestErrorValue: string;
    private convertingUrlToIdErrorValue: string;
    private notFoundErrorValue: string;
    private genericErrorValue: string;
    private yesCommunityValue: string;
    private yesInstanceValue: string;
    private yesBothValue: string
    private noValue: string;

    public async connect() {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();
        this.timezoneNameTarget.value = DateHelper.getTimezoneName();
    }

    public async loadPost(): Promise<void> {
        this.messageHolderTarget.innerHTML = '';
        this.detailsTableTarget.classList.add('hidden');
        this.restOfTheFormTarget.classList.add('hidden');

        const value = this.postIdInputTarget.value;
        if (!value) {
            this.messageHolderTarget.innerHTML = await this.notification.error(this.emptyInputErrorValue, true);
            return;
        }
        const body = new FormData();
        body.set('urlOrId', value);
        const response = await fetch(this.fetchPostUrlValue, {
            body: body,
            method: 'POST',
        });
        if (response.status === 400) {
            this.messageHolderTarget.innerHTML = await this.notification.error(this.badRequestErrorValue, true);
            return;
        }
        if (response.status === 501) {
            this.messageHolderTarget.innerHTML = await this.notification.error(this.convertingUrlToIdErrorValue, true);
            return;
        }
        if (response.status === 404) {
            this.messageHolderTarget.innerHTML = await this.notification.error(this.notFoundErrorValue, true);
            return;
        }
        if (!response.ok) {
            this.messageHolderTarget.innerHTML = await this.notification.error(this.genericErrorValue, true);
            return;
        }

        const post: Post = await response.json();

        this.restOfTheFormTarget.classList.remove('hidden');
        this.detailsTableTarget.classList.remove('hidden');
        this.titleCellTarget.innerText = post.post.name;
        this.urlCellTarget.innerHTML = post.post.url ? post.post.url : `<code>N/A</code>`;
        this.textCellTarget.innerHTML = post.post.body ? `<pre>${post.post.body}</pre>` : `<code>N/A</code>`;

        let pinnedText: string;
        if (post.post.featuredCommunity && post.post.featuredLocal) {
            pinnedText = this.yesBothValue;
        } else if (post.post.featuredCommunity) {
            pinnedText = this.yesCommunityValue;
        } else if (post.post.featuredLocal) {
            pinnedText = this.yesInstanceValue;
        } else {
            pinnedText = this.noValue;
        }
        this.pinnedCellTarget.innerText = pinnedText;
        this.communityCellTarget.innerText = `${post.community.name} (${post.community.actorId})`;

        if (post.post.featuredLocal) {
            this.unpinInstanceRadioTarget.checked = true;
        } else if (post.post.featuredCommunity) {
            this.unpinCommunityRadioTarget.checked = true;
        } else {
            this.pinCommunityRadioTarget.checked = true;
        }
    }
}
