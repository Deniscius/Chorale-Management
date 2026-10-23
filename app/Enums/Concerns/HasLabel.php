<?php

namespace App\Enums\Concerns;

trait HasLabel
{
    /**
     * Libellés lisibles, indexés par la valeur de l'enum.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [];
    }

    public function label(): string
    {
        return static::labels()[$this->value] ?? $this->value;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
