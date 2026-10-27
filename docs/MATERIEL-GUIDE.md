# Matériel : fiches, objets, étiquettes et prêts

## Une fiche peut représenter plusieurs objets

La fiche « Microphone de chant », avec une quantité de 3, représente trois microphones identiques. La page **Objets et étiquettes** les présente comme **Microphone de chant n° 1**, **n° 2** et **n° 3**. Le numéro désigne un objet, pas une quantité. Chaque objet possède également un code permanent différent.

Le nom et le numéro sont lisibles sur l’écran et sur l’étiquette imprimée. Le nom peut être raccourci visuellement sur une étiquette étroite, mais la zone du numéro reste visible. Les codes existants, les valeurs numériques et le format A4 des planches ne sont pas modifiés. Le numéro de série éventuellement inscrit par le fabricant est une autre information ; il ne remplace pas le code de l’étiquette.

## Enregistrer du matériel

1. Dans **Voir le matériel**, une personne autorisée choisit **Ajouter du matériel**.
2. Dans **Identifier le matériel**, elle renseigne le nom, la catégorie, le nombre d’objets identiques et leur état.
3. Dans **Savoir où le retrouver**, elle peut préciser le rangement, la personne responsable et les notes.
4. Les informations d’achat et de valeur restent dans un panneau facultatif. Les valeurs déjà enregistrées sont conservées lors d’une simple modification du nom ; le panneau s’ouvre si un champ financier est invalide.
5. Après vérification, **Enregistrer** applique les règles existantes et attribue les codes manquants. Aucun champ ni contrôle métier n’est supprimé.

Chaque section propose une explication audio française sur demande et un bouton d’arrêt utilisable dans la fenêtre. Ces explications sont fixes : elles ne lisent pas le contenu des champs ou les coordonnées des membres. La synthèse dépend des voix du navigateur. Les textes et libellés restent disponibles sans voix.

## Préparer les étiquettes

1. Dans **Préparer les étiquettes**, choisir le matériel ou rechercher son nom/code.
2. Cocher les objets dans la liste ; les objets retirés ne sont pas imprimables. La sélection est bloquée pendant le chargement des filtres pour éviter une sélection perdue.
3. Sur une feuille neuve, garder **1 — feuille neuve**. Sur une feuille entamée, choisir la première case libre, en comptant de gauche à droite puis ligne par ligne.
4. Choisir **Voir les étiquettes avant impression**. L’aperçu s’ouvre dans un nouvel onglet ; rien n’est envoyé automatiquement à l’imprimante.
5. Imprimer en A4, à taille réelle, sans ajustement à la page ni en-têtes/pieds de page. Faire d’abord un essai sur papier ordinaire avant d’utiliser la feuille adhésive de 24 étiquettes de 63,5 × 33,9 mm.

La limite de 240 étiquettes, les décalages de cases et les protections d’accès existantes restent appliqués. Préparer une planche ne régénère pas les codes et ne change pas les quantités.

## Prêter puis enregistrer le retour

Dans **Suivre les prêts et retours**, une personne autorisée choisit **Enregistrer un mouvement**. Le formulaire distingue le matériel/l’action/la quantité, la date/la destination/le responsable, puis les informations de retour. L’application continue de refuser une quantité supérieure au stock disponible.

La date de retour prévue n’est pas une date de retour réel. Quand le matériel revient, retrouver le prêt initial et choisir **Matériel rendu** pour enregistrer la date d’aujourd’hui, après confirmation. Pour une autre date, modifier le prêt initial. Créer une ligne de type **Retour** indépendante ne clôture pas le prêt initial : le formulaire et l’aide l’expliquent. Les mouvements portent toujours sur une fiche et une quantité ; cette adaptation ne crée pas une affectation de chaque code individuel à chaque prêt.

Un objet prêté reste à l’inventaire. **Retirer de l’inventaire** diminue la quantité : ce n’est pas une action de prêt. La confirmation indique cet effet. Réintégrer un objet conserve son code d’origine ; les règles existantes bloquant les opérations incohérentes restent appliquées.

## Validation et limites

- Tests Laravel : création des objets/codes, impression sans mutation, conservation des valeurs d’achat et des codes à la modification, capacité de stock, clôture du prêt, permissions et isolation couvertes par les tests existants et `GuidedMaterialTest`.
- `npm run test:material` rejoue dans Chromium une création de matériel, la lecture/arrêt de l’aide sans lecture des valeurs saisies, une sélection, un aperçu au même code, un prêt et son retour. Il contrôle les pages stabilisées avec axe et le repli à 320 pixels avec grand texte. La synthèse vocale est simulée ; aucun compte de production ne doit être utilisé.
- Pour préparer la base et les comptes synthétiques, suivre `docs/ACCESSIBILITE.md`. Le même fichier `ACCESSIBILITY_CREDENTIALS_FILE`, la même URL locale et la même configuration de navigateur peuvent être réutilisés. Le scénario refuse une URL distante et écrit uniquement des données synthétiques sur cette instance locale. Il faut avoir démarré le serveur local avant le test. Les captures et rapports se trouvent dans `test-results/material` ; ils sont ignorés par Git.
- La CI lance ce parcours après `test:accessibility` sur la base SQLite synthétique. Ces changements ne nécessitent ni migration de schéma ni modification d’APP_KEY ou de DB_URL.

Les contrôles automatiques ne remplacent pas un essai réel d’impression et des essais avec des membres non lecteurs, des voix réelles, TalkBack/VoiceOver et des membres de différents âges. Les présences et les formulaires comptables ne sont pas remaniés par cette modification. Aucun déploiement Render ou transfert Neon n’est effectué.
