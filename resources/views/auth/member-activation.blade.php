<x-guest-layout>
    <x-slot name="title">Activer mon compte</x-slot>
    <div class="mb-6">
        <p class="text-sm font-medium text-slate-500">{{ $organization }}</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Bienvenue, {{ $member->first_name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Votre responsable a préparé votre accès. Choisissez vos informations personnelles pour commencer.</p>
    </div>
    <form method="POST" action="{{ route('member-activation.store', ['token' => $token]) }}" class="space-y-5">
        @csrf
        <x-validation-summary />
        <div>
            <x-input-label for="username" value="Votre identifiant" />
            <x-text-input id="username" name="username" type="text" :value="old('username', $username)" class="mt-1 block w-full" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="50" aria-describedby="username-help username-error" />
            <p id="username-help" class="mt-1 text-xs text-slate-500">Proposé à partir de votre premier prénom. Vous pouvez le modifier.</p>
            <x-input-error id="username-error" :messages="$errors->get('username')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="email" value="Votre adresse e-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $member->email)" class="mt-1 block w-full" required autocomplete="email" maxlength="255" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="password" value="Choisissez votre mot de passe" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" minlength="12" maxlength="255" aria-describedby="password-help" />
            <p id="password-help" class="mt-1 text-xs text-slate-500">Au moins 12 caractères, avec des lettres et des chiffres.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmez le mot de passe" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" minlength="12" maxlength="255" />
        </div>
        <button type="submit" class="btn btn-primary w-full">Activer mon compte</button>
        <p class="text-xs text-slate-500">Ce lien personnel expire le {{ $expires->format('d/m/Y à H:i') }} et ne fonctionne qu’une seule fois.</p>
        <a href="{{ route('login') }}" class="block text-center text-sm text-slate-600 underline">J’ai déjà un compte</a>
    </form>
</x-guest-layout>
