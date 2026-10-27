# Accueil illustré et aide audio

Une adaptation progressive est disponible sur l’accueil et les layouts de connexion et de gestion :

- Cartes illustrées et libellés simples, uniquement pour les modules autorisés. Les liens n’enregistrent aucune présence ni opération comptable.
- Un bouton haut-parleur distinct explique chaque carte avant son ouverture. Aucun son automatique, aucune lecture du contenu des formulaires, des mots de passe ou des données métier.
- Bouton « Arrêter la lecture », arrêt au changement de page et à la destruction du composant. Les consignes sont en français et utilisent SpeechSynthesis du navigateur ; la disponibilité et la qualité des voix dépendent du navigateur et du système. Certaines voix peuvent nécessiter Internet. Aucun service audio tiers n’est ajouté par l’application.
- Aide pas à pas liée à la route (accueil, chants, répétitions, choristes, comptabilité, matériel, activités, courriers), avec étapes illustrées et écoute séparée. Les données ne sont jamais modifiées par l’aide.
- Accueil centré sur les grandes cartes ; les chiffres et listes restent accessibles dans « Voir les détails de la chorale ».
- Option « Voix plus lente » (0,65 au lieu de 0,85), mémorisée localement et réversible.
- Menu mobile avec focus contenu, arrière-plan inerte, fermeture par Échap et retour au bouton d’ouverture ; adaptation lors du passage au bureau.
- Formulaire de connexion avec aide reliée aux champs et affichage temporaire du mot de passe sur demande ; ne pas utiliser cet affichage devant un tiers.
- Labels visibles pour les filtres des chants et répétitions, tableaux défilables au clavier, focus visible et respect de la préférence de mouvement réduit.
- Notifications conservées jusqu’à fermeture manuelle, avec texte de statut en plus de la couleur.
- Le réglage « Grand texte » augmente la taille de base à 125 %, reste réversible et est mémorisé seulement sur le navigateur de l’appareil. Il ne contient aucune donnée de compte. L’aide reste utilisable si le stockage est refusé.
- En l’absence de synthèse vocale, un message propose un accompagnement humain. L’application conserve les libellés et son fonctionnement sans audio ; elle n’est pas entièrement utilisable sans lecture à ce stade.

## Validation avant généralisation

Sur téléphone et ordinateur, avec les voix françaises installées :

1. Ouvrir la connexion : aucun son ne doit démarrer. Vérifier l’écoute et son arrêt, y compris au passage vers une autre page.
2. Se connecter en choriste puis en administrateur. Vérifier les cartes visibles et les droits, sans effectuer d’écriture métier.
3. Écouter chaque carte : seul son descriptif doit être lu et aucune navigation ne doit démarrer. Ouvrir ensuite son lien séparément.
4. Activer le grand texte, naviguer, recharger et le désactiver. À 320 pixels de large et avec le zoom navigateur à 200 %, vérifier l’absence de boutons ou champs inaccessibles.
5. Parcourir au clavier, avec VoiceOver ou TalkBack ; vérifier l’ordre du focus et les noms des boutons. Vérifier le fonctionnement quand SpeechSynthesis est absent ou en erreur.
6. Faire réaliser à des membres non lecteurs les parcours retrouver un chant et consulter une répétition, sans leur indiquer le bouton à utiliser. Observer la reconnaissance des pictogrammes et la compréhension de la voix avant d’étendre cette interface.

Les langues locales, des enregistrements vocaux validés par les membres, une présentation simplifiée de chaque formulaire et des tutoriels illustrés restent à concevoir avec les utilisateurs. Ne pas présenter les pictogrammes ou la voix française comme une solution universelle à la non-lecture. Aucun changement de schéma, de données ou de configuration de production n’est nécessaire pour cette première adaptation.

## Tests reproductibles dans un environnement local isolé

Prérequis : PHP et extensions Laravel, Composer, Node et dépendances du projet. `npm ci` installe Playwright et axe uniquement comme outils de développement. Installer son navigateur avec `npx playwright install chromium` ou fournir `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` si Chromium est déjà disponible.

Créer un jeu de données synthétiques neuf :

```sh
php tests/browser/prepare.php
```

Ce script force SQLite, neutralise toutes les URL PostgreSQL, refuse de réutiliser un fichier de base existant et ne lance aucun seeder de production ou de démonstration. Il crée deux comptes synthétiques, un chant et une répétition. Les identifiants aléatoires restent dans `storage/framework/testing/accessibility/credentials.json` (ignoré par Git). Il ne modifie pas `.env` ni `APP_KEY`.

Démarrer ensuite l’application avec cette même base. Exemple Bash depuis la racine du dépôt, dans un terminal dédié :

```sh
APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1:8091 \
APP_CONFIG_CACHE="$PWD/storage/framework/testing/accessibility/config.php" \
DB_CONNECTION=sqlite DB_DATABASE="$PWD/storage/framework/testing/accessibility/review.sqlite" \
DB_URL= DATABASE_URL= DATABASE_URL_UNPOOLED= \
CACHE_STORE=array SESSION_DRIVER=file SESSION_COOKIE=chorale_accessibility_review \
SESSION_SECURE_COOKIE=false QUEUE_CONNECTION=sync MAIL_MAILER=array TENANCY_ENABLED=true \
php artisan serve --host=127.0.0.1 --port=8091 --no-reload
```

Dans un autre terminal, lancer :

```sh
ACCESSIBILITY_CREDENTIALS_FILE="$PWD/storage/framework/testing/accessibility/credentials.json" \
npm run test:accessibility
```

Le scénario refuse un serveur distant ; ne jamais lui fournir des comptes réels. `ACCESSIBILITY_BASE_URL` permet de choisir un autre port local. Les rapports axe et captures sont enregistrés dans `test-results/accessibility` (ignoré par Git). Les parcours couvrent connexion, affichage/masquage du mot de passe, grand texte, audio sur demande, navigation Livewire, droits du choriste, menu mobile au clavier et vues 320/390/1440 pixels. La synthèse vocale est simulée pour vérifier les textes, le débit et le déclenchement ; cela ne valide pas le son réellement produit par un téléphone. Axe cible WCAG 2 A/AA et 2.1 AA sur ces seuls écrans, sans constituer une certification.

Autres contrôles : `npm run test:ui`, `npm run build` et `php vendor/bin/phpunit`. Après validation, arrêter le serveur local. Aucune bascule Render ou Neon n’est requise.

## Résultat de la validation du 27 octobre 2026

- SQLite : 615 tests, 3 ignorés, 3 040 assertions, aucune erreur (612 réussis).
- JavaScript : 30 tests réussis.
- `npm run build` réussi ; `npm audit --audit-level=moderate` : aucune vulnérabilité signalée.
- Chromium : scénarios sur les comptes choriste et administrateur, jeux de données vide puis synthétique rempli, menu mobile au clavier, affichage à 320/390/1440 pixels, audio simulé et repli sans synthèse vocale. Zéro erreur JavaScript et zéro violation axe sur les sept états de page stabilisés examinés. Les transitions temporaires de navigation ne font pas partie de l’audit axe.
- Un job CI indépendant rejoue ces contrôles sur une base SQLite synthétique ; son exécution sur GitHub doit être vérifiée après le push. Les essais manuels VoiceOver/TalkBack, le zoom navigateur à 200 %, les voix réelles des téléphones et les essais avec des membres non lecteurs restent à effectuer.
