<?php

// Editorial instructions only: never include account or business data in speech.
return [
    'guides' => [
        'works.*' => [
            'title' => 'Retrouver un chant',
            'steps' => [
                ['icon' => 'search', 'text' => 'Cherchez le titre du chant dans la zone Rechercher un chant. Vous pouvez aussi parcourir la liste sans écrire.'],
                ['icon' => 'book', 'text' => 'Repérez le chant dans les résultats et consultez les informations ou les supports disponibles.'],
                ['icon' => 'home', 'text' => 'Pour changer de rubrique, revenez à l’accueil avec le bouton maison.'],
            ],
        ],
        'rehearsals.*' => [
            'title' => 'Préparer une répétition',
            'steps' => [
                ['icon' => 'calendar', 'text' => 'Consultez les dates des répétitions. Le filtre Période permet de choisir À venir.'],
                ['icon' => 'music', 'text' => 'Repérez le lieu et le programme de la répétition dans la liste. Demandez à votre responsable si une information manque.'],
                ['icon' => 'users', 'text' => 'Les présences sont gérées par les personnes autorisées. Consulter une répétition ne confirme pas votre présence.'],
            ],
        ],
        'members.*' => [
            'title' => 'Retrouver un choriste',
            'steps' => [
                ['icon' => 'search', 'text' => 'Utilisez la recherche pour retrouver un nom, ou parcourez la liste des choristes.'],
                ['icon' => 'users', 'text' => 'Consultez uniquement les informations nécessaires. Ne partagez pas les informations personnelles des membres.'],
            ],
        ],
        'transactions.*' => [
            'title' => 'Consulter les recettes et dépenses',
            'steps' => [
                ['icon' => 'banknotes', 'text' => 'Commencez par consulter la liste des opérations et les filtres disponibles.'],
                ['icon' => 'warning', 'text' => 'Avant tout enregistrement, vérifiez le montant et la date. Demandez l’aide du trésorier si vous avez un doute. Écouter cette aide ne crée aucune opération.'],
            ],
        ],
        'instruments.*' => [
            'title' => 'Retrouver du matériel',
            'steps' => [
                ['icon' => 'music', 'text' => 'Consultez la liste du matériel de votre chorale. Les différentes rubriques permettent de retrouver les articles et leurs mouvements.'],
                ['icon' => 'warning', 'text' => 'Avant de modifier une fiche ou un mouvement, demandez confirmation au responsable du matériel.'],
            ],
        ],
        'activities.*' => [
            'title' => 'Retrouver une activité',
            'steps' => [
                ['icon' => 'calendar', 'text' => 'Parcourez les activités et repérez leur date et leur lieu.'],
                ['icon' => 'users', 'text' => 'Si vous avez une question sur une activité, demandez au responsable de votre chorale.'],
            ],
        ],
        'correspondences.*' => [
            'title' => 'Consulter un courrier',
            'steps' => [
                ['icon' => 'mail', 'text' => 'Retrouvez le courrier dans la liste et consultez les informations disponibles pour votre rôle.'],
                ['icon' => 'warning', 'text' => 'Les courriers peuvent être confidentiels. Demandez au secrétariat avant de partager leur contenu.'],
            ],
        ],
        'dashboard' => [
            'title' => 'Découvrir votre accueil',
            'steps' => [
                ['icon' => 'home', 'text' => 'Choisissez une grande carte pour ouvrir une rubrique. Les rubriques affichées dépendent de vos droits.'],
                ['icon' => 'book', 'text' => 'Le bouton haut-parleur explique une carte sans l’ouvrir. Vous pouvez réécouter autant que nécessaire.'],
                ['icon' => 'chart', 'text' => 'Le bouton Voir les détails de la chorale affiche les chiffres et les prochaines activités.'],
            ],
        ],
    ],
];
