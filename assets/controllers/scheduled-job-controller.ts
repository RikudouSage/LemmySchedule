import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ['dateTime'];
    static values = {cancelUrl: String, errorDeletingText: String};

    private dateTimeTarget: HTMLTableCellElement;

    private cancelUrlValue: string;
    private errorDeletingTextValue: string;

    public async connect(): Promise<void> {
        const value = this.dateTimeTarget.textContent;
        const date = new Date(value);
        this.dateTimeTarget.textContent = date.toLocaleString();
    }

    public async cancel(): Promise<void> {
        const response = await fetch(this.cancelUrlValue, {
            method: 'DELETE',
        });
        if (response.status >= 200 && response.status < 300) {
            this.element.remove();
        } else {
            this.element.innerHTML = `<td colspan="4">${this.errorDeletingTextValue}</td>`;
            this.element.classList.add('btn-danger');
        }
    }
}
