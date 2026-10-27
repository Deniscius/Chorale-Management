<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DocumentVisibility: string
{
    use HasLabel;

    case Private = 'private';
    case Commission = 'commission';
    case Published = 'published';

    public static function labels(): array
    {
        return [
            self::Private->value => 'Responsables habilités',
            self::Commission->value => 'Accès de la commission',
            self::Published->value => 'Toute la chorale (connectée)',
        ];
    }

    public function description(): string
    {
        return match ($this) {
            self::Private => 'Seules les personnes habilitées à gérer les archives de cette commission peuvent consulter ce document.',
            self::Commission => 'Les personnes ayant accès au module de cette commission et ses responsables habilités peuvent le consulter.',
            self::Published => 'Tous les utilisateurs connectés de cette chorale peuvent le consulter. Le fichier ne devient jamais public sur Internet.',
        };
    }
}
