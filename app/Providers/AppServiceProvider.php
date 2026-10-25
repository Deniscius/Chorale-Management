<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\AuthenticateChoraleSession;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Budget;
use App\Models\CommissionTeam;
use App\Models\Contribution;
use App\Models\Document;
use App\Models\Donation;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\Ai\AiClient;
use App\Services\Ai\NullAiClient;
use App\Services\Ai\OllamaClient;
use App\Services\Ai\OpenRouterClient;
use App\Services\ChoraleAssistant;
use App\Services\ChoraleIdentity;
use App\Services\SettingsStore;
use App\Services\Sms\HttpSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantPresenceVerifier;
use App\Tenancy\TenantUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->extend('validation.presence', fn ($verifier, $app) => new TenantPresenceVerifier($app['db']));
        // Passerelle SMS : l'implémentation dépend de l'environnement.
        $this->app->bind(
            SmsSender::class,
            fn () => match (config('chorale.reminders.sms_driver')) {
                'http' => new HttpSmsSender,
                default => new LogSmsSender,
            }
        );

        // Fournisseur IA : remplacé par un client inactif si l'IA est éteinte.
        $this->app->bind(AiClient::class, function () {
            if (! config('ai.enabled')) {
                return new NullAiClient;
            }

            return match (config('ai.provider')) {
                'ollama' => new OllamaClient,
                default => new OpenRouterClient,
            };
        });

        $this->app->singleton(ChoraleAssistant::class);

        // Paramètres : une seule lecture de cache par requête.
        $this->app->singleton(SettingsStore::class);

        // Identité de la chorale : une instance par requête (valeurs mémorisées).
        $this->app->singleton(ChoraleIdentity::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('tenant-eloquent', fn ($app, $config) => new TenantUserProvider($app['hash'], $config['model']));
        foreach (['mail', 'sms'] as $channel) {
            RateLimiter::for('reminder-'.$channel,
                fn () => Limit::perMinute(60)->by(app(TenantContext::class)->key('reminder-'.$channel)));
        }

        // Livewire fournit Alpine : on l'injecte sur toutes les pages (même sans
        // composant Livewire) pour que les interactions x-data de la coque restent actives.
        app('livewire')->forceAssetInjection();
        Livewire::addPersistentMiddleware([EnsureModuleAccess::class, AuthenticateChoraleSession::class]);
        foreach ([CommissionTeam::class, User::class, Document::class, Donation::class, Contribution::class, Budget::class, Transaction::class, Reminder::class] as $model) {
            $model::observe(AuditObserver::class);
        }

        // Bonne pratique : le chargement paresseux des relations est interdit
        // hors production. Une requête N+1 provoque désormais une exception
        // explicite en développement et dans les tests, au lieu de dégrader
        // silencieusement les performances.
        Model::preventLazyLoading(! $this->app->isProduction());

        // L'administrateur contourne toutes les vérifications de rôle.
        Gate::before(function (User $user, string $ability) {
            if ($user->role === UserRole::Admin) {
                return true;
            }

            return null;
        });

        // Lecture d'un module fonctionnel.
        Gate::define('view-module', fn (User $user, string $module) => $user->canAccessModule($module));

        // Écriture dans un module fonctionnel.
        Gate::define('manage-module', fn (User $user, string $module) => $user->canManageModule($module));

        // Gestion des comptes utilisateurs : administrateur uniquement.
        Gate::define('manage-users', fn (User $user) => $user->role === UserRole::Admin);

        // Paramètres de l'application (identité de la chorale) : administrateur uniquement.
        Gate::define('manage-settings', fn (User $user) => $user->role === UserRole::Admin);

        // Identité de la chorale partagée avec toutes les vues (l'instance
        // singleton mémorise les lectures : aucune requête supplémentaire).
        View::share('chorale', $this->app->make(ChoraleIdentity::class));
    }
}
