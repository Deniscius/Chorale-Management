# Types et libellés propres à chaque chorale

Dans **Administration → Types de contribution**, l’administrateur peut renommer les rubriques **Cotisations** et **Dons**, modifier leurs types existants, créer de nouveaux types, choisir un montant proposé et désactiver un type. Un trésorier peut utiliser les types dans ses saisies ; leur paramétrage reste réservé à l’administrateur. Les paramètres d’une chorale ne sont pas partagés avec les autres espaces.

Exemples : renommer « Dons » en « Soutiens et collectes », modifier « Cotisation mensuelle » à 3 000 FCFA, ou créer « Participation au transport », « Uniformes » et « Collecte spéciale ». Les deux rubriques proposent un lien **Configurer les types** aux administrateurs.

## Cotisations par période

Le formulaire propose un type, un mois et un exercice. Un même membre peut avoir plusieurs types de cotisations sur la même période. Un doublon du même membre, type, mois et exercice est refusé par le formulaire et la base. Les cotisations existantes sont conservées sous le type mensuel d’origine.

Le montant du type préremplit les nouvelles saisies et reste ajustable. Modifier un montant proposé ne recalcule ni les sommes déjà dues ni les paiements déjà enregistrés. Changer de type ne remplace pas un montant saisi manuellement. Une cotisation avec un règlement validé ne peut plus changer de type, de membre, de période ou de montant dû.

Ce paramétrage n’est pas un moteur de génération automatique d’échéances : la période reste le mois et l’exercice choisis par le trésorier. Il ne modifie pas l’abonnement de 2 000 XOF à l’application, qui relève d’un autre parcours.

## Dons et contributions ponctuelles

Les types de dons existants restent disponibles et peuvent être renommés. Lors de la création d’un type, l’administrateur choisit une nature **financière** ou **en nature / matériel**. Cette nature et la rubrique restent fixes : modifier un nom ne doit pas transformer un bien matériel en entrée de trésorerie.

Une contribution financière prépare toujours une recette en brouillon, à valider par le trésorier. Un don en nature ne crée pas de recette. Les écritures déjà validées conservent leur protection. Les types personnalisés sont affichés dans les listes, filtres et indicateurs, sans fusion des catégories personnalisées sous le libellé « Autre ».

## Historique et désactivation

Les types ne sont pas supprimés depuis l’interface : les désactiver les retire des nouvelles saisies, tout en les conservant dans les anciens enregistrements et les filtres d’historique. Une ancienne dette reste payable. Les libellés affichés suivent le nom actuel du type ; les anciens montants et les descriptions des écritures déjà validées ne sont pas réécrits.

Les changements de type et de configuration des types apparaissent dans le journal métier. Les identifiants techniques restent stables lors d’un renommage. Les clés étrangères composites imposent le rattachement à un type du même espace.

## Déploiement

Sauvegarder la base et appliquer `php artisan migrate --force`, puis reconstruire la configuration et les assets. La migration crée les types de départ pour chaque espace existant et rattache les cotisations et dons à leur type, sans modifier les montants, les paiements ni les identifiants des écritures. Les nouveaux espaces reçoivent leurs propres types par défaut.

Le retour arrière automatique est refusé pour éviter la perte des types personnalisés ou la fusion de plusieurs cotisations d’un même mois. Restaurer ensemble la sauvegarde et la version précédente du code si un retour est nécessaire. La prévisualisation locale est sauvegardée avant migration ; aucune migration de production n’est effectuée par cette livraison.
