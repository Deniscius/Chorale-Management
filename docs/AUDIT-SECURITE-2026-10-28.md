# Audit de sécurité applicatif — 28 octobre 2026

## Périmètre

L’audit couvre le code Laravel, les dépendances PHP et JavaScript, les routes, les contrôles d’accès multi-tenant, les fichiers privés et une instance locale jetable. Aucun test intrusif n’a été exécuté contre Render ou Neon en production.

## Contrôles exécutés

- `composer audit --locked` et `npm audit --audit-level=moderate` ;
- recherche de secrets et de primitives dangereuses dans les fichiers suivis ;
- suite PHPUnit complète, notamment l’isolation des chorales, les rôles, les invitations, les aperçus privés et la plateforme propriétaire ;
- tests de validation contre les balises, caractères de contrôle et valeurs incohérentes ;
- vérification des en-têtes de sécurité sur les réponses publiques et authentifiées.

## Résultats et corrections

### En-têtes navigateur incomplets — corrigé

Un middleware global ajoute désormais `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` et `Permissions-Policy`. Les routes sensibles qui imposent déjà `no-referrer` conservent cette politique plus stricte. HSTS est ajouté uniquement en production sur une requête HTTPS afin de ne pas casser les environnements locaux.

### Qualité des saisies — renforcée

Les noms de personnes acceptent les alphabets Unicode, accents, espaces, apostrophes et tirets. Les noms d’espaces, activités, lieux et instruments doivent contenir un texte lisible. Les balises, caractères de contrôle et identifiants matériels contenant des caractères inattendus sont rejetés côté serveur. Les espaces superflus et les adresses électroniques sont normalisés avant validation.

### Aperçu du filigrane — corrigé

Le logo privé est vérifié, limité à un type d’image autorisé puis intégré dans l’aperçu sous forme de donnée autonome. Le navigateur et le générateur PDF ne dépendent plus d’une deuxième requête authentifiée.

### Dépendances et secrets — aucun problème détecté

Les audits de dépendances ne signalent aucun avis connu. La recherche de secrets ne trouve que des valeurs manifestement fictives utilisées par les tests et le CI. Les clés réelles restent fournies par l’environnement.

## Limites

Cet audit vérifie l’application et une instance locale. Un test externe de l’URL Render, des règles réseau et de la configuration TLS devra faire l’objet d’une autorisation explicite et d’une fenêtre dédiée avant exécution en production.
