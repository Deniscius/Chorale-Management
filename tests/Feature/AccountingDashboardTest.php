<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Accounting\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_accounting_summary(): void
    {
        $this->get('/comptabilite/synthese')->assertRedirect();
    }

    public function test_administrator_can_view_accounting_summary_without_mutating_data(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get('/comptabilite/synthese')->assertOk()
            ->assertSee('Synthèse comptable')
            ->assertSee('Cotisations clôturées attendues');

        $figures = Livewire::actingAs($admin)->test(Dashboard::class)->get('figures');
        $this->assertArrayHasKey('income', $figures);
        $this->assertArrayHasKey('arrears', $figures);
        $this->assertArrayHasKey('donation_declared', $figures);
    }
}
