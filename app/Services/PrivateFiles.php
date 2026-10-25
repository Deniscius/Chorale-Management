<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class PrivateFiles
{
    private array $existsMemo = [];

    public function primeExists(array $paths): void
    {
        if (! $this->persistent()) {
            return;
        }
        $tenant = app(TenantContext::class)->requireId();
        $existing = $this->query()->whereIn('path', $paths)->pluck('path')->all();
        foreach ($paths as $path) {
            $this->assertPath($path);
            $this->existsMemo[$tenant.':'.$path] = in_array($path, $existing, true);
        }
    }

    public function persistent(): bool
    {
        if (! in_array(config('private-files.driver'), ['database', 'local'], true)) {
            throw new \LogicException('Pilote de fichiers privés inconnu.');
        }

        return config('private-files.driver') === 'database';
    }

    private function query()
    {
        return DB::table('private_files')->where('tenant_id', app(TenantContext::class)->requireId());
    }

    private function assertPath(string $path): void
    {
        $tenant = app(TenantContext::class)->requireId();
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/'), 404);
        if (str_starts_with($path, 'tenants/')) {
            abort_unless(str_starts_with($path, 'tenants/'.$tenant.'/'), 404);
        } else {
            // Only the original tenant may have files from before tenant-prefixed paths.
            abort_unless($tenant === 1, 404);
        }
    }

    public function store(UploadedFile $file, string $directory): string
    {
        if (! $this->persistent()) {
            if (config('deployment.ephemeral_files')) {
                throw ValidationException::withMessages(['file' => 'Le stockage temporaire ne permet pas de conserver ce fichier. Activez le stockage persistant avant l’import.']);
            }
            $path = $file->store($directory, 'local');
            if (! $path) {
                throw ValidationException::withMessages(['file' => 'Le fichier n’a pas pu être enregistré.']);
            }

            return $path;
        }
        $path = trim($directory, '/').'/'.$file->hashName();
        $this->persist($path, $file->getContent(), $file->getMimeType() ?: 'application/octet-stream');

        return $path;
    }

    public function persist(string $path, string $contents, string $mime): void
    {
        $this->assertPath($path);
        unset($this->existsMemo[app(TenantContext::class)->requireId().':'.$path]);
        if (strlen($contents) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'La taille maximale est de 10 Mo.']);
        }
        $payload = Crypt::encryptString($contents);
        $size = strlen($payload);
        DB::transaction(function () use ($path, $contents, $mime, $payload, $size) {
            DB::table('private_file_mutex')->where('id', 1)->lockForUpdate()->first();
            $previous = (int) $this->query()->where('path', $path)->value('stored_bytes');
            if ((int) $this->query()->sum('stored_bytes') - $previous + $size > config('private-files.tenant_quota')
                || (int) DB::table('private_files')->sum('stored_bytes') - $previous + $size > config('private-files.global_quota')) {
                throw ValidationException::withMessages(['file' => 'L’espace de stockage est plein. Contactez l’administrateur avant de réessayer.']);
            }
            DB::table('private_files')->upsert([
                'tenant_id' => app(TenantContext::class)->requireId(), 'path' => $path, 'mime_type' => $mime,
                'size' => strlen($contents), 'stored_bytes' => $size, 'sha256' => hash('sha256', $contents),
                'payload' => $payload, 'created_at' => now(), 'updated_at' => now(),
            ], ['tenant_id', 'path'], ['mime_type', 'size', 'stored_bytes', 'sha256', 'payload', 'updated_at']);
        }, 3);
    }

    public function exists(string $path): bool
    {
        $this->assertPath($path);
        $key = app(TenantContext::class)->requireId().':'.$path;

        return ($this->persistent() && ($this->existsMemo[$key] ??= $this->query()->where('path', $path)->exists())) || Storage::disk('local')->exists($path);
    }

    public function delete(string $path): bool
    {
        $this->assertPath($path);
        unset($this->existsMemo[app(TenantContext::class)->requireId().':'.$path]);
        if ($this->persistent()) {
            DB::transaction(function () use ($path) {
                DB::table('private_file_mutex')->where('id', 1)->lockForUpdate()->first();
                $this->query()->where('path', $path)->delete();
            }, 3);
        }

        return Storage::disk('local')->delete($path);
    }

    public function response(string $path, string $name, bool $inline, ?string $mime = null, array $headers = [])
    {
        $this->assertPath($path);
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $name))) ?: 'document';
        $row = $this->persistent() ? $this->query()->where('path', $path)->first() : null;
        $headers += ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($row) {
            $contents = Crypt::decryptString($row->payload);
            abort_unless(hash_equals($row->sha256, hash('sha256', $contents)), 500, 'Le fichier n’a pas pu être vérifié.');
            $headers += ['Content-Type' => $mime ?: $row->mime_type, 'Content-Length' => (string) strlen($contents)];
            $headers['Content-Disposition'] = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name, str_replace('%', '_', Str::ascii($name)) ?: 'document');

            return response()->stream(fn () => print ($contents), 200, $headers);
        }
        abort_unless(Storage::disk('local')->exists($path), 404, 'Fichier introuvable. Réimportez le fichier original.');
        if ($mime) {
            $headers['Content-Type'] = $mime;
        }
        $response = $inline
            ? response()->file(Storage::disk('local')->path($path), $headers)->setContentDisposition('inline', $name)
            : response()->download(Storage::disk('local')->path($path), $name, $headers);
        if (str_contains($headers['Cache-Control'], 'private')) {
            $response->setPrivate();
        }

        return $response;
    }
}
