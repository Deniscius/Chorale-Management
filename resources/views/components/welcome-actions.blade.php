<section aria-labelledby="welcome-actions-title" class="mb-8">
    <h2 id="welcome-actions-title" class="text-lg font-semibold text-slate-900">Que souhaitez-vous faire ?</h2>
    <p class="mt-2 text-slate-700">Ouvrez une rubrique ou écoutez son explication.</p>
    @php
        $actions = [
            ['module' => \App\Enums\UserRole::MODULE_LITURGIQUE, 'route' => 'works.index', 'icon' => 'book', 'label' => 'Retrouver un chant', 'help' => 'Retrouver un chant. Ouvrez ce bouton pour consulter les chants et les partitions de votre chorale.'],
            ['module' => \App\Enums\UserRole::MODULE_LITURGIQUE, 'route' => 'rehearsals.index', 'icon' => 'calendar', 'label' => 'Voir les répétitions', 'help' => 'Voir les répétitions. Ouvrez ce bouton pour retrouver les dates et les lieux des répétitions.'],
            ['module' => \App\Enums\UserRole::MODULE_MEMBERS, 'route' => 'members.index', 'icon' => 'users', 'label' => 'Voir les choristes', 'help' => 'Voir les choristes. Ouvrez ce bouton pour retrouver les membres de votre chorale.'],
            ['module' => \App\Enums\UserRole::MODULE_COMPTABILITE, 'route' => 'transactions.index', 'icon' => 'banknotes', 'label' => 'Recettes et dépenses', 'help' => 'Recettes et dépenses. Ouvrez ce bouton pour consulter les opérations comptables autorisées pour votre rôle.'],
            ['module' => \App\Enums\UserRole::MODULE_ACTIVITES, 'route' => 'activities.index', 'icon' => 'sparkles', 'label' => 'Voir les activités', 'help' => 'Voir les activités. Ouvrez ce bouton pour retrouver les activités de la chorale.'],
            ['module' => \App\Enums\UserRole::MODULE_COURRIERS, 'route' => 'correspondences.index', 'icon' => 'mail', 'label' => 'Voir les courriers', 'help' => 'Voir les courriers. Ouvrez ce bouton pour consulter le courrier de la chorale.'],
            ['module' => \App\Enums\UserRole::MODULE_INSTRUMENTS, 'route' => 'instruments.index', 'icon' => 'music', 'label' => 'Voir les instruments', 'help' => 'Voir les instruments. Ouvrez ce bouton pour retrouver le matériel de la chorale.'],
        ];
    @endphp
    <div class="shortcut-list mt-3">
        @foreach ($actions as $action)
            @can('view-module', $action['module'])
                <div class="shortcut-row">
                    <a href="{{ route($action['route']) }}" wire:navigate
                        class="shortcut-link">
                        <x-icon :name="$action['icon']" class="h-5 w-5 shrink-0" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                    <div class="shrink-0"><x-listen-button :text="$action['help']" /></div>
                </div>
            @endcan
        @endforeach
    </div>
</section>
