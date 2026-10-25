<?php

namespace App\Services;

use App\Models\Setting;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

final class ChoraleBranding
{
    public function save(array $values, ?UploadedFile $logo = null): void
    {
        $previous = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);
        $stored = null;
        try {
            if ($logo) {
                try {
                    $stored = $logo->store(app(TenantContext::class)->path('chorale'), 'local');
                } catch (FilesystemException $e) {
                    throw ValidationException::withMessages(['logo' => 'Le logo n’a pas pu être enregistré. Réessayez.']);
                }
                if (! $stored) {
                    throw ValidationException::withMessages(['logo' => 'Le logo n’a pas pu être enregistré. Réessayez.']);
                }
                $values[ChoraleIdentity::KEY_LOGO_PATH] = $stored;
            }
            DB::transaction(function () use ($values, $stored, $previous) {
                Setting::setMany($values);
                if ($stored && $previous && $stored !== $previous) {
                    $this->removeAfterCommit($previous);
                }
            });
        } catch (Throwable $e) {
            if ($stored) {
                $this->remove($stored);
            }
            app(SettingsStore::class)->flush();
            throw $e;
        }
    }

    public function removeLogo(): void
    {
        $previous = Setting::get(ChoraleIdentity::KEY_LOGO_PATH);
        DB::transaction(function () use ($previous) {
            Setting::set(ChoraleIdentity::KEY_LOGO_PATH, null);
            if ($previous) {
                $this->removeAfterCommit($previous);
            }
        });
    }

    private function removeAfterCommit(string $path): void
    {
        DB::afterCommit(fn () => $this->remove($path));
    }

    private function remove(string $path): void
    {
        $failed = false;
        try {
            $failed = ! Storage::disk('local')->delete($path);
        } catch (Throwable $e) {
            $failed = true;
        }
        if ($failed) {
            try {
                Log::warning('Nettoyage de logo à reprendre.');
            } catch (Throwable $e) {
                // Post-commit cleanup must not undo a successfully stored logo.
            }
        }
    }
}
