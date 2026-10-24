// Small progressive enhancements; every form remains usable without JavaScript.
export function actionPresentation(action) {
    return {
        days: action === 'extend_trial',
        message: {
            suspend: 'Les membres de cette chorale ne pourront plus accéder aux modules.',
            resume: 'La suspension sera retirée. Les échéances existantes resteront applicables.',
            extend_trial: 'Le délai sera ajouté à l’essai encore actif, ou commencera aujourd’hui si l’essai est expiré.',
        }[action] || 'Choisissez une action pour afficher son effet avant de confirmer.',
    };
}

export function initializePlatform(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach(button => {
        const input = root.getElementById(button.dataset.passwordToggle);
        if (!input || button.dataset.bound) return;
        button.dataset.bound = 'true';
        button.hidden = false;
        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
    const select = root.querySelector('[data-access-action]');
    if (select && !select.dataset.bound) {
        select.dataset.bound = 'true';
        const update = () => {
            const state = actionPresentation(select.value);
            const field = root.querySelector('[data-access-days]');
            const days = field?.querySelector('input');
            if (field && days) { field.hidden = !state.days; days.disabled = !state.days; days.required = state.days; }
            const hint = root.querySelector('[data-access-hint]');
            if (hint) hint.textContent = state.message;
        };
        select.addEventListener('change', update);
        update();
    }
    root.querySelectorAll('[data-platform-submit]').forEach(form => {
        if (form.dataset.bound) return;
        form.dataset.bound = 'true';
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
        });
    });
}

if (typeof document !== 'undefined') {
    initializePlatform();
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-platform-submit]').forEach(form => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = false;
        });
    });
}
