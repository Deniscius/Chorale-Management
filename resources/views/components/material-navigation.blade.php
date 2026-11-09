<nav aria-label="Parcours du matériel" class="material-navigation">
    @foreach ([
        ['route' => 'instruments.index', 'icon' => 'cube', 'label' => 'Voir le matériel', 'help' => 'Voir le matériel. Consultez les fiches et les quantités. Une fiche peut contenir plusieurs objets identiques.'],
        ['route' => 'instruments.units', 'icon' => 'archive', 'label' => 'Préparer les étiquettes', 'help' => 'Sélectionnez les objets, puis préparez la feuille d’étiquettes à imprimer.'],
        ['route' => 'instruments.movements', 'icon' => 'folder', 'label' => 'Suivre les prêts et retours', 'help' => 'Suivre les prêts et retours. Consultez les mouvements du matériel. Seules les personnes autorisées peuvent enregistrer un prêt ou un retour.'],
    ] as $item)
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route($item['route']) }}" wire:navigate @if(request()->routeIs($item['route'])) aria-current="page" @endif
                class="flex min-h-12 flex-1 items-center gap-3 font-semibold">
                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" /><span>{{ $item['label'] }}</span>
            </a>
            <div class="shrink-0"><x-listen-button :text="$item['help']" /></div>
        </div>
    @endforeach
</nav>
