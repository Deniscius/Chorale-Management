<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StyledHttpErrorsTest extends TestCase
{
    public function test_not_found_page_is_helpful_and_styled(): void
    {
        $this->get('/adresse-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Erreur 404')
            ->assertSee('Page introuvable')
            ->assertSee('Revenir à l’accueil');
    }

    public function test_server_error_uses_a_safe_message(): void
    {
        Route::get('/_test/erreur-serveur', fn () => abort(500, 'Détail interne confidentiel'));

        $this->get('/_test/erreur-serveur')
            ->assertStatus(500)
            ->assertSee('Un imprévu est survenu')
            ->assertDontSee('Détail interne confidentiel');
    }
}
