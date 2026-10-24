# Dimensionnement et réduction des lectures SQL

## Budget de connexions PostgreSQL

Le banc initial était limité à 32 threads Apache et 25 connexions PostgreSQL. Les essais à 50 utilisateurs ont successivement révélé la saturation Apache puis des erreurs PostgreSQL « too many clients already ». Augmenter un seul des deux plafonds ne suffit pas.

Le profil de comparaison local utilise 64 threads web et PostgreSQL avec 100 connexions maximales et 128 Mo de shared_buffers. Les paramètres Compose sont explicites et configurables via PG_MAX_CONNECTIONS et PG_SHARED_BUFFERS. Ces valeurs constituent un point de départ pour le banc de démonstration, à adapter aux ressources et à tous les consommateurs de la base.

Avant la mise en service, contrôler le budget réel :

```sh
php artisan chorale:check-capacity --web-workers=64 --instances=1 --queue-workers=4 --other-connections=8
```

La commande lit les paramètres PostgreSQL sans les modifier. Elle calcule : workers web par instance × instances, plus workers de file et connexions des autres services. Elle soustrait les connexions réservées aux comptes privilégiés et exige une marge positive. L’exemple demande 76 connexions ; avec un maximum de 100 et 3 réservées, il reste 21 connexions de marge. Renseigner le nombre maximal de processus PHP simultanés (ou de threads sous Apache Windows) et l’ensemble des instances partageant cette base. Les autres services doivent inclure les autres applications, tâches planifiées et opérations de maintenance. Ce contrôle est conservateur, sans mesure de la mémoire ni du débit.

Un résultat positif ne garantit pas la capacité : max_connections accroît certaines allocations mémoire ; work_mem s’applique aux opérations SQL et peut se multiplier par requête et connexion. Ne pas relever ces paramètres sans budget mémoire. Conserver les réserves d’administration et surveiller pg_stat_activity, mémoire, CPU, erreurs et latences. Pour plusieurs instances ou une forte concurrence, évaluer un pool de connexions avec ses contraintes de transaction, plutôt que de multiplier indéfiniment les connexions au serveur.

Avec Compose, les paramètres command s’appliquent au démarrage de PostgreSQL, y compris avec un volume déjà créé. Une modification impose la recréation contrôlée du conteneur, avec une interruption planifiée ; ne pas supprimer le volume. Exemple après vérification des paramètres :

```sh
docker compose up -d postgres
```

Sur une installation PostgreSQL autonome, appliquer les valeurs dans le mécanisme de configuration habituel et redémarrer lors d’une fenêtre prévue. Sur PostgreSQL géré, utiliser les réglages de l’hébergeur. Aucun serveur distant n’est reconfiguré automatiquement par ce dépôt.

## Statistiques : seconde série d’optimisations

Les agrégats d’une même table sont calculés ensemble, sans cache persistant. Les comptages de documents continuent d’utiliser les autorisations de l’utilisateur courant. Les dépenses budgétaires restent calculées dans une requête distincte, pour éviter de multiplier les montants par une jointure.

| Bloc | Requêtes avant | Après |
| --- | ---: | ---: |
| Membres et voix | 4 | 2 |
| Œuvres et partitions accessibles | 4 | 2 |
| Budgets et dépenses validées | 5 | 2 |
| Mouvements d’instruments | 4 | 1 |
| Maintenances | 4 | 1 |
| Temps liturgiques et œuvres | 3 | 2 |
| Utilisateurs | 4 | 1 |
| **Total** | **28** | **11** |

Cela représente 17 requêtes de moins pour ces sept blocs, soit environ 61 %. Ce chiffre concerne les blocs de statistiques uniquement ; il ne mesure pas le gain de vitesse de l’application entière. Les listes et montants restent frais à chaque requête. Les suppressions logiques, types de résultat, dates inclusives et règles de confidentialité sont préservés.

Les tests confrontent les résultats aux calculs antérieurs sur tables vides et données renseignées, contrôlent les budgets SQL, les dates limites, les suppressions logiques et les changements de partage des partitions. Les budgets maximaux des écrans sont abaissés en conséquence.

## Vérification sous charge

Rejouer le profil prolongé de la [PR k6 nº 8](https://github.com/Deniscius/Chorale-Management/pull/8) sur une copie locale préchauffée, avec les mêmes données et seuils. Comparer les erreurs de chaque palier et la récupération, en conservant les échecs. Les réglages du serveur et les agrégats SQL constituent deux changements distincts : une amélioration globale ne doit pas être attribuée entièrement au code. Les données synthétiques et le générateur sur la même machine ne valident pas la capacité de production.

## Références

- [Connexions et réserves PostgreSQL 17](https://www.postgresql.org/docs/17/runtime-config-connection.html)
- [Mémoire PostgreSQL 17](https://www.postgresql.org/docs/17/runtime-config-resource.html)
- [Connexions concurrentes Apache Windows](https://httpd.apache.org/docs/2.4/mod/mpm_winnt.html)
