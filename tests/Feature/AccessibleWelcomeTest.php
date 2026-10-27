<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AccessibleWelcomeTest extends TestCase
{
    public function test_guest_help_does_not_embed_credentials_or_read_form_contents(): void
    {
        $html = Blade::render('<x-accessibility-help :guest="true" />');
        $this->assertStringContainsString('data-speak=', $html);
        $this->assertStringContainsString('Grand texte', $html);
        $this->assertStringContainsString('Ne lui communiquez pas votre mot de passe', $html);
        $this->assertStringContainsString('Arrêter la lecture', $html);
    }

    public function test_chorister_quick_actions_respect_module_permissions(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Choriste)->make());
        $html = Blade::render('<x-welcome-actions />');
        $this->assertStringContainsString('Retrouver un chant', $html);
        $this->assertStringContainsString('Voir les répétitions', $html);
        $this->assertStringNotContainsString('Recettes et dépenses', $html);
        $this->assertStringNotContainsString('Voir les courriers', $html);
    }

    public function test_administrator_has_illustrated_actions_with_separate_audio_controls(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->make());
        $html = Blade::render('<x-welcome-actions />');
        $this->assertStringContainsString('Recettes et dépenses', $html);
        $this->assertStringContainsString('Voir les instruments', $html);
        $this->assertSame(7, substr_count($html, 'data-speak='));
        $this->assertSame(7, substr_count($html, 'type="button"'));
    }
}
