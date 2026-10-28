<div>
    <x-page-header title="Vue financière des choristes" icon="users"
        subtitle="Consultez toute la chorale, un pupitre ou un choriste sans mélanger les données des autres espaces." />

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
        <x-stat-card label="Choristes" :value="$this->totals['members']" icon="users" color="slate" />
        <x-stat-card label="Cotisations attendues" :value="number_format($this->totals['due'], 0, ',', ' ').' FCFA'" icon="banknotes" color="blue" />
        <x-stat-card label="Cotisations versées" :value="number_format($this->totals['paid'], 0, ',', ' ').' FCFA'" icon="check" color="green" />
        <x-stat-card label="Reste à verser" :value="number_format($this->totals['balance'], 0, ',', ' ').' FCFA'" icon="alert" color="amber" />
        <x-stat-card label="Incidents en attente" :value="$this->totals['incidents']" :hint="$this->totals['absences'].' absence(s), '.$this->totals['lates'].' retard(s)'" icon="clock" color="red" />
    </div>

    <x-filter-bar class="mt-5">
        <div class="grid gap-3 md:grid-cols-4">
            <div><label for="overview-search" class="form-label">Rechercher</label><input id="overview-search" type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nom, e-mail ou téléphone"></div>
            <div><label for="overview-voice" class="form-label">Pupitre</label><select id="overview-voice" wire:model.live="voice" class="form-select"><option value="">Toute la chorale</option>@foreach($this->voiceOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="overview-member" class="form-label">Choriste</label><select id="overview-member" wire:model.live="memberId" class="form-select"><option value="">Tous les choristes</option>@foreach($this->members as $option)<option value="{{ $option->id }}">{{ $option->full_name }}</option>@endforeach</select></div>
            <div class="flex items-end"><button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">Réinitialiser</button></div>
        </div>
    </x-filter-bar>

    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Choriste</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Pupitre</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Attendu</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Versé</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Reste</th><th class="px-4 py-3 text-center text-xs font-semibold uppercase">Absences / retards</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Dossier</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($this->rows as $row)
                    @php $due=(float)($row->contribution_due ?? 0); $paid=(float)($row->contribution_paid ?? 0); @endphp
                    <tr wire:key="accounting-member-{{ $row->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $row->full_name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row->voice?->label() ?? '—' }} {{ $row->part }}</td>
                        <td class="px-4 py-3 text-right text-sm">{{ number_format($due, 0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-sm text-emerald-700">{{ number_format($paid, 0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold {{ $due > $paid ? 'text-amber-700' : 'text-slate-500' }}">{{ number_format(max(0, $due-$paid), 0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-center text-sm"><span class="text-red-700">{{ $row->rehearsal_absences + $row->activity_absences }}</span> / <span class="text-amber-700">{{ $row->rehearsal_lates + $row->activity_lates }}</span></td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('members.show', $row) }}" wire:navigate class="btn btn-secondary btn-sm">Voir le dossier</a></td>
                    </tr>
                @empty <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Aucun choriste ne correspond aux filtres.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->rows->links() }}</div>
</div>
