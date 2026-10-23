<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie la coque applicative commune à tous les écrans.
 */
class AppShellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Livewire doit être chargé même sur un écran sans composant : il fournit
     * Alpine, utilisé par le menu latéral et les infobulles de la coque.
     */
    public function test_livewire_est_charge_sur_un_ecran_sans_composant(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('livewire.js', false);
    }

    public function test_la_coque_affiche_le_nom_de_lapplication_en_francais(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Gestion Chorale');
    }
}
