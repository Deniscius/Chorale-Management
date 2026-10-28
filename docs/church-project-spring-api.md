# Intégration avec `church_project_spring`

L'application Laravel reste la source de vérité. Le projet Spring consomme l'API HTTPS et ne se connecte jamais directement à PostgreSQL.

## Créer un accès

Dans Laravel, ouvrir **Administration → Intégrations API**. Créer un jeton nommé `church_project_spring`, sélectionner uniquement les permissions nécessaires et définir une date d'expiration. Le jeton n'est affiché qu'une seule fois.

Conserver côté Spring les deux secrets suivants dans le gestionnaire de secrets de l'environnement :

```properties
CHORALE_API_URL=https://votre-application.onrender.com
CHORALE_API_TOKEN=jeton_affiche_une_seule_fois
```

Ne jamais les versionner dans `application.properties`, Git ou une image Docker.

## Requêtes disponibles

Toutes les requêtes envoient l'en-tête suivant :

```http
Authorization: Bearer ${CHORALE_API_TOKEN}
Accept: application/json
```

- `GET /api/v1/members` : liste paginée. Filtres facultatifs : `q`, `voice`, `status`, `per_page` (maximum 100).
- `GET /api/v1/members/{id}` : fiche synthétique. Les blocs `attendance` et `finance` sont retournés uniquement si le jeton possède les permissions correspondantes.

Exemple avec Spring `WebClient` :

```java
WebClient choraleApi = WebClient.builder()
    .baseUrl(System.getenv("CHORALE_API_URL"))
    .defaultHeader(HttpHeaders.AUTHORIZATION,
        "Bearer " + System.getenv("CHORALE_API_TOKEN"))
    .defaultHeader(HttpHeaders.ACCEPT, MediaType.APPLICATION_JSON_VALUE)
    .build();
```

## Réponses et rotation

- `401` : jeton absent, inconnu, expiré ou révoqué.
- `403` : permission manquante ou espace de chorale inactif.
- `422` : filtre invalide.
- `429` : limite de requêtes atteinte.

Pour faire une rotation, créer un nouveau jeton, le déployer côté Spring, vérifier une requête, puis révoquer l'ancien dans Laravel.
