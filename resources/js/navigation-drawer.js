export function navigationDrawer(browser = window) {
    let media;
    let onChange;
    let opener;
    return {
        sidebar: false,
        desktop: false,
        init() {
            media = browser.matchMedia('(min-width: 1024px)');
            this.desktop = media.matches;
            onChange = (event) => {
                this.desktop = event.matches;
                this.close(false);
            };
            media.addEventListener('change', onChange);
            this.$nextTick(() => this.restoreNavigationScroll());
        },
        rememberNavigationScroll(event = null) {
            const navigation = event?.currentTarget ?? this.$refs.navigationScroll;
            if (!navigation) return;
            try {
                browser.sessionStorage?.setItem(
                    'chorale.sidebar.scroll',
                    String(navigation.scrollTop),
                );
            } catch {
                // La navigation reste fonctionnelle si le stockage est désactivé.
            }
        },
        restoreNavigationScroll() {
            const navigation = this.$refs.navigationScroll;
            if (!navigation) return;
            try {
                const position = Number(
                    browser.sessionStorage?.getItem('chorale.sidebar.scroll'),
                );
                if (Number.isFinite(position)) navigation.scrollTop = position;
            } catch {
                // Le navigateur peut bloquer sessionStorage en mode privé strict.
            }
        },
        open() {
            opener = browser.document.activeElement;
            this.sidebar = true;
            browser.document.documentElement.classList.add('mobile-menu-open');
            this.$nextTick(() =>
                this.$refs.navigation.querySelector('button, a[href]')?.focus(),
            );
        },
        close(restoreFocus = true) {
            const wasOpen = this.sidebar;
            this.sidebar = false;
            browser.document.documentElement.classList.remove(
                'mobile-menu-open',
            );
            if (wasOpen && restoreFocus)
                this.$nextTick(() => {
                    if (opener?.isConnected) opener.focus();
                });
        },
        trap(event) {
            if (this.desktop || !this.sidebar || event.key !== 'Tab') return;
            const fields = [
                ...this.$refs.navigation.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex="0"]',
                ),
            ].filter(
                (field) =>
                    field.getClientRects().length && !field.closest('[inert]'),
            );
            const first = fields[0],
                last = fields.at(-1);
            if (!first) {
                event.preventDefault();
                return;
            }
            if (event.shiftKey && browser.document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (
                !event.shiftKey &&
                browser.document.activeElement === last
            ) {
                event.preventDefault();
                first.focus();
            }
        },
        destroy() {
            this.rememberNavigationScroll();
            media?.removeEventListener('change', onChange);
            browser.document.documentElement.classList.remove(
                'mobile-menu-open',
            );
        },
    };
}
