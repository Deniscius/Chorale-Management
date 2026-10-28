<?php

namespace App\Http\Controllers;

use App\Services\LetterheadManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LetterheadUploadController
{
    public function __invoke(Request $request, LetterheadManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:docx,pdf', 'max:10240'],
            'header_height_mm' => ['required', 'integer', 'min:20', 'max:100'],
        ], [], [
            'document' => 'document Word ou PDF',
            'header_height_mm' => 'hauteur de l’en-tête',
        ]);

        $manager->upload($validated['document'], (int) $validated['header_height_mm'], $request->user());

        return redirect(route('settings.chorale').'#papier-entete')
            ->with('letterhead_success', 'Document reçu. La préparation de l’aperçu a démarré.');
    }
}
