<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Accès mémoire aux paramètres applicatifs.
 *
 * La table `settings` est chargée en une seule fois puis conservée en cache
 * et en mémoire pour la durée de la requête. L'instance étant un singleton,
 * une page qui affiche plusieurs paramètres (nom, slogan, coordonnées, logo)
 * ne déclenche qu'une seule lecture de cache — et donc, avec le store
 * « database », une seule requête SQL.
 */
class SettingsStore
{
    /**
     * Paramètres chargés pendant la requête courante.
     *
     * @var array<string, string|null>|null
     */
    protected ?array $memo = null;

    /**
     * Tous les paramètres.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->memo ??= Cache::remember(
            Setting::CACHE_KEY,
            Setting::CACHE_TTL,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * Lit un paramètre.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Oublie les valeurs mémorisées pour la requête courante.
     */
    public function forget(): void
    {
        $this->memo = null;
    }

    /**
     * Oublie la mémoire de requête et le cache persistant.
     */
    public function flush(): void
    {
        Cache::forget(Setting::CACHE_KEY);

        $this->memo = null;
    }
}
