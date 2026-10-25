<?php

namespace App\Services;

use App\Enums\Commission;
use App\Models\Choir;
use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DocumentArchive
{
    public function save(?Document $document, array $data, ?UploadedFile $file = null): Document
    {
        Validator::make($data, [
            'commission' => ['required', Rule::enum(Commission::class)],
            'visibility' => ['sometimes', Rule::in(['private', 'published', 'commission'])],
            'document_date' => ['nullable', 'date'],
            'correspondence_id' => ['nullable', Rule::exists('correspondences', 'id')->whereNull('deleted_at')],
        ])->validate();
        if ($file) {
            Validator::make(['file' => $file], ['file' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp|max:10240'])->validate();
        }
        $commission = Commission::from($data['commission']);
        if (! empty($data['correspondence_id']) && $commission !== Commission::Secretariat) {
            throw ValidationException::withMessages(['commission' => 'Une pièce de courrier reste au secrétariat.']);
        }
        abort_unless($commission->canManageArchive(auth()->user()), 403);
        if ($commission->hasPrivateArchive()) {
            $data['visibility'] ??= $document?->visibility ?? 'private';
            if ($data['visibility'] === 'commission') {
                $data['visibility'] = 'private';
            }
        } else {
            $data['visibility'] = 'commission';
        }
        $stored = null;
        try {
            if ($file) {
                $stored = app(PrivateFiles::class)->store($file, app(TenantContext::class)->path('documents/'.$data['commission']));
                if (! $stored) {
                    throw new \RuntimeException('Le fichier n’a pas pu être enregistré.');
                }
                $data += ['path' => $stored, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
            }

            return DB::transaction(function () use ($document, $data, $stored) {
                $entry = $document ? Document::query()->lockForUpdate()->findOrFail($document->id) : new Document;
                if ($entry->exists) {
                    abort_unless($entry->canManage(auth()->user()), 403);
                }
                $old = $entry->path;
                if (! $entry->exists) {
                    $entry->fill(['choir_id' => Choir::query()->value('id'), 'uploaded_by' => auth()->id(), 'member_id' => null]);
                }
                $entry->fill($data)->save();
                if ($stored && $old) {
                    $this->removeAfterCommit($old);
                }

                return $entry;
            });
        } catch (Throwable $e) {
            if ($stored) {
                app(PrivateFiles::class)->delete($stored);
            }
            throw $e;
        }
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $entry = Document::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($entry->canManage(auth()->user()), 403);
            $path = $entry->path;
            $entry->delete();
            $this->removeAfterCommit($path);
        });
    }

    public function share(int $id, bool $published): void
    {
        DB::transaction(function () use ($id, $published) {
            $entry = Document::query()->lockForUpdate()->findOrFail($id);
            abort_unless($entry->canManage(auth()->user()) && $entry->commission->hasPrivateArchive(), 403);
            $entry->update(['visibility' => $published ? 'published' : 'private']);
        });
    }

    private function removeAfterCommit(string $path): void
    {
        DB::afterCommit(function () use ($path) {
            try {
                if (! app(PrivateFiles::class)->delete($path)) {
                    Log::warning('Archive à nettoyer', ['path' => $path]);
                }
            } catch (Throwable $e) {
                Log::warning('Archive à nettoyer', ['path' => $path, 'error' => $e->getMessage()]);
            }
        });
    }
}
