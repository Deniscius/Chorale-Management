# PostgreSQL Render → Neon, application conservée sur Render

Ce document prépare une migration de fournisseur **sans changement de version du code ou du schéma**. Aucun export de production, changement de variable sur Render, déploiement ou bascule n'a été effectué par ces modifications. Les commandes ci-dessous sont une procédure opérateur ; leur présence ne constitue pas une autorisation de les exécuter en production.

## Audit et modifications du dépôt

Audit du commit `70140ac` :

- `render.yaml` déclarait un web et PostgreSQL Render ; `deploy/render.production.yaml` déclarait web, worker, cron et PostgreSQL Render. Les deux récupéraient `DB_URL` par `fromDatabase`.
- `config/database.php` gère déjà les URL `postgresql://` par `DB_URL`. Laravel 12/PDO utilise les transactions, contraintes et verrous PostgreSQL habituels. Aucune réécriture des migrations n'est nécessaire pour changer d'hébergeur.
- Le Blueprint payant générait `APP_KEY` dans un groupe. Il demande maintenant la **clé existante** sur le web, puis la référence depuis worker et scheduler. Le Blueprint racine garde sa référence à la clé de `chorale-initial`. Ne pas changer cette référence pendant la migration.
- Les deux Blueprints ne provisionnent plus de base Render. `DB_URL` est une variable confidentielle saisie sur le web ; worker et scheduler du modèle payant utilisent `fromService` vers cette même variable. Le service gratuit reste un web seul, avec file synchrone et rappels désactivés : aucun worker payant n'est créé.
- `DB_SSLMODE=verify-full` et `DB_SSLROOTCERT=/etc/ssl/certs/ca-certificates.crt` permettent de vérifier le certificat **et le nom d'hôte**. `config/database.php` transmet maintenant le certificat racine à libpq. Ne pas désactiver TLS pour résoudre un problème de connexion.
- Les sessions, jobs, lots, échecs, cache et verrous sont des tables de la même base. Les références explicites `SESSION_CONNECTION`, `DB_CACHE_CONNECTION`, `DB_CACHE_LOCK_CONNECTION` et `DB_QUEUE_CONNECTION` du modèle payant valent `pgsql`. Vérifier aussi les éventuels réglages manuels existants sur chaque service.
- `docker/release.sh` ne lance plus aucun seeder. `RUN_MIGRATIONS_ON_RELEASE=false` permet une livraison de configuration sans appliquer de migration. Le démarrage web respecte ce réglage même si `RUN_MIGRATIONS_ON_START=true`.
- `ProductionSeeder` n'efface pas de tables et ne réinitialise ni utilisateurs, ni mots de passe, ni abonnements. Cependant, il **écrit** : `TenantDefaults` ajoute les référentiels manquants et `InstrumentIdentity` ajoute les exemplaires manquants et leurs événements d'audit. Il peut aussi échouer si le stock et les exemplaires divergent. Un échec ne garantit pas l'absence de compléments déjà validés dans d'autres transactions. Il ne doit donc pas faire partie d'une copie à l'identique. Il reste disponible, inchangé, uniquement pour une opération de maintenance distincte et examinée.
- Certaines migrations historiques transforment des données (soldes, prêts, visibilité, limites clients) et certaines sont irréversibles. La table `migrations` doit être restaurée, jamais recréée à vide ; ne pas mélanger ce transfert avec une montée de version applicative.

## Choix de connexion Neon

Commencer avec l'endpoint Neon **direct**, sans `-pooler`, pour tous les services ainsi que `pg_dump`/`pg_restore`. Ce choix évite d'introduire simultanément les contraintes du pooling en mode transaction (état de session, curseurs et configuration de connexion). Le scheduler utilise notamment `Tenant::cursor()`. Le pooling est une optimisation ultérieure à valider séparément, pas une condition de migration.

Créer le projet Neon dans une région proche de Render Frankfurt et une base/branche dédiée, vide, PostgreSQL **17** si la source est en 17. Vérifier la version réelle de la source : ne pas restaurer vers une version plus ancienne. Utiliser des outils PostgreSQL de même version majeure que la source pour cette répétition (17 ici) et une cible de même version ; toute autre combinaison requiert sa propre répétition.

Obtenir l'URL dans la console Neon et la saisir **uniquement** dans les paramètres confidentiels Render sous `DB_URL`. Remplacer son paramètre `sslmode=require` par `sslmode=verify-full` ; les paramètres de l'URL prennent priorité sur `DB_SSLMODE`. Les caractères réservés du mot de passe doivent être encodés dans l'URL. Ne pas publier l'URL, la passer en argument de commande, l'afficher dans `config:show`, la joindre à un ticket ou la committer.

Ne pas compter sur `channel_binding=require` présent dans une URL pour imposer cette option : le connecteur PostgreSQL de la version Laravel verrouillée ne transmet que `sslmode`, `sslcert`, `sslkey` et `sslrootcert` dans le DSN TLS. La vérification explicite `verify-full` reste requise. Pour les outils libpq natifs, conserver les options Neon compatibles avec leur version.

Contrôler la présence du bundle CA dans l'image PHP, le budget de connexions du compute Neon, les connexions web/worker/cron et les réserves d'administration. Tester la reprise après suspension du compute et les latences de réveil ; au besoin ajuster le compute/sa suspension dans Neon. L'offre et la rétention Neon doivent être vérifiées dans le compte : ce dépôt ne les garantit pas.

## Préparation manuelle, sans bascule

1. Inventorier les services Render réellement en activité, y compris l'ancien web, les cron, les workers, les scripts externes, les webhooks et toute autre application écrivant dans la base. Identifier le Blueprint réellement utilisé ; ne pas remplacer le modèle gratuit par le payant par accident.
2. Relever **sans les afficher** les valeurs actuelles de `APP_KEY` et `DB_URL` dans un gestionnaire sécurisé. Vérifier que la clé effective est identique sur tous les services. Les scripts conservent leur normalisation historique du préfixe `base64:` ; ne pas en profiter pour régénérer ou convertir la clé. Les secrets TOTP chiffrés en base dépendent de cette clé.
3. Inventorier les schémas, extensions et leurs versions, types, séquences, fonctions, triggers, politiques RLS, tables partitionnées, gros objets et privilèges nécessaires. Vérifier leur disponibilité sur Neon et que le rôle de restauration peut lire/restaurer tous les objets. Un privilège superuser Render n'est pas transportable sur Neon. Les options `--no-owner --no-acl` ne recréent pas les rôles : réappliquer séparément les droits nécessaires avec un rôle Neon approprié.
4. Préparer des sauvegardes protégées de la base, des fichiers privés/disques et des secrets. Les fichiers stockés hors PostgreSQL restent sur Render et ne sont pas inclus dans `pg_dump`. Les contenus privés stockés en tables sont, eux, inclus.
5. Configurer une machine d'administration de confiance avec `pg_dump`, `pg_restore`, `psql` et Python 3. Protéger le répertoire de sauvegarde (mode 0700), les fichiers libpq et mots de passe (0600), et les sauvegardes chiffrées au repos. Ni trace shell (`set -x`), ni secrets en historique/arguments. Un dump custom n'est **pas** chiffré par lui-même.
6. Définir deux services libpq dans un `PGSERVICEFILE` privé : `render_source` et `neon_target`. Renseigner `host`, `port`, `dbname`, `user`, `sslmode=verify-full` et `sslrootcert` correspondant aux CA officielles de chaque fournisseur. Placer les mots de passe dans `PGPASSFILE` privé, pas dans les commandes. Utiliser le réseau interne Render depuis une machine autorisée ou un accès externe limité aux IP d'administration ; ne jamais ouvrir à toutes les IP.
7. Vérifier connexion et TLS avec `psql -X -w 'service=render_source'` et `psql -X -w 'service=neon_target'`, puis `SELECT ssl FROM pg_stat_ssl WHERE pid=pg_backend_pid();`. Contrôler séparément `current_database()`, `current_user` et la version, sans afficher l'URL. Faire une première répétition intégrale sur une branche Neon isolée et sans services envoyant des messages/paiements réels.

**Point d'arrêt : obtenir l'autorisation explicite du propriétaire avant de commencer le gel et la migration finale de production.** Une répétition ne constitue pas une autorisation de bascule.

## Export final et restauration, après autorisation

### 1. Geler toutes les écritures

Conserver le **même commit applicatif** pendant toute la migration. Désactiver les déploiements automatiques. Mettre tous les web en maintenance, bloquer aussi les routes de paiement/webhooks qui pourraient être exemptées de la maintenance, arrêter les inscriptions et les tâches externes. Suspendre le cron puis laisser finir les jobs en cours et suspendre tous les workers. La maintenance HTTP seule n'arrête ni worker ni scheduler. Prévoir la reprise des webhooks côté fournisseur et leur idempotence.

Vérifier qu'aucune transaction d'écriture ne reste active. Arrêter tous les autres producteurs avant le dump et les manifestes. Les jobs en attente doivent être transférés avec `jobs`, `job_batches`, `failed_jobs`, les réservations et les verrous ; ne pas vider les files, sessions ou caches. Les horodatages continuent de s'écouler pendant la maintenance : un abonnement arrivant réellement à expiration n'est pas une donnée perdue.

### 2. Sauvegarder la source figée

Dans le répertoire sécurisé, avec `PGSERVICEFILE` et `PGPASSFILE` configurés :

```sh
umask 077
pg_dump --dbname='service=render_source' --format=custom --no-owner --no-acl --file=render-final.dump
pg_restore --list render-final.dump > render-final.toc
sha256sum render-final.dump > render-final.dump.sha256
python3 /chemin/du/depot/deploy/postgres_manifest.py snapshot --service render_source --output render-source.json
```

L'export porte sur la base entière : pas de filtre de table, pas de `--data-only`. Il conserve notamment `migrations`, identifiants, hashes de mots de passe, propriétaires de plateforme/TOTP chiffrés, chorales, tenants, licences, comptabilité, références/séquences, sessions, files, cache et données privées en base. Vérifier l'exit code et le TOC ; ne pas poursuivre sur un avertissement inexpliqué ou un dump incomplet. Conserver le dump final immuable et sa somme de contrôle.

### 3. Restaurer uniquement dans une cible vide

Vérifier manuellement que `neon_target` désigne la bonne branche/base **sans données utilisateur ni tables applicatives**, pas la source. Ne pas initialiser la cible avec `artisan migrate` avant la restauration. En cas d'échec ou de cible déjà peuplée, créer une **autre branche/base Neon vide** ; ne pas nettoyer automatiquement une base existante.

```sh
sha256sum --check render-final.dump.sha256
pg_restore --dbname='service=neon_target' --no-owner --no-acl --exit-on-error --single-transaction render-final.dump
python3 /chemin/du/depot/deploy/postgres_manifest.py snapshot --service neon_target --output neon-target.json
python3 /chemin/du/depot/deploy/postgres_manifest.py compare render-source.json neon-target.json
```

Aucun `--clean`, `--create`, `migrate:fresh`, `migrate:refresh`, `db:wipe` ou seeder n'est utilisé. La restauration transactionnelle échoue en bloc en cas d'erreur ; elle ne justifie jamais un nettoyage automatique. Les extensions incompatibles/objets nécessitant des privilèges restent des blocages à résoudre avant validation.

Le manifeste compare toutes les lignes (SHA-256 d'un flux JSON trié), nombres de lignes, colonnes, contraintes, index, définitions et positions des séquences du schéma `public`. Il fonctionne en lecture seule et n'affiche aucune ligne métier. Répéter `--schema public --schema autre_schema` si d'autres schémas métier existent, **avec la même liste des deux côtés**. Il exige le gel des écritures pendant tous les instantanés ; ce n'est pas un snapshot atomique multitable. Les tris peuvent demander du temps/espace temporaire sur de grandes tables : mesurer lors de la répétition.

Le manifeste ne remplace pas l'inventaire des extensions, fonctions, vues, triggers, politiques, gros objets PostgreSQL ou privilèges. Si la source en contient, comparer aussi ces objets/leurs contenus et les droits explicitement ; un simple résultat MATCH ne valide pas ces éléments. L'application actuelle ne crée pas de gros objets via l'API PostgreSQL `lo_*` ; ses fichiers en base sont des colonnes de tables et sont couverts. Vérifier ce point sur la source réelle. Ne jamais appeler `nextval` pour « tester » une séquence avant comparaison : cela la modifie.

### 4. Valider avant bascule

Exiger MATCH et aucun échec de restauration. Examiner les nombres de tenants, chorales, utilisateurs, propriétaires, licences actives/expirées, opérations validées et totaux comptables par espace/devise ; conserver le rapport protégé. Vérifier séparément migrations appliquées, intégrité référentielle, séquences (état, sans incrément), déchiffrement avec la clé actuelle et lecture des fichiers privés. Comparer les états d'abonnement exacts ; aucune extension automatique d'essai ou d'accès ne doit être faite.

Faire les essais de connexion, Livewire, contrôle d'accès, cache/verrous et jobs synthétiques sur une **branche de validation dérivée de la restauration**, avec services externes neutralisés. Ces essais écrivent (sessions, audit, métriques et cache), donc ne pas les faire sur la cible finale avant son dernier manifeste. Aucun job métier réel ne doit être consommé lors des tests. Les tests PHPUnit utilisent seulement des bases jetables ; ne jamais les pointer sur la source ou la cible finale.

## Bascule manuelle Render, toujours sous maintenance

1. **Nouvelle autorisation explicite si elle n'a pas déjà couvert précisément cette bascule**, après présentation du résultat de la restauration et des contrôles. Aucune commande du dépôt ne modifie les variables Render automatiquement.
2. Avant synchronisation du Blueprint, protéger/détacher l'ancienne base de sa gestion Blueprint si nécessaire dans Render. Examiner le plan réel : **arrêter si une suppression/recréation de base, de service Laravel ou de disque est proposée**. Le retrait du bloc YAML ne donne aucune autorisation de supprimer l'ancienne ressource. Ne pas créer un second service pour remplacer celui en place. Conserver les noms/identifiants existants.
3. Saisir la nouvelle `DB_URL` directe Neon dans le **web concerné**, sans régénérer `APP_KEY`. Dans le modèle payant, vérifier la résolution des références worker/scheduler à cette même URL et à cette même clé. La présence du même nom de variable ne prouve pas que sa valeur est identique : vérifier dans les réglages confidentiels, sans publier ces valeurs. Retirer les overrides de connexion/URL obsolètes qui détourneraient sessions/cache/files vers une autre base.
4. Mettre `RUN_MIGRATIONS_ON_RELEASE=false` pour le transfert et désactiver temporairement `PLATFORM_BOOTSTRAP_ENABLED`. Garder `TENANCY_ENABLED=true`. Ne pas activer de seeder, génération de clé ou assistant de création de compte sur la copie finale. Sur le modèle gratuit, garder aussi `RUN_MIGRATIONS_ON_START=false` pendant cette opération. Après validation, rétablir la politique de migrations pour les futures livraisons examinées ; le mode de migration n'est pas une dispense durable de migrations.
5. Redéployer **le même commit validé** sur web, worker et scheduler, avec ceux de traitement encore suspendus. Le démarrage reconstruit `config:cache`, donc la nouvelle URL est effectivement chargée ; un simple changement de secret sans redémarrage ne suffit pas. Ne pas exécuter `optimize:clear`/`cache:clear` : ils peuvent effacer le cache/verrous que l'on vient de préserver. Si une correction de config est nécessaire, `config:clear` puis `config:cache` seulement.
6. Contrôler la connexion réelle de chaque service à Neon en lecture seule (base/rôle/serveur/TLS), `/up` **et une requête SQL réelle** ; `/up` seul ne prouve pas la connexion DB. Vérifier que le propriétaire existant est présent avant toute remise en route. Les images des trois services doivent correspondre au même commit.
7. Lever la maintenance sur le web Neon, reprendre un worker puis le scheduler, surveiller jobs, doubles envois, réservations expirées, erreurs SQL/TLS, signatures/cookies et opérations comptables. Vérifier une connexion existante, les TOTP propriétaire, les formulaires Livewire et l'accès aux pièces privées. Reprendre les webhooks conformément au plan. Le contrôle `chorale:check-production` est utile mais inclut aussi SMTP/Chariow : distinguer ses échecs non liés à la base.

La copie de sessions ne garantit pas qu'un navigateur conservera une session expirée ou un cookie devenu invalide par changement de domaine ; conserver le domaine, `APP_KEY` et les paramètres de cookies pendant ce transfert. La copie de cache et verrous ne garantit pas qu'un TTL ne sera pas dépassé pendant la maintenance.

## Retour arrière et conservation

**Avant toute nouvelle écriture Neon** : garder tous les services arrêtés/en maintenance, remettre l'ancienne `DB_URL` sur tous les services, conserver `APP_KEY`, reconstruire leur cache de configuration, vérifier la connexion et reprendre Render PostgreSQL. Ne pas laisser un web sur une base et un worker sur l'autre.

**Après la première écriture Neon** : ne jamais simplement repointer vers l'ancienne base ; elle est désormais en retard. Geler à nouveau toutes les écritures, sauvegarder Neon et préparer une copie inverse ou une réconciliation contrôlée dans une cible distincte, avec validation et autorisation. Ne pas rejouer aveuglément paiements, jobs ou notifications.

Ne pas supprimer l'ancienne base Render, ses sauvegardes ou les exports à l'issue immédiate de la bascule. Définir une durée de conservation, vérifier une restauration Neon, les sauvegardes/PITR et la rétention de l'offre choisie, puis demander une autorisation distincte avant suppression. Si l'ancienne base est Render Free, vérifier sa date d'expiration réelle et migrer avant cette échéance : le retrait du YAML ne prolonge pas sa durée de vie.

## Références officielles à vérifier lors de l'opération

- [Neon : connexions](https://neon.com/docs/connect/connect-from-any-app), [migration PostgreSQL](https://neon.com/docs/import/migrate-from-postgres), [pooling](https://neon.com/docs/connect/connection-pooling)
- [Render Blueprint](https://render.com/docs/blueprint-spec), [variables et secrets](https://render.com/docs/configure-environment-variables)
- [PostgreSQL pg_dump](https://www.postgresql.org/docs/17/app-pgdump.html), [pg_restore](https://www.postgresql.org/docs/17/app-pgrestore.html), [services libpq](https://www.postgresql.org/docs/17/libpq-pgservice.html)

Les pages Neon/Render n'étaient pas accessibles depuis l'environnement d'audit (403 réseau). La validation locale ne prouve ni les droits du compte Neon, ni sa connectivité TLS, ni l'application du Blueprint sur Render : ces contrôles restent manuels avant bascule.

## Validation locale de ces modifications

- Suite PostgreSQL 17 / PHP 8.4 : 605 cas, **603 réussites, 2 ignorés**, 2 940 assertions ; aucun échec.
- Suite SQLite / PHP 8.4 : 605 cas, **602 réussites, 3 ignorés**, 2 930 assertions ; aucun échec. Deux cas d'accès sont ignorés car tous les rôles accèdent au module ; le troisième est le test d'intégration PostgreSQL, exécuté dans la suite PostgreSQL.
- Deux tests Python du manifeste réussis ; contrôles Pint des fichiers PHP concernés, syntaxe shell et `git diff --check` réussis.
- Répétition locale sur deux bases PostgreSQL 17 jetables : export custom/restauration transactionnelle, égalité des **52 tables et 42 séquences**, dont licences actives/expirées synthétiques, données comptables, mots de passe et secret propriétaire chiffré. Le secret restauré est déchiffrable avec la clé Laravel locale inchangée. Une séquence artificiellement avancée au-delà des IDs existants est conservée.
- Tests négatifs : changement d'un abonnement sans changer le nombre de lignes détecté ; décalage d'une séquence détecté. La copie de répétition est ensuite rétablie et comparée de nouveau.
- Connexion Laravel par vraie DB_URL à un PostgreSQL TLS local : autorité approuvée et nom correct acceptés ; autorité inconnue et nom erroné rejetés avec `verify-full`.
- Vérification HTTP locale : connexion administrateur, tableau de bord, choristes, transactions et répertoire toujours accessibles.

Ces résultats concernent des données de démonstration et des certificats de test locaux. Ils ne valident pas encore le certificat, les extensions, les droits ou la capacité de votre Neon réel. Les tests de structure YAML sont passés ; la validation distante du schéma JSON officiel Render et la construction/déploiement de l'image de production n'ont pas été exécutés dans cette tâche. La CI conserve la validation du schéma Render et les contrôles du conteneur. Aucun transfert de production, changement d'APP_KEY, déploiement ou suppression de base n'a eu lieu.
