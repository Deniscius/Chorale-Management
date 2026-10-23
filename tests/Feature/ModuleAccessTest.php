<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Index as ActivitiesIndex;
use App\Livewire\Correspondences\Index as CorrespondencesIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vérifie que chaque rôle n'accède qu'aux modules qui le concernent.
 */
class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les écrans applicatifs et le module attendu.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function moduleProvider(): array
    {
        return [
            'membres' => ['/membres', UserRole::MODULE_MEMBERS],
            'oeuvres' => ['/oeuvres', UserRole::MODULE_LITURGIQUE],
            'repetitions' => ['/repetitions', UserRole::MODULE_LITURGIQUE],
            'celebrations' => ['/celebrations', UserRole::MODULE_LITURGIQUE],
            'temps liturgiques' => ['/temps-liturgiques', UserRole::MODULE_LITURGIQUE],
            'rappels' => ['/rappels', UserRole::MODULE_LITURGIQUE],
            'instruments' => ['/instruments', UserRole::MODULE_INSTRUMENTS],
            'mouvements' => ['/instruments/mouvements', UserRole::MODULE_INSTRUMENTS],
            'maintenance' => ['/instruments/maintenance', UserRole::MODULE_INSTRUMENTS],
            'ensembles' => ['/instruments/ensembles', UserRole::MODULE_INSTRUMENTS],
            'courriers' => ['/courriers', UserRole::MODULE_COURRIERS],
            'activites' => ['/activites', UserRole::MODULE_ACTIVITES],
            'transactions' => ['/transactions', UserRole::MODULE_COMPTABILITE],
            'budgets' => ['/budgets', UserRole::MODULE_COMPTABILITE],
            'cotisations' => ['/cotisations', UserRole::MODULE_COMPTABILITE],
            'dons' => ['/dons', UserRole::MODULE_COMPTABILITE],
        ];
    }

    #[DataProvider('moduleProvider')]
    public function test_administrateur_accede_a_tous_les_modules(string $path): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($user)->get($path)->assertOk();
    }

    #[DataProvider('moduleProvider')]
    public function test_un_role_sans_droit_est_refuse(string $path, string $module): void
    {
        $role = collect(UserRole::cases())->first(
            fn (UserRole $candidate) => ! $candidate->canAccessModule($module)
                && ! in_array($candidate, [UserRole::Admin, UserRole::Directeur], true)
        );

        if ($role === null) {
            $this->markTestSkipped("Tous les rôles restreints ont accès au module {$module}.");
        }

        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get($path)->assertForbidden();
    }

    public function test_un_choriste_ne_peut_pas_gerer_les_comptes_utilisateurs(): void
    {
        $user = User::factory()->role(UserRole::Choriste)->create();

        $this->actingAs($user)->get('/utilisateurs')->assertForbidden();
    }

    public function test_un_choriste_peut_consulter_mais_pas_ecrire_dans_un_module(): void
    {
        $user = User::factory()->role(UserRole::Choriste)->create();

        // Le module « courriers » n'est pas visible pour un choriste.
        $this->actingAs($user)->get('/courriers')->assertForbidden();
    }

    public function test_un_secretaire_peut_creer_un_courrier(): void
    {
        $user = User::factory()->role(UserRole::Secretaire)->create();

        Livewire::actingAs($user)
            ->test(CorrespondencesIndex::class)
            ->call('create')
            ->assertSet('showForm', true);
    }

    public function test_un_choriste_ne_peut_pas_creer_dactivite(): void
    {
        $user = User::factory()->role(UserRole::Choriste)->create();

        // Le choriste consulte les activités mais n'a pas le droit d'écriture.
        Livewire::actingAs($user)
            ->test(ActivitiesIndex::class)
            ->call('create')
            ->assertForbidden();
    }
}
