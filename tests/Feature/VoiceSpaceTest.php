<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use App\Models\VoiceResource;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VoiceSpaceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_a_chorister_sees_only_resources_for_their_voice(): void
    {
        $soprano = $this->userForVoice('soprano');
        $alto = $this->userForVoice('alto');
        $sopranoResource = VoiceResource::create(['voice' => 'soprano', 'title' => 'Gloria soprano', 'lyrics' => 'Voix haute', 'uploaded_by' => $soprano->id]);
        VoiceResource::create(['voice' => 'alto', 'title' => 'Gloria alto', 'lyrics' => 'Voix alto', 'uploaded_by' => $alto->id]);

        $this->actingAs($soprano)->get(route('voice-space.index'))
            ->assertOk()->assertSee('Gloria soprano')->assertDontSee('Gloria alto');
        $this->actingAs($soprano)->get(route('voice-space.index', ['voice' => 'alto']))->assertForbidden();
        $this->actingAs($alto)->get(route('voice-space.update', $sopranoResource))->assertMethodNotAllowed();
    }

    public function test_a_chorister_can_add_lyrics_and_private_audio_to_their_voice(): void
    {
        Storage::fake('local');
        $soprano = $this->userForVoice('soprano');
        $audio = UploadedFile::fake()->create('gloria.mp3', 120, 'audio/mpeg');

        $this->actingAs($soprano)->post(route('voice-space.store'), [
            'voice' => 'soprano', 'title' => 'Gloria', 'lyrics' => "Gloire à Dieu\nAu plus haut des cieux", 'audio' => $audio,
        ])->assertRedirect(route('voice-space.index', ['voice' => 'soprano']));

        $resource = VoiceResource::sole();
        $this->assertSame('soprano', $resource->voice->value);
        $this->assertSame('gloria.mp3', $resource->audio_original_name);
        Storage::disk('local')->assertExists($resource->audio_path);
        Storage::disk('local')->put($resource->audio_path, str_repeat('a', 100));
        $response = $this->actingAs($soprano)->get(route('voice-space.audio', $resource))->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->actingAs($soprano)->withHeader('Range', 'bytes=0-9')->get(route('voice-space.audio', $resource))
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-9/100');
    }

    public function test_another_voice_cannot_read_audio_or_modify_a_resource(): void
    {
        Storage::fake('local');
        $soprano = $this->userForVoice('soprano');
        $bass = $this->userForVoice('bass');
        $path = app(TenantContext::class)->path('voice-resources/soprano/secret.mp3');
        app(PrivateFiles::class)->storeContents($path, 'private audio', 'audio/mpeg');
        $resource = VoiceResource::create([
            'voice' => 'soprano', 'title' => 'Secret soprano', 'audio_path' => $path,
            'audio_original_name' => 'secret.mp3', 'audio_mime' => 'audio/mpeg', 'audio_size' => 13, 'uploaded_by' => $soprano->id,
        ]);

        $this->actingAs($bass)->get(route('voice-space.audio', $resource))->assertForbidden();
        $this->actingAs($bass)->patch(route('voice-space.update', $resource), ['title' => 'Intrusion', 'lyrics' => 'Texte'])->assertForbidden();
        $this->assertSame('Secret soprano', $resource->fresh()->title);
    }

    public function test_a_liturgical_manager_can_manage_each_voice(): void
    {
        $manager = User::factory()->role(UserRole::CommissionLiturgique)->create();

        $this->actingAs($manager)->post(route('voice-space.store'), [
            'voice' => 'bass', 'title' => 'Entrée des basses', 'lyrics' => 'Mesure douze.',
        ])->assertRedirect(route('voice-space.index', ['voice' => 'bass']));

        $this->actingAs($manager)->get(route('voice-space.index', ['voice' => 'bass']))
            ->assertOk()->assertSee('Entrée des basses');
    }

    public function test_a_chorister_can_upload_replace_and_remove_their_private_identity_photo(): void
    {
        Storage::fake('local');
        $user = $this->userForVoice('tenor');

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->createWithContent('portrait.png', base64_decode(self::PNG)),
        ])->assertRedirect(route('profile.edit'));

        $path = $user->member->fresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $response = $this->actingAs($user)->get(route('profile.photo.show'))->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertRedirect(route('profile.edit'));
        $this->assertNull($user->member->fresh()->photo_path);
        Storage::disk('local')->assertMissing($path);
    }

    private function userForVoice(string $voice): User
    {
        $member = Member::factory()->voice($voice)->create();

        return User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
    }
}
