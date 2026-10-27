# SMS D7 Networks pour les rappels de répétition

Le pilote `d7` branche le système existant de rappels sur l’API SMS officielle de D7. Il ne remplace ni la connexion Laravel, ni les e-mails, ni la base de données. WhatsApp, OTP et messages audio ne sont pas activés par cette intégration. Aucune nouvelle migration de schéma n’est nécessaire.

Références officielles consultées le 27 octobre 2026 :

- Authentification : https://d7networks.com/docs/authentication/overview/
- Envoi : https://d7networks.com/docs/sms/send-sms/
- Livraison : https://d7networks.com/docs/sms/get-status/

## Compte et configuration serveur

Créer une application dans le panneau D7, générer son jeton d’authentification et faire valider l’expéditeur et la couverture des destinations par D7. Vérifier le crédit et les tarifs applicables au Togo et aux autres pays concernés. Le compte D7 et son crédit sont communs aux chorales de cette instance ; cette version ne gère pas de facturation SMS par chorale.

Configurer sur Render, en gardant d’abord les SMS désactivés :

```dotenv
REMINDERS_SMS_DRIVER=d7
REMINDERS_SMS_ENABLED=false
D7_API_TOKEN=
D7_SMS_ORIGINATOR=CHORALE
SMS_TIMEOUT=15
```

Renseigner le jeton uniquement dans les variables secrètes Render ; la valeur vide ci-dessus n’est pas un jeton de test. Ne pas le partager dans le chat, Git, une capture ou une variable `VITE_*`. Appliquer la même configuration aux services Laravel concernés (web, worker et scheduler) et redémarrer les processus selon votre procédure Render pour actualiser le cache de configuration et les workers. Ne pas modifier `APP_KEY` ou `DB_URL` pour activer D7. `SMS_ENDPOINT` et `SMS_TOKEN` concernent uniquement l’ancien pilote HTTP générique et ne sont pas utilisés par D7. L’URL D7 est fixe, HTTPS, sans suivi de redirection ni désactivation TLS.

Les SMS ne partent que si les rappels sont activés, le canal SMS est activé, le choriste est actif, a choisi les SMS et possède un numéro. Les règles et réservations multi-chorales existantes continuent de s’appliquer. Les anciens numéros enregistrés ne sont pas modifiés.

Saisir les numéros avec un indicatif international, par exemple `+228` suivi des huit chiffres du numéro au Togo. Les espaces, points, tirets et parenthèses sont retirés lors de l’envoi ; le préfixe `00` est converti en `+`. Un numéro local sans indicatif est refusé : l’application ne devine pas le pays.

## Validation sans envoi, puis essai autorisé

1. Laisser `REMINDERS_SMS_ENABLED=false` pendant le déploiement de code et la configuration du compte.
2. En local, avec une base synthétique, exécuter `php vendor/bin/phpunit --filter D7SmsSenderTest`. Toutes les requêtes sont simulées et aucun crédit D7 n’est consommé.
3. Examiner les répétitions et préférences dans l’application. La commande existante `php artisan chorale:send-reminders --dry-run --tenant=ID` prévisualise les rappels sans les envoyer ; les coordonnées affichées restent des données personnelles.
4. Planifier un essai vers un numéro de test dont le propriétaire a explicitement accepté. Le démarrage du canal exige `REMINDERS_SMS_ENABLED=true`, le redémarrage des processus concernés et une répétition de test dans un espace isolé. **L’activation sur une instance de production ouvre le canal pour tous les espaces actifs et choristes déjà inscrits aux SMS** : suspendre les déclencheurs automatiques selon votre procédure d’exploitation avant un essai ciblé. Ce guide n’exécute ni cette activation ni aucun envoi réel.
5. Après autorisation, lancer un seul rappel ciblé (`--tenant=ID --rehearsal=ID`) et contrôler le journal Rappels et le panneau D7. Une nouvelle répétition éligible peut aussi envoyer des e-mails aux membres qui les ont activés : utiliser uniquement l’espace et les membres de test.
6. Vérifier la réception physique sur le téléphone avant d’autoriser les rappels automatiques. Pour arrêter de nouveaux SMS, remettre le canal à `false` et actualiser la configuration des services. Cela ne peut pas rappeler un SMS déjà accepté par D7.

Les contenus accentués et les langues non latines utilisent `data_coding=auto`. La limitation existante à 160 caractères n’implique pas un coût d’un seul SMS : Unicode, caractères étendus et concaténation peuvent augmenter le nombre de segments facturés. Consulter le panneau D7 pour le coût réel. Ne pas mettre d’informations sensibles dans les rappels.

## États et relances

- `Envoyé` : HTTP réussi, `status=accepted` et référence `request_id` valide reçue. **Cela ne prouve pas la réception sur le téléphone.** La référence D7 est journalisée côté serveur, sans jeton, numéro ni contenu. Elle peut servir à consulter le panneau fournisseur ou son API de statut.
- `Échec` : refus explicite de D7 (authentification, crédit, validation ou limitation), paramètres manquants, contenu vide ou numéro invalide. Corriger avant d’utiliser la relance existante.
- `À vérifier` : timeout, erreur réseau, réponse serveur, redirection ou accusé de réception inattendu. Le SMS a pu être accepté et facturé : pas de relance automatique. Vérifier d’abord le fournisseur et utiliser le rapprochement manuel existant.

La requête d’envoi n’est jamais automatiquement répétée. Les erreurs persistées sont génériques ; elles ne recopient ni réponse brute D7 ni exception réseau susceptible de contenir des informations personnelles. La confirmation automatique de livraison, les webhooks DLR et l’enregistrement d’une référence fournisseur dans chaque ligne du journal ne sont pas inclus dans cette version. Ne pas ajouter de webhook public non authentifié pour simuler une confirmation de livraison.

## Vérifications techniques

Les tests contrôlent le contrat JSON D7, l’authentification, les numéros, les caractères Unicode, les refus HTTP et applicatifs, les réponses ambiguës, la confidentialité des erreurs, l’inscription SMS, la désactivation globale et l’absence de doublon lors du traitement des rappels. Aucun compte, jeton réel ou numéro réel n’est nécessaire pour ces tests. Les clés de test sont fictives.
