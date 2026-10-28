import './bootstrap';
import { accessibilityHelp } from './accessibility';
const registerAccessibility = () => window.Alpine.data('accessibilityHelp', accessibilityHelp);
document.addEventListener('alpine:init', registerAccessibility, { once: true });
if (window.Alpine) registerAccessibility();
import './access-expiry';
import { navigationDrawer } from './navigation-drawer';
const registerNavigation = () => window.Alpine.data('navigationDrawer', navigationDrawer);
document.addEventListener('alpine:init', registerNavigation, { once: true });
if (window.Alpine) registerNavigation();

// Alpine est fourni et démarré par Livewire : ne pas l'importer ni le démarrer ici,
// sinon deux instances se disputent le DOM et les directives wire:* ne sont plus liées.

import { modalPanel } from './modal-panel';
const registerModal = () => window.Alpine.data('modalPanel', modalPanel);
document.addEventListener('alpine:init', registerModal, { once: true });
if (window.Alpine) registerModal();

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 402) {
                preventDefault();
                window.location.assign(document.querySelector('meta[name="billing-url"]')?.content || '/abonnement');
            } else if (status === 419) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'warning', message: 'Votre session a expiré. Rechargez la page avant de recommencer.' } }));
            } else if (status === 0 || status >= 500) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'L’action n’a pas pu aboutir. Vérifiez votre connexion puis réessayez.' } }));
            }
        });
    });
});
