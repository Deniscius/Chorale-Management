<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Settings\Chorale;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChoraleIdentity;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Identité de la chorale : informations, logo et cache des paramètres.
 */
class ChoraleSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PNG transparent 1×1 valide (aucune dépendance à l'extension GD).
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_administrateur_peut_ouvrir_les_parametres_de_la_chorale(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($user)
            ->get('/parametres/chorale')
            ->assertOk();
    }

    public function test_un_role_non_administrateur_ne_peut_pas_modifier_lidentite(): void
    {
        $user = User::factory()->role(UserRole::Directeur)->create();

        $this->actingAs($user)
            ->get('/parametres/chorale')
            ->assertForbidden();
    }

    public function test_les_informations_enregistrees_alimentent_lidentite_affichee(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($user)
            ->test(Chorale::class)
            ->set('name', 'Chorale Sainte-Cécile')
            ->set('tagline', 'Chanter pour servir')
            ->set('email', 'contact@chorale.test')
            ->set('phone', '+225 07 00 00 00 00')
            ->set('address', 'Abidjan, Cocody')
            ->set('website', 'https://chorale.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => ChoraleIdentity::KEY_NAME,
            'value' => 'Chorale Sainte-Cécile',
        ]);

        $identity = app(ChoraleIdentity::class);

        $this->assertSame('Chorale Sainte-Cécile', $identity->name());
        $this->assertSame('Chanter pour servir', $identity->tagline());
        $this->assertSame('contact@chorale.test', $identity->email());
        $this->assertSame('https://chorale.test', $identity->website());
    }

    public function test_le_logo_est_stocke_sur_le_disque_prive_et_versionne(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($user)
            ->test(Chorale::class)
            ->set('name', 'Chorale Sainte-Cécile')
            ->set('logo', UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG)))
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $identity = app(ChoraleIdentity::class);
        $identity->forget();

        $this->assertTrue($identity->hasLogo());
        $this->assertStringContainsString('?v=', (string) $identity->logoUrl());
    }

    public function test_le_logo_est_servi_uniquement_en_session_sans_cache_partage(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($user)
            ->test(Chorale::class)
            ->set('name', 'Chorale Sainte-Cécile')
            ->set('logo', UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG)))
            ->call('save');

        $identity = app(ChoraleIdentity::class);
        $identity->forget();

        // Le logo appartient à la session de cette chorale, jamais à la page de connexion.
        $response = $this->get($identity->logoUrl());

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertContains('Cookie', $response->getVary());
        $this->assertStringContainsString($identity->logoVersion(), (string) $response->headers->get('ETag'));
    }

    public function test_le_remplacement_du_logo_supprime_lancien_fichier(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Admin)->create();

        $component = Livewire::actingAs($user)->test(Chorale::class)
            ->set('name', 'Chorale Sainte-Cécile')
            ->set('logo', UploadedFile::fake()->createWithContent('premier.png', base64_decode(self::PNG)))
            ->call('save');

        $first = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);

        $component
            ->set('logo', UploadedFile::fake()->createWithContent('second.png', base64_decode(self::PNG)))
            ->call('save');

        $second = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_le_logo_peut_etre_supprime(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Admin)->create();

        $component = Livewire::actingAs($user)->test(Chorale::class)
            ->set('name', 'Chorale Sainte-Cécile')
            ->set('logo', UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG)))
            ->call('save');

        $path = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);

        $component->call('removeLogo');

        $this->assertNull(Setting::get(ChoraleIdentity::KEY_LOGO_PATH));
        Storage::disk('local')->assertMissing($path);

        $identity = app(ChoraleIdentity::class);
        $identity->forget();
        $this->assertFalse($identity->hasLogo());
    }

    public function test_les_parametres_ne_sont_lus_quune_fois_par_requete(): void
    {
        Setting::set('test.a', 'Valeur A');
        Setting::set('test.b', 'Valeur B');

        app(SettingsStore::class)->flush();
        Cache::flush();

        DB::enableQueryLog();

        $this->assertSame('Valeur A', Setting::get('test.a'));
        $this->assertSame('Valeur B', Setting::get('test.b'));

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], '"settings"'));
        DB::disableQueryLog();

        // Une seule lecture de la table, quel que soit le nombre de paramètres.
        $this->assertCount(1, $queries);
    }

    public function test_une_ecriture_invalide_immediatement_la_memoire(): void
    {
        Setting::set('test.a', 'Avant');
        $this->assertSame('Avant', Setting::get('test.a'));

        Setting::set('test.a', 'Après');

        $this->assertSame('Après', Setting::get('test.a'));
    }
}
