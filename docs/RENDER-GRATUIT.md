# Mise en service et pilotage de Chorale Management

## Déploiement existant

Le Blueprint racine conserve le web Free **chorale-management** sur Render et sa référence APP_KEY à **chorale-initial**. La base cible est désormais **Neon PostgreSQL**, fournie par une variable DB_URL confidentielle ; aucune base Render n'est déclarée dans le YAML. Ce fichier décrit une cible, pas une bascule déjà réalisée.

Pour une installation existante, suivre d'abord [MIGRATION-NEON.md](MIGRATION-NEON.md) : copier et valider toutes les données, garder la même clé et obtenir l'autorisation de bascule. Ne pas supprimer l'ancienne base Render. Vérifier sa date d'expiration réelle dans Render ; le nouveau YAML ne modifie pas sa durée de vie.

Render Free conserve ses limites : veille, fichiers éphémères et absence de worker/cron indépendant. Les coûts, limites de compute et sauvegardes Neon dépendent de son offre et doivent être vérifiés séparément. Aucune garantie de coût nul ou de conservation illimitée n'est fournie ici. L'essai applicatif de 72 heures reste indépendant de l'hébergement.

## Appliquer la correction au Blueprint existant

Ne pas renommer, recréer ou remplacer les services existants pendant le transfert. Le service **chorale-initial** reste présent tant que sa clé est référencée. Ne pas supprimer ni régénérer cette clé.

Ne pas synchroniser avant autorisation et validation des données. Examiner le plan du Blueprint existant : arrêter si Render propose la suppression/recréation d'une base, d'un service ou d'un disque. DB_URL doit être saisie dans les paramètres confidentiels du web ; le retrait du bloc databases n'autorise pas la suppression de l'ancienne base.

Les variables principales sont désormais :

| Variable | Configuration |
| --- | --- |
| APP_NAME | Chorale Management |
| APP_DEMO_MODE | false, pour neutraliser l’ancien réglage |
| APP_CANONICAL_HTTPS | true |
| APP_URL | Référence automatique à RENDER_EXTERNAL_URL du service |
| PLATFORM_BOOTSTRAP_ENABLED | true pour préparer le premier propriétaire |
| PLATFORM_SETUP_TOKEN | Secret aléatoire généré une seule fois par Render |
| FILES_EPHEMERAL | true pour déclarer la réalité du stockage Free |
| DATABASE_FREE_PLAN | false : la base cible n’est plus Render Free ; vérifier l’offre Neon |
| DB_URL | URL directe Neon confidentielle, avec sslmode=verify-full |
| DB_SSLROOTCERT | /etc/ssl/certs/ca-certificates.crt |

`APP_KEY` reste inchangée. La connexion cible utilise Neon avec vérification TLS du certificat et du nom d’hôte ; les règles réseau sont à valider pour les services Render. Les en-têtes proxy non vérifiés ne sont pas acceptés. L’origine HTTPS est fixée explicitement pour les liens, formulaires et signatures. Les limites par IP peuvent être partagées entre visiteurs derrière les proxies Render ; une configuration explicite des proxies entrants est à vérifier pour une exploitation commerciale.

Les trois anciens champs PLATFORM_BOOTSTRAP_EMAIL, PLATFORM_BOOTSTRAP_PASSWORD et PLATFORM_BOOTSTRAP_TOTP_SECRET ne sont plus nécessaires avec l’assistant. Les retirer après installation réussie s’ils ont été renseignés. Ne jamais copier leurs valeurs dans une conversation ou un dépôt.

Les déploiements automatiques du service restent désactivés : après synchronisation des variables, lancer **Manual Deploy → Deploy latest commit** si nécessaire. Les migrations peuvent être exécutées au démarrage selon RUN_MIGRATIONS_ON_START et RUN_MIGRATIONS_ON_RELEASE. Les désactiver pendant le transfert décrit dans MIGRATION-NEON.md. Aucun seeder ne tourne automatiquement.

## Premier accès du propriétaire

Cette section concerne une première installation. Pour une migration de base existante, conserver les comptes restaurés et suivre MIGRATION-NEON.md ; ne pas relancer l'assistant ni réinitialiser les accès.

1. Dans Render → service web → Environment, retrouver `PLATFORM_SETUP_TOKEN` et en copier personnellement la valeur. Garder ce secret privé ; ne pas le mettre dans une URL ni l’envoyer dans une conversation.
2. Ouvrir `https://VOTRE-ADRESSE/plateforme/installation`. La page de connexion propriétaire y redirige automatiquement tant que l’installation reste ouverte et qu’aucun propriétaire n’existe.
3. Saisir la clé privée, votre nom, votre e-mail et un mot de passe unique d’au moins 16 caractères avec lettres et chiffres. Confirmer le mot de passe.
4. L’application prépare une nouvelle clé TOTP. L’ajouter par saisie manuelle dans votre application d’authentification : TOTP, SHA-1, six chiffres, période de 30 secondes. Garder cette clé dans un gestionnaire sécurisé. La préparation expire après dix minutes ; une page rechargée après expiration fournit une autre clé.
5. Saisir le code affiché et créer votre espace. La double authentification est confirmée avant toute création du compte.
6. Se connecter sur `/plateforme/connexion` avec le prochain code. L’assistant est ensuite fermé : aucun second propriétaire ni réinitialisation ne peut être obtenu avec cette page, même en conservant le jeton.
7. Après connexion réussie, retirer `PLATFORM_SETUP_TOKEN` des variables Render et redéployer pour purger sa copie dans le cache. `generateValue` le recréera lors d’une future synchronisation si absent, mais un propriétaire déjà présent maintient l’assistant fermé. Pour désactiver durablement l’initialisation, mettre `PLATFORM_BOOTSTRAP_ENABLED=false` dans le Blueprint également.

Le démarrage n’exige plus de mot de passe ni de clé TOTP du propriétaire en clair dans Render : avec un jeton d’installation valide, il laisse l’assistant terminer cette étape. Le mot de passe est haché, la clé TOTP enregistrée en base est chiffrée. L’initialisation conserve tout propriétaire existant. La page refuse les accès après création, limite les tentatives et ne réaffiche pas les secrets dans les erreurs.

## Suivi de la plateforme

Le propriétaire utilise `/plateforme`, avec une authentification distincte de celle des responsables de chorale :

- Vue d’ensemble : chorales réelles, essais, accès payants actifs, expirations et inscriptions récentes. L’espace technique réservé aux migrations n’est pas compté tant qu’il ne contient aucun utilisateur.
- Chorales : recherche, filtres, comptes rattachés, licences et adresse personnelle de connexion. Les mutations d’accès demandent votre mot de passe et un motif conservé dans le journal.
- Supervision : activité authentifiée sur sept jours, erreurs 5xx, requêtes lentes, durée moyenne côté application, tâches en attente/échec, e-mails configurés et limites de conservation de l’hébergement.
- Journal : connexions propriétaire, création du compte et actions administratives tracées.

Ces statistiques ne représentent pas les visites anonymes, les ressources statiques ni un p95 navigateur. Les compteurs de tâches ne prouvent pas qu’un worker fonctionne. Les paiements réellement encaissés et les remboursements restent à vérifier chez Chariow tant que leur synchronisation n’est pas intégrée.

Render fournit les logs de construction/exécution et les métriques d’infrastructure. Les sauvegardes, leur rétention et une restauration doivent être vérifiées séparément avant exploitation durable ; aucun compteur applicatif ne les garantit.

## Une adresse par chorale

Chaque chorale dispose d’un point d’entrée permanent : `https://VOTRE-ADRESSE/c/SON-SLUG`. L’adresse est disponible dans **Paramètres → Informations de la chorale** et sur sa fiche du portail propriétaire. Le suffixe attribué à la création distingue les chorales de même nom.

Un visiteur y voit la connexion et l’identité publique de cette chorale. Les identifiants sont vérifiés dans cet espace ; les identifiants d’une autre chorale ne sont pas acceptés sur cette connexion. Après connexion, la même adresse affiche son tableau de bord. Une session d’une autre chorale reçoit un refus et n’est jamais déplacée automatiquement vers un autre espace. Les autres modules continuent d’utiliser leurs routes communes avec isolation par compte authentifié ; le lien personnel ne modifie pas les autorisations des données.

Le nom figurant sur cette page est public. Le logo et les informations de contact restent réservés à la session de la chorale ; aucune image personnalisée ni favicon de chorale n'est affiché sur la connexion. Les routes des logos exigent l'authentification et refusent l'accès depuis une autre chorale. L’expiration de l’essai continue de rediriger vers l’abonnement. Les sous-domaines et domaines propres à chaque chorale nécessiteraient un routage DNS et une configuration séparés.

## Contrôles avant ouverture commerciale

- Vérifier `/up`, la connexion propriétaire et une connexion depuis l’adresse d’une chorale.
- Contrôler deux chorales distinctes et les accès responsable/associé aux archives.
- Vérifier SMTP/API e-mail réel, paiement Chariow, abonnement mensuel et renouvellement ; les fonctions non configurées ne sont pas réputées opérationnelles.
- Sauvegarder base, fichiers et secrets, puis tester une restauration isolée.
- Le modèle durable payant `deploy/render.production.yaml` est facultatif et ne doit pas être appliqué avec un budget nul.

Documentation officielle : [Render Free](https://render.com/docs/free), [Blueprints](https://render.com/docs/blueprint-spec), [disques persistants](https://render.com/docs/disks).
