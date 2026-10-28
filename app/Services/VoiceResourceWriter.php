<?php

namespace App\Services;

use App\Enums\Voice;
use App\Models\User;
use App\Models\VoiceResource;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class VoiceResourceWriter
{
    public function __construct(
        private readonly PrivateFiles $files,
        private readonly TenantContext $tenant,
        private readonly VoiceSpaceAccess $access,
    ) {}

    public function create(Voice $voice, array $data, ?UploadedFile $audio, User $user): VoiceResource
    {
        $this->access->authorizeVoice($user, $voice);
        if (blank($data['lyrics'] ?? null) && ! $audio) {
            throw ValidationException::withMessages(['lyrics' => 'Ajoutez des paroles, une note ou un fichier audio.']);
        }

        $stored = $this->storeAudio($audio, $voice);
        try {
            return DB::transaction(fn () => VoiceResource::create([
                'voice' => $voice->value,
                'title' => $data['title'],
                'lyrics' => filled($data['lyrics'] ?? null) ? $data['lyrics'] : null,
                'audio_path' => $stored,
                'audio_original_name' => $audio?->getClientOriginalName(),
                'audio_mime' => $audio?->getMimeType(),
                'audio_size' => $audio?->getSize(),
                'uploaded_by' => $user->id,
            ]));
        } catch (Throwable $exception) {
            $this->remove($stored);
            throw $exception;
        }
    }

    public function update(VoiceResource $resource, array $data, ?UploadedFile $audio, User $user): VoiceResource
    {
        $this->access->authorizeEdit($user, $resource);
        if (blank($data['lyrics'] ?? null) && ! $audio && ! $resource->audio_path) {
            throw ValidationException::withMessages(['lyrics' => 'Conservez des paroles, une note ou ajoutez un fichier audio.']);
        }

        $stored = $this->storeAudio($audio, $resource->voice);
        try {
            return DB::transaction(function () use ($resource, $data, $audio, $stored, $user): VoiceResource {
                $entry = VoiceResource::query()->whereKey($resource->id)->lockForUpdate()->firstOrFail();
                $this->access->authorizeEdit($user, $entry);
                $previous = $entry->audio_path;
                $values = [
                    'title' => $data['title'],
                    'lyrics' => filled($data['lyrics'] ?? null) ? $data['lyrics'] : null,
                ];
                if ($audio) {
                    $values += [
                        'audio_path' => $stored,
                        'audio_original_name' => $audio->getClientOriginalName(),
                        'audio_mime' => $audio->getMimeType(),
                        'audio_size' => $audio->getSize(),
                    ];
                }
                $entry->update($values);
                if ($stored && $previous && $stored !== $previous) {
                    DB::afterCommit(fn () => $this->remove($previous));
                }

                return $entry;
            }, 3);
        } catch (Throwable $exception) {
            $this->remove($stored);
            throw $exception;
        }
    }

    public function delete(VoiceResource $resource, User $user): void
    {
        DB::transaction(function () use ($resource, $user): void {
            $entry = VoiceResource::query()->whereKey($resource->id)->lockForUpdate()->firstOrFail();
            $this->access->authorizeEdit($user, $entry);
            $path = $entry->audio_path;
            $entry->delete();
            if ($path) {
                DB::afterCommit(fn () => $this->remove($path));
            }
        }, 3);
    }

    private function storeAudio(?UploadedFile $audio, Voice $voice): ?string
    {
        if (! $audio) {
            return null;
        }
        try {
            return $this->files->store($audio, $this->tenant->path('voice-resources/'.$voice->value));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['audio' => $exception->errors()['file'] ?? 'Le fichier audio n’a pas pu être enregistré.']);
        }
    }

    private function remove(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            $this->files->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Fichier audio de pupitre à nettoyer.', ['path' => $path, 'exception' => $exception::class]);
        }
    }
}
