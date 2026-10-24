# Optimisation des écrans et des statistiques

## Changements

Les cartes de synthèse de huit modules relisaient chaque table pour chaque indicateur. Les agrégats conditionnels SQL regroupent désormais les calculs d’une même table dans une seule requête. Les comptages des présences et le regroupement par nature de don conservent une requête distincte lorsque les données proviennent d’une autre table ou d’un autre regroupement.

| Statistiques du module | Requêtes avant | Requêtes après |
|---|---:|---:|
| Célébrations | 4 | 1 |
| Activités | 6 | 1 |
| Courriers | 5 | 1 |
| Répétitions et présences | 4 | 2 |
| Instruments | 5 | 1 |
| Opérations financières | 4 | 1 |
| Cotisations | 4 | 1 |
| Dons et nature dominante | 4 | 2 |
| **Total des huit blocs** | **36** | **10** |

Il s’agit de 72 % de requêtes en moins pour ces blocs de statistiques, pas de 72 % de réduction du temps de réponse global. Les listes, la pagination et les autres lectures de chaque écran ne sont pas incluses dans ce total.

L’identité de la chorale est également partagée une fois avec les vues, au lieu de reconstruire un compositeur pour chaque composant Blade. L’objet partagé charge ses valeurs à la demande et conserve le mécanisme existant de rafraîchissement lors de la modification des paramètres.

## Fraîcheur et contrôle d’accès

Aucun cache persistant de statistiques, de montants ou de documents privés n’est ajouté. Les chiffres sont recalculés à chaque requête à partir de la base ; les suppressions logiques applicables restent filtrées par les modèles. Les agrégats utilisent des paramètres SQL liés et fonctionnent sur SQLite et PostgreSQL. Les types numériques et les clés de résultat restent identiques.

Les règles d’accès des commissions, des archives privées et des modules sont conservées. Aucune migration de schéma ni modification des données métier n’est nécessaire.

## Vérification

Les tests comparent les résultats aux calculs précédents sur des données renseignées, sur des tables vides et sur les modèles utilisant la suppression logique. Ils imposent un budget de 1 ou 2 requêtes pour chaque bloc. Les budgets SQL des écrans ont été abaissés en conséquence. Un test vérifie que la modification du nom de la chorale reste visible après un premier rendu.

Les mesures HTTP utilisent le script de la [PR nº 6](https://github.com/Deniscius/Chorale-Management/pull/6), avec les mêmes profils, seuils, données et réglages Apache/PHP/PostgreSQL avant et après les changements. Le serveur et ses caches sont préchauffés. Les suites de tests ne tournent pas en parallèle avec les mesures k6.

Les chiffres de temps de réponse sont publiés dans la pull request et le rapport associé. Une paire de mesures locales est indicative : les gains SQL sont déterministes, mais les gains HTTP dépendent aussi du rendu des vues, de la machine et de la charge. Rejouer le test sur le serveur cible avec un volume représentatif avant de conclure sur la capacité de production.

## Mise à jour

Déployer les sources puis régénérer les caches de configuration et de vues selon la procédure habituelle. Aucun réglage de l’hébergement de production n’est appliqué automatiquement.
