# Configuration Brevo — courriels transactionnels Chorale-Management

## Objectif et limites

Laravel fournit déjà le transport SMTP dans `config/mail.php`. Les courriels
de réinitialisation de mot de passe et les rappels de répétition peuvent utiliser
le même transport après activation. Les notifications Web Push, les SMS et les
notifications en base de données restent indépendants de Brevo.

**Aucun identifiant Brevo n'est stocké dans ce dépôt.** Le déploiement Render
conserve `MAIL_MAILER=log` et `MAIL_DELIVERY_ENABLED=false` par défaut.
Ne pas activer l'envoi tant que la vérification du domaine, les secrets,
la délivrabilité et les tests n'ont pas abouti.

## 1. Préparer l'expéditeur dans Brevo

1. Dans Brevo, valider le domaine d'expédition et configurer les enregistrements
   DNS requis (SPF, DKIM et, idéalement, DMARC), puis attendre leur validation.
2. Déclarer une adresse expéditrice vérifiée, par exemple
   `notifications@votre-domaine.tld` (adresse indicative seulement).
3. Dans **SMTP et API → SMTP**, relever **l'identifiant SMTP** et générer une
   **clé SMTP**. Ne pas utiliser la clé API ni le mot de passe Brevo.
4. Vérifier que le compte autorise les e-mails transactionnels.

Documentation : https://help.brevo.com/hc/fr/articles/7924908994450

## 2. Préparer les secrets Render

Dans le service **web** Render (et le **worker** si un service worker séparé
est utilisé), configurer ces variables sans les partager dans les discussions
ou les commits :

| Variable | Valeur |
| --- | --- |
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `2525` |
| `MAIL_USERNAME` | identifiant SMTP récupéré dans Brevo |
| `MAIL_PASSWORD` | clé SMTP créée dans Brevo (secret) |
| `MAIL_FROM_ADDRESS` | adresse expéditrice vérifiée |
| `MAIL_FROM_NAME` | `Chorale Management` |
| `MAIL_SCHEME` | laisser non défini (STARTTLS automatique) |
| `MAIL_MAILER` | d'abord `log`, puis `smtp` lors de l'activation |
| `MAIL_DELIVERY_ENABLED` | d'abord `false`, puis `true` après test |

Render Free bloque les ports sortants 25, 465 et 587. Brevo propose le port
2525. Le chiffrement STARTTLS doit être vérifié au test de connexion ; ne pas
désactiver la vérification du certificat TLS.

Render Free : https://render.com/docs/free

Brevo : https://help.brevo.com/hc/fr/articles/10905415650322

Le Blueprint `render.yaml` contient des champs à renseigner manuellement.
Dans `deploy/render.production.yaml`, le worker récupère ses identifiants
SMTP depuis le service web via `fromService`.

## 3. Vérifier avant d'activer l'envoi

- Vérifier le domaine d'envoi et l'expéditeur dans Brevo.
- Vérifier les variables SMTP du web, et celles du worker si présent.
- Déployer les variables, puis vider le cache de configuration Laravel via
  le processus de déploiement prévu, sans toucher à la base.
- Envoyer **un seul** courriel transactionnel depuis un compte de test
  explicitement autorisé, idéalement vers une boîte de test.
- Vérifier la réception, le dossier spam, la signature DKIM et le tableau de
  transactions Brevo. Un serveur SMTP qui accepte le message ne garantit pas
  sa livraison dans la boîte de réception.
- Contrôler qu'aucun mot de passe, jeton ou lien de réinitialisation n'apparaît
  dans les journaux applicatifs.

**Puis seulement**, sur tous les processus qui envoient des e-mails,
définir `MAIL_MAILER=smtp` et `MAIL_DELIVERY_ENABLED=true`.
Ne pas ajouter `log` comme mailer de secours en production pour les
e-mails contenant des liens de réinitialisation.

## 4. Vérification fonctionnelle

- Réinitialisation de mot de passe : lien reçu, token utilisable et non
  journalisé.
- Rappel de répétition : envoyer un essai sur une séance de test, vérifier
  l'horaire et le nom de l'expéditeur.
- Gestion des erreurs : échec SMTP remonté au journal technique sans exposer
  le secret ; pas de relance aveugle pour les envois incertains.
- Multi-tenants : aucun destinataire d'une autre chorale.
- Limites Brevo : vérifier quota, statut et réputation de l'expéditeur.

Les e-mails métier restent pilotés par l'application. Ne pas utiliser Brevo
comme source de vérité des présences, inscriptions, cotisations ou rappels.

## Retour arrière

En cas d'incident, remettre immédiatement
`MAIL_DELIVERY_ENABLED=false` puis `MAIL_MAILER=log` après s'être assuré
qu'aucun secret ou token ne pourra être écrit dans les journaux. Une option
plus sûre pour un arrêt provisoire est de conserver `MAIL_MAILER=smtp` avec
le garde-fou de livraison désactivé, afin d'éviter la journalisation accidentelle
de contenus sensibles. Conserver les enregistrements du journal des rappels
pour rapprochement, ne pas réexpédier les messages marqués incertains sans contrôle.
