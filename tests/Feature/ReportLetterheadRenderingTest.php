<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ChoirCommission;
use App\Models\LetterheadTemplate;
use App\Models\Rehearsal;
use App\Models\User;
use App\Services\Letterhead;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Support\ReportHeaderFixture;
use Tests\TestCase;

final class ReportLetterheadRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_report_types_embed_the_same_undistorted_header_in_portrait_and_landscape(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);
        $path = app(TenantContext::class)->path('letterheads/fixture/header.png');
        app(PrivateFiles::class)->storeContents($path, ReportHeaderFixture::png(), 'image/png');
        LetterheadTemplate::query()->create(['original_name' => 'fixture.pdf', 'header_path' => $path,
            'header_height_mm' => 45, 'status' => 'active', 'activated_at' => now(), 'uploaded_by' => $admin->id]);
        $activity = Activity::factory()->create(['attendance_finalized_at' => now()]);
        $rehearsal = Rehearsal::factory()->create(['attendance_finalized_at' => now()]);
        $commission = ChoirCommission::query()->create(['name' => 'Commission test', 'name_key' => 'commission-test', 'kind' => 'permanente',
            'mission' => 'Préparer les activités', 'starts_on' => today()->subMonth(), 'module_key' => 'activites', 'leader_id' => $admin->id]);
        $urls = [
            route('activities.detail-report', $activity),
            route('activities.detail-report', [$activity, 'orientation' => 'portrait']),
            route('attendance.report', ['activite', $activity->id, 'document' => 'fiche']),
            route('attendance.report', ['repetition', $rehearsal->id]),
            route('activities.report', ['orientation' => 'landscape']),
            route('activities.report', ['orientation' => 'portrait']),
            route('members.report'), route('reports.commission', $commission), route('settings.letterheads.sample'),
        ];
        foreach ($urls as $url) {
            $response = $this->get($url)->assertOk()->assertSee('class="document-letterhead"', false)
                ->assertSee('data:image/png;base64,', false)->assertSee('height:auto', false)
                ->assertSee('object-fit:contain', false)->assertDontSee('object-fit:fill', false);
            $this->assertEquals(210.0, $response->viewData('headerWidthMm'), $url);
        }
    }

    public function test_an_unavailable_header_uses_the_complete_choir_identity_instead_of_a_broken_image(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        LetterheadTemplate::query()->create(['original_name' => 'absent.pdf', 'header_path' => 'letterheads/absent.png',
            'status' => 'active', 'header_height_mm' => 45, 'activated_at' => now(), 'uploaded_by' => $admin->id]);
        $this->actingAs($admin)->get(route('settings.letterheads.sample'))->assertOk()
            ->assertSee('Gestion Chorale')->assertDontSee('class="document-letterhead"', false);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_large_valid_header_images_are_not_silently_dropped_at_the_old_five_megabyte_limit(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $path = app(TenantContext::class)->path('letterheads/large/header.png');
        $png = ReportHeaderFixture::png();
        // An ancillary chunk keeps valid PNG geometry while exceeding the old embedding limit.
        $data = 'Comment'."\0".str_repeat('x', 5 * 1024 * 1024);
        $chunk = pack('N', strlen($data)).'tEXt'.$data.hash('crc32b', 'tEXt'.$data, true);
        $png = substr($png, 0, -12).$chunk.substr($png, -12);
        unset($data, $chunk);
        app(PrivateFiles::class)->storeContents($path, $png, 'image/png');
        unset($png);
        LetterheadTemplate::query()->create(['original_name' => 'large.pdf', 'header_path' => $path,
            'status' => 'active', 'header_height_mm' => 45, 'activated_at' => now(), 'uploaded_by' => $admin->id]);
        $presentation = app(Letterhead::class)->viewData();
        $this->assertNotNull($presentation['header']);
        $this->assertEquals(210, $presentation['headerWidthMm']);
    }
}
