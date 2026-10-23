<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Members\Access;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Attribution du rôle d'accès de chaque choriste par l'administrateur.
 */
class MemberAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_cree_un_compte_pour_un_choriste(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Kouassi',
            'email' => 'jean.kouassi@exemple.ci',
        ]);

        Livewire::actingAs($admin)
            ->test(Access::class, ['member' => $member])
            ->set('role', UserRole::Secretaire->value)
            ->set('username', 'jean.kouassi')
            ->set('email', 'jean.kouassi@exemple.ci')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::firstWhere('member_id', $member->id);

        $this->assertNotNull($user);
        $this->assertSame('jean.kouassi', $user->username);
        $this->assertSame(UserRole::Secretaire, $user->role);
        $this->assertSame('Jean Kouassi', $user->name);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_un_non_administrateur_ne_peut_pas_attribuer_de_role(): void
    {
        $user = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->create();

        Livewire::actingAs($user)
            ->test(Access::class, ['member' => $member])
            ->assertForbidden();
    }

    public function test_le_role_dun_compte_existant_est_modifie_sans_toucher_au_mot_de_passe(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();

        $user = User::factory()->create([
            'member_id' => $member->id,
            'name' => $member->full_name,
            'username' => 'membre.existant',
            'role' => UserRole::Choriste->value,
            'password' => 'ancien-secret',
        ]);

        $hash = $user->password;

        Livewire::actingAs($admin)
            ->test(Access::class, ['member' => $member])
            ->set('role', UserRole::Secretaire->value)
            ->set('username', 'membre.existant')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame(UserRole::Secretaire, $user->role);
        $this->assertSame($hash, $user->password);
        $this->assertTrue(Hash::check('ancien-secret', $user->password));
    }

    public function test_un_identifiant_deja_utilise_est_refuse(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();

        User::factory()->create(['username' => 'deja_pris']);

        Livewire::actingAs($admin)
            ->test(Access::class, ['member' => $member])
            ->set('role', UserRole::Choriste->value)
            ->set('username', 'deja_pris')
            ->call('save')
            ->assertHasErrors('username');

        $this->assertDatabaseMissing('users', ['member_id' => $member->id]);
    }

    public function test_un_identifiant_avec_point_est_accepte(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();

        Livewire::actingAs($admin)
            ->test(Access::class, ['member' => $member])
            ->set('role', UserRole::Choriste->value)
            ->set('username', 'prenom.nom')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'member_id' => $member->id,
            'username' => 'prenom.nom',
        ]);
    }

    public function test_le_choriste_peut_se_connecter_avec_son_identifiant(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create(['email' => 'nouveau.choriste@exemple.ci']);

        Livewire::actingAs($admin)
            ->test(Access::class, ['member' => $member])
            ->set('role', UserRole::Choriste->value)
            ->set('username', 'nouveau.choriste')
            ->set('email', 'nouveau.choriste@exemple.ci')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors();

        auth()->logout();

        $this->post('/login', [
            'login' => 'nouveau.choriste',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
