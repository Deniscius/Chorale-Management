<x-platform-layout title="Connexion propriétaire" :guest="true">
    <div class="p-auth-shell">
        <aside class="p-auth-story" aria-label="Gestion Chorale">
            <div class="p-brand"><span class="p-brand-mark"><x-icon name="music" /></span><span>Gestion Chorale<small>Espace propriétaire</small></span></div>
            <div class="p-auth-copy"><p class="p-kicker">Le pilotage, en toute simplicité</p><h2>Votre plateforme. <br><em>Une vue d’ensemble.</em></h2><p>Gardez le fil de vos chorales, accompagnez vos clients et suivez la vie de votre application.</p><ul class="p-auth-benefits"><li><x-icon name="check-circle" />Vos chorales et leurs accès réunis</li><li><x-icon name="check-circle" />L’activité et les performances à portée de main</li><li><x-icon name="check-circle" />Chaque action importante, tracée</li></ul></div>
            <p class="p-auth-bottom">© {{ now()->year }} Gestion Chorale · Espace réservé au propriétaire</p><div class="p-auth-art" aria-hidden="true"></div>
        </aside>
        <main id="contenu" class="p-auth-content"><div class="p-auth-card">
            <div class="p-auth-lock"><x-icon name="lock" /></div><p class="p-kicker">Accès propriétaire</p><h1>Connectez-vous<br>à votre espace.</h1><p class="p-description">Retrouvez le pilotage de votre plateforme avec vos identifiants et votre code de sécurité.</p>
            @if(session('status'))<div role="status" class="p-alert" style="margin-bottom:20px">{{ session('status') }}</div>@endif
            @if($errors->any())<div role="alert" class="p-alert p-alert-error" style="margin-bottom:20px"><x-icon name="warning" /><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
            <form action="{{ route('platform.login.store') }}" method="POST" class="p-form" data-platform-submit>
                @csrf
                <div class="p-field"><label for="email">Adresse e-mail propriétaire</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" placeholder="vous@exemple.com" autofocus @if($errors->has('email')) aria-invalid="true" @endif></div>
                <div class="p-field"><label for="password">Mot de passe</label><div class="p-password"><input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password" placeholder="Votre mot de passe"><button type="button" data-password-toggle="password" aria-label="Afficher le mot de passe" aria-pressed="false" hidden><x-icon name="eye" /></button></div></div>
                <div class="p-field"><label for="code">Code de sécurité à 6 chiffres</label><input id="code" name="code" class="p-otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000" aria-describedby="code-help" @if($errors->has('code')) aria-invalid="true" @endif><small id="code-help">Le code affiché dans votre application d’authentification.</small></div>
                <button class="p-btn p-btn-primary" type="submit"><span data-submit-label>Accéder au pilotage</span><x-icon name="arrow-right" /></button>
            </form>
            <details class="p-help"><summary>Vous avez perdu l’accès à votre compte ?</summary><p>La récupération s’effectue auprès de votre responsable d’hébergement, depuis le serveur de l’application.</p></details>
            <p class="p-auth-trust"><x-icon name="lock" />Double authentification obligatoire</p>
        </div></main>
    </div>
</x-platform-layout>
