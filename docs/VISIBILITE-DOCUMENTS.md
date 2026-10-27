# Visibilité des documents importés

Chaque import propose trois choix. Par défaut, le document est réservé aux responsables habilités.

| Choix | Personnes pouvant consulter |
| --- | --- |
| Responsables habilités | Les responsables disposant du droit de gérer les archives de cette commission. Pour le secrétariat et la commission liturgique, seuls le responsable et son associé désignés. |
| Accès de la commission | Les utilisateurs autorisés à consulter le module de la commission, ainsi que ses responsables habilités. Les droits généraux et les délégations existantes restent applicables. |
| Toute la chorale (connectée) | Tous les utilisateurs authentifiés de la même chorale. Aucun accès anonyme ou partage public sur Internet. |

« Accès de la commission » suit les droits du module existants : il ne constitue pas une liste nominative indépendante. Par exemple, des droits généraux peuvent déjà donner accès au module. La consultation ne confère aucun droit de modification, suppression ou changement de visibilité.

## Utilisation

À l’import ou pendant la modification, choisir « Qui peut consulter ce document ? ». Dans la liste, le bouton **Visibilité** permet aux responsables habilités de changer uniquement ce choix, sans remplacer le fichier. Les autres utilisateurs ne voient pas ce bouton et le serveur refuse leurs tentatives directes.

Le titre, la description, les catégories, les compteurs, la liste, la prévisualisation et le téléchargement suivent les mêmes droits. Les réponses contenant le fichier demandent au navigateur de ne pas le mettre en cache. Réduire la visibilité retire l’accès aux prochaines consultations ; cela ne peut pas effacer une copie déjà téléchargée par un destinataire.

## Aperçu et téléchargement

Le bouton avec l’icône œil ouvre le document dans une fenêtre de la plateforme. Cet aperçu est disponible pour les PDF, les images JPEG/PNG/WebP, les fichiers texte et les CSV. Le serveur impose un type de contenu passif, refuse l’intégration depuis un autre site et n’autorise jamais le cache du fichier privé.

Le bouton avec l’icône de téléchargement enregistre volontairement une copie. Word, Excel et PowerPoint ne sont pas envoyés à un lecteur externe : ils restent téléchargeables, mais n’affichent pas de bouton d’aperçu. Cette limite évite de transmettre un document privé à Google, Microsoft ou un autre prestataire sans accord explicite.

L’aperçu et le téléchargement utilisent des routes distinctes mais appliquent la même règle d’accès. Le serveur revérifie cette règle à chaque requête et après tout changement de visibilité ou de rôle.

Une page de modification ouverte avant un changement de visibilité doit être rechargée. Elle ne peut pas rétablir accidentellement l’ancien accès. Les droits du responsable sont revérifiés au moment de l’enregistrement.

## Données existantes et mise en production

Aucune migration de schéma, suppression de fichier ou modification en masse des documents existants. Les valeurs existantes `private`, `commission` et `published` sont conservées. Une valeur inconnue refuse la consultation.

Les documents anciennement classés `commission` restent accessibles selon les droits du module. Les documents classés `private` sont réservés aux responsables habilités, pour toutes les commissions. Vérifier ce classement avec les responsables avant publication de cette version.

Les changements sont à relire et à déployer séparément. Aucune bascule de base, modification de `APP_KEY`, réinitialisation ni opération de production n’est nécessaire pour préparer cette fonctionnalité.

## Vérification

`tests/Feature/DocumentVisibilityTest.php` vérifie l’équivalence liste/consultation directe pour tous les rôles, commissions et niveaux, le refus entre chorales, l’import privé par défaut, la révocation, les formulaires périmés et le refus d’un droit retiré après ouverture du formulaire. `tests/Feature/DocumentsTest.php` vérifie aussi la séparation aperçu/téléchargement, les en-têtes privés et le refus des formats non affichables. Les tests d’archives existants restent actifs.

`npm run test:documents` s’exécute uniquement sur une application locale isolée avec comptes fictifs : import, publication interne, lecture du contenu, restriction, affichage mobile et audits axe. Utiliser les mêmes variables `ACCESSIBILITY_BASE_URL`, `ACCESSIBILITY_CREDENTIALS_FILE` et éventuellement `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` que les autres scénarios navigateur. Ne jamais lancer ce scénario contre la production.

Le serveur du scénario navigateur utilise `APP_ENV=local` avec la base SQLite isolée et les services externes désactivés. Cela permet à Livewire d’utiliser son véritable circuit de téléversement ; `APP_ENV=testing` réserve un disque temporaire aux tests PHPUnit.
