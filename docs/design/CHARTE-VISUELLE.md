# Le carnet de chœur

## Audit avant intervention

L’application comprend un espace de chorale, un espace propriétaire distinct, les écrans d’accès, les fiches de gestion, les archives et cinq familles de documents imprimables. Les routes et les contrôles d’accès restent la référence fonctionnelle.

Les défauts observés : des cartes identiques pour les raccourcis, les chiffres et les contenus ; une navigation très dense ; des titres précédés de pictogrammes décoratifs ; des palettes froides ou multicolores selon le module ; des dégradés sans fonction ; une aide utile mais visuellement plus importante que le contenu ; des formulaires et documents qui ne partagent pas la même identité.

## Charte

- **Prune de chœur `#493447`** : navigation, actions principales, repère de page. Évoque les tenues de chœur sans emprunter les codes des applications financières.
- **Encre `#302c32`** : texte courant et chiffres.
- **Papier `#faf9f6`** et **blanc `#ffffff`** : fond de travail et surfaces de saisie.
- **Ligne `#dedad6`** : séparations des listes et des groupes de champs.
- **Laiton `#9a743e`** : petits repères et filet de signature ; jamais du texte clair peu contrasté.

Alegreya pour les titres, inspirée de la composition des recueils ; Source Sans 3 pour la navigation, les libellés et les données. Les fontes sont hébergées avec l’application : aucune requête vers un fournisseur de polices au chargement. Les tableaux conservent des chiffres tabulaires et des montants alignés. Les couleurs de statut (erreur, alerte, réussite) gardent leur sens.

## Composition

Une colonne de navigation prune, un bandeau de contexte sobre, un titre typographique aligné à gauche et des actions à droite. Les chiffres se lisent dans des bandes de synthèse ouvertes ; les listes utilisent des séparations horizontales. Les formulaires et tableaux sont des surfaces blanches, avec des angles modérés. Les raccourcis sont des liens lisibles avec de petites icônes fonctionnelles, sans grandes tuiles répétitives.

```
Navigation │ contexte, année, compte
           │ aide compacte et commandes de lecture
           │ Titre de la rubrique          Actions
           │ chiffres de synthèse
           │ filtres
           │ tableau ou contenu de travail
```

Sur téléphone : menu à ouverture explicite, actions qui passent à la ligne, zones tactiles d’au moins 44 px, tableaux défilants sans couper les actions. Le grand texte, les explications audio, les guides, le focus clavier et les confirmations restent disponibles.

## Relecture du parti pris

Pas de fond crème avec accent terracotta, de dégradé violet, de pastilles multicolores pour chaque chiffre ou de faux vocabulaire musical dans les commandes. La référence au chœur se trouve dans la typographie et les couleurs ; les libellés restent ceux du travail réel. Les titres restent en casse de phrase. Les icônes servent la navigation et les actions, et non la décoration des titres.

## Périmètre et entretien

Les primitives partagées couvrent tous les modules : liturgie, membres, activités, instruments, secrétariat, comptabilité, paramètres, profils, rapports et administration propriétaire. Les écrans d’accès et les erreurs utilisent la même palette. Le papier à en-tête fourni par la chorale reste prioritaire ; les documents n’ajoutent pas de décoration qui le concurrence et conservent leur géométrie d’impression.

Utiliser les classes communes `btn`, `form-*`, `page-heading`, `metric`, `work-surface` et les composants Blade. Réserver les ombres aux éléments superposés. Ne pas ajouter de nouvelle carte lorsqu’un simple groupe ou une ligne suffit. Préserver les conditions `@can`, les paramètres des routes et les attributs Livewire/Alpine lors des changements de présentation.

## Vérification et aperçu local

1. Installer les dépendances, puis exécuter `npm run build`.
2. Créer une nouvelle base de démonstration avec `php tests/browser/prepare.php`. Ce script refuse de réinitialiser une base existante.
3. Ajouter les données fictives et un compte propriétaire avec `php tests/browser/prepare-design.php`. Ce script n’accepte que le fichier SQLite local de la fixture, désactive les envois et conserve les identifiants dans un fichier privé ignoré par Git.
4. Démarrer l’application avec les variables d’isolation du job d’accessibilité dans `.github/workflows/quality.yml`.
5. Avec `ACCESSIBILITY_CREDENTIALS_FILE` pointant vers le fichier privé, lancer `npm run test:design` et `npm run test:accessibility`. Le premier parcourt 39 rubriques sur ordinateur et téléphone, les formulaires d’intervenant et de cotisation, puis la plateforme via son authentification réelle avec code à usage unique. Il refuse les domaines distants et conserve les captures et résultats axe dans `test-results/design/`.
6. Exécuter `npm run test:reports` pour les huit mises en page imprimables et `php vendor/bin/phpunit` pour les permissions, l’isolation des chorales et les parcours fonctionnels.

Les fontes locales sont distribuées sous SIL Open Font License ; leurs licences sont dans `public/fonts/`. Les captures et identifiants de démonstration ne sont jamais ajoutés au dépôt.
