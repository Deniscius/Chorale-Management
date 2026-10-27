import test from 'node:test';
import assert from 'node:assert/strict';
import { accessibilityHelp } from '../../resources/js/accessibility.js';

function fixture({ supported = true, storageFails = false } = {}) {
    const spoken = [],
        sizes = [],
        stored = new Map();
    let cancelled = 0;
    const browser = {
        document: {
            documentElement: {
                classList: { toggle: (...args) => sizes.push(args) },
            },
        },
        localStorage: {
            getItem: (key) => {
                if (storageFails) throw Error();
                return stored.get(key);
            },
            setItem: (key, value) => {
                if (storageFails) throw Error();
                stored.set(key, value);
            },
        },
    };
    if (supported) {
        browser.SpeechSynthesisUtterance = class {
            constructor(text) {
                this.text = text;
            }
        };
        browser.speechSynthesis = {
            getVoices: () => [{ lang: 'fr-FR' }],
            speak: (value) => spoken.push(value),
            cancel: () => cancelled++,
        };
    }
    return {
        state: accessibilityHelp(browser),
        spoken,
        sizes,
        stored,
        cancelled: () => cancelled,
    };
}

test('audio is opt-in and reads only the explicit instruction in French', () => {
    const f = fixture();
    f.state.init();
    assert.equal(f.spoken.length, 0);
    f.state.read('Voir les répétitions');
    assert.equal(f.spoken[0].text, 'Voir les répétitions');
    assert.equal(f.spoken[0].lang, 'fr-FR');
    assert.equal(f.state.speaking, true);
    f.spoken[0].onend();
    assert.equal(f.state.speaking, false);
});
test('new instructions replace audio and stale callbacks cannot stop the new instruction', () => {
    const f = fixture();
    f.state.read('Premier');
    const oldEnd = f.spoken[0].onend;
    f.state.read('Deuxième');
    oldEnd();
    assert.equal(f.cancelled(), 1);
    assert.equal(f.state.speaking, true);
    f.state.destroy();
    assert.equal(f.cancelled(), 2);
    assert.equal(f.state.speaking, false);
});
test('unsupported speech and blocked storage do not break text enlargement', () => {
    const f = fixture({ supported: false, storageFails: true });
    f.state.init();
    f.state.read('Aide');
    assert.equal(f.state.available, false);
    f.state.toggleSize();
    assert.equal(f.state.large, true);
    assert.deepEqual(f.sizes.at(-1), ['comfortable-text', true]);
    f.state.toggleSize();
    assert.equal(f.state.large, false);
});
test('large text preference is restored and reversible', () => {
    const f = fixture();
    f.stored.set('chorale-large-text', 'true');
    f.state.init();
    assert.equal(f.state.large, true);
    f.state.toggleSize();
    assert.equal(f.stored.get('chorale-large-text'), 'false');
});
test('speech failure offers a visible fallback', () => {
    const f = fixture();
    f.state.read('Aide');
    f.spoken[0].onerror();
    assert.equal(f.state.speaking, false);
    assert.match(f.state.message, /indisponible/);
});
test('slower speech preference persists and restarts only explicit active guidance', () => {
    const f = fixture();
    f.state.init();
    f.state.toggleSlow();
    assert.equal(f.spoken.length, 0);
    assert.equal(f.stored.get('chorale-slow-speech'), 'true');
    f.state.read('Consigne');
    assert.equal(f.spoken[0].rate, 0.65);
    f.state.toggleSlow();
    assert.equal(f.spoken.at(-1).rate, 0.85);
    assert.equal(f.spoken.at(-1).text, 'Consigne');
});
test('saved slower speech preference is restored without automatically speaking', () => {
    const f = fixture();
    f.stored.set('chorale-slow-speech', 'true');
    f.state.init();
    assert.equal(f.state.slow, true);
    assert.equal(f.spoken.length, 0);
});
