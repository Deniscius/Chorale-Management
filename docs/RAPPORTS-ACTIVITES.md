# Rapports d’activité et personnes extérieures

Dans **Activités**, ouvrir **Fiche de présence** pour accéder directement à l’activité choisie. Si la liste est vide, sélectionner les participants et les ajouter. L’inscription ne renseigne pas leur présence. Une fiche finalisée doit être rouverte avant de modifier sa liste.

**Imprimer la fiche de présence** produit une feuille avec espace de signature, sans informations financières. **Rapport complet** rassemble les informations générales, le compte rendu, les rôles, les heures et les présences. La comptabilité est affichée uniquement aux personnes autorisées : opérations validées, autres opérations hors totaux, participations attendues, avances et remboursements. Les participations déclarées ne sont pas additionnées une seconde fois aux recettes. Les présences non finalisées restent masquées à la trésorerie.

Les documents peuvent être imprimés ou enregistrés en PDF depuis le navigateur. Les modèles partagent un en-tête proportionnel, répété à l’impression. Dans **Paramètres > En-têtes**, contrôler l’aperçu de l’en-tête effectivement imprimé. Si une partie du document source manque, augmenter la hauteur de découpe, recalculer puis activer le modèle.

Dans **Choristes**, utiliser **Nouvel intervenant extérieur** pour une personne qui aide ponctuellement sans appartenir à la chorale. Sa voix et son adresse électronique sont facultatives. Son statut est **Intervenant extérieur**, disponible aussi dans le formulaire habituel et les filtres. Aucun compte n’est créé automatiquement. Elle peut être inscrite à une activité depuis les participants ou la fiche de présence et apparaît comme intervenant extérieur dans le rapport. Elle ne reçoit ni les cotisations récurrentes des choristes ni les frais automatiques de participation ou pénalités de présence. Les répétitions continuent à préparer par défaut la liste des choristes actifs.

Dans **Cotisations**, la modification conserve la cotisation existante. Les anciennes cotisations sans type utilisent le type mensuel. Les notes d’un règlement historique restent modifiables sans créer une nouvelle recette. Les montants d’un paiement déjà validé ou historique nécessitant un rapprochement restent protégés par les règles comptables.

Vérification locale : `php artisan test` et `npm run build`. Le contrôle des huit modèles en navigateur s’exécute avec `npm run test:reports` (Chromium Playwright et `pdftotext` requis). Il utilise une base SQLite en mémoire et des personnes fictives ; les exports sont dans `test-results/activity-reports/`.

## Rattacher une opération à une activité

Depuis **Activités > Écritures**, le journal est filtré sur l’activité. **Nouvelle opération** pré-remplit ce lien ; il peut être conservé, changé ou laissé vide pour une opération générale. Le compte classe l’écriture ; l’activité indique son objet. Le lien ne crée pas une deuxième recette ou dépense et ne modifie pas le budget déclaré de l’activité.

Sur un brouillon, **Rattacher** permet de choisir le compte et l’activité sans modifier le montant, la date ou le bénéficiaire. Cette action est aussi disponible pour une recette issue d’un don, dont le montant reste géré depuis le don d’origine. Une recette de cotisation ou un remboursement conserve l’activité définie par son document d’origine. Les frais d’inscription d’activité créent déjà ce lien automatiquement.

Après validation, l’écriture entre dans le bilan de l’activité et devient protégée. Les changements ultérieurs passent par une régularisation comptable. Les avances personnelles se déclarent dans **Avances des choristes** ; leur remboursement suit ce parcours pour éviter une dépense saisie deux fois. Une activité possédant des opérations, cotisations ou avances ne peut plus être supprimée ; son statut peut être modifié en conservant son historique.

## Destinataires des e-mails

Les messages groupés « Toute la chorale » concernent les choristes actifs, avec ou sans compte, sous réserve d’une adresse valide. Les messages d’activité sont limités aux participants éligibles, y compris les intervenants extérieurs. Le rappel de répétition programmé peut également atteindre un choriste sans compte. En revanche, les rappels transversaux du carnet personnel nécessitent actuellement un compte.

**Paramètres > Diagnostic des e-mails** distingue ces possibilités et les adresses manquantes. Les périmètres d’événement ou de commission et la configuration d’envoi restent applicables. Ni ces chiffres ni l’acceptation par le transport ne confirment l’arrivée dans la boîte du destinataire.
