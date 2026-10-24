import test from 'node:test';
import assert from 'node:assert/strict';
import { initializePlatform } from '../../resources/js/platform.js';

function node(properties = {}) {
    return { dataset: {}, attributes: {}, handlers: {}, ...properties,
        addEventListener(name, handler) { (this.handlers[name] ??= []).push(handler); },
        setAttribute(name, value) { this.attributes[name] = value; },
        fire(name, event = {}) { for (const handler of this.handlers[name] ?? []) handler(event); },
    };
}

function fixture() {
    const password = node({ type: 'password', value: 'PrivateValue' });
    const toggle = node({ dataset: { passwordToggle: 'password' }, hidden: true });
    const select = node({ value: '' });
    const days = node({ disabled: false, required: false, value: '3' });
    const field = node({ hidden: false, querySelector: () => days });
    const hint = node({ textContent: '' });
    const submit = node({ disabled: false });
    const form = node({ querySelector: () => submit });
    const root = {
        getElementById: () => password,
        querySelector: selector => ({ '[data-access-action]': select, '[data-access-days]': field, '[data-access-hint]': hint })[selector],
        querySelectorAll: selector => ({ '[data-password-toggle]': [toggle], '[data-platform-submit]': [form] })[selector] ?? [],
    };
    return { root, password, toggle, select, days, field, hint, submit, form };
}

test('password visibility updates accessible state without changing the secret or binding twice', () => {
    const f = fixture();
    initializePlatform(f.root);
    initializePlatform(f.root);
    assert.equal(f.toggle.hidden, false);
    f.toggle.fire('click');
    assert.equal(f.password.type, 'text');
    assert.equal(f.toggle.attributes['aria-pressed'], 'true');
    assert.equal(f.toggle.attributes['aria-label'], 'Masquer le mot de passe');
    f.toggle.fire('click');
    assert.equal(f.password.type, 'password');
    assert.equal(f.password.value, 'PrivateValue');
    assert.equal(f.toggle.attributes['aria-pressed'], 'false');
});

test('only extending the trial submits a required day count and the action effect is explained', () => {
    const f = fixture();
    initializePlatform(f.root);
    assert.equal(f.field.hidden, true);
    assert.equal(f.days.disabled, true);
    f.select.value = 'extend_trial';
    f.select.fire('change');
    assert.equal(f.field.hidden, false);
    assert.equal(f.days.disabled, false);
    assert.equal(f.days.required, true);
    assert.match(f.hint.textContent, /délai sera ajouté/);
    f.select.value = 'resume';
    f.select.fire('change');
    assert.equal(f.days.disabled, true);
    assert.equal(f.days.required, false);
    assert.match(f.hint.textContent, /échéances existantes/);
    assert.equal(f.days.value, '3');
});

test('a second submission is blocked while the first valid form submission proceeds', () => {
    const f = fixture();
    initializePlatform(f.root);
    let prevented = 0;
    const event = { preventDefault() { prevented++; } };
    f.form.fire('submit', event);
    assert.equal(prevented, 0);
    assert.equal(f.submit.disabled, true);
    assert.equal(f.form.attributes['aria-busy'], 'true');
    f.form.fire('submit', event);
    assert.equal(prevented, 1);
});
