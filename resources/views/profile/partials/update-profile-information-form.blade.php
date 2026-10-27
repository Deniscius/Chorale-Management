<section>
    <header>
        <h2 class="text-lg font-semibold text-slate-900">Mes informations personnelles</h2>
        <p class="mt-2 text-sm text-slate-700">Vous modifiez votre propre fiche de choriste. Les responsabilités et les accès sont attribués par un administrateur, en complément de votre appartenance à la chorale.</p>
    </header>
    @if($user->member_id && ! $user->member)
        <p role="alert" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">Votre fiche choriste n’est plus disponible. Contactez l’administrateur pour la vérifier ou la restaurer. Aucune autre fiche ne sera créée à sa place.</p>
    @else
    @unless($user->member)
        <p class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">Votre compte n’a pas encore de fiche choriste liée. Complétez vos prénom et nom pour créer votre propre fiche. Si une fiche existe déjà à votre adresse e-mail, contactez l’administrateur : elle ne sera pas fusionnée automatiquement.</p>
    @endunless
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')
        <x-validation-summary />
        <fieldset class="min-w-0 rounded-xl border border-slate-200 p-4">
            <legend class="px-2 font-semibold text-slate-900">1. Mon identité</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="first_name" label="Prénom(s)" :value="old('first_name', $user->member?->first_name)" maxlength="100" required autocomplete="given-name" />
                <x-input name="last_name" label="Nom" :value="old('last_name', $user->member?->last_name)" maxlength="100" required autocomplete="family-name" />
                <x-input name="birth_date" label="Date de naissance" type="date" :value="old('birth_date', $user->member?->birth_date?->format('Y-m-d'))" :max="today()->toDateString()" autocomplete="bday" help="Facultatif. Indiquez votre date de naissance réelle ; une date future est refusée." />
                <x-input name="profession" label="Profession ou activité" :value="old('profession', $user->member?->profession)" maxlength="255" autocomplete="organization-title" />
            </div>
        </fieldset>
        <fieldset class="min-w-0 rounded-xl border border-slate-200 p-4">
            <legend class="px-2 font-semibold text-slate-900">2. Mes coordonnées et ma connexion</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="username" label="Identifiant de connexion" :value="old('username', $user->username)" maxlength="50" pattern="[a-z0-9][a-z0-9._-]*" required autocomplete="username" autocapitalize="none" spellcheck="false" help="Minuscules, chiffres, points ou tirets. Conservez votre nouvel identifiant pour la prochaine connexion." />
                <x-input name="email" label="Adresse e-mail" type="email" :value="old('email', $user->email)" maxlength="255" required autocomplete="email" />
                <x-input name="phone" label="Téléphone" type="tel" :value="old('phone', $user->member?->phone)" maxlength="30" autocomplete="tel" help="Pour les SMS, utilisez un numéro international : +228 suivi des 8 chiffres au Togo." />
                <x-input name="address" label="Adresse" :value="old('address', $user->member?->address)" maxlength="255" autocomplete="street-address" />
            </div>
        </fieldset>
        <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-2 font-semibold text-slate-900">3. Mes rappels de répétition</legend>
            <div class="flex flex-wrap gap-4">
                @foreach(['notify_email' => 'Par e-mail', 'notify_sms' => 'Par SMS'] as $field => $label)
                    <input type="hidden" name="{{ $field }}" value="0">
                    <label class="help-control inline-flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $user->member?->$field ?? false)) class="h-5 w-5 rounded border-slate-300">{{ $label }}</label>
                @endforeach
            </div>
            <p class="mt-2 text-sm text-slate-700">Ces choix concernent les rappels. Ils ne donnent aucun rôle supplémentaire et restent soumis aux services activés par votre chorale.</p>
        </fieldset>
        <div class="flex flex-wrap items-center gap-4">
            <x-primary-button class="max-w-full whitespace-normal">Enregistrer mes informations</x-primary-button>
            @if(session('status') === 'profile-updated')<p role="status" class="text-sm font-medium text-emerald-700">Vos informations ont été enregistrées.</p>@endif
        </div>
    </form>
    @endif
</section>
