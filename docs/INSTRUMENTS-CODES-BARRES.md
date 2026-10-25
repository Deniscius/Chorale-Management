# Identification physique des instruments

Chaque référence d’inventaire peut contenir plusieurs pièces. Chaque exemplaire dispose désormais d’un identifiant permanent, par exemple `CM00000023-0001`, et d’un code-barres Code 128 correspondant. L’identifiant est unique dans l’application, conservé dans PostgreSQL et indépendant du numéro de série du fabricant.

## Utilisation

1. Créer ou modifier la référence dans **Instruments → Inventaire** et renseigner sa quantité (1 à 999). Les identifiants manquants sont attribués automatiquement.
2. Ouvrir **Articles et étiquettes**, depuis le menu ou la ligne de référence.
3. Filtrer une référence, rechercher un nom ou scanner/saisir un identifiant. Un lecteur Code 128 en mode clavier peut renseigner la recherche ; la capture par caméra n’est pas intégrée.
4. Sélectionner les articles à imprimer, éventuellement sur plusieurs pages. **Sélectionner cette page** ajoute les 24 lignes visibles. Le changement de filtre efface la sélection pour éviter les impressions involontaires.
5. Choisir la première case libre de la feuille (1 à 24, ligne par ligne), puis **Préparer les étiquettes A4**. Une nouvelle fenêtre présente la planche.
6. Utiliser **Imprimer / enregistrer en PDF** : papier A4, échelle 100 %, sans ajustement automatique ni en-têtes/pieds de page du navigateur. Tester une feuille ordinaire avant une planche adhésive.

Le format proposé comporte 24 cases de 63,5 × 33,9 mm, trois colonnes et huit lignes, avec des contours pointillés imprimés pour faciliter le découpage. La disposition utilise des marges physiques en millimètres et des barres vectorielles, sans service externe. Les imprimantes et planches peuvent nécessiter un contrôle d’alignement ; faire un essai physique avant une série.

Maximum : 240 articles par impression. Réimprimer ne crée aucun nouveau code et ne modifie pas le stock. Les feuilles peuvent aussi être enregistrées en PDF par le navigateur.

## Cycle de vie et sécurité

- Les utilisateurs ayant accès en consultation aux instruments peuvent consulter les identifiants et préparer les impressions.
- Le responsable habilité et ses associés désignés peuvent créer et modifier les références, attribuer les identifiants manquants, augmenter une quantité, retirer ou réintégrer un exemplaire. Le retrait d’une référence est un archivage qui conserve les identifiants.
- L’administrateur ouvre **Commissions → Déléguer**, choisit le responsable et coche les associés des instruments (20 maximum). Une délégation ne change pas leur rôle général. Les membres ordinaires restent en consultation ; les accès délégués cessent à l’archivage ou à l’échéance de la commission. Les règles des archives privées liturgiques et des courriers restent indépendantes.
- Réduire directement une quantité déjà identifiée est refusé : retirer explicitement les exemplaires concernés pour conserver la correspondance entre les objets et leurs étiquettes.
- Un retrait diminue le stock de 1, conserve le code et reste réversible. Une réintégration retrouve le code d’origine. Une augmentation de quantité crée de nouveaux identifiants ; aucun code retiré n’est réattribué.
- Les prêts/sorties existants restent suivis par quantité. Ils ne désignent pas encore un exemplaire précis. Leur présence bloque le retrait ou la réintégration d’un exemplaire pour éviter une ambiguïté de stock.
- Pour retirer le dernier exemplaire, retirer la référence depuis l’inventaire. Ses identifiants restent conservés.
- Les routes, recherches, impressions et relations sont isolées par chorale. Un code-barres est un identifiant d’article, pas un moyen de contourner l’authentification.

## Déploiement

La migration ajoute `instrument_units`. Le seeder de production identifie les pièces déjà présentes dans les espaces clients, de façon idempotente et sans créer de faux instruments. Les codes sont conservés à chaque redéploiement. Les changements sont journalisés.

La génération vectorielle utilise [Picqer](https://github.com/picqer/php-barcode-generator), sans GD et sans service payant. Le gabarit suit les dimensions du format [Avery L7159](https://www.avery.fr/modele-l7159), sans garantie d’alignement pour toutes les imprimantes.
