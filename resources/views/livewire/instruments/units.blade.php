<div class="space-y-5">
    <x-page-header title="Objets et étiquettes" subtitle="Une étiquette différente pour chaque objet, même quand plusieurs objets sont identiques." icon="cube">
        <x-slot name="actions"><a href="{{ route('instruments.index') }}" wire:navigate class="btn btn-secondary max-w-full whitespace-normal">Retour au matériel</a></x-slot>
    </x-page-header>
    <x-material-navigation />
    <x-validation-summary />
    <x-material-step number="1" title="Retrouver les objets à étiqueter" icon="search"
        help="Une fiche Microphone avec une quantité de trois représente trois objets : Microphone numéro un, numéro deux et numéro trois. Chacun possède son propre code. Le numéro de l’objet n’est pas sa quantité.">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="unit-reference" class="form-label">Quel matériel ?</label>
                <select id="unit-reference" wire:model.live="instrumentId" class="form-select mt-1">
                    <option value="">Tous les types de matériel</option>
                    @foreach($references as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="unit-search" class="form-label">Rechercher un nom ou un code</label>
                <input id="unit-search" type="search" wire:model.live.debounce.250ms="search" class="form-input mt-1 w-full" placeholder="Microphone ou CM00000001-0001" autocomplete="off" spellcheck="false">
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="retired" class="rounded border-slate-300">Voir uniquement les objets retirés</label>
        </div>
        @if($reference)
            <p class="text-sm text-slate-700">{{ $reference->identified_count }} objet(s) avec un code pour {{ $reference->quantity }} objet(s) dans cette fiche.</p>
            @if($this->canManage() && $reference->identified_count < $reference->quantity)
                <button type="button" wire:click="generate" wire:loading.attr="disabled" class="btn btn-primary max-w-full whitespace-normal">Créer les codes manquants</button>
            @endif
        @endif
        <p class="text-sm text-slate-700">Cochez les objets dans la liste ci-dessous. Les codes existants sont conservés.</p>
    </x-material-step>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" role="region" aria-label="Liste des objets à étiqueter, défilement horizontal possible">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Un objet par ligne, avec son code permanent</caption>
            <thead class="bg-slate-50"><tr><th scope="col" class="p-3">À imprimer</th><th scope="col" class="p-3">Code permanent</th><th scope="col" class="p-3">Objet</th><th scope="col" class="p-3">Présence à l’inventaire</th><th scope="col" class="p-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($units as $unit)
                    <tr wire:key="unit-{{ $unit->id }}">
                        <td class="p-3">@unless($unit->retired_at)<input type="checkbox" wire:model.live="selected" wire:loading.attr="disabled" wire:target="instrumentId,search,retired" value="{{ $unit->id }}" aria-label="Sélectionner {{ $unit->instrument->name }} n° {{ $unit->ordinal }}, code {{ $unit->inventory_code }}" class="h-5 w-5 rounded border-slate-300">@endunless</td>
                        <td class="p-3 font-mono font-semibold">{{ $unit->inventory_code }}</td>
                        <td class="p-3"><span class="font-semibold">{{ $unit->instrument->name }} n° {{ $unit->ordinal }}</span><span class="block text-sm text-slate-600">Un seul objet physique</span></td>
                        <td class="p-3">{{ $unit->retired_at ? 'Retiré de l’inventaire' : 'En inventaire' }}</td>
                        <td class="p-3">
                            @if($this->canManage())
                                <x-confirm-action :call="'retire('.$unit->id.', '.($unit->retired_at ? 'false' : 'true').')'" :title="$unit->retired_at ? 'Réintégrer cet objet' : 'Retirer de l’inventaire'" :message="$unit->retired_at ? 'Le code d’origine sera conservé et la quantité augmentera de 1.' : 'La quantité diminuera de 1 et le code sera conservé. Pour un prêt temporaire, utilisez Prêts et retours.'" :label="$unit->retired_at ? 'Réintégrer' : 'Retirer'" trigger-class="help-control text-sm text-slate-700 underline" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-700">Aucun objet pour ces critères. Choisissez un type de matériel pour vérifier ses codes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $units->links() }}
    <form method="post" action="{{ route('instruments.labels') }}" target="_blank" class="space-y-4">
        @csrf
        @foreach($selected as $id)<input type="hidden" name="units[]" value="{{ $id }}">@endforeach
        <x-material-step number="2" title="Préparer la feuille d’étiquettes" icon="archive"
            help="Choisissez les objets à imprimer, puis la première case libre de votre feuille. Sur une feuille neuve, gardez la case un, en haut à gauche. La préparation ouvre un aperçu dans un nouvel onglet et n’imprime pas directement.">
            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="selectPage" wire:loading.attr="disabled" wire:target="instrumentId,search,retired" class="btn btn-secondary max-w-full whitespace-normal">Cocher les objets de cette page</button>
                <button type="button" wire:click="clearSelection" class="btn btn-secondary max-w-full whitespace-normal">Tout décocher</button>
                <span class="self-center text-sm text-slate-700" role="status" aria-live="polite">{{ count($selected) }} objet(s) sélectionné(s)</span>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="label-start" class="form-label">Première case libre de la feuille</label>
                    <select id="label-start" name="start" wire:model="start" class="form-select mt-1" aria-describedby="label-start-help">
                        @for($i=1;$i<=24;$i++)<option value="{{ $i }}">{{ $i === 1 ? '1 — feuille neuve' : $i }}</option>@endfor
                    </select>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="selected,selectPage,clearSelection,instrumentId,search,retired" class="btn btn-primary max-w-full whitespace-normal" @disabled(count($selected) === 0 || count($selected) > 240)>Voir {{ count($selected) }} étiquette(s) avant impression</button>
            </div>
            <p id="label-start-help" class="text-sm text-slate-700">La case 1 est en haut à gauche. Comptez ensuite de gauche à droite, ligne par ligne. Feuille A4 de 24 étiquettes, format 63,5 × 33,9 mm. Maximum 240 étiquettes par préparation.</p>
            <p class="text-sm text-slate-700">Dans l’aperçu, imprimez ou enregistrez en PDF à taille réelle (100 %). Faites d’abord un essai sur papier ordinaire.</p>
        </x-material-step>
    </form>

    <p class="text-sm text-slate-700">Pour ajouter des objets identiques, augmentez la quantité dans leur fiche de matériel. Un objet prêté reste dans l’inventaire : ne le retirez pas pour enregistrer un prêt.</p>
</div>
