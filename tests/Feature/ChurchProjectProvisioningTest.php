<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ChurchProjectProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_one_time_church_project_invitation_provisions_the_linked_space(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.registration' => true,
            'services.church_project.enabled' => true,
            'services.church_project.url' => 'https://church-project.example',
        ]);
        $claim = [
            'choralePublicId' => '11111111-1111-4111-8111-111111111111',
            'choraleNom' => 'Chorale Sainte Cécile',
            'paroissePublicId' => '22222222-2222-4222-8222-222222222222',
            'paroisseNom' => 'Paroisse Saint Paul',
            'doyennePublicId' => '33333333-3333-4333-8333-333333333333',
            'doyenneNom' => 'Doyenné Nord',
        ];
        Http::fakeSequence()->push($claim)->push($claim);

        $this->get(route('spaces.create'))->assertOk()->assertSee('Invitation Church Project')->assertDontSee('Nom de votre chorale');
        $this->post(route('spaces.store'), [
            'invitation' => 'choir_'.str_repeat('a', 50),
            'name' => 'Responsable Test',
            'username' => 'responsable.test',
            'email' => 'responsable@example.test',
            'password' => 'ChoraleSecret2026',
            'password_confirmation' => 'ChoraleSecret2026',
            'terms' => '1',
            'website' => '',
        ])->assertRedirect(route('dashboard'));

        $tenant = Tenant::query()->where('church_project_chorale_id', $claim['choralePublicId'])->firstOrFail();
        $this->assertSame('Chorale Sainte Cécile', $tenant->name);
        $this->assertSame($claim['paroissePublicId'], $tenant->church_project_paroisse_id);
        $this->assertSame($claim['doyennePublicId'], $tenant->church_project_doyenne_id);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/invitations/consume'));
    }

    public function test_an_invalid_invitation_does_not_create_a_space(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.registration' => true,
            'services.church_project.enabled' => true,
            'services.church_project.url' => 'https://church-project.example',
        ]);
        Http::fake(['*' => Http::response(['message' => 'Invitation invalide'], 404)]);
        $before = Tenant::count();

        $this->post(route('spaces.store'), [
            'invitation' => 'choir_'.str_repeat('x', 50),
            'name' => 'Responsable Test',
            'username' => 'responsable.test',
            'email' => 'responsable@example.test',
            'password' => 'ChoraleSecret2026',
            'password_confirmation' => 'ChoraleSecret2026',
            'terms' => '1',
            'website' => '',
        ])->assertSessionHasErrors('invitation');

        $this->assertSame($before, Tenant::count());
        $this->assertGuest();
    }
}
