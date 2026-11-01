<?php

namespace Tests\Feature;

use App\Livewire\Activities\Index as ActivityIndex;
use App\Livewire\Activities\Participants;
use App\Models\Activity;
use App\Models\ManagementYear;
use App\Models\Member;
use App\Services\ManagementYearContext;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class YearContextAndBulkParticipantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_pastoral_year_filters_activity_rows_and_statistics(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale Années',
            'name' => 'Responsable',
            'username' => 'years-admin',
            'email' => 'years-admin@example.test',
            'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function (): void {
            $year = ManagementYear::create([
                'type' => 'pastorale',
                'label' => '2026-2027',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-08-31',
                'theme' => 'Grandir ensemble',
                'is_active' => true,
            ]);
            $inside = Activity::factory()->create(['starts_at' => '2027-04-03 16:00:00']);
            Activity::factory()->create(['starts_at' => '2026-06-03 16:00:00']);
            app(ManagementYearContext::class)->choose($year->id);

            $screen = new ActivityIndex;
            $this->assertSame(1, $screen->stats()['total']);
            $this->assertSame($inside->id, $screen->activities()->items()[0]->id);
        });
    }

    public function test_multiple_participants_can_be_invited_without_overwriting_existing_records(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale Participants',
            'name' => 'Responsable',
            'username' => 'participant-admin',
            'email' => 'participant-admin@example.test',
            'password' => 'SecretChorale2026',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $activity = Activity::factory()->create();
            $first = Member::factory()->active()->create();
            $second = Member::factory()->active()->create();

            Livewire::actingAs($admin)->test(Participants::class, ['activity' => $activity])
                ->set('selectedMemberIds', [(string) $first->id, (string) $second->id])
                ->call('addSelected')
                ->assertHasNoErrors();

            $this->assertSame(2, $activity->participants()->count());
            $this->assertSame('invite', $activity->participants()->first()->pivot->status);

            Livewire::actingAs($admin)->test(Participants::class, ['activity' => $activity])
                ->set('selectedMemberIds', [(string) $first->id])
                ->call('addSelected')
                ->assertHasNoErrors();

            $this->assertSame(2, $activity->participants()->count());
        });
    }
}
