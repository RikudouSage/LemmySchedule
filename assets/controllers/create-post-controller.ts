import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    static targets = ['timezoneOffset', 'communitySelect'];

    private timezoneOffsetTarget: HTMLInputElement;
    private communitySelectTarget: HTMLSelectElement;

    public connect() {
        const offset = -new Date().getTimezoneOffset();
        const hours = Math.abs(Math.floor(offset / 60));
        const minutes = Math.abs(offset) - hours * 60;

        let result= `${this.padLeft(String(hours), 2)}:${this.padLeft(String(minutes), 2)}`;
        if (offset > 0) {
            result = `+${result}`;
        } else {
            result = `-${result}`;
        }

        this.timezoneOffsetTarget.value = result;

        new TomSelect(this.communitySelectTarget, {
            create: true,
            maxItems: 1,
        });
    }

    private padLeft(string: string, padAmount: number, padWith: string = '0'): string {
        if (string.length >= padAmount) {
            return string;
        }

        const diff = padAmount - string.length;
        let result = '';
        for (let i = 0; i < diff; ++i) {
            result += padWith;
        }

        return `${result}${string}`;
    }
}
