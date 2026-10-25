<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class CheckProductionReadiness extends Command
{
    protected $signature = 'chorale:check-production';

    protected $description = 'Vérifier en lecture seule la configuration requise avant ouverture commerciale';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $payment = (string) config('tenancy.chariow.payment_url');
        $https = fn (string $value): bool => filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_SCHEME) === 'https';
        $checks = [
            'Environnement production' => app()->isProduction(),
            'Mode debug désactivé' => config('app.debug') === false,
            'URL de l’application en HTTPS' => $https($url),
            'Isolation multi-tenant activée' => config('tenancy.enabled') === true,
            'Base PostgreSQL configurée' => config('database.default') === 'pgsql',
            'Cookie de session HTTPS et HttpOnly' => config('session.secure') === true && config('session.http_only') === true,
            'Protection SameSite des cookies' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Session persistante configurée' => ! in_array(config('session.driver'), ['array', null], true),
            'Cache persistant configuré' => ! in_array(config('cache.default'), ['array', 'null', null], true),
            'Envoi e-mail configuré hors simulation' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'Lien commercial Chariow en HTTPS' => $https($payment),
            'Vérification des licences Chariow configurée' => filled(config('tenancy.chariow.api_key')) && filled(config('tenancy.chariow.product_id')),
        ];
        try {
            $checks['Clé de chiffrement utilisable'] = Crypt::decryptString(Crypt::encryptString('readiness')) === 'readiness';
        } catch (Throwable $e) {
            $checks['Clé de chiffrement utilisable'] = false;
        }
        try {
            DB::select('select 1');
            $checks['Connexion à la base'] = true;
            $checks['Tables SaaS et propriétaire présentes'] = Schema::hasTable('tenants') && Schema::hasTable('platform_admins') && Schema::hasTable('platform_usage_daily');
            $checks['Propriétaire actif provisionné'] = Schema::hasTable('platform_admins') && DB::table('platform_admins')->where('enabled', true)->exists();
            $demo = Schema::hasTable('users') && DB::table('users')->where(function ($q) {
                $q->where('email', 'like', '%.test')->orWhere('username', 'like', 'demo.%')->orWhereIn('username', ['preview.beta', 'preview.expire']);
            })->exists();
            if (Schema::hasTable('users')) {
                $seeded = DB::table('users')->whereIn('email', ['admin@chorale.ci', 'directeur@chorale.ci', 'liturgique@chorale.ci', 'instruments@chorale.ci', 'tresorerie@chorale.ci', 'secretariat@chorale.ci', 'choriste@chorale.ci'])->pluck('password');
                $demo = $demo || $seeded->contains(fn ($hash) => password_verify('password', $hash));
            }
            $ownerDemo = Schema::hasTable('platform_admins') && DB::table('platform_admins')->where('email', 'like', '%.test')->exists();
            $checks['Aucun compte de démonstration détecté'] = ! $demo && ! $ownerDemo;
        } catch (Throwable $e) {
            $checks['Vérification de la base et des comptes'] = false;
        }
        $rows = [];
        foreach ($checks as $label => $passed) {
            $rows[] = [$passed ? 'OK' : 'À corriger', $label];
        }
        $this->table(['Résultat', 'Contrôle'], $rows);
        $this->line('Aucun secret affiché. Aucun paramètre ou compte modifié.');
        $this->line('À vérifier séparément : e-mails réels, achat Chariow, HTTPS externe, sauvegardes/restauration, workers et planificateur.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
