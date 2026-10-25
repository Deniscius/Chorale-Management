<?php

namespace Tests\Feature\Analysis;

use App\Enums\UserRole;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\TenantAddress;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use ReflectionClass;
use Tests\TestCase;

/**
 * Contrôles d'architecture et de sécurité du RBAC.
 *
 * Vérifie que la séparation des responsabilités (routes, middleware, gates,
 * composants Livewire) reste cohérente : aucun écran accessible hors
 * périmètre du rôle, aucun droit d'écriture accordé par inadvertance.
 */
class ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes accessibles sans authentification.
     *
     * @var list<string>
     */
    protected array $routesPubliques = [
        'login',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'chorale.logo',
        'spaces.create',
        'spaces.store',
        'platform.login',
        'platform.login.store',
        'platform.setup',
        'platform.setup.store',
        'tenant.entry',
        'tenant.logo',
        'tenant.login',
        'member-activation.show',
        'member-activation.store',
    ];

    public function test_personal_addresses_have_tenant_checks_and_initial_setup_is_rate_limited(): void
    {
        foreach (['tenant.entry', 'tenant.logo', 'tenant.login'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains(TenantAddress::class, $route->middleware());
            $this->assertContains('slug', $route->parameterNames());
        }
        foreach (['platform.setup', 'platform.setup.store'] as $name) {
            $this->assertTrue(collect(Route::getRoutes()->getByName($name)->middleware())->contains(fn ($middleware) => str_starts_with($middleware, 'throttle:')));
        }
    }

    public function test_les_routes_activation_publiques_restent_limitees_et_reservees_aux_invites(): void
    {
        foreach (['member-activation.show', 'member-activation.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('guest', $route->middleware());
            $this->assertTrue(collect($route->middleware())->contains(fn ($middleware) => str_starts_with($middleware, 'throttle:')));
            $this->assertContains('token', $route->parameterNames());
        }
    }

    /**
     * Toute route applicative doit être protégée par le middleware d'authentification.
     */
    public function test_toutes_les_routes_applicatives_sont_authentifiees(): void
    {
        $protegees = [];

        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();

            if ($nom === null || in_array($nom, $this->routesPubliques, true)) {
                continue;
            }

            // Seules les routes de l'application sont concernées
            // (les routes internes du framework sont exclues).
            $uses = $route->getAction('uses');

            if (! is_string($uses) || ! str_starts_with($uses, 'App\\')) {
                continue;
            }

            if (! in_array('auth', $route->middleware(), true) && ! in_array('platform.auth', $route->middleware(), true)) {
                $protegees[] = sprintf('%s (%s)', $nom, $route->uri());
            }
        }

        $this->assertSame([], $protegees, implode("\n", $protegees));
    }

    /**
     * Chaque écran de module fonctionnel doit être protégé par le middleware « module ».
     */
    public function test_chaque_ecran_de_module_est_protecte_par_le_middleware_module(): void
    {
        $ecransSansProtection = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            $uses = $action['uses'] ?? null;

            if (! is_string($uses) || ! str_contains($uses, '@')) {
                continue;
            }

            [$classe] = explode('@', $uses);

            if (! class_exists($classe) || ! is_subclass_of($classe, Component::class)) {
                continue;
            }

            // Le tableau de bord est réservé aux rôles ayant accès à l'ensemble.
            if ($classe === DashboardController::class) {
                continue;
            }

            $middleware = $route->middleware();

            // Un écran doit être protégé par le middleware « module »
            // ou, pour les écrans réservés à l'administration, par une ability.
            $protege = collect($middleware)->contains(
                fn (string $m): bool => str_starts_with($m, 'module:') || str_starts_with($m, 'can:')
            );

            if (! $protege) {
                $ecransSansProtection[] = $route->getName().' ('.$classe.')';
            }
        }

        $this->assertSame([], $ecransSansProtection, implode("\n", $ecransSansProtection));
    }

    /**
     * Le module déclaré par un composant Livewire doit exister dans l'énumération des rôles.
     */
    public function test_chaque_module_declare_est_reference_par_l_enumeration_des_roles(): void
    {
        $modules = [];

        foreach ($this->composantsLivewire() as $classe) {
            if (! defined($classe.'::MODULE_KEY')) {
                continue;
            }

            $modules[constant($classe.'::MODULE_KEY')] = $classe;
        }

        $this->assertNotEmpty($modules);

        $modulesConnus = array_merge(
            [
                UserRole::MODULE_MEMBERS,
                UserRole::MODULE_LITURGIQUE,
                UserRole::MODULE_INSTRUMENTS,
                UserRole::MODULE_COURRIERS,
                UserRole::MODULE_ACTIVITES,
                UserRole::MODULE_COMPTABILITE,
            ],
        );

        foreach ($modules as $module => $classe) {
            $this->assertContains(
                $module,
                $modulesConnus,
                "Le module « {$module} » déclaré par {$classe} n'est pas référencé dans l'énumération des rôles."
            );
        }
    }

    /**
     * Tout composant utilisant le trait d'autorisation doit déclarer son module.
     */
    public function test_chaque_composant_autorise_doit_declarer_son_module(): void
    {
        $manquants = [];

        foreach ($this->composantsLivewire() as $classe) {
            $usesTrait = in_array(InteractsWithModule::class, class_uses($classe), true);

            if (! $usesTrait) {
                continue;
            }

            if (! defined($classe.'::MODULE_KEY')) {
                $manquants[] = $classe;
            }
        }

        $this->assertSame([], $manquants, implode("\n", $manquants));
    }

    /**
     * Un rôle ne peut jamais gérer un module qu'il ne peut pas consulter.
     */
    public function test_les_droits_de_gestion_sont_inclus_dans_les_droits_de_consultation(): void
    {
        foreach (UserRole::cases() as $role) {
            $consultables = $role->modules();
            $gerables = $role->manageModules();

            $this->assertSame(
                [],
                array_values(array_diff($gerables, $consultables)),
                sprintf('Le rôle %s gère un module qu\'il ne peut pas consulter.', $role->value)
            );
        }
    }

    /**
     * Chaque rôle dispose d'un libellé et d'une couleur (affichage des badges).
     */
    public function test_chaque_role_possede_un_libelle_et_une_couleur(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame(
                $role->value,
                $role->label(),
                "Le rôle {$role->value} n'a pas de libellé lisible."
            );

            $this->assertNotEmpty(
                $role->color(),
                "Le rôle {$role->value} n'a pas de couleur définie."
            );
        }

        $this->assertSame(
            count(UserRole::cases()),
            count(UserRole::labels()),
            'Tous les rôles doivent figurer dans la table des libellés.'
        );

        $this->assertSame(
            count(UserRole::cases()),
            count(UserRole::colors()),
            'Tous les rôles doivent figurer dans la table des couleurs.'
        );
    }

    /**
     * Les abilities attendues sont définies.
     */
    public function test_les_abilities_rbac_sont_definies(): void
    {
        foreach (['view-module', 'manage-module', 'manage-users'] as $ability) {
            $this->assertTrue(
                Gate::has($ability),
                "L'ability « {$ability} » n'est pas enregistrée."
            );
        }
    }

    /**
     * L'administrateur contourne les restrictions, les autres rôles non.
     */
    public function test_seul_ladministrateur_contourne_les_restrictions(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $choriste = User::factory()->role(UserRole::Choriste)->create();

        $this->assertTrue($admin->can('view-module', UserRole::MODULE_COMPTABILITE));
        $this->assertTrue($admin->can('manage-module', UserRole::MODULE_COMPTABILITE));
        $this->assertTrue($admin->can('manage-users'));

        $this->assertFalse($choriste->can('view-module', UserRole::MODULE_COMPTABILITE));
        $this->assertFalse($choriste->can('manage-module', UserRole::MODULE_MEMBERS));
        $this->assertFalse($choriste->can('manage-users'));
    }

    /**
     * Un choriste ne doit jamais obtenir de droit de gestion sur un module.
     */
    public function test_aucun_restreint_n_obtient_de_droit_de_gestion(): void
    {
        foreach (UserRole::cases() as $role) {
            if (in_array($role, [UserRole::Admin, UserRole::Directeur], true)) {
                continue;
            }

            if ($role === UserRole::Secretaire) {
                // La secrétaire gère le courrier et les fiches membres.
                continue;
            }

            foreach ($role->manageModules() as $module) {
                $this->assertNotEmpty($module);
            }
        }
    }

    /**
     * La liste des modules accessible doit être stable et non vide pour tout rôle.
     */
    public function test_tout_role_accede_a_au_moins_un_module(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotEmpty(
                $role->modules(),
                "Le rôle {$role->value} n'a accès à aucun module."
            );
        }
    }

    /**
     * Liste des composants Livewire à page entière.
     *
     * @return list<class-string>
     */
    protected function composantsLivewire(): array
    {
        $classes = [];

        $dossier = app_path('Livewire');

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                continue;
            }

            $chemin = $fichier->getPathname();

            if (str_contains($chemin, DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $classe = 'App\\Livewire\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                ltrim(str_replace($dossier, '', $chemin), '/\\')
            );

            if (! class_exists($classe)) {
                continue;
            }

            $reflexion = new ReflectionClass($classe);

            if ($reflexion->isAbstract()) {
                continue;
            }

            $classes[] = $classe;
        }

        return $classes;
    }
}
