<?php

namespace App\Models;

use App\Services\SettingsStore;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paramètre applicatif persistant (clé / valeur).
 *
 * Les lectures passent par {@see SettingsStore} : la table est chargée une
 * seule fois par requête, ce qui évite une lecture de cache par paramètre
 * affiché (nom de la chorale, slogan, coordonnées, logo…).
 */
class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Durée du cache des paramètres (en secondes).
     */
    public const CACHE_TTL = 3600;

    public const CACHE_PREFIX = 'settings.';

    /**
     * Clé unique du cache contenant tous les paramètres.
     */
    public const CACHE_KEY = self::CACHE_PREFIX.'all';

    protected static function booted(): void
    {
        $flush = function (): void {
            app(SettingsStore::class)->flush();
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Tous les paramètres (une seule lecture de cache par requête).
     *
     * @return array<string, string|null>
     */
    public static function allCached(): array
    {
        return app(SettingsStore::class)->all();
    }

    /**
     * Lit un paramètre (valeur par défaut si absent).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return app(SettingsStore::class)->get($key, $default);
    }

    /**
     * Écrit un paramètre.
     */
    public static function set(string $key, mixed $value): void
    {
        static::setMany([$key => $value]);
    }

    /**
     * Écrit plusieurs paramètres en une seule requête.
     *
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        if ($values === []) {
            return;
        }

        $now = now();

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [
                'tenant_id' => app(TenantContext::class)->requireId(),
                'key' => $key,
                'value' => $value === null ? null : (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        static::upsert($rows, ['tenant_id', 'key'], ['value', 'updated_at']);

        app(SettingsStore::class)->flush();
        $tenantId = app(TenantContext::class)->requireId();
        DB::afterCommit(function () use ($tenantId) {
            try {
                app(TenantContext::class)->run($tenantId, fn () => app(SettingsStore::class)->flush());
            } catch (Throwable $e) {
                // The database is already committed. A cache incident must not
                // make a caller undo newly stored files referenced by that data.
                try {
                    Log::warning('Invalidation du cache des paramètres à reprendre.', ['tenant_id' => $tenantId]);
                } catch (Throwable $loggingError) {
                    // A failed log destination must not turn a committed write into a rollback signal.
                }
            }
        });
    }

    /**
     * Vide le cache des paramètres.
     */
    public static function flushCache(): void
    {
        app(SettingsStore::class)->flush();
    }
}
