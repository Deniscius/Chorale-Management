# Espaces clients, essai de trois jours et accès payant

## Périmètre

Un espace client est une organisation qui possède ses utilisateurs, chorales, commissions et données. Cette première version rattache chaque compte à un seul espace ; l’e-mail et l’identifiant de connexion restent uniques sur la plateforme. Les rôles existants s’exercent dans cet espace : un administrateur de chorale n’est pas un administrateur global. Il n’y a pas de changement d’espace depuis un formulaire ou un paramètre d’URL. Une personne souhaitant gérer plusieurs espaces doit disposer de comptes distincts dans cette version.

Les 31 tables métier et de coordination disposent d’un tenant_id. Les scopes des modèles, validations de références, restaurations de modèles Livewire et requêtes SQL directes utilisent le contexte de l’utilisateur authentifié. Les relations sont aussi protégées par des clés étrangères composites. Les contraintes uniques sont locales à l’espace, sauf les identifiants de connexion globaux. Le cache des paramètres et du tableau de bord, les nouvelles archives, logos et fichiers temporaires de téléversement sont séparés par espace. Le calcul différé du tableau de bord rétablit son contexte avant exécution.

Les partitions publiées sont partagées uniquement dans leur espace. Les pièces privées restent réservées au responsable et à l’associé désignés de la commission concernée. Les tâches de rappel emportent le contexte client et ne sont pas envoyées après expiration de l’accès. Les données d’un espace expiré ne sont pas supprimées.

Cette version n’installe pas de politiques PostgreSQL RLS. Les contrôles applicatifs sont renforcés par les contraintes relationnelles. Ne pas considérer un rôle PostgreSQL partagé comme un accès direct autorisé pour les clients : seul le serveur doit pouvoir utiliser ses identifiants SQL. Les requêtes ajoutées à l’avenir doivent conserver le contexte client, y compris les requêtes SQL directes.

## Essai et inscription

Le formulaire /creer-un-espace crée une organisation, son administrateur, une chorale principale, son identité, les équipes de commissions et un plan comptable de base. Il ne crée aucun mouvement financier fictif. L’inscription publique peut être désactivée avec TENANT_REGISTRATION_ENABLED=false. Les tentatives d’inscription sont limitées à trois par heure et par adresse IP, et les activations à cinq par minute. Un champ invisible aux utilisateurs (honeypot) rejette les robots qui le remplissent avant toute création de données. Cette protection réduit les abus sans imposer de CAPTCHA ; elle ne bloque pas tous les robots, et plusieurs personnes partageant une adresse IP partagent aussi la limite.

L’essai est enregistré côté serveur à la création de l’espace pour exactement 72 heures. La création d’un autre compte ne renouvelle pas cet essai. Un bandeau affiche sa date de fin. Le propriétaire est connecté directement au tableau de bord, sans envoi automatique ni confirmation d’e-mail pour tester. Son adresse reste non vérifiée en base. La vérification est demandée uniquement avant activation d’une licence payante ; le propriétaire peut alors demander le lien depuis l’écran de vérification. Configurer un envoi e-mail réel pour la récupération de compte et l’activation payante : MAIL_MAILER=log convient uniquement à une prévisualisation locale.

À l’instant où l’essai expire, les modules, téléchargements et actions Livewire sont bloqués. Une page déjà ouverte reçoit un refus d’accès et rejoint l’écran /abonnement. La connexion, déconnexion, récupération du compte et vérification de l’e-mail restent disponibles. L’écran d’abonnement est accessible à tous les membres ; seul un administrateur de l’espace peut activer une licence. Les autres membres sont invités à contacter leur administrateur.

## Chariow : paramètres à renseigner ultérieurement

```dotenv
TENANCY_ENABLED=true
TENANT_REGISTRATION_ENABLED=true
CHARIOW_PAYMENT_URL=
CHARIOW_API_KEY=
CHARIOW_PRODUCT_ID=
```

Le lien doit être une URL HTTPS de vente de votre produit. Le site chariow.com/fr n’est pas utilisé comme faux lien de commande. Sans lien, le bouton affiche « Paiement bientôt disponible ». Sans clé d’API et produit, l’activation est désactivée ; aucune requête externe n’est envoyée.

Le parcours préparé utilise un produit Chariow de type licence : après achat via le lien, l’administrateur renseigne sa clé dans l’écran d’accès. Le serveur interroge l’API Chariow, vérifie le produit configuré, le statut actif, l’absence d’expiration/révocation, l’e-mail vérifié du propriétaire et une échéance future ou un accès permanent explicitement indiqué par Chariow. Il associe la licence à un seul espace avec une contrainte unique. Le renouvellement utilise la date fournie par Chariow ; rejouer la même activation ne prolonge pas arbitrairement l’accès. Les clés d’API restent sur le serveur. La clé de licence n’est ni conservée en clair ni remise dans les champs après erreur ; seuls son empreinte et l’identifiant fournisseur sont enregistrés.

Le montant et la devise sont gérés par votre offre Chariow. Aucune périodicité n’est inventée. Les licences datées et permanentes sont prises en charge selon la réponse explicite de Chariow. Aucun clic « J’ai payé », paramètre de retour ou donnée envoyée par le navigateur ne suffit à débloquer un espace.

L’API doit disposer du droit licenses.read. Les appels sont temporisés et les erreurs n’activent aucun accès. Les tests simulent les réponses ; aucun paiement réel ni appel avec une clé commerciale n’a été effectué. Les remboursements et révocations ultérieurs ne sont pas synchronisés automatiquement dans cette version : les opérations de suspension doivent être répercutées sur l’espace par l’exploitant. Le parcours d’achat et l’offre réelle devront être vérifiés après ajout de vos paramètres. Il n’y a pas de webhook actif dans cette version.

Références : [licences SaaS](https://chariow.dev/en/guides/saas-license-integration), [lecture d’une licence](https://chariow.dev/api-reference/licenses/get-license).

## Déploiement et migration

1. Sauvegarder la base et les fichiers privés, vérifier une restauration sur une copie, puis prévoir une fenêtre de maintenance. Ne pas lancer de seeds de démonstration en production.
2. Arrêter les workers et vider proprement les anciennes tâches de rappel avant migration : un ancien job sans contexte client ne doit pas être rejoué dans un espace arbitraire.
3. Déployer le code et vider les anciens caches de configuration avant la migration. Configurer TENANCY_ENABLED=true. La désactivation de ce paramètre est réservée aux tests de régression ; les requêtes HTTP hors tests refusent de servir l’application lorsqu’il est désactivé.
4. Exécuter php artisan migrate --force. Les données existantes sont conservées et rattachées à l’espace 1. Son essai commence à la migration et dure 72 heures ; ne pas ouvrir ce déploiement sans prévoir l’activation commerciale après cet essai. Les anciennes archives gardent leur chemin et restent protégées par leur propriété en base.
5. Régénérer les caches de configuration et de vues, compiler les ressources frontend et redémarrer les processus. Vérifier SMTP, HTTPS, sauvegardes et accès aux fichiers privés avec deux espaces distincts.
6. Reprendre les workers et le planificateur. La commande chorale:send-reminders parcourt les espaces actifs ; une répétition ciblée exige --tenant. Le planificateur de récupération rétablit lui aussi chaque contexte.

La migration de séparation ne propose pas de retour destructeur vers des données fusionnées : sa méthode down refuse cette opération. En cas de retour arrière, restaurer ensemble la sauvegarde cohérente de base, les fichiers et la révision du code antérieure. Tester cette procédure avant production.

## Validation

Les tests couvrent l’inscription, les 72 heures exactes, les pages restées ouvertes, les modèles sans contexte, les validations et relations entre espaces, les contraintes SQL, les caches et références, les archives publiées/privées, les répertoires de fichiers, les tâches et l’activation simulée. Le scénario tests/performance/tenants.js préchauffe les vingt écrans et teste les documents de deux espaces fictifs sur localhost uniquement. Il utilise K6_PASSWORD et K6_FIXTURES, un JSON local contenant deux comptes avec username, brand, own_document et foreign_document. PROFILE=smoke exécute la vérification séquentielle ; PROFILE=load lance la montée jusqu’à 50 utilisateurs totaux répartis sur les deux espaces, avec un palier de 45 secondes à 50.

Conserver les résultats et rejouer sur des volumes représentatifs. Une mesure locale sur deux espaces ne définit pas la capacité de production ou l’endurance de la plateforme.
