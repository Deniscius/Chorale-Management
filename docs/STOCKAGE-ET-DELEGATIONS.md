# Conservation des données, cache et commissions

## Logo et fichiers sur Render Free

Les lignes métier sont enregistrées dans PostgreSQL. Le cache sert à accélérer leur lecture ; il ne doit jamais être l’unique copie d’un logo, d’une partition ou d’une opération.

Le disque local de Render Free est temporaire : un déploiement, redémarrage ou passage en veille peut effacer les fichiers importés. C’était la cause de la disparition du logo. Vider le cache ne permet pas de reconstituer un fichier effacé : il faut réimporter l’original.

Lorsque `FILES_EPHEMERAL=true`, le pilote privé utilise désormais PostgreSQL par défaut. Les nouveaux logos et archives finalisés sont chiffrés avec la clé de l’application et enregistrés dans `private_files`, séparément du cache. Les réponses passent toujours par les contrôles de droits existants. Les fichiers locaux encore présents restent lisibles en transition.

- `PRIVATE_FILES_DRIVER=database` : stockage privé persistant dans PostgreSQL.
- `PRIVATE_FILES_DRIVER=local` : réservé à un environnement avec disque durable. Un import est refusé si le disque est déclaré temporaire.
- Taille maximale d’archive : 10 Mo ; le logo reste limité à 2 Mo.
- Quota par chorale : 64 Mo de données chiffrées, configurable avec `PRIVATE_FILES_TENANT_QUOTA`.
- Quota global : 256 Mo de données chiffrées, configurable avec `PRIVATE_FILES_GLOBAL_QUOTA`.
- Une limite atteinte refuse le nouvel import et conserve les données existantes. Les contrôles de quota sont sérialisés pour éviter les dépassements par imports simultanés.

Ce choix permet de conserver les petits fichiers sans ajouter de service payant. Pour une volumétrie importante, utiliser un stockage objet privé ou un disque durable et une base de production adaptée. **Render PostgreSQL Free expire après 30 jours et n’inclut pas de sauvegardes** : cette solution protège des déploiements du serveur web, pas de l’expiration ou de la perte de la base. Voir [les limites Render Free](https://render.com/docs/free).

Sauvegarder la base ET conserver `APP_KEY` dans un gestionnaire de secrets. Sans cette clé, les fichiers chiffrés et les secrets MFA ne sont pas déchiffrables. Ne pas changer cette clé pour vider le cache ou résoudre un incident de logo.

La page propriétaire **Santé** indique le mode de conservation, le volume chiffré utilisé et le quota global. Les quotas n’incluent pas toute la taille physique de PostgreSQL : tables métier, index et cache occupent aussi de l’espace.

## Cache

Sur Render : `CACHE_STORE=database` et `SESSION_DRIVER=database`. Les paramètres sont isolés par chorale ; leur cache est invalidé lors des modifications. Le tableau de bord est mutualisé par chorale et droits effectifs, avec un rafraîchissement différé de 30 à 120 secondes. Les listes métier interrogent directement les tables.

Les autorisations de commission ne sont mémorisées que pendant la requête. Les services de modification invalident cette mémoire ; la requête suivante recalcule les droits. Les échéances et archivages sont évalués sans cron. Un cache vidé ne supprime ni les fichiers persistants ni les lignes métier.

## Responsables et accès

Le menu **Commissions** regroupe les commissions fonctionnelles de référence et les missions personnalisées. Les références existantes sont complétées sans réinitialiser les choix des responsables, les membres ni les missions modifiées.

- **Directrice** : création et organisation des équipes, missions et périodes.
- **Administrateur** : bouton **Déléguer le responsable**, choix du compte responsable et du module associé pour une mission personnalisée.
- **Membre affecté** : consultation du module associé pendant la mission.
- **Responsable désigné** : consultation et gestion de ce module pendant la mission.

Une personne peut appartenir à plusieurs commissions et en diriger plusieurs. Les droits se cumulent. Le rôle général du compte et les informations personnelles ne sont pas modifiés. Les droits généraux déjà attribués restent valables indépendamment de ces délégations.

L’accès à un module porte sur les données de la chorale dans ce module, pas sur une copie de données réservée à chaque sous-équipe. Une mission personnalisée peut aussi rester sans module associé : elle dispose alors de son espace de mission et de son équipe.

Les archives privées de liturgie et de secrétariat restent réservées au responsable désigné et à son associé. Une affectation ordinaire ne donne pas accès à leurs pièces privées. Les documents explicitement partagés restent consultables par les utilisateurs connectés.

Le responsable doit disposer d’un compte activé. S’il est lié à une fiche choriste, il rejoint l’équipe automatiquement. La directrice ne peut pas retirer cette fiche tant que l’administrateur n’a pas réattribué la responsabilité. Avant de supprimer un compte délégué, réattribuer ou retirer ses responsabilités.
