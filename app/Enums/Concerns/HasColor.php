<?php

namespace App\Enums\Concerns;

trait HasColor
{
    /**
     * Couleurs Tailwind pour les badges, indexées par la valeur de l'enum.
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return [];
    }

    public function color(): string
    {
        return static::colors()[$this->value] ?? 'gray';
    }

    /**
     * Classes Tailwind complètes d'un badge.
     */
    public function badge(): string
    {
        return 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium '
            .'bg-'.$this->color().'-100 text-'.$this->color().'-800 '
            .'dark:bg-'.$this->color().'-900 dark:text-'.$this->color().'-200';
    }
}
