<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\ChoraleBranding;
use App\Services\ChoraleIdentity;
use App\Services\PrivateFiles;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Gestion de l'identité de la chorale : nom, informations et logo.
 *
 * Réservé à l'administrateur (Gate « manage-settings »).
 */
class Chorale extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('nullable|string|max:150')]
    public ?string $tagline = null;

    #[Validate('nullable|email|max:150')]
    public ?string $email = null;

    #[Validate('nullable|string|max:40')]
    public ?string $phone = null;

    #[Validate('nullable|string|max:255')]
    public ?string $address = null;

    #[Validate('nullable|url|max:255')]
    public ?string $website = null;

    /**
     * Fichier logo en cours de téléversement (temporaire Livewire).
     */
    #[Validate('nullable|image|mimes:png,jpg,jpeg,webp|max:2048')]
    public $logo = null;

    public function mount(ChoraleIdentity $identity): void
    {
        $this->authorizeManage();

        $this->name = $identity->name();
        $this->tagline = $identity->tagline();
        $this->email = $identity->email();
        $this->phone = $identity->phone();
        $this->address = $identity->address();
        $this->website = $identity->website();
    }

    public function save(ChoraleIdentity $identity): void
    {
        $this->authorizeManage();

        $this->validate();

        app(ChoraleBranding::class)->save([
            ChoraleIdentity::KEY_NAME => $this->name,
            ChoraleIdentity::KEY_TAGLINE => $this->tagline,
            ChoraleIdentity::KEY_EMAIL => $this->email,
            ChoraleIdentity::KEY_PHONE => $this->phone,
            ChoraleIdentity::KEY_ADDRESS => $this->address,
            ChoraleIdentity::KEY_WEBSITE => $this->website,
        ], $this->logo);
        $this->reset('logo');

        // Le logo et les informations viennent de changer : on réinitialise la
        // mémoire de requête pour que la coque affiche les nouvelles valeurs
        // dès le rendu suivant.
        $identity->forget();

        $this->dispatch('toast', type: 'success', message: 'Informations de la chorale enregistrées.');
    }

    /**
     * Supprime le logo courant.
     */
    public function removeLogo(ChoraleIdentity $identity): void
    {
        $this->authorizeManage();

        app(ChoraleBranding::class)->removeLogo();

        $identity->forget();

        $this->dispatch('toast', type: 'success', message: 'Logo supprimé.');
    }

    /**
     * Retire le logo en attente d'enregistrement.
     */
    public function cancelLogoUpload(): void
    {
        $this->reset('logo');
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403, "Paramètres réservés à l'administrateur.");
    }

    public function render(): View
    {
        $path = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);

        return view('livewire.settings.chorale', ['logoMissing' => $path && ! app(PrivateFiles::class)->exists($path)]);
    }
}
