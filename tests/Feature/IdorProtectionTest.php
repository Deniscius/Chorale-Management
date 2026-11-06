<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\Instrument;
use App\Models\LetterheadTemplate;
use App\Models\Member;
use App\Models\VoiceResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRegistration;
use App\Services\VoiceSpaceAccess;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class IdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true, 'tenancy.registration' => true]);
        Storage::fake('local');
    }

    public function test_direct_identifiers_cannot_cross_a_tenant_boundary(): void
    {
        [$tenantA, $adminA] = $this->space('alpha');
        [$tenantB, $adminB] = $this->space('beta');

        [$member, $instrument, $resource, $template, $commission] = app(TenantContext::class)->run($tenantB->id, function () use ($adminB): array {
            $member = Member::factory()->create(['photo_path' => 'tenants/2/members/private.png']);
            $instrument = Instrument::factory()->create(['image_path' => 'tenants/2/instruments/private.png']);
            $resource = VoiceResource::query()->create(['voice' => 'soprano', 'title' => 'Privé', 'audio_path' => 'tenants/2/voice-resources/private.mp3', 'uploaded_by' => $adminB->id]);
            $template = LetterheadTemplate::query()->create(['original_name' => 'private.pdf', 'original_path' => 'tenants/2/letterheads/private.pdf', 'status' => LetterheadTemplate::STATUS_READY, 'uploaded_by' => $adminB->id]);
            $commission = ChoirCommission::query()->create(['name' => 'Commission privée', 'name_key' => 'private-'.$adminB->id, 'kind' => 'autre', 'mission' => 'Privée', 'starts_on' => today(), 'leader_id' => $adminB->id]);

            return [$member, $instrument, $resource, $template, $commission];
        });

        $this->actingAs($adminA)->get('/membres/'.$member->id.'/photo')->assertNotFound();
        $this->actingAs($adminA)->get('/membres/'.$member->id.'/dossier')->assertNotFound();
        $this->actingAs($adminA)->get('/instruments/'.$instrument->id.'/image')->assertNotFound();
        $this->actingAs($adminA)->get('/mon-pupitre/'.$resource->id.'/audio')->assertNotFound();
        $this->actingAs($adminA)->get('/parametres/chorale/papier-entete/'.$template->id.'/original')->assertNotFound();
        $this->actingAs($adminA)->get('/rapports/commissions/'.$commission->id)->assertNotFound();

        $this->assertSame($tenantA->id, $adminA->tenant_id);
    }

    public function test_voice_access_service_refuses_a_foreign_resource_even_when_it_is_supplied_directly(): void
    {
        [$tenantA, $adminA] = $this->space('alpha');
        [$tenantB] = $this->space('beta');
        $resourceId = app(TenantContext::class)->run($tenantB->id, fn (): int => VoiceResource::query()->create([
            'voice' => 'soprano', 'title' => 'Privé', 'uploaded_by' => User::query()->firstOrFail()->id,
        ])->id);

        $foreign = VoiceResource::withoutGlobalScopes()->findOrFail($resourceId);
        app(TenantContext::class)->set($tenantA->id);

        try {
            app(VoiceSpaceAccess::class)->authorizeRead($adminA, $foreign);
            $this->fail('A foreign resource must never be authorized.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        } finally {
            app(TenantContext::class)->set(null);
        }
    }

    /** @return array{Tenant, User} */
    private function space(string $suffix): array
    {
        $user = app(TenantRegistration::class)->create([
            'organization' => 'Chorale '.$suffix,
            'name' => 'Responsable '.$suffix,
            'username' => $suffix,
            'email' => $suffix.'@example.test',
            'password' => 'SecretChorale2026',
        ]);
        $tenant = Tenant::findOrFail($user->tenant_id);
        app(TenantContext::class)->run($tenant->id, fn () => $user->forceFill([
            'email_verified_at' => now(), 'role' => UserRole::Admin,
        ])->save());

        return [$tenant, $user->fresh()];
    }
}
