<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\MemberPhotoManager;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MemberPhotoController extends Controller
{
    public function store(Request $request, MemberPhotoManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
        ], [], ['photo' => 'photo d’identité']);
        $manager->save($request->user(), $validated['photo']);

        return redirect()->route('profile.edit')->with('success', 'Votre photo d’identité a été enregistrée.');
    }

    public function show(Request $request, PrivateFiles $files): BinaryFileResponse|StreamedResponse
    {
        $member = $request->user()->loadMissing('member')->member;
        abort_unless($member, 404);

        return $this->photoResponse($member, $files);
    }

    public function member(Member $member, PrivateFiles $files): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view-member-dossier', $member);

        return $this->photoResponse($member, $files);
    }

    private function photoResponse(Member $member, PrivateFiles $files): BinaryFileResponse|StreamedResponse
    {
        abort_unless($member->photo_path, 404);
        $extension = strtolower(pathinfo($member->photo_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            default => abort(415),
        };

        return $files->response($member->photo_path, 'photo-'.$member->id.'.'.$extension, true, $mime, [
            'Cache-Control' => 'private, no-store', 'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function destroy(Request $request, MemberPhotoManager $manager): RedirectResponse
    {
        $manager->delete($request->user());

        return redirect()->route('profile.edit')->with('success', 'Votre photo d’identité a été supprimée.');
    }
}
