const stack = [];
let previousOverflow = '';

export function modalPanel(closeEvent = 'close-form') {
    return {
        dirty: false,
        confirming: false,
        pending: 0,
        uploads: 0,
        inertNodes: [],
        get busy() { return this.pending > 0 || this.uploads > 0; },
        isTop() { return stack.at(-1) === this.root; },
        init() {
            this.root = this.$el;
            this.returnFocus = document.activeElement;
            if (!stack.length) previousOverflow = document.body.style.overflow;
            stack.push(this.root);
            document.body.style.overflow = 'hidden';
            // Inert only siblings along the path to body, never an ancestor of the dialog.
            let branch = this.root;
            while (branch && branch !== document.body) {
                for (const sibling of branch.parentElement?.children ?? []) {
                    if (sibling !== branch && !['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) {
                        this.inertNodes.push([sibling, sibling.inert]);
                        sibling.inert = true;
                    }
                }
                branch = branch.parentElement;
            }
            this.focusListener = (event) => {
                if (this.isTop() && !this.root.contains(event.target)) this.focusFirst();
            };
            document.addEventListener('focusin', this.focusListener);
            this.removeHook = window.Livewire?.hook('commit', ({component, respond, succeed}) => {
                if (!this.root.contains(component.el) && !component.el.contains(this.root)) return;
                this.pending++;
                respond(() => { this.pending = Math.max(0, this.pending - 1); });
                succeed(({effects}) => {
                    const calls = effects?.dispatches ?? [];
                    if (calls.some((event) => event.name === 'toast' && event.params?.type === 'success')) this.dirty = false;
                    this.$nextTick(() => {
                        const error = this.root.querySelector('[aria-invalid="true"]') ?? this.root.querySelector('[data-validation-summary]');
                        error?.focus();
                        error?.scrollIntoView({block: 'nearest'});
                    });
                });
            });
            this.$nextTick(() => this.focusFirst());
        },
        destroy() {
            this.removeHook?.();
            document.removeEventListener('focusin', this.focusListener);
            for (const [node, inert] of this.inertNodes.reverse()) node.inert = inert;
            const index = stack.indexOf(this.root);
            if (index !== -1) stack.splice(index, 1);
            if (!stack.length) document.body.style.overflow = previousOverflow;
            if (this.returnFocus?.isConnected && !this.returnFocus.inert) this.returnFocus.focus();
        },
        focusables() {
            return Array.from(this.root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'))
                .filter((el) => el.getClientRects().length && !el.closest('[inert]'));
        },
        focusFirst() {
            const autofocus = this.root.querySelector('[autofocus]:not([disabled])');
            (autofocus ?? this.focusables()[0] ?? this.root).focus();
        },
        markDirty(event) {
            if (!event.target.closest('[data-modal-transient]')) this.dirty = true;
        },
        requestClose() {
            if (!this.isTop() || this.busy) return;
            if (this.dirty) {
                this.confirming = true;
                this.$nextTick(() => this.$refs.keepEditing.focus());
                return;
            }
            this.$dispatch(closeEvent);
        },
        discard() {
            if (this.busy) return;
            this.dirty = false;
            this.confirming = false;
            this.$dispatch(closeEvent);
        },
        keepEditing() {
            this.confirming = false;
            this.$nextTick(() => this.focusFirst());
        },
        guardClose(event) {
            const action = event.target.closest('[wire\\:click]')?.getAttribute('wire:click') ?? '';
            if (!/\$dispatch\(['"]close-(form|access|participants|team|report)['"]/.test(action)) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            this.requestClose();
        },
        escape(event) {
            if (!this.isTop()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            if (this.confirming) this.keepEditing();
            else this.requestClose();
        },
        trapTab(event) {
            if (!this.isTop()) return;
            const items = this.focusables();
            if (!items.length) { event.preventDefault(); this.root.focus(); return; }
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && (document.activeElement === first || !items.includes(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !items.includes(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        },
        beforeUnload(event) {
            if (!this.dirty && !this.busy) return;
            event.preventDefault();
            event.returnValue = '';
        },
    };
}
