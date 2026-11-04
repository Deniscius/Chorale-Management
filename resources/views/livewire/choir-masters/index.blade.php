<div class="space-y-5">
    <x-page-header title="Espace des maîtres de chœur" icon="music"
        subtitle="Responsables de la préparation musicale et de la conduite des répétitions.">
        <x-slot name="actions">
            <a href="{{ route('rehearsals.index') }}" wire:navigate class="btn btn-secondary">Répétitions</a>
            <a href="{{ route('works.index') }}" wire:navigate class="btn btn-secondary">Œuvres et partitions</a>
            @can('manage-users')
                <a href="{{ route('commissions.index') }}" wire:navigate class="btn btn-primary">Gérer les responsables</a>
            @endcan
        </x-slot>
    </x-page-header>
    <p class="text-sm text-slate-600">Chaque commission liturgique désigne son maître de chœur responsable et, si nécessaire, d'autres maîtres de chœur. Les attributions sont gérées par l'administrateur dans « Commissions → Déléguer le responsable ».</p>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($commissions as $commission)
            <section wire:key="choir-masters-{{ $commission->id }}" class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ $commission->name }}</h2>
                <div class="mt-3 rounded-lg bg-sky-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Maître de chœur responsable</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $commission->leader?->name ?? 'À désigner' }}</p>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-slate-800">Autres maîtres de chœur</h3>
                <ul class="mt-2 space-y-2">
                    @forelse($commission->choirMasters as $master)
                        <li wire:key="master-{{ $commission->id }}-{{ $master->id }}" class="rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ $master->name }}</li>
                    @empty
                        <li class="text-sm text-slate-500">Aucun maître de chœur associé pour le moment.</li>
                    @endforelse
                </ul>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">Aucune commission liturgique configurée. L'administrateur peut rattacher une commission au module liturgique.</div>
        @endforelse
    </div>
</div>
