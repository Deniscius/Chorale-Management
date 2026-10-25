# Abonnement mensuel de Chorale Management

L’offre est de **2 000 FCFA (XOF) par mois et par chorale**, après l’essai gratuit de 72 heures. Le prix couvre l’espace et ses membres ; il ne dépend pas du nombre de comptes. L’application ne conserve aucune carte bancaire et ne déclenche aucun prélèvement. La souscription, l’autorisation de récurrence, le moyen de paiement et la résiliation relèvent du parcours Chariow.

## Configuration commerciale à terminer

Dans le compte marchand Chariow, créer un produit de licence avec une périodicité **mensuelle**, un tarif de **2 000 XOF**, et le renouvellement automatique. Vérifier que le moyen de paiement choisi prend effectivement en charge la récurrence. La prise en charge annoncée par Chariow ne remplace pas un essai sur le compte marchand et le moyen de paiement réels.

Renseigner sur le serveur `CHARIOW_PAYMENT_URL`, `CHARIOW_PRODUCT_ID` et `CHARIOW_API_KEY` avec le scope `licenses.read`. Ne jamais placer la clé d’API dans le frontend ou le dépôt. Le lien doit ouvrir la véritable offre commerciale ; l’URL générale chariow.com/fr ne suffit pas. L’API doit retourner le produit configuré, un prix de base de 2 000 avec la devise XOF et une échéance datée. Les licences permanentes ne sont plus acceptées lors des nouvelles activations. Les anciens droits permanents explicitement accordés restent inchangés : aucune conversion ou annulation automatique n’est effectuée.

Le responsable souscrit puis rattache sa clé une première fois dans **Mon accès**. Son adresse doit être vérifiée et correspondre à l’acheteur. Le même identifiant fournisseur ne peut être attribué à une seconde chorale. La clé n’est stockée qu’en empreinte. Une activation répétée ne rajoute aucun mois artificiel.

## Renouvellements et expiration

La commande `php artisan chorale:sync-subscriptions` relit les licences déjà rattachées **par leur identifiant public** auprès de Chariow. Elle reprend la date d’échéance actuelle du fournisseur, y compris pour une licence locale déjà expirée. Aucun ajout arbitraire de 30 jours n’est effectué : un mois calendaire et une période confirmée par le fournisseur peuvent avoir des durées différentes.

Le scheduler la lance toutes les cinq minutes si Chariow est configuré. Chaque licence est revérifiée lorsqu’elle n’a pas été vérifiée depuis quinze minutes. La commande traite par défaut 100 licences, dans l’ordre des dernières vérifications, et propose `--limit` de 1 à 1000. Elle s’arrête après trois erreurs consécutives du fournisseur. Cette borne protège l’API, mais un grand arriéré peut allonger le délai ; dimensionner les lots et les workers à partir des quotas API et du volume réel avant montée en production. Les refus de paiement et annulations restent traités dans Chariow ; l’application tient compte de la validité et des révocations renvoyées par l’API.

Une révocation ou expiration confirmée retire le droit correspondant, en conservant les autres licences valides de la chorale. Une panne, une réponse incohérente ou une erreur de format ne prolonge jamais l’accès ; la période déjà vérifiée reste applicable jusqu’à son échéance. Le droit d’essai restant, les suspensions administratives et les anciens droits permanents gardent leurs règles propres.

Le bouton **Actualiser mon abonnement** permet à un responsable de demander une vérification immédiate de ses licences récentes, sans nouveau paiement. L’état de synchronisation et la date de dernière vérification sont visibles dans le portail propriétaire sur la fiche client. Les montants encaissés, frais, remboursements et revenus récurrents ne sont pas reconstruits à partir de droits d’accès : ils restent à consulter dans Chariow.

Lorsqu’une page restée ouverte atteint son ancienne échéance, le serveur revérifie l’accès. Si la synchronisation a déjà confirmé le renouvellement, le navigateur revient au tableau de bord avec la nouvelle échéance au lieu de demander de payer à nouveau. L’ouverture volontaire de **Mon accès** affiche toujours l’abonnement normalement.

Cette version synchronise les licences existantes, sans webhook de paiement. Si le produit marchand émet une **nouvelle clé et un nouvel identifiant à chaque échéance** au lieu de renouveler la licence existante, il faut rattacher la nouvelle clé, ou compléter le raccordement événementiel après vérification des événements réels du compte marchand. Ne pas annoncer de renouvellement transparent avant d’avoir validé ce comportement.

## Mise en service et recette

1. Sauvegarder et appliquer la migration additive : `php artisan migrate --force`.
2. Configurer l’offre réelle et les secrets ; reconstruire le cache de configuration et les assets.
3. Activer un seul scheduler de production, avec un cache de verrouillage adapté à l’hébergement, et surveiller les échecs de la commande. Les appels sont bornés en durée et sérialisés par chorale pour éviter qu’une réponse ancienne écrase une nouvelle échéance.
4. Vérifier un achat initial, le rattachement, un renouvellement réel, une résiliation, un paiement refusé, une panne API, l’échéance exacte et un retour après expiration. Ne pas forcer une licence fictive en production pour contourner ces contrôles.
5. Confirmer l’affichage du tarif et des conditions Chariow avant de rendre le paiement public.

Sans le lien produit, l’identifiant et la clé d’API réels, le paiement reste indisponible et aucun prélèvement n’est actif. Les tests automatisés emploient exclusivement des réponses Chariow fictives.

Références : [abonnements Chariow](https://chariow.com/fr/licenses), [lecture d’une licence par clé ou identifiant public](https://chariow.dev/api-reference/licenses/get-license).
