import {Controller} from "@hotwired/stimulus";
import {Notification} from "../notification";
import {DateHelper} from "../date-helper";

interface Post {
    post: {
        name: string;
        featuredCommunity: boolean;
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
        'pinRadio',
        'unpinRadio',
        'timezoneOffset',
    ];
    static values = {
        fetchPostUrl: String,
        emptyInputError: String,
        badRequestError: String,
        convertingUrlToIdError: String,
        notFoundError: String,
        genericError: String,
        yes: String,
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
    private pinRadioTarget: HTMLInputElement;
    private unpinRadioTarget: HTMLInputElement;
    private timezoneOffsetTarget: HTMLInputElement;

    private fetchPostUrlValue: string;
    private emptyInputErrorValue: string;
    private badRequestErrorValue: string;
    private convertingUrlToIdErrorValue: string;
    private notFoundErrorValue: string;
    private genericErrorValue: string;
    private yesValue: string;
    private noValue: string;

    public async connect() {
        this.timezoneOffsetTarget.value = DateHelper.getTimezoneOffset();
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
        this.pinnedCellTarget.innerText = post.post.featuredCommunity ? this.yesValue : this.noValue;
        this.communityCellTarget.innerText = `${post.community.name} (${post.community.actorId})`;

        if (post.post.featuredCommunity) {
            this.unpinRadioTarget.checked = true;
        } else {
            this.pinRadioTarget.checked = true;
        }
    }
}
