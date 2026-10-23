# Archives privées et cartes de synthèse

## Utilisation

- Ouvrir **Archives liturgiques** pour déposer un **Programme de messe** ou une **Partition**, avec une date, un titre et une description. La recherche, la catégorie, l’année et la visibilité permettent de retrouver les fichiers.
- L’administrateur ouvre **Responsable et associé** pour choisir le responsable parmi les comptes du rôle Commission liturgique (ou Secrétaire pour les courriers). Le responsable peut ensuite désigner ou remplacer son associé, quel que soit le rôle de ce dernier. L’associé ne peut pas modifier ces désignations.
- Les fichiers sont **privés par défaut**, accessibles aux deux personnes désignées uniquement. Le rôle administrateur ou directeur ne donne pas à lui seul accès au fichier privé.
- **Partager** ouvre le fichier aux utilisateurs connectés. **Rendre privé** retire ce partage. Un utilisateur déconnecté ne peut jamais consulter le fichier.
- **Archives des courriers** applique les mêmes règles. Dans le registre Courriers, le responsable désigné peut joindre un fichier au formulaire ; le bouton **Pièces jointes** retrouve les fichiers de ce courrier. L’associé peut aussi déposer une pièce depuis son archive. Partager une pièce ne donne pas accès au registre ni au contenu du courrier.
- Les cartes en tête des listes ouvrent les résultats correspondants : comptes administrateurs, courriers à traiter, opérations validées, cotisations restant à recouvrer, saison actuelle, etc. Les cartes de totaux conduisent à la liste complète. Les filtres sont conservés dans l’URL.

## Installation et reprise

Cette évolution utilise le PostgreSQL déjà pris en charge par le projet ; SQLite reste couvert par les tests.

1. Sauvegarder la base et le disque privé avant la mise à jour.
2. Installer les dépendances habituelles et compiler les ressources (`npm ci`, `npm run build`).
3. Exécuter `php artisan migrate --force`, puis `php artisan optimize:clear` sur la base cible.
4. Vérifier les responsables et désigner les associés dans les deux espaces d’archives.

La migration conserve les documents existants. Les archives de liturgie et du secrétariat deviennent privées ; les autres commissions conservent leur politique d’accès par module. Un responsable existant est repris automatiquement uniquement lorsqu’un seul compte possède le rôle correspondant. En cas de plusieurs comptes, ou sur une installation neuve migrée avant la création des utilisateurs, l’administrateur doit le désigner explicitement. Aucun associé n’est choisi automatiquement.

Les anciennes chaînes `pdf_path` du catalogue et `document_path` des courriers ne sont pas transformées en fichiers : importer les fichiers réels dans les archives. Le formulaire Courriers n’accepte plus de chemin brut.

## Garanties et limites

Les fichiers restent sur le disque privé et sont servis par une route authentifiée contrôlant l’accès au moment de la consultation. Les listes, catégories et compteurs filtrent les documents invisibles en SQL. Les téléchargements utilisent `private, no-store` et `nosniff`. L’import valide les formats et une limite de 10 Mo ; les erreurs de sauvegarde nettoient les nouveaux fichiers. Les changements de visibilité et de personnes désignées alimentent le journal des modifications.

Changer d’associé ou retirer un partage bloque les consultations suivantes. Cela ne peut pas supprimer une copie déjà téléchargée ni des octets déjà reçus par un navigateur. Les administrateurs techniques ayant un accès direct au stockage ou à la base restent hors du périmètre des droits applicatifs.

Les programmes et partitions sont des documents archivés ; cette évolution ne crée pas un éditeur de programme musical. Aucun déploiement en production ni fusion de pull request n’est automatique.
