<nav aria-label="Parcours du matériel" class="mb-5 grid gap-3 sm:grid-cols-3">
    @foreach ([
        ['route' => 'instruments.index', 'icon' => 'cube', 'label' => 'Voir le matériel', 'help' => 'Voir le matériel. Consultez les fiches et les quantités. Une fiche peut contenir plusieurs objets identiques.'],
        ['route' => 'instruments.units', 'icon' => 'archive', 'label' => 'Préparer les étiquettes', 'help' => 'Sélectionnez les objets, puis préparez la feuille d’étiquettes à imprimer.'],
        ['route' => 'instruments.movements', 'icon' => 'folder', 'label' => 'Suivre les prêts et retours', 'help' => 'Suivre les prêts et retours. Consultez les mouvements du matériel. Seules les personnes autorisées peuvent enregistrer un prêt ou un retour.'],
    ] as $item)
        <div class="rounded-2xl border border-slate-200 bg-white p-3">
            <a href="{{ route($item['route']) }}" wire:navigate @if(request()->routeIs($item['route'])) aria-current="page" @endif
                class="flex min-h-24 items-center gap-3 rounded-xl bg-indigo-50 p-3 font-bold text-indigo-950 hover:bg-indigo-100">
                <x-icon :name="$item['icon']" class="h-9 w-9 shrink-0" /><span>{{ $item['label'] }}</span>
            </a>
            <div class="mt-3"><x-listen-button :text="$item['help']" /></div>
        </div>
    @endforeach
</nav>
