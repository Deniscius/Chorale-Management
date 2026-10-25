# Activation personnelle des membres

## Parcours

Un administrateur ajoute une fiche dans **Choristes**. Il ne saisit ni identifiant ni mot de passe. Un lien d’activation personnel apparaît après enregistrement ; le responsable le copie et le transmet directement au membre concerné. Aucun e-mail ou SMS n’est envoyé automatiquement. La création reste possible sans adresse e-mail sur la fiche.

Le lien ouvre un formulaire proposant le **premier prénom comme identifiant** : `Émile Pierre` donne `emile`. Si le nom est déjà utilisé globalement, un suffixe est proposé, par exemple `emile.2`. L’identifiant n’est réservé qu’à l’activation : deux invitations en attente peuvent proposer le même prénom, puis la validation demande de choisir un identifiant libre si nécessaire.

Le membre confirme ou modifie l’identifiant, renseigne son adresse e-mail et choisit un mot de passe d’au moins 12 caractères avec lettres et chiffres. Le compte est créé seulement après validation, avec le rôle attribué par le responsable et dans l’espace de l’invitation. Le membre est connecté immédiatement, sans étape de confirmation d’e-mail. L’adresse n’est pas marquée vérifiée artificiellement. Si l’essai commercial de l’espace est terminé, le contrôle d’accès existant dirige ensuite vers l’abonnement.

## Gestion des accès

Le bouton de gestion des accès d’un choriste permet de préparer un accès à une ancienne fiche, de choisir le rôle, de renouveler un lien ou de le retirer. Renouveler le lien invalide immédiatement le précédent. Pour un compte déjà actif, ce panneau ne modifie que le rôle ; les identifiants et le mot de passe restent inchangés.

Seuls les comptes autorisés à gérer les utilisateurs préparent ces accès. Un directeur ou autre gestionnaire de fiches sans ce droit continue d’ajouter des fiches, sans créer d’accès applicatif ni pouvoir attribuer des rôles. Les anciens comptes actifs restent utilisables et ne sont pas convertis ni réinitialisés automatiquement.

## Protections et déploiement

- Migration additive : `php artisan migrate --force`. Elle crée `member_invitations`, sans changer les mots de passe existants. Sauvegarder avant tout déploiement.
- Chaque lien contient un jeton aléatoire de 256 bits ; seule son empreinte SHA-256 est stockée. Il expire après **48 heures**, indépendamment des 72 heures d’essai commercial de l’espace, et il ne fonctionne qu’une fois.
- L’activation et le renouvellement utilisent le verrou des comptes et des transactions. Une erreur de validation ne consomme pas le lien. Les contraintes d’unicité et la clé étrangère composite empêchent le rattachement à un membre d’un autre espace.
- La recherche globale n’utilise que le jeton opaque ; les opérations suivantes restaurent explicitement le contexte de son espace. Les champs de rôle, de membre ou de tenant envoyés par le navigateur sont ignorés.
- Les pages d’activation ne sont pas mises en cache et ne transmettent pas de referrer. Les accès GET et POST disposent de limites distinctes. Les journaux métier d’invitation n’enregistrent pas le jeton ni son empreinte.
- Configurer `APP_URL` avec le domaine HTTPS réel, actualiser le cache de configuration et compiler les assets lors du déploiement. Les liens ne doivent pas être publiés dans un groupe. Masquer les segments de jetons dans les journaux d’accès et outils de supervision de l’hébergement.

Tests : création sans identifiants, fiche sans e-mail, rôles, accents et homonymes, activation personnalisée, validation, expiration exacte, renouvellement, rejeu, révocation, membre supprimé, confidentialité du jeton et isolation inter-espaces.
