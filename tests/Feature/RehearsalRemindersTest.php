<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\UserRole;
use App\Livewire\Reminders\History;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rappels de répétition : sélection, envoi, idempotence, préférences.
 */
class RehearsalRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function rehearsalInTwoDays(): Rehearsal
    {
        return Rehearsal::factory()->create([
            'date' => today()->addDays(2),
            'status' => 'planifiee',
        ]);
    }

    private function activeMember(array $attributes = []): Member
    {
        return Member::factory()->active()->create(array_merge([
            'email' => 'choriste@exemple.ci',
            'notify_email' => true,
            'phone' => '+225 07 00 00 00 00',
            'notify_sms' => false,
        ], $attributes));
    }

    public function test_les_rappels_partent_par_email_aux_choristes_actifs(): void
    {
        Mail::fake();

        $rehearsal = $this->rehearsalInTwoDays();
        $member = $this->activeMember();

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertSentCount(1);

        $this->assertDatabaseHas('reminders', [
            'rehearsal_id' => $rehearsal->getKey(),
            'member_id' => $member->getKey(),
            'channel' => ReminderChannel::Mail->value,
            'recipient' => 'choriste@exemple.ci',
            'status' => ReminderStatus::Envoye->value,
        ]);
    }

    public function test_un_choriste_inactif_ne_recoit_aucun_rappel(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();
        Member::factory()->inactive()->create([
            'email' => 'inactif@exemple.ci',
            'notify_email' => true,
        ]);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_le_rappel_nest_envoye_quune_seule_fois(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();
        $this->activeMember();

        $this->artisan('chorale:send-reminders')->assertSuccessful();
        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('reminders', 1);
    }

    public function test_les_preferences_de_notification_sont_respectees(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();

        // Refuse l'e-mail : aucun rappel ne doit être journalisé.
        $this->activeMember(['email' => 'sans-mail@exemple.ci', 'notify_email' => false]);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_un_email_absent_annule_le_canal_email(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();
        $this->activeMember(['email' => null, 'notify_email' => true]);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_le_sms_nest_utilise_que_sil_est_active_globalement(): void
    {
        Mail::fake();
        config()->set('chorale.reminders.sms_enabled', false);

        $this->rehearsalInTwoDays();
        $this->activeMember([
            'notify_email' => false,
            'notify_sms' => true,
            'phone' => '+225 07 11 22 33 44',
        ]);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_le_sms_part_quand_le_canal_est_active(): void
    {
        Mail::fake();
        config()->set('chorale.reminders.sms_enabled', true);

        $rehearsal = $this->rehearsalInTwoDays();
        $member = $this->activeMember([
            'notify_email' => false,
            'notify_sms' => true,
            'phone' => '+225 07 11 22 33 44',
        ]);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        $this->assertDatabaseHas('reminders', [
            'rehearsal_id' => $rehearsal->getKey(),
            'member_id' => $member->getKey(),
            'channel' => ReminderChannel::Sms->value,
            'status' => ReminderStatus::Envoye->value,
        ]);
    }

    public function test_la_simulation_nenvoie_rien(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();
        $this->activeMember();

        $this->artisan('chorale:send-reminders', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_une_repetition_hors_delai_est_ignoree(): void
    {
        Mail::fake();

        Rehearsal::factory()->create([
            'date' => today()->addDays(10),
            'status' => 'planifiee',
        ]);
        $this->activeMember();

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_les_rappels_sont_desactives_par_configuration(): void
    {
        Mail::fake();
        config()->set('chorale.reminders.enabled', false);

        $this->rehearsalInTwoDays();
        $this->activeMember();

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_le_journal_des_rappels_est_reserve_au_module_liturgique(): void
    {
        $choriste = User::factory()->role(UserRole::CommissionVieCulturelle)->create();

        // La commission culturelle n'a pas accès au module liturgique.
        $this->actingAs($choriste)->get('/rappels')->assertForbidden();
    }

    public function test_un_responsable_consulte_le_journal(): void
    {
        $user = User::factory()->role(UserRole::CommissionLiturgique)->create();

        Reminder::create([
            'rehearsal_id' => $this->rehearsalInTwoDays()->getKey(),
            'member_id' => $this->activeMember()->getKey(),
            'channel' => ReminderChannel::Mail,
            'recipient' => 'choriste@exemple.ci',
            'subject' => 'Rappel',
            'body' => 'Contenu',
            'status' => ReminderStatus::Envoye,
            'sent_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(History::class)
            ->assertOk()
            ->assertSee('choriste@exemple.ci')
            ->assertSee('1 envoi');
    }

    public function test_le_message_contient_le_lieu_et_lhoraire(): void
    {
        $rehearsal = Rehearsal::factory()->create([
            'date' => today()->addDays(2),
            'venue' => 'Salle paroissiale',
            'starts_at' => '18:00',
            'ends_at' => '20:00',
        ]);

        $dispatcher = app(ReminderDispatcher::class);
        $body = $dispatcher->body($rehearsal, ReminderChannel::Mail);

        $this->assertStringContainsString('Salle paroissiale', $body);
        $this->assertStringContainsString('18:00', $body);
    }

    public function test_le_sms_respecte_la_longueur_maximale(): void
    {
        $rehearsal = Rehearsal::factory()->create([
            'date' => today()->addDays(2),
            'venue' => str_repeat('Très longue salle paroissiale ', 10),
        ]);

        $dispatcher = app(ReminderDispatcher::class);
        $body = $dispatcher->body($rehearsal, ReminderChannel::Sms);

        $this->assertLessThanOrEqual(160, mb_strlen($body));
    }

    public function test_un_choriste_actif_sans_coordonnee_ne_bloque_pas_les_autres(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();

        Member::factory()->active()->create([
            'email' => null,
            'phone' => null,
            'notify_email' => true,
            'notify_sms' => true,
        ]);

        $this->activeMember(['email' => 'joignable@exemple.ci']);

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_les_choristes_non_actifs_sont_exclus_quel_que_soit_le_statut(): void
    {
        Mail::fake();

        $this->rehearsalInTwoDays();

        foreach ([MemberStatus::Inactif, MemberStatus::Invite, MemberStatus::Honoraire, MemberStatus::Externe] as $status) {
            Member::factory()->create([
                'status' => $status->value,
                'email' => 'membre.'.$status->value.'@exemple.ci',
                'notify_email' => true,
            ]);
        }

        $this->artisan('chorale:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_la_commande_cible_une_repetition_precise(): void
    {
        Mail::fake();

        $rehearsal = Rehearsal::factory()->create([
            'date' => today()->addDays(30),
            'status' => 'planifiee',
        ]);
        $this->activeMember();

        $this->artisan('chorale:send-reminders', ['--rehearsal' => $rehearsal->getKey()])
            ->assertSuccessful();

        Mail::assertSentCount(1);
    }
}
