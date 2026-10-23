# Audit de l’application — interface et fiabilité

Date : 23 octobre 2026. Base examinée : `fffbc1f2cd36a16882f2c521942e3265de79d5c2` (archives privées déjà intégrées).

## Périmètre

Revue du code des écrans et formulaires Livewire, composants Blade partagés, autorisations, archives et téléchargements, opérations financières existantes, prêts d’instruments, programmes musicaux, participants, validation, pagination et budgets de requêtes SQL. Les tests de l’audit précédent sont conservés. Ce complément ajoute des contrôles des fenêtres, des créations, des erreurs et des enregistrements atomiques.

Les essais dans la démo PostgreSQL utilisent des données fictives. Le nom de la chorale et les données modifiées par l’utilisateur sont conservés.

## Problèmes constatés et corrections

| Constat | Effet utilisateur | Correction |
|---|---|---|
| Croix et Échap envoient toujours `close-form` | Les fenêtres des accès et des participants restent ouvertes | Événement de fermeture propre à chaque fenêtre ; même traitement pour croix, Échap, Annuler et clic extérieur |
| Confirmation native lors de l’abandon | Interruption du navigateur et comportement hétérogène | Confirmation dans la fenêtre avec « Continuer la saisie » et « Abandonner les modifications » |
| Arrière-plan encore accessible au clavier | Focus perdu et actions involontaires sous une fenêtre | Arrière-plan `inert`, focus initial, boucle Tab/Maj-Tab, retour au déclencheur et nettoyage à la fermeture |
| Fenêtre fermable pendant un traitement ou un import | Retour incertain et risque de perte du contexte | Fermeture bloquée pendant les requêtes/téléversements ; état de traitement affiché |
| Identifiants réutilisés dans les filtres et les formulaires | Un libellé peut viser le mauvais champ | Identifiants propres à chaque formulaire ; liens vers les erreurs et état `aria-invalid` |
| Barre supérieure au-dessus du dialogue sur mobile | Titre et croix masqués | Suppression de l’animation du contenu principal qui créait une superposition indésirable ; dialogue au-dessus de la navigation |
| Pagination traduite uniquement côté Laravel | Listes Livewire encore en anglais | Vue de pagination Livewire en français, boutons accessibles et filtres conservés |
| Modèle vide injecté dans les créations | Certaines ouvertures directes de formulaire échouent | Normalisation des modèles non enregistrés dans les formulaires de création |
| Œuvres du programme non validées une à une | Une référence invalide peut provoquer une erreur SQL | Contrôle des identifiants, doublons et œuvres supprimées ; limite du nombre d’œuvres |
| Événement enregistré avant son programme | Enregistrement partiel si l’association échoue | Transaction unique et verrouillage pour répétitions et célébrations ; annulation vérifiée par injection d’une panne |
| Enregistrement supprimé ouvert en édition | Risque de basculer silencieusement en création | Référence d’édition manquante traitée en 404, sans nouvelle fiche créée |
| Année de courrier non numérique dans l’URL | Erreur de conversion possible sur PostgreSQL | Filtre converti avant la requête |
| Ajout concurrent d’un participant | Vérification de doublon séparée de l’ajout | Vérification et ajout dans une transaction verrouillant l’activité ; refus des choristes supprimés |
| Ancien dialogue de suppression du profil différent | Accessibilité et focus incohérents | Réutilisation du composant partagé ; texte corrigé pour décrire le retrait de l’accès |

Les œuvres déjà sélectionnées restent visibles dans un programme même lorsqu’elles quittent le répertoire. Les listes d’œuvres des formulaires chargent uniquement les colonnes nécessaires.

## Vérifications

- 41 scénarios PHP supplémentaires : 16 créations, 16 listes avec formulaire ouvert et contrôle des identifiants, fermeture des accès/participants, pagination française, références invalides, rollback des deux programmes, édition périmée, choriste supprimé et filtre d’année.
- 11 tests JavaScript : confirmation d’abandon, événements de fermeture, chargement, clavier, focus, restauration du défilement, nettoyage des écouteurs et conservation de la racine du dialogue lorsque le contexte d’un bouton Alpine change.
- Contrôles existants : matrice des rôles/modules, confidentialité des archives, comptabilité et grand livre, stocks et prêts, rappels, intégrité SQL, index, compilation des vues et budgets de requêtes.
- Compilation des ressources et vérification du style PHP.
- CI exécute les suites sur SQLite et PostgreSQL ainsi que les audits de dépendances npm et Composer.
- Vérification navigateur des archives : ouverture, saisie, annulation, erreur d’import sans fichier, reprise du focus et écran mobile de 390 × 844 pixels. Aucun fichier personnel importé.

Les résultats finaux des exécutions sont indiqués dans la pull request associée et le récapitulatif livré avec cet audit.

## Mise en service et limites

Pas de nouvelle migration de schéma pour ce complément. Compiler les ressources (`npm ci`, `npm run build`), puis nettoyer les caches applicatifs à la mise à jour (`php artisan optimize:clear`). Les règles responsable/associé et le stockage privé sont conservés.

Cet audit porte sur le code et la démo locale. Il ne constitue pas un test d’intrusion externe ni une validation d’un hébergement en production. Le TLS, la configuration réelle du serveur, les sauvegardes/restaurations, les volumes réels, les envois SMS/mail et les fournisseurs d’IA devront être contrôlés dans l’environnement de déploiement. Aucun envoi réel, déploiement ou fusion automatique n’est effectué.
