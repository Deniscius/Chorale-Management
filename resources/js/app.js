import './bootstrap';

// Alpine est fourni et démarré par Livewire : ne pas l'importer ni le démarrer ici,
// sinon deux instances se disputent le DOM et les directives wire:* ne sont plus liées.

import { modalPanel } from './modal-panel';
const registerModal = () => window.Alpine.data('modalPanel', modalPanel);
document.addEventListener('alpine:init', registerModal, { once: true });
if (window.Alpine) registerModal();
