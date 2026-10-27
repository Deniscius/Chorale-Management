# Accueil illustré et aide audio

Une première adaptation est disponible sur l’accueil et les layouts de connexion et de gestion :

- Cartes illustrées et libellés simples, uniquement pour les modules autorisés. Les liens n’enregistrent aucune présence ni opération comptable.
- Un bouton haut-parleur distinct explique chaque carte avant son ouverture. Aucun son automatique, aucune lecture du contenu des formulaires, des mots de passe ou des données métier.
- Bouton « Arrêter la lecture », arrêt au changement de page et à la destruction du composant. Les consignes sont en français et utilisent SpeechSynthesis du navigateur ; la disponibilité et la qualité des voix dépendent du navigateur et du système. Certaines voix peuvent nécessiter Internet. Aucun service audio tiers n’est ajouté par l’application.
- Le réglage « Grand texte » augmente la taille de base à 125 %, reste réversible et est mémorisé seulement sur le navigateur de l’appareil. Il ne contient aucune donnée de compte. L’aide reste utilisable si le stockage est refusé.
- En l’absence de synthèse vocale, un message propose un accompagnement humain. L’application conserve les libellés et son fonctionnement sans audio ; elle n’est pas entièrement utilisable sans lecture à ce stade.

## Validation avant généralisation

Sur téléphone et ordinateur, avec les voix françaises installées :

1. Ouvrir la connexion : aucun son ne doit démarrer. Vérifier l’écoute et son arrêt, y compris au passage vers une autre page.
2. Se connecter en choriste puis en administrateur. Vérifier les cartes visibles et les droits, sans effectuer d’écriture métier.
3. Écouter chaque carte : seul son descriptif doit être lu et aucune navigation ne doit démarrer. Ouvrir ensuite son lien séparément.
4. Activer le grand texte, naviguer, recharger et le désactiver. À 320 pixels de large et avec le zoom navigateur à 200 %, vérifier l’absence de boutons ou champs inaccessibles.
5. Parcourir au clavier, avec VoiceOver ou TalkBack ; vérifier l’ordre du focus et les noms des boutons. Vérifier le fonctionnement quand SpeechSynthesis est absent ou en erreur.
6. Faire réaliser à des membres non lecteurs les parcours retrouver un chant et consulter une répétition, sans leur indiquer le bouton à utiliser. Observer la reconnaissance des pictogrammes et la compréhension de la voix avant d’étendre cette interface.

Les langues locales, des enregistrements vocaux validés par les membres, une présentation simplifiée de chaque formulaire et des tutoriels illustrés restent à concevoir avec les utilisateurs. Ne pas présenter les pictogrammes ou la voix française comme une solution universelle à la non-lecture. Aucun changement de schéma, de données ou de configuration de production n’est nécessaire pour cette première adaptation.
