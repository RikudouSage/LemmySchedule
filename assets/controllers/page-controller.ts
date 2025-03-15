import {Controller} from "@hotwired/stimulus";
import {Notification} from "../notification";

interface NewVersionCheckResult {
    currentVersion: string;
    latestVersion: string;
    hasNewVersion: boolean;
}

export default class extends Controller {
    static targets = ['sideMenu', 'sideMenuToggler', 'notificationWrapper'];
    static values = {isLoggedIn: Boolean, newVersionCheckUrl: String};

    private readonly notification = new Notification();

    private sideMenuTarget: HTMLElement;
    private sideMenuTogglerTarget: HTMLElement;
    private notificationWrapperTarget: HTMLDivElement;

    private isLoggedInValue: boolean;
    private newVersionCheckUrlValue: string;

    private readonly autoCollapse = 992;

    public async connect(): Promise<void> {
        if (window.outerWidth <= this.autoCollapse) {
            await this.toggleSideMenu(null);
        }

        if (this.isLoggedInValue) {
            await this.checkForNewVersion();
        }
    }

    public async toggleSideMenu(event: Event | null): Promise<void> {
        event?.preventDefault();
        if (this.element.classList.contains('sidebar-collapse')) {
            // expand
            if (window.outerWidth <= this.autoCollapse) {
                this.element.classList.add('sidebar-open');
            }
            this.element.classList.remove('sidebar-collapse');
            this.element.classList.remove('sidebar-closed');
        } else {
            // collapse
            await this.hideMenu();
        }
    }

    public async hideMenu(): Promise<void> {
        if (window.outerWidth <= this.autoCollapse) {
            this.element.classList.remove('sidebar-open');
            this.element.classList.add('sidebar-closed');
        }
        this.element.classList.add('sidebar-collapse');
    }

    public async hideMenuOnMobile(event: Event): Promise<void> {
        if (this.sideMenuTarget.contains(<HTMLElement>event.target) || this.sideMenuTogglerTarget.contains(<HTMLElement>event.target)) {
            return;
        }
        if (window.outerWidth <= this.autoCollapse) {
            await this.hideMenu();
        }
    }

    private async checkForNewVersion(): Promise<void> {
        const lastChecked = new Date(localStorage.getItem('scheduler.new_version.last_checked') ?? new Date('1970-01-01').toISOString());
        const now = new Date();

        const minimumDiff = 12 * (60 * 60 * 1_000); // twelve hours
        const actualDiff = now.getTime() - lastChecked.getTime();

        if (actualDiff < minimumDiff) {
            return;
        }

        const lastVersion: NewVersionCheckResult = await (await fetch(this.newVersionCheckUrlValue)).json();
        if (lastVersion.hasNewVersion) {
            const notification = (await this.notification.success("A new version of scheduler (%1) is available, you're currently running version %2."))
                .replace('%1', lastVersion.latestVersion)
                .replace('%2', lastVersion.currentVersion)
            ;
            this.notificationWrapperTarget.innerHTML += notification;
        }
        localStorage.setItem('scheduler.new_version.last_checked', now.toISOString());
    }
}
