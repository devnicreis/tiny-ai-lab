/**
 * Estado visual do shell autenticado — Sidebar e Header (Sessão 3.2).
 *
 * Substitui o JavaScript solto de .claude/docs/mockups/sidebar_v3.html e
 * header_3.0.html. Nada aqui fala com o servidor: colapso, menus, abas, busca
 * e tema são estado da interface (SAD M.9). As ações de domínio — trocar de
 * Brand ou de organização — são chamadas Livewire nas próprias views.
 */

const DESKTOP_MIN_WIDTH = 768;

const THEME_KEY = 'arkion.theme';
const SIDEBAR_KEY = 'arkion.sidebar.collapsed';

// localStorage pode lançar (navegação privada, armazenamento bloqueado); o
// shell precisa funcionar sem ele, com os padrões: tema claro, sidebar aberta.
const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Preferência não persistida; a interface segue funcionando.
        }
    },
};

const isDesktop = () => window.innerWidth >= DESKTOP_MIN_WIDTH;

export default function registerShell(Alpine) {
    Alpine.data('appSidebar', () => ({
        collapsed: storage.get(SIDEBAR_KEY) === '1',
        mobileOpen: false,
        helpDesktopOpen: false,
        helpMobileOpen: false,
        helpStyle: '',
        helpTimer: null,
        creditsOpen: false,
        creditsStyle: '',

        toggleCollapse() {
            const button = this.$refs.toggle;

            // Reinicia a animação do botão, como o mockup: remove a classe,
            // força o reflow e aplica a classe da nova direção.
            button.classList.remove('anim-close', 'anim-open');
            void button.offsetWidth;

            this.collapsed = !this.collapsed;
            button.classList.add(this.collapsed ? 'anim-close' : 'anim-open');

            storage.set(SIDEBAR_KEY, this.collapsed ? '1' : '0');
        },

        openMobile() {
            this.mobileOpen = true;
        },

        closeMobile() {
            this.mobileOpen = false;
            this.helpMobileOpen = false;
        },

        // No desktop o submenu de ajuda abre por hover, preso ao lado do
        // botão; no mobile, por clique, como bottom sheet.
        clickHelp() {
            if (!isDesktop()) {
                this.helpMobileOpen = true;
            }
        },

        positionHelp() {
            const rect = this.$refs.helpBtn.getBoundingClientRect();

            this.helpStyle = `top: auto; bottom: ${window.innerHeight - rect.bottom}px; left: ${rect.right + 8}px;`;
        },

        showHelp() {
            if (!isDesktop()) {
                return;
            }

            clearTimeout(this.helpTimer);
            this.positionHelp();
            this.helpDesktopOpen = true;
        },

        hideHelp() {
            if (!isDesktop()) {
                return;
            }

            this.helpTimer = setTimeout(() => {
                this.helpDesktopOpen = false;
            }, 150);
        },

        positionCredits() {
            const rect = this.$refs.credits.getBoundingClientRect();

            this.creditsStyle = `top: ${rect.top + rect.height / 2}px; left: ${rect.right + 8}px;`;
        },

        showCredits() {
            if (!isDesktop() || !this.$refs.credits) {
                return;
            }

            this.positionCredits();
            this.creditsOpen = true;
        },

        hideCredits() {
            this.creditsOpen = false;
        },

        // Rolar a Sidebar com um painel aberto o mantém preso ao seu botão.
        onBodyScroll() {
            if (!isDesktop()) {
                return;
            }

            if (this.helpDesktopOpen) {
                this.positionHelp();
            }

            if (this.creditsOpen && this.$refs.credits) {
                this.positionCredits();
            }
        },

        // O `theme-toggle-btn` é o único controle autorizado a alternar a
        // classe `dark` do <html> (SAD 10.14). Sem preferência salva, o
        // documento fica no tema claro, que é o padrão (CLAUDE.md regra 20).
        toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');

            storage.set(THEME_KEY, dark ? 'dark' : 'light');
        },
    }));

    Alpine.data('appHeader', () => ({
        // Um menu aberto por vez: 'org' | 'notifications' | 'language' | 'profile'.
        open: null,
        profileView: 'main',
        notificationsTab: 'activity',
        searchOpen: false,
        filterOpen: false,
        actionsClipped: false,
        clipTimer: null,

        toggle(menu) {
            this.open = this.open === menu ? null : menu;
            this.filterOpen = false;

            if (menu === 'profile') {
                this.profileView = 'main';
            }
        },

        close(menu) {
            if (this.open !== menu) {
                return;
            }

            this.open = null;

            if (menu === 'profile') {
                setTimeout(() => {
                    this.profileView = 'main';
                }, 200);
            }
        },

        // A busca expande sobre o grupo de ações, que recolhe; o grupo só
        // volta a permitir que seus menus transbordem depois da animação.
        openSearch() {
            this.open = null;
            this.searchOpen = true;

            clearTimeout(this.clipTimer);
            this.actionsClipped = true;

            this.$nextTick(() => this.$refs.searchInput.focus());
        },

        closeSearch() {
            if (!this.searchOpen) {
                return;
            }

            this.searchOpen = false;
            this.filterOpen = false;

            this.clipTimer = setTimeout(() => {
                this.actionsClipped = false;
            }, 500);
        },

        actionsStyle() {
            if (this.searchOpen) {
                return 'max-width: 0px; margin-right: 0px; opacity: 0;';
            }

            return `max-width: 300px; margin-right: ${window.innerWidth < 640 ? '0.5rem' : '1rem'}; opacity: 1;`;
        },
    }));
}
