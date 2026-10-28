<?php

namespace App\Http\Controllers;

use App\Enums\Voice;
use App\Models\VoiceResource;
use App\Rules\ReadableText;
use App\Services\VoiceResourceWriter;
use App\Services\VoiceSpaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class VoiceSpaceController extends Controller
{
    public function index(Request $request, VoiceSpaceAccess $access): View
    {
        $voice = $access->selectedVoice($request->user(), $request->query('voice'));

        return view('voice-space.index', [
            'voice' => $voice,
            'voices' => Voice::cases(),
            'isManager' => $access->isManager($request->user()),
            'resources' => $voice
                ? VoiceResource::query()->where('voice', $voice->value)->with('uploader:id,name')->latest()->paginate(20)->withQueryString()
                : collect(),
        ]);
    }

    public function store(Request $request, VoiceResourceWriter $writer, VoiceSpaceAccess $access): RedirectResponse
    {
        $validated = $this->validateResource($request, true);
        $voice = Voice::from($validated['voice']);
        $access->authorizeVoice($request->user(), $voice);
        $writer->create($voice, $validated, $request->file('audio'), $request->user());

        return $this->backTo($voice)->with('success', 'Le morceau a été ajouté à votre pupitre.');
    }

    public function update(Request $request, VoiceResource $resource, VoiceResourceWriter $writer): RedirectResponse
    {
        $validated = $this->validateResource($request, false);
        $writer->update($resource, $validated, $request->file('audio'), $request->user());

        return $this->backTo($resource->voice)->with('success', 'Les paroles et le fichier audio ont été mis à jour.');
    }

    public function destroy(Request $request, VoiceResource $resource, VoiceResourceWriter $writer): RedirectResponse
    {
        $voice = $resource->voice;
        $writer->delete($resource, $request->user());

        return $this->backTo($voice)->with('success', 'Le morceau a été retiré du pupitre.');
    }

    private function validateResource(Request $request, bool $creating): array
    {
        if (is_string($request->input('title'))) {
            $request->merge(['title' => trim(preg_replace('/\s+/u', ' ', $request->input('title')))]);
        }
        if (is_string($request->input('lyrics'))) {
            $request->merge(['lyrics' => trim(str_replace(["\r\n", "\r"], "\n", $request->input('lyrics')))]);
        }

        return $request->validate([
            'voice' => [$creating ? 'required' : 'sometimes', Rule::enum(Voice::class)],
            'title' => ['required', 'string', 'max:180', new ReadableText],
            'lyrics' => ['nullable', 'string', 'max:20000', 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:10240'],
        ], [], [
            'title' => 'nom du morceau',
            'lyrics' => 'paroles ou notes',
            'audio' => 'fichier audio',
            'voice' => 'pupitre',
        ]);
    }

    private function backTo(Voice $voice): RedirectResponse
    {
        return redirect()->route('voice-space.index', ['voice' => $voice->value]);
    }
}
