<div class="space-y-5">
    <x-page-header title="Accueil et suivi des nouveaux choristes" icon="users"
        subtitle="Accompagner les nouveaux membres jusqu’à leur intégration à la chorale." />
    <p class="text-sm text-slate-600">Les membres sont présentés par date d'adhésion et selon l'année de travail sélectionnée. Ce suivi est interne à la commission et distinct de la direction musicale.</p>
    @if ($this->joinRequests->isNotEmpty())
        <section class="overflow-x-auto rounded-xl border border-amber-200 bg-amber-50">
            <div class="border-b border-amber-200 px-4 py-3">
                <h2 class="font-semibold text-amber-950">Demandes reçues depuis le lien de la chorale</h2>
                <p class="mt-1 text-sm text-amber-900">Vérifiez chaque personne avant de créer son accès. Le lien d’activation est envoyé par e-mail.</p>
            </div>
            <table class="min-w-full divide-y divide-amber-100 text-sm">
                <thead class="text-left text-amber-950"><tr><th class="p-3">Personne</th><th class="p-3">Contact</th><th class="p-3">Pupitre</th><th class="p-3">Reçue le</th><th class="p-3">Décision</th></tr></thead>
                <tbody class="divide-y divide-amber-100">
                    @foreach ($this->joinRequests as $request)
                        <tr wire:key="join-request-{{ $request->id }}">
                            <td class="p-3 font-medium">{{ $request->first_name }} {{ $request->last_name }}</td>
                            <td class="p-3">{{ $request->email }}<br><span class="text-xs {{ $request->email_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $request->email_verified_at ? 'Adresse confirmée' : 'Confirmation attendue' }}</span>@if($request->phone)<br><span class="text-slate-600">{{ $request->phone }}</span>@endif</td>
                            <td class="p-3">{{ $request->voice ? \App\Enums\Voice::from($request->voice)->label() : 'À confirmer' }}</td>
                            <td class="p-3">{{ $request->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3">
                                @can('manage-users')
                                    <div class="flex gap-2">
                                        <x-confirm-action :call="'approveJoinRequest('.$request->id.')'" title="Accepter cette demande" message="La fiche sera créée et un lien d’activation personnel sera envoyé à cette adresse e-mail." label="Accepter" trigger-class="btn btn-primary text-xs" />
                                        <x-confirm-action :call="'declineJoinRequest('.$request->id.')'" title="Classer cette demande" message="La demande sera classée sans suite. Aucun compte ne sera créé." label="Refuser" trigger-class="btn btn-ghost text-xs text-red-700" button-class="btn bg-red-600 text-white hover:bg-red-700" />
                                    </div>
                                @else
                                    <span class="text-slate-600">En attente d’un administrateur</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
    <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
        <div><label for="onboarding-search" class="form-label">Rechercher un choriste</label>
            <input id="onboarding-search" type="search" wire:model.live.debounce.350ms="search" class="form-input mt-1 w-full" placeholder="Prénom ou nom…">
        </div>
        <div><label for="onboarding-status" class="form-label">Étape d'intégration</label>
            <select id="onboarding-status" wire:model.live="statusFilter" class="form-select mt-1 w-full">
                <option value="">Toutes les étapes</option>
                <option value="a_accueillir">À accueillir</option>
                <option value="en_accompagnement">En accompagnement</option>
                <option value="integre">Intégré</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600"><tr>
                <th scope="col" class="p-3">Choriste</th><th scope="col" class="p-3">Adhésion</th>
                <th scope="col" class="p-3">Suivi</th><th scope="col" class="p-3">Dernier contact</th><th scope="col" class="p-3">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($this->members as $member)
                    <tr wire:key="onboarding-{{ $member->id }}">
                        <td class="p-3 font-medium text-slate-800">{{ $member->full_name }}</td>
                        <td class="p-3">{{ $member->joined_on?->format('d/m/Y') ?? 'Non renseignée' }}</td>
                        <td class="p-3">{{ ['a_accueillir' => 'À accueillir', 'en_accompagnement' => 'En accompagnement', 'integre' => 'Intégré'][$member->onboardingFollowup?->status ?? 'a_accueillir'] }}</td>
                        <td class="p-3">{{ $member->onboardingFollowup?->contacted_on?->format('d/m/Y') ?? '—' }}</td>
                        <td class="p-3"><button type="button" class="font-semibold text-sky-700 hover:underline" wire:click="edit({{ $member->id }})">Mettre à jour</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-500">Aucun choriste pour cette recherche ou cette année.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->members->links() }}
    @if($editingId !== null)
        <section class="rounded-xl border border-sky-200 bg-white p-5">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-semibold">Mettre à jour l'accompagnement</h2>
                <div><label for="onboarding-step" class="form-label">Étape d'intégration</label>
                    <select id="onboarding-step" wire:model="status" class="form-select mt-1">
                        <option value="a_accueillir">À accueillir</option>
                        <option value="en_accompagnement">En accompagnement</option>
                        <option value="integre">Intégré</option>
                    </select><x-input-error :messages="$errors->get('status')"/></div>
                <div><label for="onboarding-contact" class="form-label">Date du dernier contact</label>
                    <input id="onboarding-contact" type="date" wire:model="contactedOn" class="form-input mt-1">
                    <x-input-error :messages="$errors->get('contactedOn')"/></div>
                <div><label for="onboarding-note" class="form-label">Note d'accompagnement (facultative)</label>
                    <textarea id="onboarding-note" wire:model="note" rows="3" maxlength="2000" class="form-textarea mt-1 w-full" placeholder="Éléments utiles à l’accueil, sans données médicales ni informations confidentielles."></textarea>
                    <x-input-error :messages="$errors->get('note')"/></div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" wire:click="cancel">Annuler</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer le suivi</button>
                </div>
            </form>
        </section>
    @endif
</div>
