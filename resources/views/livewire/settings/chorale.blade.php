<div>
    <x-page-header title="Informations de la chorale" icon="building"
        subtitle="Nom, coordonnées et logo propres à votre espace chorale. Le logo reste réservé aux pages après connexion." />

    @if($logoMissing ?? false)<p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">L’ancien logo n’est plus disponible. Réimportez votre fichier original pour rétablir l’identité visuelle.</p>@endif
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        @if($space = request()->attributes->get('tenant'))
            <x-card title="Adresse de votre chorale" subtitle="Partagez cette adresse avec vos membres pour ouvrir la connexion de votre chorale." class="lg:col-span-3">
                <label for="chorale-access-url" class="text-sm font-medium text-slate-700">Lien d’accès personnel à la chorale</label>
                <input id="chorale-access-url" type="url" readonly class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm" value="{{ route('tenant.entry', ['slug' => $space->slug]) }}" onclick="this.select()">
                <p class="mt-2 text-sm text-slate-500">Cette adresse est propre à votre espace. La connexion et les autorisations restent obligatoires.</p>
            </x-card>
        @endif
        {{-- Logo --}}
        <x-card title="Logo" subtitle="PNG, JPG ou WEBP — 2 Mo maximum." class="lg:col-span-1">
            <div class="flex flex-col items-center gap-4 text-center">
                <div
                    class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3 text-slate-400">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu du logo"
                            class="h-full w-full object-contain" />
                    @elseif (app(\App\Services\ChoraleIdentity::class)->hasLogo())
                        <x-chorale-logo icon-class="h-10 w-10" />
                    @else
                        <x-icon name="image" class="h-10 w-10" />
                    @endif
                </div>

                <div class="w-full">
                    <label for="logo"
                        class="btn btn-secondary w-full cursor-pointer justify-center">
                        <x-icon name="upload" class="h-4 w-4" />
                        Choisir un logo
                        <input id="logo" type="file" wire:model="logo" class="sr-only"
                            accept="image/png,image/jpeg,image/webp" />
                    </label>
                    <p wire:loading wire:target="logo" class="mt-2 text-xs text-slate-500">Téléversement…</p>
                </div>

                <x-input-error :messages="$errors->get('logo')" class="text-center" />

                @if ($logo)
                    <button type="button" wire:click="cancelLogoUpload" class="btn btn-soft btn-sm">
                        Annuler la sélection
                    </button>
                @elseif (app(\App\Services\ChoraleIdentity::class)->hasLogo())
                    <x-confirm-action call="removeLogo" title="Supprimer le logo" message="Le logo de la chorale ne sera plus affiché sur ses documents et espaces authentifiés."
                        label="Supprimer le logo" icon="trash" trigger-class="btn btn-ghost btn-sm text-red-600 hover:bg-red-50" button-class="btn bg-red-600 text-white hover:bg-red-700" />
                @endif
            </div>
        </x-card>

        {{-- Informations --}}
        <x-card title="Coordonnées" subtitle="Ces informations apparaissent sur la page de connexion et dans la coque."
            class="lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Nom de la chorale" />
                    <x-text-input id="name" type="text" wire:model="name" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="tagline" value="Slogan / sous-titre" />
                    <x-text-input id="tagline" type="text" wire:model="tagline" class="mt-1 block w-full"
                        placeholder="Espace de gestion interne" />
                    <x-input-error :messages="$errors->get('tagline')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="email" value="E-mail de contact" />
                    <x-text-input id="email" type="email" wire:model="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="phone" value="Téléphone" />
                    <x-text-input id="phone" type="text" wire:model="phone" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="address" value="Adresse" />
                    <x-text-input id="address" type="text" wire:model="address" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="website" value="Site web" />
                    <x-text-input id="website" type="url" wire:model="website" class="mt-1 block w-full"
                        placeholder="https://" />
                    <x-input-error :messages="$errors->get('website')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end border-t border-slate-200 pt-4">
                <button type="submit" class="btn btn-primary">
                    <span wire:loading.remove wire:target="save">Enregistrer</span>
                    <span wire:loading wire:target="save">Enregistrement…</span>
                </button>
            </div>
        </x-card>
    </form>

    <livewire:settings.letterheads />
</div>
