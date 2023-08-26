import {Controller} from "@hotwired/stimulus";
import {Component, getComponent} from "@symfony/ux-live-component";

export default class extends Controller {
    private component: Component;

    public async initialize() {
        this.component = await getComponent(this.element as HTMLElement);

        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (timezone === undefined) {
            await Promise.all([
                this.component.action('showTimezoneError'),
                this.component.action('setTimezoneAsString', {timezone: 'UTC'}),
            ]);
        } else {
            await this.component.action('setTimezoneAsString', {timezone: timezone});
        }
    }
}
