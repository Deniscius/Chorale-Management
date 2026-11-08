# Rapports d’activité et personnes extérieures

Dans **Activités**, ouvrir **Fiche de présence** pour accéder directement à l’activité choisie. Si la liste est vide, sélectionner les participants et les ajouter. L’inscription ne renseigne pas leur présence. Une fiche finalisée doit être rouverte avant de modifier sa liste.

**Imprimer la fiche de présence** produit une feuille avec espace de signature, sans informations financières. **Rapport complet** rassemble les informations générales, le compte rendu, les rôles, les heures et les présences. La comptabilité est affichée uniquement aux personnes autorisées : opérations validées, autres opérations hors totaux, participations attendues, avances et remboursements. Les participations déclarées ne sont pas additionnées une seconde fois aux recettes. Les présences non finalisées restent masquées à la trésorerie.

Les documents peuvent être imprimés ou enregistrés en PDF depuis le navigateur. Les modèles partagent un en-tête proportionnel, répété à l’impression. Dans **Paramètres > En-têtes**, contrôler l’aperçu de l’en-tête effectivement imprimé. Si une partie du document source manque, augmenter la hauteur de découpe, recalculer puis activer le modèle.

Dans **Choristes**, utiliser **Nouvel intervenant extérieur** pour une personne qui aide ponctuellement sans appartenir à la chorale. Sa voix et son adresse électronique sont facultatives. Son statut est **Intervenant extérieur**, disponible aussi dans le formulaire habituel et les filtres. Aucun compte n’est créé automatiquement. Elle peut être inscrite à une activité depuis les participants ou la fiche de présence et apparaît comme intervenant extérieur dans le rapport. Elle ne reçoit ni les cotisations récurrentes des choristes ni les frais automatiques de participation. Les répétitions continuent à préparer par défaut la liste des choristes actifs.

Dans **Cotisations**, la modification conserve la cotisation existante. Les anciennes cotisations sans type utilisent le type mensuel. Les notes d’un règlement historique restent modifiables sans créer une nouvelle recette. Les montants d’un paiement déjà validé ou historique nécessitant un rapprochement restent protégés par les règles comptables.

Vérification locale : `php artisan test` et `npm run build`. Le contrôle des huit modèles en navigateur s’exécute avec `npm run test:reports` (Chromium Playwright et `pdftotext` requis). Il utilise une base SQLite en mémoire et des personnes fictives ; les exports sont dans `test-results/activity-reports/`.
