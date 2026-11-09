@props(['guest' => false])
<aside aria-label="Aide à l’utilisation"
    class="accessibility-tools">
    <div class="flex flex-wrap items-center gap-3">
        <span class="hidden font-semibold sm:inline">Besoin d’aide ?</span>
        <x-listen-button :text="$guest
            ? (request()->routeIs('password.*')
                ? 'Pour récupérer votre accès, suivez les indications de ce formulaire. Demandez de l’aide à une personne de confiance si nécessaire. Ne partagez ni votre mot de passe ni le lien de réinitialisation.'
                : 'Bienvenue. Pour vous connecter, indiquez votre identifiant et votre mot de passe, puis choisissez Se connecter. Si vous avez besoin d’aide, demandez à une personne de confiance. Ne lui communiquez pas votre mot de passe.')
            : 'Bienvenue dans votre chorale. Pour revenir à l’accueil, choisissez la maison dans le menu. Sur téléphone, le bouton Menu ouvre les rubriques. À l’accueil, choisissez une rubrique pour l’ouvrir. Le bouton haut-parleur explique chaque choix, sans l’ouvrir. Le bouton Grand texte agrandit l’affichage.'" />
        <button type="button" x-cloak @click="toggleSize()" :aria-pressed="large.toString()"
            class="help-control rounded-xl border border-slate-300 px-4 py-3 font-semibold">
            <span aria-hidden="true" class="mr-2 text-xl">A<span class="text-sm">A</span></span> Grand texte
        </button>
        <button type="button" x-cloak x-show="available" @click="toggleSlow()" :aria-pressed="slow.toString()" class="help-control border border-slate-300 px-3 py-2 font-semibold">Voix plus lente</button>
        <button type="button" x-cloak x-show="speaking" @click="stop()"
            class="help-control rounded-xl border border-slate-300 px-4 py-3 font-semibold">
            <span aria-hidden="true">■</span> Arrêter la lecture
        </button>
    </div>
    @unless ($guest)
        <x-page-guide />
    @endunless
    <p x-cloak x-show="!available" class="mt-2 text-sm">La lecture audio n’est pas disponible sur ce navigateur. Une personne de confiance peut vous accompagner.</p>
    <p x-cloak x-show="message" x-text="message" role="status" class="mt-2 text-sm"></p>
    <noscript><p>Pour utiliser l’aide audio et le grand texte, activez JavaScript. Vous pouvez aussi agrandir la page avec votre navigateur.</p></noscript>
</aside>
