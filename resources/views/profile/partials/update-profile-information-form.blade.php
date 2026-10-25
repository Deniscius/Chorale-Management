<section>
    <header><h2 class="text-lg font-semibold text-slate-900">Mes informations personnelles</h2><p class="mt-2 text-sm text-slate-600">Vous seul pouvez modifier ces informations. La direction gère votre voix, votre pupitre et vos affectations.</p></header>
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf @method('patch')
        <x-validation-summary />
        @if($user->member)
            <input type="hidden" name="name" value="{{ $user->name }}">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['first_name' => 'Prénom(s)', 'last_name' => 'Nom'] as $field => $label)
                    <div><x-input-label :for="$field" :value="$label"/><x-text-input :id="$field" :name="$field" :value="old($field, $user->member->$field)" class="mt-1 w-full" maxlength="100" required/><x-input-error :messages="$errors->get($field)"/></div>
                @endforeach
            </div>
        @else
            <div><x-input-label for="name" value="Nom complet"/><x-text-input id="name" name="name" :value="old('name', $user->name)" class="mt-1 w-full" maxlength="255" required autocomplete="name"/><x-input-error :messages="$errors->get('name')"/></div>
        @endif
        <div><x-input-label for="username" value="Identifiant de connexion"/><x-text-input id="username" name="username" :value="old('username', $user->username)" class="mt-1 w-full" maxlength="50" pattern="[a-z0-9][a-z0-9._-]*" required autocomplete="username" autocapitalize="none" spellcheck="false"/><p class="mt-1 text-xs text-slate-500">Lettres minuscules, chiffres, points ou tirets. Conservez votre nouvel identifiant pour la prochaine connexion.</p><x-input-error :messages="$errors->get('username')"/></div>
        <div><x-input-label for="email" value="Adresse e-mail"/><x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" class="mt-1 w-full" maxlength="255" required autocomplete="email"/><x-input-error :messages="$errors->get('email')"/></div>
        @if($user->member)
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['phone'=>'Téléphone','birth_date'=>'Date de naissance','profession'=>'Profession','address'=>'Adresse'] as $field=>$label)
                    <div><x-input-label :for="$field" :value="$label"/><x-text-input :id="$field" :name="$field" :type="$field === 'birth_date' ? 'date' : ($field === 'phone' ? 'tel' : 'text')" :value="old($field, $field === 'birth_date' ? $user->member->birth_date?->format('Y-m-d') : $user->member->$field)" class="mt-1 w-full"/><x-input-error :messages="$errors->get($field)"/></div>
                @endforeach
            </div>
            <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 text-sm font-semibold">Mes rappels de répétition</legend><div class="flex flex-wrap gap-4">
                @foreach(['notify_email'=>'Par e-mail','notify_sms'=>'Par SMS'] as $field=>$label)
                    <input type="hidden" name="{{ $field }}" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $user->member->$field)) class="rounded border-slate-300">{{ $label }}</label>
                @endforeach
            </div><p class="mt-2 text-xs text-slate-500">Les rappels dépendent des services activés par votre chorale et des coordonnées renseignées.</p></fieldset>
        @endif
        <div class="flex flex-wrap items-center gap-4"><x-primary-button>Enregistrer mes informations</x-primary-button>@if(session('status') === 'profile-updated')<p role="status" class="text-sm font-medium text-emerald-700">Vos informations ont été enregistrées.</p>@endif</div>
    </form>
</section>
