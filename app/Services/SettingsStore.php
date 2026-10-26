<?php

namespace App\Services;

use App\Models\Setting;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Accès mémoire aux paramètres applicatifs.
 *
 * La table `settings` est chargée en une seule fois puis conservée en cache
 * et en mémoire pour la durée de la requête et le client actif.
 * Une page qui affiche plusieurs paramètres (nom, slogan, coordonnées, logo)
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

    protected ?string $memoScope = null;

    /**
     * Tous les paramètres.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $context = app(TenantContext::class);
        if (TenantContext::isolated() && $context->id() === null) {
            return [];
        }
        $scope = $context->key(Setting::CACHE_KEY);
        if ($this->memoScope !== $scope) {
            $this->forget();
            $this->memoScope = $scope;
        }

        return $this->memo ??= Cache::remember(
            $scope,
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
        $this->memoScope = null;
    }

    /**
     * Oublie la mémoire de requête et le cache persistant.
     */
    public function flush(): void
    {
        Cache::forget(app(TenantContext::class)->key(Setting::CACHE_KEY));

        $this->forget();
    }
}
