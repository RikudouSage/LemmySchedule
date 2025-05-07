import {Controller} from "@hotwired/stimulus";
import tippy, {Instance} from 'tippy.js';

export default class extends Controller {
    public static override values = {message: String};

    private tippyInstance: Instance | null = null;

    private messageValue: string;

    public connect(): void {
        this.tippyInstance = tippy(this.element, {
            content: this.messageValue,
        });
    }

    public disconnect(): void {
        this.tippyInstance?.destroy()
    }
}
