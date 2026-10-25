<x-platform-layout title="Installation du propriétaire" :guest="true">
    <main id="contenu" class="p-auth-content" style="min-height:100vh"><div class="p-auth-card">
        <p class="p-kicker">Première installation</p><h1>Votre espace<br>propriétaire.</h1>
        <p class="p-description">Créez votre accès personnel pour piloter les chorales, les abonnements et l’activité de la plateforme. Cette installation se ferme dès que le premier propriétaire est créé.</p>
        @if($errors->any())<div role="alert" class="p-alert p-alert-error" style="margin-bottom:20px"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
        <form action="{{ route('platform.setup.store') }}" method="POST" class="p-form" data-platform-submit>
            @csrf
            <div class="p-field"><label for="setup_token">Clé privée d’installation</label><input id="setup_token" name="setup_token" type="password" required minlength="32" maxlength="256" autocomplete="off" aria-describedby="setup-help"><small id="setup-help">Copiez la valeur PLATFORM_SETUP_TOKEN depuis les variables privées de votre service Render. Ne la partagez pas.</small></div>
            <div class="p-field"><label for="name">Votre nom</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></div>
            <div class="p-field"><label for="email">Votre adresse e-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username"></div>
            <div class="p-field"><label for="password">Votre mot de passe</label><input id="password" name="password" type="password" required minlength="16" maxlength="255" autocomplete="new-password" aria-describedby="password-help"><small id="password-help">Au moins 16 caractères, avec des lettres et des chiffres. Utilisez un mot de passe unique.</small></div>
            <div class="p-field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="16" maxlength="255" autocomplete="new-password"></div>
            <section class="p-help" style="margin:12px 0;padding:16px" aria-labelledby="mfa-title">
                <h2 id="mfa-title" class="p-health-title">Double authentification</h2>
                <p>Dans votre application d’authentification, ajoutez un compte par saisie manuelle : type TOTP, SHA-1, 6 chiffres, période de 30 secondes. Utilisez cette clé :</p>
                <p><code style="overflow-wrap:anywhere;user-select:all">{{ $secret }}</code></p>
                <p>Conservez cette clé dans votre gestionnaire sécurisé. Cette préparation expire après 10 minutes ; après expiration, rechargez la page et remplacez la clé dans votre application.</p>
            </section>
            <div class="p-field"><label for="code">Code affiché par votre application</label><input id="code" name="code" class="p-otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000"></div>
            <button class="p-btn p-btn-primary" type="submit"><span data-submit-label>Créer mon espace propriétaire</span><x-icon name="arrow-right" /></button>
        </form>
        <p class="p-auth-trust"><x-icon name="lock" />Accès réservé au détenteur de la clé d’installation</p>
    </div></main>
</x-platform-layout>
