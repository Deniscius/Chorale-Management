# Neon CLI pour Laravel sur Render

Le projet reste une application PHP/Laravel hébergée sur Render. Seul PostgreSQL est déclaré dans `neon.ts` ; l'authentification, le stockage et les tâches Laravel restent inchangés. Le pilote utilisé reste **PDO PostgreSQL**, pas un pilote JavaScript serverless.

## Initialisation réalisée

La CLI Neon 8.11.4 a été obtenue avec `npx neon@latest` après échec de l'installation globale (répertoire système non accessible). Le contexte local `.neon`, ignoré par Git, cible :

- organisation : `org-twilight-rice-47633189` ;
- projet : `dark-flower-47799201` ;
- branche : `production`.

L'initialisation a créé `neon.ts` et installé `@neon/config` et `@neon/env`. Le plugin global ne pouvait pas écrire dans le répertoire utilisateur ; les compétences `neon` et `neon-postgres` ont été installées localement avec `neon skills -s neon -s neon-postgres --agent codex -y` et consultées.

Le contexte a été écrit **hors ligne** : il ne prouve pas que le compte connecté a accès au projet ou que la branche existe. Aucun `neon deploy` n'a réussi et aucune variable confidentielle Neon n'a été récupérée. Les fichiers `.env` et leur `APP_KEY` sont restés inchangés.

## Connexion dans cet environnement Codex

Le proxy a refusé l'accès aux services Neon (CONNECT 403). Les domaines `console.neon.tech`, `oauth2.neon.tech` et `neon.com` sont ajoutés au brouillon réseau, en conservant les autorisations existantes. Enregistrer puis publier les changements dans les paramètres de l'environnement Codex ; cela ne publie pas l'application Render.

Un raccourci local utilise des répertoires de configuration/cache accessibles :

```sh
/workspace/chorale-cloud/bin/neon login
```

Terminer personnellement l'authentification dans le navigateur lorsque la CLI le demande. Ne jamais coller de jeton, mot de passe ou URL PostgreSQL dans le chat. Une connexion réalisée sur une autre machine ne connecte pas automatiquement cet environnement.

Après connexion, les étapes restantes sont :

```sh
cd /workspace/Chorale-Management
/workspace/chorale-cloud/bin/neon config plan
/workspace/chorale-cloud/bin/neon deploy
```

Examiner le plan pour le projet et la branche demandés avant application. La configuration ne prévoit aucun service supplémentaire ni suppression de données. `deploy` récupère ensuite les variables PostgreSQL dans `.env` existant. Ne pas imprimer le fichier ; vérifier les noms et la présence seulement. Le domaine de l'endpoint PostgreSQL devra aussi être accessible depuis le runtime.

## Variables raccordées à Laravel

La connexion `pgsql` choisit la première valeur non vide parmi :

1. `DB_URL`, configuration explicite Render conservée ;
2. `DATABASE_URL_UNPOOLED`, fournie par la CLI Neon ;
3. `DATABASE_URL`, repli si seule cette valeur existe.

Le choix direct est conservé pour cette migration d'une application PHP persistante, afin de ne pas modifier simultanément les comportements de session SQL, curseurs et migrations. Les outils de migration et d'export nécessitent une connexion directe ; une optimisation du trafic web par pooling pourra faire l'objet d'une validation distincte.

Pour Neon, conserver `sslmode=verify-full` dans la connexion effective et le bundle CA configuré par `DB_SSLROOTCERT`. Les paramètres de l'URL prennent priorité sur `DB_SSLMODE` ; contrôler ce point après récupération des variables avant tout accès distant. Ne jamais désactiver TLS.

`phpunit.xml` neutralise les trois URL pour que les variables récupérées dans `.env` ne détournent pas les tests vers Neon. Utiliser uniquement une base jetable via les paramètres `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, etc. Les suites de tests ne doivent jamais être exécutées sur la production.

## Validation et limites

La compilation Vite et les tests ciblés des URL, du déploiement et des services PostgreSQL locaux passent. Les tests PostgreSQL ont également été lancés avec de fausses URL externes injectées : celles-ci sont bien neutralisées par PHPUnit. Ces validations ne prouvent pas une connexion à Neon réel.

Après authentification et déploiement réussis, vérifier en lecture seule la connexion et le schéma Neon, puis effectuer les essais d'application sur une branche de validation isolée. Ne pas initialiser une base contenant des données, exécuter un seeder de démonstration ou repointer Render automatiquement. La copie de données et la bascule restent régies par [MIGRATION-NEON.md](MIGRATION-NEON.md), avec conservation d'APP_KEY et de l'ancienne base Render.
