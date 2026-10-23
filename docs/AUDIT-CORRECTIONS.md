# Corrections de l’audit du 23 octobre 2026

Cette livraison traite les 17 constats du rapport. Les vérifications d’hébergement, des fournisseurs réels et de restauration restent des opérations de recette de l’environnement.

| Constats | Réponse apportée |
|---|---|
| F01–F02 | Politique unique par commission ; middleware de module persistant et lecture réévaluée à chaque requête Livewire ; contrôle du document source lors d’un déplacement. |
| F03, F12 | Écriture des comptes sérialisée par verrou SQL, refus de perdre le dernier administrateur, unicité compte/choriste en base. |
| F04 | Nouveau fichier conservé avant changement SQL ; ancien fichier retiré seulement après commit ; nettoyage du nouveau fichier si l’écriture échoue. |
| F05–F06 | Quantités par mouvement, stock contrôlé sous verrou, tous les prêts ouverts pris en compte, dates de retour vérifiées et clôture idempotente. |
| F07–F08 | Chaque complément a sa date ; don anonyme sans nom ni liaison au choriste, y compris dans la recette. |
| F09 | Réalisé = reprise antérieure + dépenses validées/clôturées. Les recettes et brouillons ne consomment pas le budget. |
| F10, F13 | Jobs par destinataire, limitation de débit, vérification des préférences, réservations et jetons ; rappels expirés « À vérifier » avec rapprochement explicite. |
| F11 | Notes libres exclues de l’IA ; contexte exact affiché pour relecture avant génération. |
| F14 | Journal des créations/modifications/suppressions sensibles, avec auteur et différences, écran réservé à l’administrateur ; mots de passe et messages exclus. |
| F15 | Mise à jour du parseur CSS via override compatible avec la chaîne existante, compilation et audit npm contrôlés. |
| F16–F17 | Confirmation de perte de saisie ; recherche serveur dans les sélecteurs de membres des formulaires financiers, limitée à 30 résultats plus la sélection courante. |

## Déploiement

1. Sauvegarder et vérifier la restauration de la base et des fichiers privés. Ne jamais lancer `migrate:fresh` sur une base à conserver.
2. Installer les dépendances avec les fichiers lock, compiler, puis `php artisan migrate --force`.
3. La migration d’unicité refuse les comptes multiples pour un même choriste sans les supprimer. Rapprocher ces comptes avant de la relancer si elle signale ce cas.
4. La migration budgétaire conserve `realized_amount` historique, et initialise `opening_realized_amount` à max(ancien réalisé moins dépenses déjà validées, 0). Le nouveau réalisé ajoute ensuite les dépenses validées à cette reprise. Contrôler ce rapprochement avec le trésorier, particulièrement si l’ancien suivi manuel était incomplet. Aucune écriture comptable n’est créée par ce rapprochement.
5. Les mouvements historiques reçoivent une quantité de 1 : vérifier les prêts historiques portant sur plusieurs unités. La disponibilité est recalculée ; des mouvements indépendants de type « retour » ne clôturent pas implicitement un autre prêt. Clôturer le prêt précis avec sa date réelle.
6. Vérifier `QUEUE_CONNECTION=database`, `REMINDERS_QUEUED=true`, le worker et le cron. Redémarrer les workers après déploiement (`php artisan queue:restart`). Le timeout du worker doit rester inférieur au `retry_after` de la connexion de file ; le job de rappel a un timeout de 60 secondes.
7. Le planificateur examine toutes les cinq minutes les réservations expirées (60 minutes par défaut). Le journal Rappels affiche « À vérifier ». Après contrôle du fournisseur, un responsable peut confirmer l’envoi, relancer explicitement ou ignorer. Un jeton périmé ne peut plus livrer un rappel après rapprochement. Il n’existe pas de garantie absolue d’un seul envoi sans coopération du fournisseur ; une interruption pendant l’appel externe reste à rapprocher.
8. Recetter les droits avec des sessions déjà ouvertes, les documents Direction, la suppression de profil et les reçus. Vérifier HTTPS, cookies, APP_DEBUG, SMTP et SMS sur l’hébergement réel.

## Journal et données

Le journal est en ajout uniquement via le modèle applicatif, et son écran est réservé à l’administrateur. Le propriétaire SQL reste techniquement capable de l’altérer : pour un besoin d’inaltérabilité face à cet acteur, prévoir un stockage externe et des droits SQL séparés. Définir la durée de conservation avec les responsables ; aucun effacement automatique n’est activé. Les champs du journal sont une liste autorisée, sans mots de passe, jetons de réservation, e-mails, téléphones ou texte des notifications.

Les fichiers retirés sont supprimés après commit. Une panne de stockage à cette étape est journalisée pour nettoyage ultérieur ; elle ne remet pas en cause la nouvelle archive. Un arrêt brutal ou l’annulation d’une transaction englobante peut laisser un nouveau fichier orphelin : rapprocher périodiquement le dossier privé documents avec les chemins actifs avant nettoyage, sans supprimer les téléversements récents. L’ancien document reste protégé en cas d’échec SQL.

Les notes libres ne sont plus envoyées à l’IA. Le lieu et les titres des œuvres sont encore des champs saisis : le contexte affiché permet de les relire. Ne pas y saisir de données qui ne doivent pas être transmises au fournisseur choisi.

## Recette

Les tests de régression attendent désormais les comportements corrigés, contrairement aux sondes de l’audit qui reproduisaient les défauts. La CI teste SQLite et PostgreSQL, compile les assets et contrôle les audits Composer/npm. Les tests réinitialisent leur base : utiliser exclusivement une base dédiée.

La recette visuelle doit couvrir bureau, petites largeurs et clavier virtuel sur appareils réels. La protection contre la perte de saisie couvre les boutons de fermeture, Échap et la fermeture de page ; une sauvegarde réussie referme sans confirmation supplémentaire.

Une désactivation globale et une annulation de répétition bloquent aussi les jobs déjà en file. Les erreurs de transport dont le résultat est incertain passent « À vérifier » ; les refus explicites (SMS refusé ou HTTP 4xx) passent en échec et restent reprenables. Une erreur SQL après acceptation par le fournisseur ne déclenche pas de nouvel envoi automatique.
