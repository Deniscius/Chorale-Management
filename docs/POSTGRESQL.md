# PostgreSQL et règles de comptabilité

## Démarrage sur une nouvelle base de démonstration

L'ancienne base doit rester disponible jusqu'à vérification. Cette procédure ne copie ni ne supprime ses données. Si elle contient finalement des données réelles, arrêter avant le peuplement de démonstration et organiser un transfert contrôlé.

1. Sauvegarder l'ancien fichier `.env` et la base existante (SQLite : copie du fichier, application arrêtée ; MySQL : sauvegarde cohérente). Ne pas versionner ces sauvegardes.
2. Installer PostgreSQL 17 et activer `pdo_pgsql` dans le PHP utilisé par l'application et le terminal. Alternative locale avec Docker : copier `.env.example` vers `.env`, choisir un mot de passe puis `docker compose up -d postgres`.
3. Pour un serveur PostgreSQL existant, créer un rôle dédié et une nouvelle base lui appartenant. Ne pas réutiliser une base d'une autre application.
4. Renseigner `.env` :

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chorale
DB_USERNAME=chorale
DB_PASSWORD=votre-mot-de-passe
DB_SSLMODE=prefer
```

En production distante, utiliser les exigences TLS de l'hébergeur, typiquement `DB_SSLMODE=verify-full` avec les certificats requis. Le service Docker fourni est destiné au développement local, exposé uniquement sur 127.0.0.1.

5. Installer et initialiser :

```bash
composer install
npm ci
php artisan key:generate # uniquement pour une nouvelle installation ; conserver une clé existante
php artisan optimize:clear
php artisan migrate
php artisan db:seed      # uniquement sur la nouvelle base de démonstration vide
npm run build
php artisan serve
```

Les comptes de démonstration du README servent uniquement aux essais. Ne pas exécuter `migrate:fresh` sur une base à conserver. Ne pas relancer les seeders de démonstration sur des données réelles.

## Vérifications après démarrage

Vérifier connexion par identifiant/e-mail, recherche sans distinction de casse, filtres d'année des dons/courriers, création de références, dons, cotisations partielles puis soldées, et validation des recettes. Contrôler aussi les fichiers privés, SMTP et SMS avec les paramètres de l'environnement.

Pour tester une base PostgreSQL dédiée, exporter DB_CONNECTION, DB_DATABASE, DB_HOST, DB_PORT, DB_USERNAME et DB_PASSWORD dans le terminal puis lancer `php vendor/phpunit/phpunit/phpunit`. Ne jamais utiliser une base contenant des données à conserver : les tests la réinitialisent.

La CI exécute la suite sur SQLite et PostgreSQL. Les paramètres PHPUnit n'écrasent pas les variables DB déjà exportées.

## Règles comptables introduites

- Les dons saisis via l'application préparent une recette en brouillon, reliée au don. Modifier ou supprimer un don en brouillon met à jour ou retire cette recette dans la même transaction SQL.
- Une cotisation partiellement réglée prépare une recette en brouillon. Un complément après validation génère uniquement la différence ; une soumission répétée ne crée pas de recette supplémentaire.
- La recette reste exclue du solde de trésorerie jusqu'à validation par le trésorier.
- Les écritures validées ou clôturées sont protégées contre modification et suppression depuis les actions de l'application. Une erreur doit faire l'objet d'une écriture de régularisation explicite.
- Les règlements historiques non rapprochés ne sont pas convertis automatiquement. Un règlement historique de cotisation déjà encaissé est signalé au trésorier avant modification afin d'éviter un double comptage.
- Le statut de cotisation est recalculé au moment de l'enregistrement. Les montants négatifs, les dépassements et les doublons de période sont refusés avec des erreurs de champ.
- Les références sont réservées atomiquement. Une réservation abandonnée peut laisser un trou : les numéros ne sont pas réutilisés.

## Limites de périmètre

Le contrôle des droits reste fondé sur les rôles et modules. La présence d'un choir_id ne fournit pas une isolation entre organisations indépendantes. Ce déploiement reste une application de gestion partagée ; une offre SaaS multi-organisations nécessite une conception distincte des périmètres d'accès.
