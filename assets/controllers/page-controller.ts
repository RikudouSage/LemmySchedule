import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ['sideMenu', 'sideMenuToggler'];

    private sideMenuTarget: HTMLElement;
    private sideMenuTogglerTarget: HTMLElement;

    private readonly autoCollapse = 992;

    public async connect(): Promise<void> {
        if (window.outerWidth <= this.autoCollapse) {
            await this.toggleSideMenu(null);
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
}
