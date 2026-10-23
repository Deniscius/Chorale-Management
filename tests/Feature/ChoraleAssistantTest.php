<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Rehearsals\Index as RehearsalsIndex;
use App\Models\Rehearsal;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\NullAiClient;
use App\Services\ChoraleAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Double de test : renvoie un texte fixe et mémorise la consigne reçue.
 */
class FakeAiClient implements AiClient
{
    public string $lastSystem = '';

    public string $lastPrompt = '';

    public function __construct(private readonly string $reply = 'Compte rendu de test.') {}

    public function complete(string $system, string $prompt): string
    {
        $this->lastSystem = $system;
        $this->lastPrompt = $prompt;

        return $this->reply;
    }
}

/**
 * Assistant IA : génération, garde-fous et relecture humaine.
 */
class ChoraleAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function assistantWith(AiClient $client): ChoraleAssistant
    {
        return new ChoraleAssistant($client);
    }

    public function test_le_prompt_contient_le_contexte_de_la_seance(): void
    {
        $fake = new FakeAiClient;
        $assistant = $this->assistantWith($fake);

        $rehearsal = Rehearsal::factory()->create([
            'date' => today(),
            'venue' => 'Salle paroissiale',
            'notes' => 'Travail sur les nuances.',
        ]);

        $report = $assistant->generateRehearsalReport($rehearsal);

        $this->assertSame('Compte rendu de test.', $report);
        $this->assertStringContainsString('Salle paroissiale', $fake->lastPrompt);
        $this->assertStringContainsString('Travail sur les nuances.', $fake->lastPrompt);
        $this->assertStringContainsString('français', $fake->lastSystem);
    }

    public function test_le_compte_rendu_est_enregistre_sur_la_repetition(): void
    {
        $assistant = $this->assistantWith(new FakeAiClient('Résumé de la séance.'));
        $rehearsal = Rehearsal::factory()->create(['date' => today()]);

        $assistant->storeRehearsalReport($rehearsal);

        $rehearsal->refresh();

        $this->assertSame('Résumé de la séance.', $rehearsal->ai_report);
        $this->assertNotNull($rehearsal->ai_report_generated_at);
    }

    public function test_aucune_donnee_financiere_nest_transmise(): void
    {
        $fake = new FakeAiClient;
        $this->assistantWith($fake)->generateRehearsalReport(
            Rehearsal::factory()->create(['date' => today(), 'notes' => 'Note simple'])
        );

        $this->assertStringNotContainsString('cotisation', mb_strtolower($fake->lastPrompt));
        $this->assertStringNotContainsString('transaction', mb_strtolower($fake->lastPrompt));
    }

    public function test_le_client_inactif_refuse_de_generer(): void
    {
        $this->expectException(RuntimeException::class);

        $this->assistantWith(new NullAiClient)->generateRehearsalReport(
            Rehearsal::factory()->create(['date' => today()])
        );
    }

    public function test_le_bouton_napparait_pas_quand_lia_est_desactivee(): void
    {
        config()->set('ai.enabled', false);

        $user = User::factory()->role(UserRole::CommissionLiturgique)->create();

        Livewire::actingAs($user)
            ->test(RehearsalsIndex::class)
            ->assertSet('aiEnabled', false)
            ->assertDontSee('Compte rendu assisté');
    }

    public function test_laction_de_generation_est_reservee_aux_roles_autorises(): void
    {
        config()->set('ai.enabled', true);

        // Le rôle « instruments » n'a pas accès au module liturgique.
        $user = User::factory()->role(UserRole::CommissionInstruments)->create();

        Livewire::actingAs($user)
            ->test(RehearsalsIndex::class)
            ->assertForbidden();
    }

    public function test_un_responsable_autorise_genere_puis_enregistre_un_compte_rendu(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.roles', [UserRole::CommissionLiturgique->value]);

        $this->app->instance(AiClient::class, new FakeAiClient('Proposition IA à relire.'));

        $rehearsal = Rehearsal::factory()->create(['date' => today(), 'status' => 'terminee']);
        $user = User::factory()->role(UserRole::CommissionLiturgique)->create();

        Livewire::actingAs($user)
            ->test(RehearsalsIndex::class)
            ->call('openReport', $rehearsal->getKey())
            ->assertSet('reportId', $rehearsal->getKey())
            ->call('generateReport')
            ->assertSet('reportDraft', 'Proposition IA à relire.')
            ->set('reportDraft', 'Texte relu et corrigé.')
            ->call('saveReport')
            ->assertSet('reportId', null);

        $this->assertSame('Texte relu et corrigé.', $rehearsal->refresh()->ai_report);
    }

    public function test_le_texte_est_modifiable_avant_enregistrement(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.roles', [UserRole::CommissionLiturgique->value]);

        $this->app->instance(AiClient::class, new FakeAiClient('Brouillon.'));

        $rehearsal = Rehearsal::factory()->create(['date' => today()]);
        $user = User::factory()->role(UserRole::CommissionLiturgique)->create();

        Livewire::actingAs($user)
            ->test(RehearsalsIndex::class)
            ->call('openReport', $rehearsal->getKey())
            ->call('generateReport')
            ->set('reportDraft', '')
            ->call('saveReport');

        $this->assertNull($rehearsal->refresh()->ai_report);
    }

    public function test_le_service_est_lie_au_client_inactif_par_defaut(): void
    {
        config()->set('ai.enabled', false);

        $this->assertInstanceOf(NullAiClient::class, app(AiClient::class));
    }
}
