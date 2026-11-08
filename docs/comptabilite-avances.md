# Activités, comptes et avances des choristes

La direction et la trésorerie disposent de deux écrans dans la rubrique Comptabilité : **Comptes d’opération** et **Avances des choristes**.

## Comptes d’opération

Créer un compte avec son code, son nom et son type, ou modifier les comptes déjà présents. Un code est unique dans la chorale. Désactiver un compte empêche son utilisation dans de nouvelles écritures et remboursements ; son historique est conservé. Une écriture existante conserve son compte lorsqu’il est renommé ou désactivé.

## Écritures par activité

Depuis une activité, **Écritures** ouvre le journal filtré sur cette activité. Chaque ligne affiche le choriste, l’activité et le compte. Les cartes du journal reflètent les filtres appliqués. Le filtre d’activité inclut les opérations de toutes les années ; les filtres explicites de date et de statut restent applicables.

Lors de la création d’une opération depuis ce journal filtré, l’activité est présélectionnée. Le formulaire permet aussi de la choisir ou de la corriger sur un brouillon. Les écritures validées restent protégées.

## Dépense payée par un choriste

1. Dans **Avances des choristes**, enregistrer le choriste ayant payé, l’activité éventuelle, la date, l’objet, le montant et la référence du justificatif.
2. Si le choriste réclame son remboursement, renseigner la date de réclamation à la création ou utiliser **Enregistrer une réclamation aujourd’hui** dans le détail.
3. Enregistrer l’approbation ou le refus du bureau, avec le motif ou la référence de la décision. L’enregistrement de cette décision ne constitue pas un paiement. Une décision peut être corrigée tant qu’aucun remboursement n’a été enregistré.
4. Après un paiement réellement effectué, renseigner sa date, son montant, le compte, le mode et la référence de paiement. Un remboursement partiel est possible. Le système refuse un paiement non approuvé ou supérieur au reste dû.

Chaque remboursement crée une dépense validée liée au choriste, à l’avance et à l’activité. Ne pas ressaisir ce paiement dans le journal général. L’enregistrement d’une avance ne crée pas de sortie de trésorerie : les avances et les remboursements sont présentés séparément pour éviter un double comptage. Les dettes approuvées restent visibles dans la synthèse même après changement d’année de travail.

Les rapports de présence et d’activité présentent les avances, remboursements et soldes dus pour les utilisateurs autorisés aux finances. La situation des avances correspond à la date de génération ; les écritures du rapport périodique suivent les dates de paiement sélectionnées.

## Mise en service

La migration `2026_11_08_000001_create_member_expenses` ajoute le suivi des avances et les liens vers les remboursements. Exécuter les migrations avec la procédure habituelle de déploiement avant de mettre cette version en service. La migration `2026_11_08_000002_link_activity_participation_receipts` rattache les anciens règlements sans activité à celle de leur cotisation source, uniquement dans la même chorale. Les montants et statuts comptables restent inchangés. Aucun montant existant n’est reclassé automatiquement et aucun paiement bancaire n’est déclenché par l’application.
