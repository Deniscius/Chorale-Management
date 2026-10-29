<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\AuthenticateChoraleSession;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\AttendanceIncident;
use App\Models\AttendanceRule;
use App\Models\Budget;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Models\Contribution;
use App\Models\Document;
use App\Models\Donation;
use App\Models\FinancialType;
use App\Models\LetterheadTemplate;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VoiceResource;
use App\Observers\AuditObserver;
use App\Observers\PersonalInformationObserver;
use App\Services\Ai\AiClient;
use App\Services\Ai\NullAiClient;
use App\Services\Ai\OllamaClient;
use App\Services\Ai\OpenRouterClient;
use App\Services\ChoraleAssistant;
use App\Services\ChoraleIdentity;
use App\Services\CommissionAccess;
use App\Services\FinancialCatalog;
use App\Services\PrivateFiles;
use App\Services\SettingsStore;
use App\Services\Sms\D7SmsSender;
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
        $this->app->scoped(CommissionAccess::class);
        $this->app->scoped(PrivateFiles::class);
        $this->app->extend('validation.presence', fn ($verifier, $app) => new TenantPresenceVerifier($app['db']));
        // Passerelle SMS : l'implémentation dépend de l'environnement.
        $this->app->bind(
            SmsSender::class,
            fn () => match (config('chorale.reminders.sms_driver')) {
                'http' => new HttpSmsSender,
                'd7' => new D7SmsSender,
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
        $this->app->scoped(SettingsStore::class);
        $this->app->singleton(FinancialCatalog::class);

        // Identité de la chorale : une instance par requête (valeurs mémorisées).
        $this->app->scoped(ChoraleIdentity::class);
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
        foreach ([FinancialType::class, MemberInvitation::class, CommissionTeam::class, User::class, Document::class, Donation::class, Contribution::class, Budget::class, Transaction::class, Reminder::class, LetterheadTemplate::class, VoiceResource::class, AttendanceRule::class, AttendanceIncident::class, Rehearsal::class, RehearsalAttendance::class, Activity::class, ActivityParticipant::class] as $model) {
            $model::observe(AuditObserver::class);
        }

        // Bonne pratique : le chargement paresseux des relations est interdit
        // hors production. Une requête N+1 provoque désormais une exception
        // explicite en développement et dans les tests, au lieu de dégrader
        // silencieusement les performances.
        Model::preventLazyLoading(! $this->app->isProduction());

        Member::observe(PersonalInformationObserver::class);
        User::observe(PersonalInformationObserver::class);
        ChoirCommission::observe(AuditObserver::class);

        // L'administrateur contourne toutes les vérifications de rôle.
        Gate::before(function (User $user, string $ability) {
            if ($ability === 'manage-commissions') {
                return $user->role === UserRole::Directeur;
            }
            if ($user->role === UserRole::Admin) {
                return true;
            }

            return null;
        });

        // Lecture d'un module fonctionnel.
        Gate::define('view-module', fn (User $user, string $module) => $user->canAccessModule($module));

        // Écriture dans un module fonctionnel.
        Gate::define('manage-module', fn (User $user, string $module) => $user->canManageModule($module));

        Gate::define('manage-commissions', fn (User $user) => $user->role === UserRole::Directeur);

        // Gestion des comptes utilisateurs : administrateur uniquement.
        Gate::define('manage-users', fn (User $user) => $user->role === UserRole::Admin);

        // Paramètres de l'application (identité de la chorale) : administrateur uniquement.
        Gate::define('manage-settings', fn (User $user) => $user->role === UserRole::Admin);

        Gate::define('view-attendance', fn (User $user) => in_array($user->role, [
            UserRole::Directeur, UserRole::Secretaire, UserRole::CommissionLiturgique,
            UserRole::CommissionSuivieFormation,
        ], true));
        Gate::define('manage-attendance', fn (User $user) => in_array($user->role, [
            UserRole::Directeur, UserRole::Secretaire, UserRole::CommissionLiturgique,
            UserRole::CommissionSuivieFormation,
        ], true));
        Gate::define('finalize-attendance', fn (User $user) => in_array($user->role, [
            UserRole::Directeur, UserRole::Secretaire,
        ], true));
        Gate::define('view-attendance-finance', fn (User $user) => in_array($user->role, [
            UserRole::Directeur, UserRole::Tresorier,
        ], true));
        Gate::define('manage-attendance-finance', fn (User $user) => in_array($user->role, [
            UserRole::Directeur, UserRole::Tresorier,
        ], true));
        Gate::define('configure-attendance-finance', fn (User $user) => $user->role === UserRole::Directeur);

        Gate::define('view-member-dossier', fn (User $user, Member $member) => $user->member_id === $member->id || in_array($user->role, [
            UserRole::Directeur, UserRole::Secretaire, UserRole::Tresorier,
        ], true));

        Gate::define('view-member-attendance', fn (User $user, Member $member) => $user->member_id === $member->id || in_array($user->role, [
            UserRole::Directeur, UserRole::Secretaire, UserRole::Tresorier,
            UserRole::CommissionLiturgique, UserRole::CommissionSuivieFormation,
        ], true));

        Gate::define('view-member-finance', fn (User $user, Member $member) => $user->member_id === $member->id || in_array($user->role, [
            UserRole::Directeur, UserRole::Tresorier,
        ], true));
    }
}
