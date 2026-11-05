<x-guest-layout>
    <x-slot name="title">Rejoindre la chorale</x-slot>
    <h1 class="text-2xl font-semibold text-slate-900">Demander à rejoindre la chorale</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">Votre responsable vérifie votre demande avant de créer votre accès. Votre adresse e-mail sert uniquement à vous envoyer le lien personnel d’activation.</p>

    <form method="POST" action="{{ $submitAction }}" class="mt-6 space-y-5">
        @csrf
        <x-validation-summary />
        <div class="grid gap-5 sm:grid-cols-2">
            <div><x-input-label for="first_name" value="Prénom" /><x-text-input id="first_name" name="first_name" :value="old('first_name')" class="mt-1 block w-full" required autocomplete="given-name" maxlength="100" /><x-input-error :messages="$errors->get('first_name')" class="mt-1" /></div>
            <div><x-input-label for="last_name" value="Nom" /><x-text-input id="last_name" name="last_name" :value="old('last_name')" class="mt-1 block w-full" required autocomplete="family-name" maxlength="100" /><x-input-error :messages="$errors->get('last_name')" class="mt-1" /></div>
        </div>
        <div><x-input-label for="email" value="Adresse e-mail" /><x-text-input id="email" name="email" type="email" :value="old('email')" class="mt-1 block w-full" required autocomplete="email" maxlength="255" /><p class="mt-1 text-xs text-slate-500">Elle recevra votre lien personnel après validation.</p><x-input-error :messages="$errors->get('email')" class="mt-1" /></div>
        <div><x-input-label for="phone" value="Téléphone (facultatif)" /><x-text-input id="phone" name="phone" type="tel" :value="old('phone')" class="mt-1 block w-full" autocomplete="tel" maxlength="30" /><x-input-error :messages="$errors->get('phone')" class="mt-1" /></div>
        <div><x-input-label for="voice" value="Pupitre souhaité (facultatif)" /><select id="voice" name="voice" class="form-select mt-1 block w-full"><option value="">À confirmer avec le responsable</option>@foreach(\App\Enums\Voice::options() as $value => $label)<option value="{{ $value }}" @selected(old('voice') === $value)>{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('voice')" class="mt-1" /></div>
        <button type="submit" class="btn btn-primary w-full">Envoyer ma demande</button>
        <a href="{{ route('tenant.entry', request()->route('slug')) }}" class="block text-center text-sm text-slate-600 underline">J’ai déjà un compte</a>
    </form>
</x-guest-layout>
