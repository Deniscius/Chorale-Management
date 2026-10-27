@props(['guest' => false])
<aside aria-label="Aide à l’utilisation"
    class="mb-6 rounded-2xl border border-slate-300 bg-white p-4 text-slate-900 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <span class="font-semibold">Besoin d’aide ?</span>
        <x-listen-button :text="$guest
            ? 'Bienvenue. Pour vous connecter, indiquez votre identifiant et votre mot de passe, puis choisissez Se connecter. Si vous avez besoin d’aide, demandez à une personne de confiance. Ne lui communiquez pas votre mot de passe.'
            : 'Bienvenue dans votre chorale. Pour revenir à l’accueil, choisissez la maison dans le menu. Sur téléphone, le bouton Menu ouvre les rubriques. À l’accueil, choisissez une grande image pour ouvrir une rubrique. Le bouton haut-parleur explique chaque choix, sans l’ouvrir. Le bouton Grand texte agrandit l’affichage.'" />
        <button type="button" x-cloak @click="toggleSize()" :aria-pressed="large.toString()"
            class="help-control rounded-xl border border-slate-300 px-4 py-3 font-semibold">
            <span aria-hidden="true" class="mr-2 text-xl">A<span class="text-sm">A</span></span> Grand texte
        </button>
        <button type="button" x-cloak x-show="speaking" @click="stop()"
            class="help-control rounded-xl border border-slate-300 px-4 py-3 font-semibold">
            <span aria-hidden="true">■</span> Arrêter la lecture
        </button>
    </div>
    <p x-cloak x-show="!available" class="mt-2 text-sm">La lecture audio n’est pas disponible sur ce navigateur. Une personne de confiance peut vous accompagner.</p>
    <p x-cloak x-show="message" x-text="message" role="status" class="mt-2 text-sm"></p>
    <noscript><p>Pour utiliser l’aide audio et le grand texte, activez JavaScript. Vous pouvez aussi agrandir la page avec votre navigateur.</p></noscript>
</aside>
