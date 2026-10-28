<?php

namespace App\Services;

use App\Jobs\ProcessLetterheadTemplate;
use App\Models\LetterheadTemplate;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class LetterheadManager
{
    public function __construct(
        private readonly DocxSecurityInspector $inspector,
        private readonly PdfSecurityInspector $pdfInspector,
        private readonly PrivateFiles $files,
        private readonly TenantContext $tenant,
    ) {}

    public function upload(UploadedFile $document, int $height, User $user): LetterheadTemplate
    {
        if (strtolower($document->getClientOriginalExtension()) === 'pdf') {
            $this->pdfInspector->inspect($document);
        } else {
            $this->inspector->inspect($document);
        }

        $template = DB::transaction(function () use ($document, $height, $user): LetterheadTemplate {
            $template = LetterheadTemplate::create([
                'original_name' => $document->getClientOriginalName(),
                'status' => LetterheadTemplate::STATUS_PENDING,
                'header_height_mm' => $height,
                'uploaded_by' => $user->id,
            ]);
            $template->update([
                'original_path' => $this->files->store($document, $this->tenant->path("letterheads/{$template->id}")),
            ]);

            return $template;
        });

        // Répondre à Livewire avant de lancer LibreOffice. Sur Render Free la file
        // est synchrone : sans ce report, le navigateur semble bloqué jusqu'à la
        // fin de la conversion et peut atteindre la limite PHP de 60 secondes.
        ProcessLetterheadTemplate::dispatchAfterResponse($this->tenant->requireId(), $template->id);

        return $template;
    }

    public function reprocess(LetterheadTemplate $template, int $height): void
    {
        abort_unless($template->original_path, 422);
        if ($template->status === LetterheadTemplate::STATUS_ACTIVE) {
            $template = LetterheadTemplate::create([
                'original_name' => $template->original_name,
                'original_path' => $template->original_path,
                'header_height_mm' => $height,
                'status' => LetterheadTemplate::STATUS_PENDING,
                'uploaded_by' => auth()->id() ?: $template->uploaded_by,
            ]);
            ProcessLetterheadTemplate::dispatchAfterResponse($this->tenant->requireId(), $template->id);

            return;
        }
        $template->update([
            'header_height_mm' => $height,
            'status' => LetterheadTemplate::STATUS_PENDING,
            'error_message' => null,
        ]);
        ProcessLetterheadTemplate::dispatchAfterResponse($this->tenant->requireId(), $template->id);
    }

    public function activate(LetterheadTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $this->tenant->lockForAccountChanges();
            $selected = LetterheadTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            if (! $selected->isUsable() || ! $this->files->exists($selected->header_path)) {
                throw ValidationException::withMessages(['activation' => 'Vérifiez l’aperçu avant d’activer ce papier à en-tête.']);
            }
            LetterheadTemplate::query()->whereKeyNot($selected->id)
                ->where('status', LetterheadTemplate::STATUS_ACTIVE)
                ->lockForUpdate()->update(['status' => LetterheadTemplate::STATUS_REPLACED]);
            $selected->update([
                'status' => LetterheadTemplate::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);
        }, 3);
    }

    public function delete(LetterheadTemplate $template): void
    {
        $paths = DB::transaction(function () use ($template): array {
            $selected = LetterheadTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $candidates = array_values(array_unique(array_filter([
                $selected->original_path,
                $selected->preview_path,
                $selected->header_path,
            ])));

            // A recalculated version can share its original with the active
            // version. Never remove a file that another template still uses.
            $paths = array_values(array_filter($candidates, function (string $path) use ($selected): bool {
                return ! LetterheadTemplate::query()
                    ->whereKeyNot($selected->id)
                    ->where(function ($query) use ($path): void {
                        $query->where('original_path', $path)
                            ->orWhere('preview_path', $path)
                            ->orWhere('header_path', $path);
                    })
                    ->exists();
            }));

            $selected->delete();

            return $paths;
        }, 3);

        foreach ($paths as $path) {
            try {
                $this->files->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Papier à en-tête supprimé, fichier privé à nettoyer.', [
                    'tenant_id' => $this->tenant->id(),
                    'template_id' => $template->id,
                    'path' => $path,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
