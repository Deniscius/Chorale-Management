<div>
    <x-page-header title="Utilisateurs" icon="cog"
        :subtitle="'Comptes d\'accès et rôles applicatifs — '.$this->stats['total'].' compte(s).'">
        <x-slot name="actions">
            <button type="button" wire:click="create"
                class="btn btn-primary">
                <x-icon name="plus" class="h-4 w-4" />
                Nouvel utilisateur
            </button>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Comptes actifs" :value="$this->stats['total']" icon="users" color="slate" :href="route('users.index', []).'#resultats'" />
        <x-stat-card label="Administrateurs" :value="$this->stats['admins']" icon="cog" color="red" :href="route('users.index', ['role'=>'admin']).'#resultats'" />
        <x-stat-card label="Membres de commission" :value="$this->stats['commissions']" icon="sparkles" color="purple"
            :hint="$this->stats['commissions_libelles']" :href="route('users.index', ['vue'=>'commissions']).'#resultats'" />
        <x-stat-card label="Rattachés à un choriste" :value="$this->stats['liens']" icon="music" color="green" :href="route('users.index', ['vue'=>'lies']).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Rechercher un nom, un identifiant ou une adresse e-mail…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="role" class="sr-only">Rôle</label>
                <select id="role" wire:model.live="role"
                    class="form-select">
                    <option value="">Tous les rôles</option>
                    @foreach ($this->roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->role !== '')
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters"
                    class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            </div>
        @endif
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table id="resultats" :headers="[
            ['label' => 'Utilisateur'],
            ['label' => 'Rôle'],
            ['label' => 'Choriste rattaché'],
            ['label' => 'Dernière activité', 'align' => 'text-right'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun utilisateur" empty-message="Aucun compte ne correspond aux filtres sélectionnés."
            empty-icon="users">
            @forelse ($this->users as $user)
                <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold uppercase text-slate-600">
                                {{ \Illuminate\Support\Str::of($user->name)->substr(0, 2)->upper() }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    <span class="font-medium text-slate-600">{{ $user->username }}</span>
                                    · {{ $user->email }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$user->role" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($user->member)
                            {{ $user->member->full_name }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-xs text-slate-500">
                        {{ $user->updated_at?->diffForHumans() ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <a href="{{ route('users.commissions', $user) }}" wire:navigate class="help-control mb-2 inline-flex items-center rounded-lg px-2 text-sm font-semibold text-indigo-700 underline">Commissions et accès</a>
                        <button type="button" wire:click="openPasswordReset({{ $user->id }})"
                            class="help-control mb-2 inline-flex items-center rounded-lg px-2 text-sm font-semibold text-amber-700 underline">
                            Réinitialiser le mot de passe
                        </button>
                        <x-row-actions edit="edit" :id="$user->id" remove="delete"
                            :confirm="'Supprimer le compte de '.$user->name.' ?'" />
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->users->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'"
            subtitle="Définissez l'identité, le rôle et l'accès du compte.">
            <livewire:users.form :user="$this->editingUser" />
        </x-modal-panel>
    @endif

    @if ($showPasswordReset && $this->resetUser)
        <x-modal-panel close-event="close-password-reset" size="lg"
            title="Aider à réinitialiser le mot de passe"
            :subtitle="'Compte de '.$this->resetUser->name">
            @if ($generatedResetLink === '')
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                    Le lien remplacera tout lien précédent, expirera dans {{ config('auth.passwords.users.expire') }} minutes et ne pourra être utilisé qu’une seule fois. Vérifiez l’identité de la personne avant de le transmettre en privé.
                </div>
                <form wire:submit="generatePasswordResetLink" class="mt-5 space-y-4">
                    <div>
                        <label for="reset-current-password" class="form-label">Votre mot de passe administrateur</label>
                        <input id="reset-current-password" type="password" wire:model="currentPassword" required autocomplete="current-password" class="form-input">
                        <x-input-error :messages="$errors->get('currentPassword')" />
                    </div>
                    <button class="btn btn-primary">Générer le lien sécurisé</button>
                </form>
            @else
                <div x-data="{ copied: false }" class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
                    <h3 class="font-semibold text-amber-950">Copiez ce lien maintenant</h3>
                    <p class="mt-1 text-sm text-amber-900">Il disparaîtra à la fermeture de cette fenêtre. Envoyez-le uniquement à la personne concernée, dans une conversation privée.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input x-ref="resetLink" readonly value="{{ $generatedResetLink }}" class="form-input min-w-0 flex-1 font-mono text-xs" aria-label="Lien sécurisé de réinitialisation">
                        <button type="button" class="btn btn-primary shrink-0" @click="navigator.clipboard.writeText($refs.resetLink.value); copied=true; window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Lien copié.' } }))">
                            <x-icon name="copy" class="h-4 w-4" /><span x-text="copied ? 'Copié' : 'Copier le lien'"></span>
                        </button>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-600">Après utilisation, le lien devient invalide. Pour annuler ce lien avant son utilisation, générez-en un nouveau pour ce même compte.</p>
            @endif
        </x-modal-panel>
    @endif
</div>
