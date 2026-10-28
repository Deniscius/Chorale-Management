<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\FlashNotifications;
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

    public function test_les_messages_apres_redirection_saffichent_dans_une_notification_popup(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->withSession(['status' => 'profile-updated']);
        $this->assertSame(
            'Vos informations personnelles ont été enregistrées.',
            app(FlashNotifications::class)->all()[0]['message'],
        );

        $this
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notification-popup', false);
    }

    public function test_les_notifications_derreur_utilisent_aussi_un_popup(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->withSession(['billing_error' => 'Le fournisseur est momentanément indisponible.']);
        $notification = app(FlashNotifications::class)->all()[0];
        $this->assertSame('error', $notification['type']);
        $this->assertSame('Le fournisseur est momentanément indisponible.', $notification['message']);

        $this
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notification-popup', false);
    }

    public function test_le_portail_proprietaire_affiche_aussi_les_notifications_en_popup(): void
    {
        $this->withSession(['status' => 'Votre espace propriétaire est prêt.'])
            ->get(route('platform.login'))
            ->assertOk()
            ->assertSee('data-notification-popup', false)
            ->assertSee('Votre espace propriétaire est prêt.');
    }
}
