# Démonstration Render avec un budget de 0

Le fichier **render.yaml** à la racine contient uniquement un service web Free et une base PostgreSQL Free. Aucun worker, cron ou disque payant n'est créé. Les paiements sont laissés désactivés et les rappels automatiques arrêtés. Les seeders de démonstration ne sont pas exécutés : la démo commence sans utilisateurs fictifs.

## Limites à connaître

- Le site se met en veille après 15 minutes sans trafic. Le réveil peut prendre environ une minute.
- Tous les fichiers importés sont temporaires : ils disparaissent après une veille, un redémarrage ou une livraison. Un bandeau le rappelle dans l'application. Tester seulement avec des fichiers et des personnes fictifs.
- La base PostgreSQL gratuite expire après **30 jours**. Elle devient alors inaccessible ; après 14 jours supplémentaires, Render la supprime si elle n'a pas été mise à niveau. Les comptes et les données ne sont donc pas pérennes. Cette échéance de l'hébergement est indépendante de l'essai applicatif de trois jours par chorale.
- Il n'y a pas de sauvegarde intégrée de la base gratuite, de Shell Render ni de tâches planifiées garanties. Les e-mails sont simulés dans les logs : activation par mail, réinitialisation par mail et rappels réels ne sont pas disponibles. Les ports SMTP usuels sont bloqués par Render Free ; un fournisseur par API HTTPS serait une évolution distincte.
- Les quotas de calcul, de construction et de trafic du compte s'appliquent. Pour respecter strictement 0, utiliser l'offre de compte gratuite, ne pas ajouter de moyen de paiement pour dépassements et vérifier les limites dans Billing ; Render peut suspendre le service ou les builds à épuisement. Ne pas choisir Upgrade ni une ressource payante.

Ces limites rendent cette configuration adaptée à une **présentation et des essais**, pas à la vente d'un service de conservation des archives. La configuration durable optionnelle se trouve dans `deploy/render.production.yaml` et nécessite un budget.

## Création dans Render

1. Se connecter à [Render](https://dashboard.render.com/) avec GitHub et autoriser `Deniscius/Chorale-Management`.
2. **New → Blueprint**, sélectionner le dépôt et la branche contenant la préparation Render, puis le fichier racine `render.yaml`.
3. Vérifier que **chorale-demo** et **chorale-demo-postgres** sont tous deux **Free**, et qu'aucune ressource payante n'est proposée. Une seule base PostgreSQL Free est autorisée par espace de travail : si vous en avez déjà une, ne la supprimer pas pour créer celle-ci ; adapter la configuration.
4. Renseigner `APP_URL` avec l'URL HTTPS publique choisie pour le site. Après attribution de l'adresse `onrender.com`, corriger cette variable si nécessaire et relancer la livraison avant de partager le lien.
5. Renseigner `TRUSTED_PROXIES` avec les adresses/CIDR réels des proxies entrants confirmés avec Render, séparés par des virgules. Ne pas utiliser de joker ni confondre avec les adresses de sortie. Vérifier HTTPS et IP client avant ouverture. Une liste vide ne suffit pas à valider le déploiement.
6. Renseigner les trois secrets d'initialisation propriétaire décrits ci-dessous directement dans Render. Ne pas les transmettre dans une conversation ou un commit.
7. Lancer la création gratuite. Le conteneur applique les migrations puis `ProductionSeeder` **au démarrage**, car ce profil ne dépend pas d'un pré-déploiement payant. Il ne réinitialise pas la base. Attendre la réussite des migrations et du démarrage.

## Votre accès propriétaire sans Shell payant

Le premier démarrage peut créer un unique compte propriétaire grâce à :

| Secret Render | Valeur à fournir personnellement |
| --- | --- |
| `PLATFORM_BOOTSTRAP_EMAIL` | Votre adresse e-mail |
| `PLATFORM_BOOTSTRAP_PASSWORD` | Un mot de passe unique d'au moins 16 caractères contenant lettres et chiffres |
| `PLATFORM_BOOTSTRAP_TOTP_SECRET` | Une nouvelle clé Base32 aléatoire de 32 caractères, alphabet A–Z et 2–7 |

Générer la clé TOTP dans votre gestionnaire de mots de passe, ou localement avec Node.js :

```sh
node -e "const c=require('crypto'),a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; console.log(Array.from({length:32},()=>a[c.randomInt(32)]).join(''))"
```

Ajouter cette clé par saisie manuelle dans votre application d'authentification : TOTP, SHA-1, six chiffres, période de 30 secondes. Garder la clé et le mot de passe dans votre gestionnaire sécurisé. Aucune clé commune prédéfinie n'est fournie.

Après la première connexion réussie à **https://VOTRE-URL/plateforme/connexion**, **retirer les trois variables PLATFORM_BOOTSTRAP des variables Render** et redéployer pour purger leur copie dans la configuration en cache. Les démarrages suivants préservent le propriétaire existant et n'utilisent plus ces secrets. La commande refuse de créer un second propriétaire ou de réinitialiser les accès existants. Sans mode démo, elle est désactivée.

Garder `APP_KEY` inchangée : sa valeur générée par Render est normalisée au format Laravel par le script, et chiffre notamment la clé TOTP enregistrée en base. Ne pas publier cette valeur. Le profil gratuit ne fournit pas de récupération administrative interactive ; garder soigneusement les accès et facteurs de récupération.

## Démonstration et contrôles

- `/up` doit répondre 200, `/login` doit afficher le bandeau de démo et fonctionner en HTTPS.
- Créer votre chorale pilote par l'inscription publique puis vous connecter normalement ; il n'y a pas de mot de passe de démonstration partagé. L'essai reste limité à 72 heures et ne se réinitialise pas au réveil du site.
- Vérifier les modals, contributions personnalisées, commissions, import de partitions et droits d'accès avec des données fictives. Les pièces sont temporaires sur ce profil.
- Vérifier la connexion propriétaire avec votre mot de passe et votre code TOTP. Les métriques d'infrastructure et les logs sont dans Render.
- Vérifier le comportement après un redémarrage : comptes conservés tant que la base est active, fichiers temporaires perdus, mot de passe propriétaire conservé.
- Les achats et renouvellements automatiques ne peuvent pas être validés sur ce profil. Le contrôle `chorale:check-production` échoue volontairement pour cette démo : il exige les conditions de l'ouverture commerciale.

Pour une conservation durable à 0, il faudrait étudier des services externes avec leurs propres quotas et conditions, puis adapter et tester le stockage, les e-mails et la planification. Cette préparation ne prétend pas les avoir intégrés.

Source : [limitations officielles de Render Free](https://render.com/docs/free), vérifiées le 25 octobre 2026.
