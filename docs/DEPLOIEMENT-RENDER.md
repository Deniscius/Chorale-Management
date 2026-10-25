# Déploiement Render et seeders

**Budget retenu : 0.** Le fichier racine `render.yaml` déploie seulement une **démo gratuite temporaire** ; suivre [RENDER-GRATUIT.md](RENDER-GRATUIT.md). Les étapes d'exploitation payante ci-dessous concernent exclusivement le modèle optionnel `deploy/render.production.yaml`. Ne pas l'appliquer avec un budget nul.

## Ce qui entre en production

Partir d'une **nouvelle base PostgreSQL**, sans importer la base locale ou les données de démonstration. Ne jamais lancer `migrate:fresh`, `migrate:refresh`, `db:wipe` ou le `DatabaseSeeder` sur une base de production.

Le script `docker/release.sh` exécute :

```sh
php artisan migrate --force --no-interaction
php artisan db:seed --class=ProductionSeeder --force --no-interaction
```

`ProductionSeeder` complète seulement les référentiels manquants des espaces ayant déjà des utilisateurs : comptes comptables, équipes de commission, verrou comptable et catégories financières. Il ne crée aucun utilisateur, choriste, don, paiement ou abonnement. Il ne réinitialise ni mot de passe, ni montant personnalisé, ni libellé, ni attribution des responsables, ni essai de 72 heures. Une seconde exécution conserve les données existantes.

Les huit seeders de démonstration refusent toute exécution avec `APP_ENV=production`, y compris un lancement direct avec `--class` et `--force`. Les factories et le code exécuté manuellement restent réservés aux environnements de test. La migration historique réserve un espace technique « Espace existant » ; il peut apparaître dans les métriques, sans constituer un client réel et sans compte de connexion. Ne pas le supprimer automatiquement.

Chaque nouvelle inscription crée sa chorale et ses référentiels dans une transaction. Les comptes des clients sont donc créés au fil des inscriptions, jamais par un seeder de production.

## Architecture proposée et coût

Le Blueprint optionnel `deploy/render.production.yaml` prépare, dans la région Frankfurt :

| Ressource | Rôle | Configuration initiale |
| --- | --- | --- |
| `chorale-web` | Site Laravel / Livewire | Docker PHP 8.4 Apache, Starter, disque privé 5 Go |
| `chorale-worker` | Envois en arrière-plan | Docker Starter, file PostgreSQL |
| `chorale-scheduler` | Rappels et licences | Cron chaque minute UTC |
| `chorale-postgres` | Données clients | PostgreSQL 17, 0.5 CPU / 1 Go, stockage 5 Go |

**Ce sont des ressources payantes.** Examiner l'estimation complète affichée par Render avant de valider : services, base, disque, cron, éventuel abonnement d'espace de travail, trafic et frais du prestataire e-mail. Aucun tarif fixe ni disponibilité d'une offre gratuite ne sont garantis. Les déploiements automatiques sont désactivés pour permettre une validation des tests avant chaque livraison.

Le disque `/var/data/chorale` conserve partitions, archives, courriers et logos. Il appartient seulement au service web ; le worker et le cron actuels n'utilisent pas ces pièces jointes. Une évolution vers des exports ou e-mails avec pièces jointes devra utiliser un stockage objet privé partagé. Ce choix initial impose **une instance web** et une brève interruption pendant ses redéploiements. Pour plusieurs instances ou des déploiements sans coupure, migrer d'abord les fichiers privés vers un stockage objet, avec autorisation applicative conservée.

## 1. Préparer le compte et les valeurs

1. Se connecter à Render avec GitHub et autoriser le dépôt `Deniscius/Chorale-Management`.
2. S'assurer que la branche à déployer contient les personnalisations financières et la préparation Render ; ces changements sont proposés dans des PR distinctes tant qu'ils ne sont pas fusionnés.
3. Préparer une URL HTTPS publique : domaine personnel ou adresse `onrender.com` attribuée au service. L'URL peut être corrigée dans les variables après création ; ne pas ouvrir les inscriptions avant cette vérification.
4. Préparer un fournisseur SMTP avec expéditeur vérifié. Le Blueprint utilise SMTP sur le port 587 (STARTTLS). Adapter les paramètres si votre fournisseur impose un autre port/protocole. Ne jamais coller les identifiants dans GitHub, un document ou une conversation.
5. Obtenir et vérifier les adresses/CIDR réels des proxies de confiance utilisés devant le service. Renseigner `TRUSTED_PROXIES` avec une liste séparée par des virgules. **Ne pas utiliser `*`, `0.0.0.0/0` ou `::/0`**. Les adresses de sortie des services Render ne sont pas automatiquement les adresses des proxies entrants. Confirmer les adresses avec Render et vérifier la stabilité de cette liste avant ouverture.
6. Ajouter plus tard le lien Chariow, la clé API et le produit de licence mensuelle 2 000 XOF. Sans cela, les essais peuvent fonctionner, mais l'achat/activation commercial ne peut pas être validé. La reconduction automatique dépend de la configuration et du fonctionnement réel du produit Chariow.

## 2. Créer le Blueprint

Dans Render : **New → Blueprint**, choisir le dépôt puis la branche préparée et le fichier `deploy/render.production.yaml`. Examiner les ressources et l'estimation avant toute confirmation payante.

Le formulaire demande : `APP_URL`, `TRUSTED_PROXIES`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et les trois valeurs Chariow. Si la facturation n'est pas encore prête, laisser ses valeurs vides si le formulaire le permet, ou les ajouter seulement ensuite dans les variables ; ne pas inventer une clé ni un lien commercial.

La clé `APP_KEY` est générée **une seule fois** dans le groupe d'environnement partagé. Render fournit 32 octets encodés en base64 ; les scripts ajoutent le préfixe Laravel `base64:` avant le cache de configuration. Tous les services utilisent la même clé. Ne jamais la régénérer à chaque déploiement : elle protège notamment les secrets d'authentification du propriétaire. Conserver une copie dans un gestionnaire de secrets.

La connexion PostgreSQL utilise l'URL interne et TLS. `ipAllowList: []` ferme les connexions externes à la base ; les services du même environnement utilisent le réseau privé. Ne pas ouvrir la base à Internet pour simplifier une connexion locale.

Les migrations et l'initialisation sont exécutées seulement par le pré-déploiement du web. **Premier lancement : attendre la réussite du web avant de relancer le worker et le cron si ceux-ci ont démarré avant la création des tables.** Les démarrages suivants n'exécutent aucune migration. À chaque mise à jour, déployer le web puis les deux services de traitement sur le même commit validé ; ne pas laisser le cron ou le worker sur un ancien code incompatible avec le schéma.

## 3. Créer votre accès propriétaire

Dans **chorale-web → Shell**, exécuter :

```sh
php artisan platform:admin votre-adresse-reelle@example.com --name="Votre nom"
```

Remplacer cette adresse par la vôtre. La commande demande de manière interactive un mot de passe personnel fort et la configuration d'une application d'authentification TOTP. Aucun compte administrateur avec mot de passe prédéfini n'est livré. Garder les éléments de récupération dans un gestionnaire sécurisé, hors des logs de déploiement.

Ouvrir ensuite `https://VOTRE-DOMAINE/plateforme/connexion`. Le portail propriétaire sert à suivre les espaces clients, les essais, les abonnements, l'usage et les événements d'administration. Les métriques d'infrastructure, les redémarrages, le disque, PostgreSQL et les logs se suivent dans Render. Le responsable d'une chorale utilise l'entrée de connexion normale du site.

## 4. Valider avant ouverture

Dans le Shell du web :

```sh
php artisan chorale:check-production
php artisan queue:failed
php artisan schedule:list
```

Le contrôle de production est en lecture seule et n'affiche aucun secret. Il doit réussir avant ouverture commerciale ; tant que Chariow n'est pas renseigné, ses contrôles restent en échec. Il ne remplace pas les vérifications opérationnelles suivantes :

- `/up` répond 200 ; connexion, déconnexion et formulaire Livewire fonctionnent en HTTPS sans contenu mixte ni erreur 419.
- Confirmer que l'IP client détectée et les en-têtes HTTPS correspondent aux requêtes réelles ; une requête avec des en-têtes proxy falsifiés ne doit pas usurper l'utilisateur ni contourner les limites de connexion.
- Créer une chorale pilote réelle, vérifier ses référentiels et l'essai de 72 h, sans lancer les seeders de démonstration.
- Créer une deuxième chorale pilote et vérifier la séparation des listes, pièces et accès.
- Importer une partition privée, tester les accès responsable/associé/publication, puis vérifier sa présence après un redéploiement.
- Vérifier l'envoi SMTP réel et un rappel traité par le worker ; examiner les échecs de jobs.
- Vérifier l'expiration d'un essai dans un environnement de préproduction séparé, puis le paiement Chariow et le renouvellement avec le produit réel avant commercialisation. Ne pas modifier l'horloge ni les abonnements de tous les clients de production pour simuler un essai.

## Sauvegardes et mises à jour

Activer/vérifier les sauvegardes et la rétention PostgreSQL proposées dans le compte Render. Le disque privé a ses propres snapshots, distincts des sauvegardes PostgreSQL. Prévoir aussi des exports chiffrés hors de l'hébergement et tester une restauration dans un environnement isolé. Sauvegarder ensemble la base, les fichiers et les secrets nécessaires à leur lecture ; ne jamais committer ces sauvegardes.

Avant chaque livraison : tests au vert sur le commit choisi, sauvegarde vérifiée, examen des migrations, livraison du web puis du worker/cron. Un retour à une ancienne image ne restaure pas le schéma ni les données. Certaines migrations financières sont volontairement irréversibles : une restauration doit être préparée et testée.

## Sources officielles

- [Blueprint Render](https://render.com/docs/blueprint-spec)
- [Docker sur Render](https://render.com/docs/docker)
- [Disques : persistance, limites et snapshots](https://render.com/docs/disks)
- [Cron Render](https://render.com/docs/cronjobs)
- [Tarification à vérifier avant création](https://render.com/pricing)
- [Proxies de confiance Laravel](https://laravel.com/docs/12.x/requests#configuring-trusted-proxies)
