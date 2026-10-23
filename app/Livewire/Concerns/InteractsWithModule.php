<?php

namespace App\Livewire\Concerns;

/**
 * Gestion centralisée des droits sur un module fonctionnel.
 *
 * Les composants Livewire déclarent le module qu'ils exposent via la
 * propriété $module ; le trait fournit les helpers d'autorisation
 * et les gardes à appeler dans mount()/actions().
 */
trait InteractsWithModule
{
    /**
     * Clé du module fonctionnel exposé par le composant.
     *
     * La classe utilisatrice doit déclarer la constante MODULE_KEY.
     */
    public function bootInteractsWithModule(): void
    {
        $this->authorizeView();
    }

    protected function moduleKey(): string
    {
        return static::MODULE_KEY;
    }

    /**
     * L'utilisateur courant peut-il consulter le module ?
     */
    public function canView(): bool
    {
        return auth()->user()?->can('view-module', $this->moduleKey()) ?? false;
    }

    /**
     * L'utilisateur courant peut-il écrire dans le module ?
     */
    public function canManage(): bool
    {
        return auth()->user()?->can('manage-module', $this->moduleKey()) ?? false;
    }

    protected function authorizeView(): void
    {
        abort_unless($this->canView(), 403, "Accès au module « {$this->moduleKey()} » refusé.");
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403, "Droits d'écriture sur « {$this->moduleKey()} » refusés.");
    }
}
