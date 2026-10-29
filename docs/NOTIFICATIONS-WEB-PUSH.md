# Notifications Web Push

L’application conserve chaque notification dans PostgreSQL. Le centre de notifications la récupère à la connexion, au retour en ligne, au retour sur l’onglet et toutes les 60 secondes. L’abonnement Web Push permet en plus au navigateur d’afficher les rappels lorsque le site est fermé.

## Configuration Render

Générer une seule paire VAPID depuis un poste d’administration :

```bash
php -r "require 'vendor/autoload.php'; print_r(Minishlink\\WebPush\\VAPID::createVapidKeys());"
```

Ajouter ensuite aux variables secrètes du service web Render :

- `WEBPUSH_PUBLIC_KEY` : clé publique ;
- `WEBPUSH_PRIVATE_KEY` : clé privée, à ne jamais placer dans Git ;
- `WEBPUSH_SUBJECT` : `mailto:adresse-administrative@domaine.tld` ou l’URL HTTPS de l’application.

Le Blueprint payant transmet les trois valeurs au worker. Après le déploiement et les migrations, l’utilisateur active volontairement **Recevoir les rappels sur cet appareil** depuis la cloche. Un site HTTPS est requis en production.

La désactivation supprime l’abonnement côté application et côté navigateur. Les abonnements expirés sont supprimés automatiquement après une réponse 404 ou 410 du fournisseur Web Push.

## Planification gratuite

Le workflow GitHub Actions `Rappels quotidiens` appelle chaque jour à 08:00 (Africa/Lome) un point d’entrée Render protégé. Il utilise un jeton OIDC signé et valable quelques minutes : le serveur vérifie la signature GitHub, le dépôt, la branche, le workflow et le type d’événement. Aucun secret de base de données ou jeton permanent n’est confié à GitHub. Le point d’entrée n’accepte aucune autre opération et limite les appels. Un lancement manuel se fait en simulation par défaut.
