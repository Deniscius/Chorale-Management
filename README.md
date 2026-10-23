> Migration PostgreSQL, démarrage sur une base neuve et règles comptables : [guide détaillé](docs/POSTGRESQL.md).

# Gestion Chorale

Application interne de gestion pour une chorale paroissiale. Elle centralise les
choristes, le répertoire liturgique, les répétitions et célébrations, l'inventaire
des instruments, le courrier, les activités et la comptabilité — avec un contrôle
d'accès par rôle (RBAC) et une interface entièrement en français.

> Application privée : l'inscription publique est désactivée. Les comptes sont
> créés par un administrateur depuis le module **Utilisateurs**.

---

## Sommaire

- [Pile technique](#pile-technique)
- [Modules fonctionnels](#modules-fonctionnels)
- [Rôles et droits (RBAC)](#rôles-et-droits-rbac)
- [Installation](#installation)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Structure du projet](#structure-du-projet)
- [Performance](#performance)
- [Rappels de répétition (e-mail / SMS)](#rappels-de-répétition-e-mail--sms)
- [Assistant IA](#assistant-ia)
- [Qualité et tests](#qualité-et-tests)
- [Déploiement en production](#déploiement-en-production)

---

## Pile technique

| Élément | Version / choix |
| --- | --- |
| Framework | Laravel 12 |
| Langage | PHP 8.2 |
| Composants d'interface | Livewire 4 (pages entières et formulaires) |
| Styles | Tailwind CSS 3 (design system centralisé) |
| Authentification | Laravel Breeze (sessions) |
| Base de données | PostgreSQL par défaut, SQLite pour les tests rapides |
| File d'attente / cache / sessions | `database` (aucun service externe requis) |

Aucune dépendance JavaScript lourde : Alpine.js est fourni par Livewire pour les
interactions de la coque (menus, notifications, fenêtres modales).

---

## Modules fonctionnels

| Module | Écrans | Contenu |
| --- | --- | --- |
| **Choristes** | `/membres` | Fiches choristes, voix, statuts, rattachement à une chorale, comptes associés |
| **Commission liturgique** | `/oeuvres`, `/repetitions`, `/celebrations`, `/temps-liturgiques`, `/rappels` | Répertoire, programme des répétitions (compte rendu assisté), célébrations et concerts, années liturgiques, journal des rappels |
| **Commission des instruments** | `/instruments`, `/instruments/mouvements`, `/instruments/maintenance`, `/instruments/ensembles` | Inventaire, prêts et mouvements, maintenance, ensembles instrumentaux |
| **Secrétariat** | `/courriers`, `/activites` | Courrier entrant/sortant, suivi de traitement, activités et participants |
| **Comptabilité** | `/transactions`, `/budgets`, `/cotisations`, `/dons` | Recettes et dépenses, budgets annuels, cotisations des choristes, dons |
| **Administration** | `/utilisateurs` | Comptes d'accès et rôles (réservé à l'administrateur) |
| **Pilotage** | `/dashboard` | Indicateurs consolidés et échéances à venir |

Chaque écran de liste propose la recherche, des filtres, la pagination en français
et les actions adaptées aux droits de l'utilisateur.

Deux fonctions transverses complètent les modules : les **rappels de répétition**
(e-mail / SMS, écran `/rappels`) et l'**assistant IA** de rédaction des comptes
rendus (facultatif, désactivé par défaut).

---

## Rôles et droits (RBAC)

Le contrôle d'accès repose sur trois mécanismes complémentaires :

1. **Énumération `App\Enums\UserRole`** — source unique de vérité : libellé,
   couleur, modules consultables (`modules()`) et modules modifiables
   (`manageModules()`).
2. **Gates Laravel** — `view-module`, `manage-module`, `manage-users`. Un
   `Gate::before` accorde tous les droits à l'administrateur.
3. **Middleware `module:<clé>`** (`EnsureModuleAccess`) — protège chaque route
   d'écran ; une requête hors périmètre renvoie une erreur 403.

### Matrice des droits

`L` = lecture · `É` = écriture · `—` = aucun accès

| Rôle | Choristes | Liturgique | Instruments | Courriers | Activités | Comptabilité | Utilisateurs |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Administrateur | É | É | É | É | É | É | É |
| Directeur de la chorale | É | É | É | É | É | É | — |
| Commission liturgique | L | É | L | — | L | — | — |
| Commission des instruments | L | — | É | — | L | — | — |
| Commission Vie culturelle | L | — | — | L | É | — | — |
| Commission Suivi et Formation | L | É | — | — | L | — | — |
| Trésorier | L | — | — | L | L | É | — |
| Secrétaire | É | L | — | É | L | — | — |
| Choriste | L | L | — | — | L | — | — |

Les droits d'écriture sont toujours inclus dans les droits de lecture : un rôle ne
peut jamais modifier un module qu'il ne peut pas consulter (vérifié par un test).

---

## Installation

### Prérequis

- PHP **8.2+** avec les extensions `pdo_pgsql` (ou `pdo_sqlite`), `mbstring`, `openssl`
- Composer 2
- Node.js **18+** et npm

### Mise en route

```bash
# 1. Dépendances
composer install
npm install

# 2. Configuration
cp .env.example .env
# Renseigner PostgreSQL et créer la base : voir docs/POSTGRESQL.md
php artisan key:generate

# 3. Base de données et jeu de démonstration
php artisan migrate --seed

# 4. Assets
npm run build        # ou : npm run dev (rechargement à chaud)

# 5. Serveur local
php artisan serve
```

L'application est disponible sur <http://127.0.0.1:8000>.

### Données de démonstration

Le seeder crée deux chorales, une soixantaine de choristes, un répertoire
liturgique, des répétitions, des instruments, du courrier, des activités et un
exercice comptable complet.

```bash
php artisan migrate:fresh --seed   # réinitialisation complète
```

---

## Comptes de démonstration

Mot de passe commun : **`password`**

| Adresse | Rôle |
| --- | --- |
| `admin@chorale.ci` | Administrateur |
| `directeur@chorale.ci` | Directeur de la chorale |
| `liturgique@chorale.ci` | Commission liturgique |
| `instruments@chorale.ci` | Commission des instruments |
| `tresorerie@chorale.ci` | Trésorier |
| `secretariat@chorale.ci` | Secrétaire |
| `choriste@chorale.ci` | Choriste |

> Ces comptes sont destinés au développement et à la démonstration. Supprimez-les
> ou changez leurs mots de passe avant toute mise en production.

---

## Structure du projet

```
app/
├── Console/Commands/        Commandes planifiées
├── Enums/                   Énumérations métier (rôles, statuts, types)
├── Http/
│   ├── Controllers/         Tableau de bord, profil, authentification
│   └── Middleware/          EnsureModuleAccess (protection par module)
├── Livewire/                Écrans et formulaires (un dossier par module)
│   └── Concerns/            InteractsWithModule (helpers d'autorisation)
├── Models/                  Modèles Eloquent
├── Providers/               Gates, garde-fous Eloquent
└── Support/                 Utilitaires (formatage monétaire, etc.)

resources/
├── css/app.css              Design system (classes composants)
└── views/
    ├── components/          Bibliothèque de composants Blade partagés
    ├── layouts/             Coque applicative (sidebar, topbar, toasts)
    └── livewire/            Vues des écrans Livewire

tests/
├── Concerns/                Outils d'analyse (compteur de requêtes SQL)
└── Feature/
    └── Analysis/            Audit automatisé (performance, intégrité, RBAC)
```

---

## Performance

L'application a fait l'objet d'un audit de performance. Les pratiques suivantes
sont appliquées et **verrouillées par des tests**.

### 1. Navigation sans rechargement (SPA)

Les liens internes de la coque (barre latérale, cartes de statistiques,
pagination, profil) utilisent `wire:navigate` et le préchargement au survol
(`wire:navigate.hover`). La page suivante est chargée en arrière-plan, seules
les zones utiles sont remplacées : navigation instantanée, état des composants
Livewire conservé et barre de progression aux couleurs de l'application
(`--livewire-progress-bar-color`).

Les tableaux affichent un état de chargement discret (`wire:loading.delay`) et
conservent leur hauteur (`scrollbar-gutter: stable`) pour éviter les sauts de
mise en page.

### 2. Propriétés calculées Livewire lues comme des propriétés

Les agrégats (statistiques d'écran) sont exposés par des méthodes `#[Computed]`.
Ils doivent être lus avec la **syntaxe de propriété** :

```blade
{{-- Correct : mis en cache pour la durée de la requête --}}
<x-stat-card :value="$this->stats['total']" />

{{-- À proscrire : contourne le cache et relance les agrégats SQL --}}
<x-stat-card :value="$this->stats()['total']" />
```

Cette correction a fait passer les écrans de **40 à 75 requêtes** à **5 à 9
requêtes** (jusqu'à −90 %).

### 3. Chargement anticipé des relations

Les relations sont chargées avec `with()` / `withCount()`. Le chargement
paresseux est **interdit hors production** :

```php
// app/Providers/AppServiceProvider.php
Model::preventLazyLoading(! $this->app->isProduction());
```

Une requête N+1 déclenche donc une exception en développement et dans les tests,
au lieu de dégrader silencieusement les performances en production.

### 4. Pagination systématique

Chaque écran de liste pagine (15 à 20 lignes). Le nombre de requêtes reste
constant quel que soit le volume de données.

### 5. Index de base de données

Les colonnes filtrées (statut, type, voix, rôle) et toutes les clés étrangères
sont indexées — voir `2026_01_01_000027_add_performance_indexes.php`.

### 6. Paramètres et tableau de bord mis en cache

- **Paramètres** : `SettingsStore` (singleton) effectue **une seule lecture** de
  la table `settings` par requête, partagée par la coque, les layouts et les
  composants. Nom, logo et coordonnées sont lus une fois au lieu d'une requête
  par affichage. Toute écriture (`Setting::set`) invalide immédiatement le cache
  et la mémoire de requête.
- **Tableau de bord** : `Cache::flexible('dashboard.overview.v1', [30, 120])`
  applique un *stale-while-revalidate*. Les agrégats sont servis depuis le cache
  pendant 30 s ; au-delà, la version en cache reste affichée et est rafraîchie
  en fin de requête (aucune attente pour l'utilisateur).
  `withCount('works')` supprime un N+1 : **19 → 15 requêtes**.
- **Identité de la chorale** : `ChoraleIdentity` mémorise les valeurs pendant la
  requête ; le logo est exposé par une route dédiée avec une URL versionnée
  (`?v=…`), un `ETag` et `Cache-Control: public, max-age=31536000, immutable`.
  Le navigateur ne le retélécharge que lorsqu'il change.

### 7. Assets et CSS

- Le safelist Tailwind ne contient que les classes réellement composées
  dynamiquement (badges d'enum, pastilles de saison). Une safelist par motif
  générait toutes les variantes d'opacité (`bg-…-100/5` à `/95`) :
  **458 Ko → 93 Ko** de CSS, soit **70 Ko → 14 Ko** gzip.
- Les assets sont hachés par Vite (`npm run build`) et peuvent donc être servis
  avec un cache immuable d'un an (voir Déploiement).

### 8. SQLite (développement et petits déploiements)

Les pragmas sont définis dans `config/database.php` et restent donc actifs même
sans cache de configuration :

```dotenv
DB_JOURNAL_MODE=wal       # lectures concurrentes, écritures plus rapides
DB_BUSY_TIMEOUT=5000      # attente au lieu d'une erreur « database is locked »
DB_SYNCHRONOUS=normal     # bon compromis durabilité / performance
```

Pour un déploiement multi-utilisateurs, préférer MySQL ou PostgreSQL.

### 9. Mesurer avant d'optimiser

Le rapport de mesure est régénéré à chaque exécution des tests :

```bash
php artisan test --filter=QueryBudgetTest
# Rapport : storage/app/analysis/queries.json
```

---

## Rappels de répétition (e-mail / SMS)

Chaque matin, le planificateur envoie aux choristes **actifs** un rappel pour la
répétition ayant lieu dans *N* jours : date, horaire, lieu et programme
d'œuvres. Le message part par e-mail et, si le canal est activé, par SMS.

```bash
php artisan chorale:send-reminders                 # envoi réel
php artisan chorale:send-reminders --dry-run       # simulation, rien n'est envoyé
php artisan chorale:send-reminders --rehearsal=12  # cibler une répétition
php artisan schedule:list                          # vérifier la planification
```

**Garanties**

- **Idempotence** : la table `reminders` journalise chaque envoi ; relancer la
  commande le même jour ne double aucun message.
- **Respect des préférences** : un canal n'est utilisé que si le choriste l'a
  activé *et* si la coordonnée correspondante est renseignée (`notify_email` /
  `notify_sms`, écran du choriste).
- **Traçabilité** : l'écran **Rappels** (module liturgique) liste les envois,
  filtre par canal et par statut, et affiche la cause des échecs.
- **Aucun service externe requis en développement** : le pilote SMS `log`
  écrit le message dans `storage/logs/laravel.log`.

**Configuration** (voir `.env.example`) : `REMINDERS_ENABLED`,
`REMINDERS_DAYS_BEFORE`, `REMINDERS_SEND_AT`, `REMINDERS_SMS_ENABLED`,
`REMINDERS_SMS_DRIVER`, `SMS_ENDPOINT`, `SMS_TOKEN`.

Le pilote SMS HTTP envoie un corps JSON `{ from, to, message }` authentifié par
un jeton Bearer : la plupart des agrégateurs peuvent être branchés ainsi
(`app/Services/Sms/HttpSmsSender.php`).

---

## Assistant IA

L'IA est **facultative et désactivée par défaut** : l'application est complète
sans elle. Quand elle est active, un bouton **Compte rendu** apparaît sur les
répétitions pour les rôles autorisés : l'assistant rédige une proposition à
partir du lieu, du programme, des effectifs et des notes de séance. Le texte est
toujours présenté comme un brouillon — le responsable le relit, le corrige, puis
l'enregistre.

- Deux fournisseurs pris en charge : **OpenRouter** (service hébergé) et
  **Ollama** (modèle local — aucune donnée ne quitte le serveur).
- Les notes libres sont exclues de l’envoi. Le contexte exact (date, lieu, œuvres, effectifs) est affiché avant génération pour relecture ; vérifier les champs saisis avant tout envoi à un fournisseur distant.
- Activation : `AI_ENABLED=true`, `AI_PROVIDER`, puis la clé ou l'URL du
  fournisseur (voir `.env.example`).
- Rôles autorisés : `config/ai.php` (`roles`), en plus du droit de gestion du
  module liturgique.

---

## Qualité et tests

```bash
php artisan test                       # suite complète
php artisan test --filter=Analysis     # audit applicatif uniquement
```

### Couverture de l'audit (`tests/Feature/Analysis/`)

| Suite | Ce qu'elle vérifie |
| --- | --- |
| `QueryBudgetTest` | Budget de requêtes par écran, absence de N+1, indépendance au volume |
| `DataIntegrityTest` | Valeurs conformes aux énumérations, absence d'orphelins, unicité, cascades, cohérence métier (montants, dates) |
| `ArchitectureTest` | Routes authentifiées, protection `module:`, cohérence des rôles et des Gates |
| `ViewQualityTest` | Cache des propriétés calculées, compilation des vues, composants existants, pagination française, échappement |
| `IndexCoverageTest` | Présence des index sur les colonnes filtrées et les clés étrangères |

En complément de l'audit, deux suites couvrent les fonctions transverses :
`RehearsalRemindersTest` (sélection des destinataires, idempotence, préférences,
canaux, journal) et `ChoraleAssistantTest` (génération, garde-fous RBAC,
relecture humaine) — l'IA y est testée via un client factice, sans appel réseau.

Les tests s'appuient sur `Tests\Concerns\InteractsWithQueryCounter` pour compter
les requêtes, identifier les motifs répétés et détecter les signatures N+1.

### Formatage du code

```bash
./vendor/bin/pint
```

---

## Déploiement en production

### 1. Configuration

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chorale.votre-domaine.ci
APP_LOCALE=fr

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_SSLMODE=prefer
DB_DATABASE=chorale
DB_USERNAME=chorale
DB_PASSWORD=••••••

# Cache et sessions partagés : Redis est recommandé dès qu'il est disponible.
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=database

# Sans Redis : CACHE_STORE=file et SESSION_DRIVER=file restent valides.
# Paramètres SQLite uniquement (DB_CONNECTION=sqlite) :
# DB_JOURNAL_MODE=wal
# DB_BUSY_TIMEOUT=5000
# DB_SYNCHRONOUS=normal

MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.ci
MAIL_PORT=587
MAIL_USERNAME=••••••
MAIL_PASSWORD=••••••
MAIL_FROM_ADDRESS=contact@chorale.ci

# Rappels : à ajuster selon les usages de la chorale.
REMINDERS_ENABLED=true
REMINDERS_DAYS_BEFORE=2
REMINDERS_SEND_AT=08:00
REMINDERS_SMS_ENABLED=false
REMINDERS_SMS_DRIVER=http
SMS_ENDPOINT=
SMS_TOKEN=
```

### 2. Déploiement

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan optimize            # config + routes + vues
php artisan storage:link        # si des pièces jointes sont servies
```

Après toute modification de `.env`, exécuter `php artisan optimize:clear` puis
`php artisan optimize` (les fichiers de cache ne se rechargent pas seuls).

Recommandations serveur :

- ne servir que le dossier `public/` (jamais la racine du projet) ;
- activer HTTP/2 et la compression **gzip** ou **brotli** pour CSS, JS, JSON et
  SVG ;
- servir `/build/assets/*` avec `Cache-Control: public, max-age=31536000, immutable` (les noms sont hachés par Vite) ;
- ne pas mettre en cache les réponses HTML (Livewire a besoin de réponses
  dynamiques) ;
- garder **OPcache** actif et recharger PHP-FPM après chaque déploiement pour
  vider le cache de bytecode (`opcache.validate_timestamps=0` en production).

### 3. Droits du système de fichiers

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### 4. Files d'attente et tâches planifiées

```bash
# Worker supervisé (notifications, tâches longues)
php artisan queue:work --tries=3 --timeout=90

# Planificateur (cron, toutes les minutes)
* * * * * cd /chemin/vers/l-app && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Liste de contrôle finale

- [ ] `APP_DEBUG=false` et `APP_ENV=production`
- [ ] `php artisan key:generate` exécuté (clé unique par environnement)
- [ ] HTTPS actif et cookies de session sécurisés
- [ ] Mots de passe de démonstration supprimés ou modifiés
- [ ] Sauvegarde automatique de la base de données
- [ ] `php artisan optimize` exécuté après chaque déploiement
- [ ] `CACHE_STORE` / `SESSION_DRIVER` sur Redis (ou `file` à défaut)
- [ ] Compression gzip/brotli et cache immuable des assets activés
- [ ] Worker de file d'attente supervisé (Supervisor / systemd)
- [ ] Planificateur actif (`schedule:run` chaque minute) si les rappels sont activés
- [ ] `REMINDERS_*` renseignés et `SMS_ENDPOINT` / `SMS_TOKEN` configurés si le SMS est activé
- [ ] `AI_ENABLED` selon le besoin, avec une clé ou une URL de fournisseur valide
- [ ] OPcache activé sur PHP-FPM
- [ ] Journalisation et supervision des erreurs en place

---

## Licence

Projet interne. Tous droits réservés à la chorale.

Les corrections de l’audit et les étapes de déploiement sont décrites dans [docs/AUDIT-CORRECTIONS.md](docs/AUDIT-CORRECTIONS.md).
