# Notifications e-mail et diagnostic Brevo

## Configuration effective

Le transport `brevo` utilise `https://api.brevo.com/v3/smtp/email` sur le port 443. Il ne lit pas `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_HOST` ou `MAIL_PORT` : ces paramètres concernent uniquement le transport `smtp`.

Variables requises pour l’API :

| Variable | Usage |
| --- | --- |
| `MAIL_MAILER=brevo` | Transport API HTTPS |
| `MAIL_DELIVERY_ENABLED=true` | Activation des envois |
| `BREVO_API_KEY` | Clé API v3, conservée dans les secrets Render |
| `MAIL_FROM_ADDRESS` | Expéditeur validé dans Brevo |
| `MAIL_FROM_NAME` | Nom affiché de la chorale |

Après modification des secrets dans Render, déployer le service pour recharger la configuration Laravel. Le processus Docker reconstruit le cache au démarrage. Si des workers séparés sont utilisés, ils doivent recevoir la même configuration et être redémarrés. Les Blueprints gardent l’envoi désactivé pour une nouvelle installation non configurée ; vérifier les valeurs effectives du service existant.

Ne jamais publier la clé dans une capture, un commit ou un journal. Toute clé exposée doit être révoquée dans Brevo et remplacée dans Render.

## Diagnostic depuis l’application

L’administrateur dispose de **Administration → Diagnostic des e-mails** (`/parametres/emails`). Cet écran affiche la configuration réellement chargée, sans montrer les secrets. Il indique aussi l’adresse de réception retenue et propose un test uniquement vers l’adresse de l’administrateur connecté. Les tests sont limités à un par minute.

En console, `php artisan chorale:check-mail` effectue les mêmes contrôles de configuration, sans envoyer d’e-mail. Un contrôle vert signifie que les paramètres sont présents, pas que Brevo a validé la clé, l’expéditeur ou le domaine.

Une configuration de production utilisant `log`, `array` ou un `failover` susceptible de tomber vers les journaux ne doit pas être considérée comme une livraison réelle. Le garde-fou refuse ces envois en production.

## Adresse et contenu des notifications

Les notifications métier utilisent l’adresse valide de la fiche choriste ; si elle manque, elles utilisent l’adresse valide du compte. Les responsables sans fiche choriste peuvent recevoir leurs notifications métier. Les liens de vérification et de réinitialisation de mot de passe continuent d’utiliser exclusivement l’adresse du compte.

Le texte complet et la signature sont conservés dans l’e-mail. Le résumé du centre de notifications peut être plus court. Les résultats des messages groupés et des courriers distinguent notifications applicatives, e-mails acceptés et échecs ; l’apparition d’une notification dans l’application ne prouve pas l’envoi d’un mail.

## Rappels automatiques et files d’attente

Le point d’entrée signé des rappels GitHub Actions utilise `--inline` : l’envoi des rappels de répétition est exécuté dans ce processus, sans dépendre d’un worker séparé. La configuration habituelle des files reste inchangée pour les autres usages. Un planificateur externe doit néanmoins exécuter ce point d’entrée ; l’intervalle configuré dans GitHub ne garantit pas une exécution à l’instant exact.

Les rappels imminents tolèrent une exécution retardée à l’intérieur du quart d’heure. Une empreinte stable empêche un second envoi dans le même créneau.

La migration `2026_11_08_000003_track_notification_email_delivery` sépare le suivi applicatif du suivi e-mail des rappels transversaux :

- `pending` : attente d’activation ou d’envoi ;
- `accepted` : message accepté par le transport ;
- `failed` : refus explicite, nouvelle tentative possible dans la limite prévue ;
- `uncertain` / `sending` : vérifier les événements du fournisseur avant toute relance ;
- `no_address` : aucune adresse valide ;
- `legacy` : ancien enregistrement dont le statut e-mail ne peut pas être prouvé.

Une livraison e-mail refusée peut être retentée sans dupliquer la copie applicative. Les anciens enregistrements ne sont pas renvoyés en masse. Les envois incertains ne sont pas automatiquement répétés.

## Vérification chez Brevo

Contrôler le domaine et l’expéditeur dans Brevo, puis les événements du message de test : accepté, délivré, rejeté, bloqué, quota dépassé ou rebond. Vérifier les enregistrements DNS demandés par Brevo et l’alignement DKIM/DMARC. Un domaine avec une politique DMARC `reject` exige un alignement correct ; un code de vérification de domaine seul ne prouve pas que DKIM est opérationnel.

Les refus HTTP 400/401/403/429 sont distingués dans les erreurs de l’application sans afficher la réponse brute ni les secrets. Une référence `messageId` est exigée pour considérer une réponse API comme confirmée. Une réponse acceptée par l’API ne prouve pas l’arrivée dans la boîte de réception : vérifier aussi les indésirables et les événements de livraison Brevo.

Pour suspendre les envois, conserver le transport réel et définir `MAIL_DELIVERY_ENABLED=false`, puis redéployer. Ne pas utiliser le mailer `log` comme preuve de livraison ou comme secours pour des messages contenant des liens privés.
