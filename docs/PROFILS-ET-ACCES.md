# Profil personnel, choriste et responsabilités de commission

## Une personne, sa fiche, puis ses responsabilités

Le compte de connexion représente une personne. Sa fiche de choriste porte ses prénom/nom, date de naissance, coordonnées et préférences de rappels. Les responsabilités de commission s’ajoutent à cette appartenance : une même personne peut être membre de plusieurs commissions, responsable dans certaines et associée habilitée dans d’autres.

Les anciens rôles généraux (`Choriste`, `Administrateur`, `Directeur`, etc.) restent conservés. Les affectations par commission complètent les accès existants ; elles ne retirent pas les privilèges déjà accordés par un rôle général. Pour un compte ordinaire, choisir le rôle général **Choriste**, puis lui attribuer les affectations appropriées. Ne pas transformer automatiquement les anciens administrateurs ou responsables en simples choristes. La protection du dernier administrateur reste inchangée.

## Modifier ses propres informations

Le lien **Mes informations** du menu et **Mon profil** du menu du compte conduisent au même écran. Il permet de modifier prénom(s), nom, date de naissance, téléphone, adresse, profession/activité, adresse e-mail, identifiant et préférences de rappels. Le mot de passe reste dans son formulaire séparé. Les champs de date, de longueur, d’adresse e-mail et d’identifiant sont validés côté serveur ; aucune date de naissance future n’est acceptée.

Pour un compte lié à une fiche, les informations du membre et du compte sont enregistrées dans une seule transaction. Une modification de prénom/nom met à jour le nom d’affichage ; une modification de l’e-mail conserve les contrôles d’unicité et réinitialise la vérification de l’adresse selon la règle existante. Le changement d’identifiant s’applique à la prochaine connexion. L’utilisateur ne choisit pas sa voix, son pupitre, son statut, ses affectations ou son rôle depuis ce formulaire.

Pour un compte ancien ou nouveau sans fiche liée, la personne saisit elle-même ses prénom et nom. Lors de cet enregistrement explicite, une fiche de choriste lui est créée et liée atomiquement, avec son identité déclarée. L’application ne devine ni noms, ni date de naissance, ni voix, ni pupitre, ni rattachement à une chorale interne ; les rappels ne sont pas activés sans choix explicite. La direction peut compléter les données d’organisation ensuite. Les anciennes mises à jour techniques contenant uniquement `name` et `email` restent compatibles ; elles ne fabriquent pas de fiche personnelle incomplète.

Il n’y a **aucun traitement automatique des anciens comptes en production**. Si une fiche existe déjà au même e-mail, l’application refuse de la réclamer ou de la fusionner automatiquement : prévoir un rapprochement vérifié par l’administration avant de poursuivre. Une fiche supprimée doit être restaurée ; elle ne peut pas être remplacée en créant un doublon depuis Mon profil. Le lien existant entre un compte et une personne reste protégé contre les réaffectations.

L’écran affiche aussi les responsabilités actives et le rôle général en lecture seule. Modifier ses préférences de rappels n’accorde aucun droit supplémentaire et n’active pas globalement un fournisseur SMS.

## Cases à cocher administrées

Dans **Utilisateurs**, l’administrateur choisit **Commissions et accès** sur le compte concerné. La fiche de choriste doit être disponible avant de nouvelles affectations.

Chaque commission affiche son état, son module et les rôles applicables :

| Case | Effet |
| --- | --- |
| Membre | Consultation du module associé pendant la période active de la commission. |
| Responsable | Gestion du module et accès au rôle de responsable, conformément aux règles existantes. Une seule personne responsable par commission. |
| Associé habilité — matériel | Gestion du matériel en complément du responsable, pour les commissions d’instruments seulement. La limite existante de 20 associés et l’obligation d’un responsable restent appliquées. |
| Associé désigné — archives privées | Accès aux archives privées de liturgie ou secrétariat uniquement, selon la délégation existante. Cela n’accorde pas à lui seul la gestion du module. |

Responsable ou associé implique également l’appartenance à la commission ; l’enregistrement normalise cette appartenance. Un responsable ne peut pas être son propre associé. Les autres membres/associés ne sont pas retirés. Les responsabilités déjà occupées par une autre personne ne sont pas remplacées depuis cet écran : utiliser la gestion de la commission pour un remplacement explicite. Une commission future, terminée ou archivée ne donne pas de nouveaux droits actifs.

Les cases sont cumulables entre commissions. Les cases d’une même commission sont soumises à ces règles, pas à une simple addition de privilèges. La consultation concerne les données du module dans la chorale, pas uniquement les données propres au membre. Décocher les délégations les révoque, sous réserve des droits conférés par d’autres commissions et par le rôle général. La voix et le pupitre ne sont pas modifiés.

L’enregistrement s’effectue en une transaction, avec verrouillage et contrôle d’un instantané serveur signé dans l’état Livewire. Si les affectations ont changé depuis l’ouverture, l’administrateur doit recharger la page. Les modifications de droits sont journalisées sans recopier les noms, coordonnées ou dates de naissance dans l’audit. Les confirmations de sauvegarde restent dans la page, avec indication des changements non enregistrés.

## Sécurité et validation

Les routes sont protégées et l’administration est revérifiée à chaque requête et à l’enregistrement. L’identifiant du compte ciblé et l’instantané de droits sont verrouillés côté Livewire ; les commissions et les comptes doivent appartenir à la même chorale. Les champs `role`, `member_id`, `tenant_id` et les affectations envoyés à Mon profil ne deviennent jamais des privilèges.

Les tests couvrent : complétion d’un profil sans fiche, confidentialité et unicité, refus de date future et de fiche supprimée, modification atomique, multi-commissions, droits de consultation/gestion, révocation, conservation des associés, archives privées, concurrence, refus d’auto-attribution et isolation entre chorales. Les anciens contrôles d’identité et de dernier administrateur sont conservés.

`npm run test:profiles` complète les parcours navigateur locaux décrits dans `docs/ACCESSIBILITE.md`. Il utilise les mêmes comptes synthétiques et variables `ACCESSIBILITY_BASE_URL`, `ACCESSIBILITY_CREDENTIALS_FILE`, `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`. Il refuse une URL distante, modifie uniquement la base locale de test, vérifie le profil et la date de naissance, accorde puis retire une délégation et contrôle les vues mobiles avec axe. Les rapports sont dans `test-results/profiles`, ignorés par Git. En CI, il s’exécute après les tests d’accessibilité et de matériel.

Aucune migration de schéma, aucun seeder, aucune modification d’APP_KEY/DB_URL et aucun envoi de message ne sont nécessaires pour ce changement. Le code ne fusionne pas les fiches existantes et ne modifie pas les rôles en production. La publication ou le déploiement reste une opération séparée. D7 reste indépendant.
