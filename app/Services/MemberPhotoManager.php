<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemberPhotoManager
{
    public function __construct(private readonly PrivateFiles $files, private readonly TenantContext $tenant) {}

    public function save(User $user, UploadedFile $photo): void
    {
        abort_unless($user->member_id, 422, 'Complétez d’abord votre fiche choriste.');
        $stored = null;
        try {
            $stored = $this->files->store($photo, $this->tenant->path('members/'.$user->member_id.'/identity'));
            DB::transaction(function () use ($user, $stored): void {
                $member = Member::query()->whereKey($user->member_id)->lockForUpdate()->firstOrFail();
                abort_unless((int) auth()->id() === (int) $user->id && (int) auth()->user()->member_id === (int) $member->id, 403);
                $previous = $member->photo_path;
                $member->update(['photo_path' => $stored]);
                if ($previous && $previous !== $stored) {
                    DB::afterCommit(fn () => $this->remove($previous));
                }
            }, 3);
        } catch (ValidationException $exception) {
            $this->remove($stored);
            throw ValidationException::withMessages(['photo' => $exception->errors()['file'] ?? 'La photo n’a pas pu être enregistrée.']);
        } catch (Throwable $exception) {
            $this->remove($stored);
            throw $exception;
        }
    }

    public function delete(User $user): void
    {
        abort_unless($user->member_id, 404);
        DB::transaction(function () use ($user): void {
            $member = Member::query()->whereKey($user->member_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) auth()->id() === (int) $user->id && (int) auth()->user()->member_id === (int) $member->id, 403);
            $previous = $member->photo_path;
            $member->update(['photo_path' => null]);
            if ($previous) {
                DB::afterCommit(fn () => $this->remove($previous));
            }
        }, 3);
    }

    private function remove(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            $this->files->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Photo de choriste à nettoyer.', ['path' => $path, 'exception' => $exception::class]);
        }
    }
}
