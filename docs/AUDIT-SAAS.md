# Audit transversal SaaS — octobre 2026

## Périmètre et méthode

Revue des routes HTTP et Livewire, authentification et récupération de compte, rôles et commissions, scoping des modèles, requêtes SQL directes, contraintes et transactions, archives privées, logos, comptabilité, tableaux de bord, tâches différées, configuration de déploiement et dépendances verrouillées. Les suites existantes couvrent les modules fonctionnels, les budgets SQL, les accès, les modals et leurs interactions. Les nouvelles régressions sont dans `tests/Feature/FinalAuditTest.php` et `tests/js/access-expiry.test.js`.

La vérification locale utilise une base jetable pour les tests et une prévisualisation distincte pour les essais HTTP/k6. Aucun achat, SMS, envoi d’e-mail réel, déploiement de production ou migration de la base de démo originale. Les résultats chiffrés et la révision exacte sont documentés dans la pull request. L’absence de défaillance dans ces contrôles n’est pas une garantie d’absence de tout défaut.

## Corrections

### Comptes et sessions

- L’administration d’un compte réinitialise la vérification de l’adresse lorsque l’e-mail change, comme le profil utilisateur. Une adresse inchangée garde son état. Les formulaires de comptes normalisent l’e-mail avant validation.
- L’unicité du nom de connexion est validée globalement sur le bon champ, tandis que les fiches et relations restent limitées à leur espace. La suggestion d’un identifiant de choriste gère les collisions dans un autre espace et les noms longs. Une collision concurrente est revérifiée après rollback pour retourner une erreur de formulaire, sans SQL exposé.
- Les variantes d’espaces autour du nom de connexion utilisent le même compteur de tentatives. Même les identifiants inconnus passent par la temporisation du guard. Une limite générale de 60 requêtes de connexion par minute s’ajoute au compteur d’échecs.
- Chaque politique de limitation dispose d’un préfixe distinct : inscription, connexion chorale, connexion propriétaire, vérification, renvoi d’e-mail, activation et gestion propriétaire. Les essais de connexion ne consomment plus la limite horaire d’inscription.
- Les routes métier et leurs actions Livewire contrôlent l’empreinte du mot de passe en session via le mécanisme Laravel. Un mot de passe changé depuis un autre accès révoque l’ancienne session. Le changement par le formulaire du profil conserve la session courante. Le compte propriétaire garde son guard distinct.

### Archives, fichiers et identité

- La politique du document et celle de l’équipe vérifient aussi l’appartenance de l’utilisateur au même espace, même si un modèle a été chargé auparavant dans un autre contexte. Les routes et contraintes continuent d’assurer leur isolation.
- Le nouveau logo est écrit avant de remplacer sa référence. Les informations et la référence sont enregistrées dans une transaction ; l’ancien fichier est supprimé après commit. Un échec de stockage ou de base préserve l’ancien logo, et le nouveau fichier est nettoyé en cas de rollback.
- Le cache des paramètres est invalidé immédiatement puis à nouveau après commit dans le contexte capturé. Un incident de cache ou de journalisation après commit ne doit pas entraîner la suppression du nouveau fichier déjà référencé. Les nettoyages après commit restent au mieux : un incident de stockage peut laisser un fichier à reprendre.

### Expiration et expérience utilisateur

Les pages métier authentifiées reçoivent `Cache-Control: no-store, private`. Leur échéance effective tient compte de la plus tardive des dates d’essai et d’accès payant ; un accès permanent n’a pas de minuteur. Le navigateur rejoint l’abonnement lorsque cette échéance est atteinte, vérifie à nouveau au retour sur un onglet et au retour par l’historique, et ne redémarre pas l’essai lors de la restauration d’un ancien instantané Livewire. Aucun appel périodique supplémentaire n’est ajouté. Le refus côté serveur reste l’autorité, même sans JavaScript.

Les erreurs de génération IA restent génériques dans les notifications. Le brouillon est conservé et les journaux n’incluent pas le message brut du fournisseur, qui pourrait contenir des données sensibles.

### Calculs et affichages

Le tableau de bord partage son cache par espace et ensemble de droits. Les modules non autorisés ne sont plus interrogés. Le solde des recettes/dépenses validées ou clôturées utilise une seule agrégation SQL ; les transferts restent exclus. Les listes en cache ne sélectionnent que les colonnes affichées et les identifiants nécessaires. Les événements annulés ou terminés ne figurent plus parmi les prochains événements. Les compteurs et listes utilisent le même filtre de statut.

Ces optimisations ne modifient ni les écritures du grand livre ni les anciens règlements. Les tests comptables, d’intégrité et de concurrence existants restent applicables.

### Mise en production

Les seeders généraux et les comptes publics de démonstration refusent explicitement l’environnement de production. Ils ne remplacent pas le provisionnement sécurisé du véritable propriétaire.

Commande de contrôle sans mutation :

```bash
php artisan chorale:check-production
```

Elle vérifie les paramètres de production, le mode debug, HTTPS, PostgreSQL, la protection des cookies, les stores persistants, la configuration des e-mails/Chariow, la clé de chiffrement, la présence des tables, un propriétaire actif et les comptes de test connus. Elle affiche des états, jamais les clés ni les mots de passe. Elle retourne un code d’échec si un contrôle échoue. Le contrôle détecte les comptes de démonstration courants ; il ne peut pas identifier tout compte de test renommé arbitrairement.

Cette commande ne prouve pas l’envoi SMTP, l’achat Chariow, l’état des workers, du scheduler, de HTTPS depuis Internet, ni la réussite des sauvegardes et restaurations. Vérifier aussi la version corrigée de PHP et la configuration de l’hébergement retenu. La branche PHP 8.2 arrive en fin de support de sécurité le 31 décembre 2026 ; prévoir une version supportée avec ses derniers correctifs, validée par la suite de tests. Le runtime local de développement n’est pas qualifié comme runtime de production.

## Limites maintenues et points à raccorder

- Le lien commercial, le produit et la clé Chariow doivent être configurés par l’exploitant. Les montants des ventes, remboursements et révocations ne sont pas synchronisés automatiquement. Aucun webhook commercial nouveau n’est activé par cet audit. Les identifiants fournisseur vides/trop longs et les dates de calendrier invalides sont refusés lors de la validation simulée des licences.
- Les essais restent immédiatement accessibles sans confirmation d’e-mail. Les limites et le honeypot réduisent les abus mais ne prouvent pas qu’une personne ne crée pas plusieurs comptes.
- La surveillance extérieure, les alertes et les sauvegardes/restaurations réelles dépendent du déploiement et restent à connecter.
- L’isolation repose sur l’application et les contraintes relationnelles ; PostgreSQL RLS n’est pas installée. Le rôle SQL partagé reste réservé au serveur.
- Les mesures k6 sur deux espaces fictifs locaux ne qualifient ni l’endurance, les grands volumes, les gros fichiers, les paiements réels ou toutes les écritures simultanées. Les durées avant et après préchauffage sont distinguées ; aucun seuil n’est abaissé pour faire passer un résultat.

Références : [sessions Laravel](https://laravel.com/docs/12.x/authentication#invalidating-sessions-on-other-devices), [audit npm](https://docs.npmjs.com/cli/v11/commands/npm-audit/), [versions PHP supportées](https://www.php.net/supported-versions.php), [licences Chariow](https://chariow.dev/en/guides/saas-license-integration).
