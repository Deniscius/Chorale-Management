# Portail propriétaire

## Interface et navigation

Le portail dispose de sa propre feuille de styles et d’un module JavaScript léger, chargés uniquement par sa coque. La navigation latérale devient une navigation compacte sur les petits écrans. Les tableaux restent défilants dans leur région, les statuts conservent un libellé en plus de la couleur et les répartitions utilisent les nombres réels, sans données fictives. Les cartes du tableau de bord ouvrent les listes filtrées.

Les formulaires fonctionnent sans JavaScript. Avec JavaScript, le mot de passe peut être affiché temporairement, les jours supplémentaires sont demandés uniquement pour une prolongation et un double envoi est bloqué côté interface. Les contrôles serveur restent obligatoires. Le retour par l’historique du navigateur rétablit les boutons d’envoi. La récupération de compte reste disponible par le terminal serveur sécurisé, pas par un lien public.

## Accès

Le portail `/plateforme` est distinct des écrans des chorales. Ses comptes sont stockés dans `platform_admins` et utilisent le guard de session `platform`. Un administrateur de chorale ne peut pas s’attribuer ces droits. Aucun formulaire d’inscription publique et aucun compte par défaut ne sont créés. L’essai et la suspension d’une chorale ne bloquent pas le propriétaire, même s’il possède aussi une session de chorale dans le même navigateur.

La connexion nécessite un mot de passe et un code TOTP à six chiffres (SHA-1, pas de 30 secondes, tolérance d’un pas). Le dernier compteur est verrouillé et enregistré pour interdire le rejeu concurrent d’un code. Le secret est chiffré par Laravel avec APP_KEY ; les mots de passe sont hachés. Cinq tentatives par minute et par IP/adresse de compte, avec un plafond HTTP additionnel, limitent les essais. Les codes et mots de passe ne sont pas remis dans les formulaires après erreur. Pas de connexion persistante : la session propriétaire expire après huit heures et une réinitialisation d’identifiants la révoque. L’horloge du serveur et du téléphone doit être synchronisée.

## Installation et récupération

1. Sauvegarder la base et APP_KEY de manière sécurisée, puis mettre l’application en maintenance.
2. Déployer et exécuter `php artisan migrate --force`, régénérer les caches de configuration/routes/vues, compiler les ressources et redémarrer les workers. Le scheduler doit fonctionner pour la purge quotidienne des statistiques de plus de 90 jours.
3. Depuis un terminal serveur sécurisé : `php artisan platform:admin votre-adresse@example.com --name="Votre nom"`. Le mot de passe est demandé sans affichage (minimum 16 caractères, lettres et chiffres). La clé d’enrôlement TOTP est montrée dans ce terminal uniquement : l’ajouter manuellement à une application d’authentification, puis saisir le code demandé. Aucun secret n’est transmis à un service de génération de QR code.
4. Ouvrir `/plateforme/connexion` en HTTPS et utiliser le prochain code TOTP. Aucun lien d’activation par e-mail n’est requis pour ce compte provisionné sur le serveur.
5. En cas de perte du téléphone : même commande avec `--reset`, depuis le terminal serveur sécurisé. Elle demande un nouveau mot de passe et un nouvel enrôlement TOTP, révoque les sessions précédentes et inscrit la récupération dans le journal. Aucun contournement de la MFA n’est disponible sur le Web.

Limiter l’accès à SSH et aux sauvegardes d’APP_KEY. Les comptes et secrets de la démo locale ne doivent pas être repris en production. Les deux guards utilisent la même session navigateur avec des identifiants d’authentification distincts ; ce n’est pas une isolation par domaine ou par cookie. Le portail ne propose ni usurpation de session client ni accès aux fichiers privés.

## Gestion des clients

La vue d’ensemble et la liste filtrable présentent des statuts exclusifs : suspendu, payant actif, en essai, expiré. Les cartes conduisent aux listes correspondantes. Chaque fiche expose les métadonnées de l’espace, les comptes avec nom/e-mail/rôle, les volumes métier, l’activité et les dix dernières licences rattachées. Le volume documentaire est la somme déclarée en base, pas la consommation complète du disque.

Trois actions exigent une confirmation du mot de passe propriétaire et un motif de 5 à 500 caractères : suspendre, retirer la suspension, prolonger l’essai de 1 à 30 jours. Elles verrouillent l’espace et enregistrent les valeurs avant/après dans la même transaction que le journal. Une prolongation démarre à la dernière échéance encore future, ou à maintenant si l’essai est expiré. Elle exige de retirer d’abord une éventuelle suspension. Retirer une suspension ne crée pas de licence ni de nouveau délai. Aucun paiement n’est fabriqué par ces opérations. Il n’y a pas de suppression de clients ou de modifications directes de leurs données privées.

Les connexions réussies, déconnexions, créations/récupérations de propriétaires et modifications d’accès figurent dans un journal paginé sans édition via l’interface. Ce journal applicatif n’est pas un coffre inviolable : un opérateur SQL peut modifier sa base. Les tentatives échouées sont limitées mais ne sont pas enregistrées dans ce journal.

## Métriques et limites

Les requêtes HTTP authentifiées dans un espace sont agrégées par jour, utilisateur et espace : volume, erreurs serveur (5xx), requêtes lentes (au moins 1 500 ms) et durée cumulée. L’écriture est atomique et une erreur de télémétrie ne bloque pas l’utilisateur. Ni contenu de requête, URL, clé de licence, mot de passe, document ou adresse IP ne sont enregistrés dans ces statistiques. Les données de mesure restent liées à un identifiant d’utilisateur : il ne s’agit pas de statistiques anonymes. Rétention glissante de 90 jours avec le scheduler.

L’interface affiche les utilisateurs actifs et espaces actifs sur sept jours, les nouvelles inscriptions sur trente jours, les requêtes et leur durée moyenne. Les durées sont mesurées côté application avant l’écriture statistique, pas dans le navigateur ; aucun p95 n’est calculé à partir d’une moyenne. Les visiteurs anonymes, assets, portail propriétaire et refus émis avant le middleware sont exclus. Les mesures commencent à l’installation ; aucune activité antérieure n’est reconstruite. Une écriture SQL par requête suivie est ajoutée : vérifier son coût sur la charge de production avant une forte montée en charge.

La supervision vérifie la connexion SQL à l’ouverture et compte les tâches en base, leur plus ancienne date et les échecs conservés. Elle ne prouve pas la disponibilité extérieure du service, le fonctionnement des workers ou du planificateur, ni la réussite des sauvegardes. La surveillance externe, l’espace disque, les alertes et les tests de restauration restent à raccorder à l’hébergement. Aucun contenu des tâches échouées ou des logs n’est exposé au navigateur.

## Paiements

Les accès payants correspondent aux droits déjà enregistrés sur chaque espace. Le portail indique si le lien et la vérification de licences Chariow sont configurés, sans exposer les secrets. Il ne connaît pas encore les montants des ventes, remboursements ou revenus récurrents : ils restent dans Chariow. Les finances des chorales ne sont jamais assimilées aux revenus de la plateforme.

Le parcours à expiration reste celui de `docs/TENANCY.md` : écran d’abonnement, lien Chariow configuré par l’exploitant, puis saisie et vérification serveur de la licence. Aucun webhook de paiement n’est ajouté par ce portail. L’activation automatique et la synchronisation des révocations restent un chantier distinct nécessitant le produit réel, ses paramètres et des essais du fournisseur.

Références techniques : [guards Laravel](https://laravel.com/docs/12.x/authentication), [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238).
