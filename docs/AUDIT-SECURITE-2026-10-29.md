# Audit de sécurité de bout en bout — 29 octobre 2026

## Conclusion

Le socle applicatif est sain : aucune vulnérabilité connue dans les dépendances, aucune clé privée suivie par Git, contrôles multi-tenant et fichiers privés couverts par des tests, MFA TOTP sur l'administration plateforme, TLS vérifié vers Neon et configuration de production sans mode debug.

L'audit a toutefois confirmé un risque opérationnel critique hors du code : l'ancien secret PostgreSQL Render a été communiqué en clair pendant l'assistance. Il doit être révoqué, même si Neon est désormais la base utilisée. Le code ne contient pas ce secret.

Les quatre lacunes techniques identifiées ont été corrigées : processus d'arrière-plan exécutés sans privilèges root, sessions chiffrées au repos, politique CSP de base, et validation stricte de l'URL Church Project. Les actions GitHub tierces sont désormais liées à des commits immuables.

## Périmètre et méthode

- application Laravel 12, Livewire, Blade et JavaScript ;
- authentification, autorisations, MFA plateforme, limitation de débit et révocation des sessions ;
- isolation des chorales, Gates, modèles, routes et fichiers privés ;
- téléversements DOCX/PDF/images/audio et conversion LibreOffice/Poppler ;
- PostgreSQL Neon, sessions, cache, files d'attente et migrations ;
- Docker, Render Blueprints, scripts de démarrage et CI GitHub Actions ;
- intégrations Church Project, Chariow, D7, SMS et IA ;
- dépendances Composer/npm et recherche de secrets dans tous les objets Git ;
- vérifications HTTP passives de l'instance Render publique.

Les contrôles de production sont restés non destructifs. Aucun essai de mot de passe, modification de données, téléversement malveillant ou scan agressif n'a été lancé sur Render.

## Résultats par sévérité

### Critique — secret de l'ancienne base Render exposé hors du dépôt

Le mot de passe a été partagé en clair dans la conversation. Un secret exposé doit être considéré comme compromis. La recherche de l'historique Git confirme qu'il n'est pas versionné.

Actions manuelles obligatoires :

1. conserver et vérifier le dernier backup ;
2. révoquer ou régénérer le mot de passe PostgreSQL Render ;
3. supprimer l'autorisation réseau publique `0.0.0.0/0` dès que les contrôles de restauration sont terminés ;
4. révoquer puis recréer toute clé API Neon affichée dans une capture ou copiée dans un canal non secret ;
5. ne supprimer l'ancienne base qu'après la période de rétention et la validation métier prévues.

### Élevé — workers et scheduler exécutés en root — corrigé

Les processus Apache traitaient déjà les requêtes sous `www-data`, mais les commandes `queue:work` et `schedule:run` héritaient du compte root du conteneur. Une vulnérabilité dans un traitement de document ou un job aurait donc eu un impact inutilement élevé dans le conteneur.

`gosu` est installé et les deux processus abandonnent maintenant les privilèges avant Laravel. La construction complète de l'image de production valide ce changement.

### Moyen — contenu des sessions PostgreSQL non chiffré — corrigé dans la configuration

Les cookies étaient protégés, `Secure`, `HttpOnly` et `SameSite=Lax`, mais la charge utile des sessions restait lisible dans la table `sessions`. `SESSION_ENCRYPT=true` est maintenant imposé dans les deux Blueprints et dans l'exemple d'environnement.

L'activation sur le service Render existant doit être faite pendant une courte fenêtre de maintenance : arrêter les nouvelles connexions, vider uniquement la table `sessions`, activer la variable, puis redéployer. Cette opération déconnectera les utilisateurs sans toucher aux données métier. `APP_KEY` doit rester strictement inchangée.

### Moyen — URL Church Project insuffisamment contrainte — corrigé

Le jeton d'invitation pouvait être envoyé vers toute URL définie dans l'environnement et le client acceptait les redirections HTTP. La configuration est désormais refusée si l'URL n'est pas HTTPS, contient des identifiants, une requête, un fragment ou une adresse IP privée/réservée. HTTP reste permis uniquement vers localhost en développement/test. Les redirections sont désactivées, le délai de connexion est borné et le délai total est limité à 30 secondes.

### Faible — protection navigateur et chaîne CI — corrigé

- ajout de `Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'self'`, compatible avec Livewire ;
- conservation de HSTS, `nosniff`, `SAMEORIGIN`, politique de référent et permissions navigateur ;
- épinglage de toutes les actions GitHub tierces sur leur SHA-1 complet, avec la version lisible en commentaire.

## Contrôles satisfaisants

- `composer audit --locked` : aucun avis de sécurité ;
- `npm audit`, dépendances de production et de développement : 0 vulnérabilité ;
- historique Git : aucune clé privée ni secret réel détecté ;
- mot de passe : hachage Laravel, règles minimales, erreurs de connexion génériques et limitation par IP/identifiant ;
- plateforme : mot de passe + TOTP, anti-rejeu, durée maximale de session de 8 heures, révocation après changement des identifiants ;
- multi-tenant : contexte réinitialisé par requête, scopes et Gates, refus des fichiers hors du tenant ;
- documents : quotas, chemins privés, contrôle d'intégrité SHA-256, `no-store`, types limités, blocage des macros/objets DOCX et actions actives PDF ;
- conversion : commandes Symfony Process sous forme de tableau, sans interprétation shell, répertoire temporaire aléatoire en mode `0700` ;
- Neon : `sslmode=verify-full` et autorités système, URL secrète hors Git, aucune opération de seeding automatique ;
- production publique : HTTPS avec redirection HTTP 301, HSTS, TRACE refusé, debug absent ; `.env`, `.git`, Composer, Telescope, Ignition, server-status et phpinfo non exposés ; traversée de chemin rejetée ;
- CI : permissions GitHub minimales `contents: read`, tests SQLite/PostgreSQL et construction réelle du conteneur.

## Risques résiduels et prochaine étape

1. **MFA des administrateurs de chorale** : seuls les propriétaires de plateforme ont aujourd'hui un second facteur. Ajouter TOTP ou WebAuthn aux comptes administrateurs de tenant réduirait le principal risque de prise de compte.
2. **Rotation et récupération** : documenter un calendrier trimestriel pour les clés API, les comptes de secours et un test de restauration Neon. Conserver les secrets uniquement dans Render/Neon/GitHub Secrets.
3. **Journalisation et alertes** : centraliser les échecs d'authentification, changements de rôles, exports et téléchargements sensibles, avec alertes sans données personnelles ni jetons.
4. **Test authentifié en préproduction** : exécuter un DAST avec deux tenants jetables et plusieurs rôles afin de vérifier les IDOR et les parcours Livewire sans risquer les données réelles.
5. **CSP plus stricte** : collecter les violations en préproduction, puis ajouter progressivement `script-src`, `style-src`, `connect-src` et des nonces compatibles Livewire.

## Preuves de validation

```text
Image Docker de production : construite avec succès
Vite : 64 modules transformés, build réussi
Tests ciblés sécurité : 11 tests, 90 assertions — OK
Suite PHPUnit : 716 tests, 4217 assertions, 4 ignorés — OK
Test CI immuable ajouté : 7 tests unitaires, 57 assertions — OK
HTTP public : fichiers sensibles 404, traversée 400, HTTP vers HTTPS 301
```

Les quatre tests ignorés dépendent d'outils ou de conditions spécifiques et ne correspondent pas à un échec. Aucune bascule de base, rotation de secret ou suppression de données n'a été effectuée pendant cet audit.
