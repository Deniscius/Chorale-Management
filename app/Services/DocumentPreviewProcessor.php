<?php

namespace App\Services;

use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class DocumentPreviewProcessor
{
    public function __construct(private readonly PrivateFiles $files, private readonly TenantContext $tenant) {}

    public function process(Document $document): void
    {
        if (! $document->requiresGeneratedPreview()) {
            return;
        }
        $document->update(['preview_status' => 'processing', 'preview_error' => null]);
        $directory = storage_path('framework/tmp/document-previews/'.Str::uuid());
        try {
            File::ensureDirectoryExists($directory, 0700, true);
            $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
            $source = $directory.'/source.'.$extension;
            File::put($source, $this->files->contents($document->path));
            $profile = $directory.'/profile';
            File::ensureDirectoryExists($profile, 0700, true);
            $process = new Process([
                'soffice', '--headless', '--nologo', '--nodefault', '--nofirststartwizard',
                '-env:UserInstallation=file://'.$profile, '--convert-to', 'pdf', '--outdir', $directory, $source,
            ]);
            $process->setTimeout(120);
            $process->mustRun();
            $pdf = $directory.'/source.pdf';
            if (! File::isFile($pdf) || File::size($pdf) < 5) {
                throw new RuntimeException('Conversion PDF absente.');
            }
            $path = $this->tenant->path('document-previews/'.$document->id.'/preview.pdf');
            $this->files->storeContents($path, File::get($pdf), 'application/pdf');
            $updated = Document::query()->whereKey($document->id)->where('path', $document->path)->update([
                'preview_path' => $path, 'preview_status' => 'ready', 'preview_error' => null,
            ]);
            if ($updated === 0) {
                $this->files->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Échec de préparation de l’aperçu documentaire.', ['document_id' => $document->id, 'tenant_id' => $this->tenant->id(), 'exception' => $exception::class]);
            Document::query()->whereKey($document->id)->update([
                'preview_status' => 'error',
                'preview_error' => 'Aperçu impossible. Vérifiez que le fichier s’ouvre correctement, puis réessayez.',
            ]);
        } finally {
            if (str_starts_with($directory, storage_path('framework/tmp/document-previews/'))) {
                File::deleteDirectory($directory);
            }
        }
    }
}
