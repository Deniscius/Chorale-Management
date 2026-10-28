import test from 'node:test';
import assert from 'node:assert/strict';
import { navigationDrawer } from '../../resources/js/navigation-drawer.js';

function fixture() {
    let change,
        removed = false;
    const classes = new Set();
    const document = {
        documentElement: {
            classList: {
                add: (c) => classes.add(c),
                remove: (c) => classes.delete(c),
            },
        },
    };
    const stored = new Map();
    const button = {
        isConnected: true,
        focus() {
            document.activeElement = this;
        },
    };
    const first = {
        focus() {
            document.activeElement = this;
        },
        getClientRects: () => [1],
        closest: () => null,
    };
    const last = { ...first };
    document.activeElement = button;
    const state = navigationDrawer({
        document,
        sessionStorage: {
            getItem: (key) => stored.get(key) ?? null,
            setItem: (key, value) => stored.set(key, value),
        },
        matchMedia: () => ({
            matches: false,
            addEventListener: (name, callback) => (change = callback),
            removeEventListener: () => (removed = true),
        }),
    });
    state.$nextTick = (fn) => fn();
    state.$refs = {
        navigation: {
            querySelector: () => first,
            querySelectorAll: () => [first, last],
        },
        navigationScroll: { scrollTop: 0 },
    };
    state.init();
    return {
        state,
        document,
        button,
        first,
        last,
        classes,
        resize: (matches) => change({ matches }),
        removed: () => removed,
        stored,
    };
}

test('mobile navigation focuses the close control and restores the opener on close', () => {
    const f = fixture();
    f.state.open();
    assert.equal(f.document.activeElement, f.first);
    assert.equal(f.classes.has('mobile-menu-open'), true);
    f.state.close();
    assert.equal(f.document.activeElement, f.button);
    assert.equal(f.classes.size, 0);
});
test('sidebar keeps its scroll position between Livewire pages', () => {
    const f = fixture();
    f.state.$refs.navigationScroll.scrollTop = 318;
    f.state.rememberNavigationScroll();
    f.state.$refs.navigationScroll.scrollTop = 0;
    f.state.restoreNavigationScroll();
    assert.equal(f.state.$refs.navigationScroll.scrollTop, 318);
});
test('tab and shift tab wrap inside the mobile menu', () => {
    const f = fixture();
    f.state.open();
    let prevented = 0;
    f.state.trap({
        key: 'Tab',
        shiftKey: true,
        preventDefault: () => prevented++,
    });
    assert.equal(f.document.activeElement, f.last);
    f.state.trap({
        key: 'Tab',
        shiftKey: false,
        preventDefault: () => prevented++,
    });
    assert.equal(f.document.activeElement, f.first);
    assert.equal(prevented, 2);
});
test('switching to desktop unlocks scrolling and disables the modal trap', () => {
    const f = fixture();
    f.state.open();
    f.resize(true);
    assert.equal(f.state.desktop, true);
    assert.equal(f.state.sidebar, false);
    assert.equal(f.classes.size, 0);
    f.state.trap({
        key: 'Tab',
        preventDefault: () => assert.fail('desktop trapped'),
    });
    f.state.destroy();
    assert.equal(f.removed(), true);
});
