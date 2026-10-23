<?php

namespace App\View\Composers;

use App\Services\ChoraleIdentity;
use Illuminate\View\View;

/**
 * Injecte l'identité de la chorale dans les vues qui l'affichent.
 *
 * Évite d'appeler le service directement dans les templates et permet de
 * partager un objet unique entre la sidebar, la topbar et les layouts.
 */
class ChoraleIdentityComposer
{
    public function __construct(protected ChoraleIdentity $identity) {}

    public function compose(View $view): void
    {
        $view->with('chorale', $this->identity);
    }
}
