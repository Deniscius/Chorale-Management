<?php

namespace App\Services;

use App\Models\Choir;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DocumentArchive
{
    public function save(?Document $document, array $data, ?UploadedFile $file = null): Document
    {
        $stored = null;
        try {
            if ($file) {
                $stored = $file->store('documents/'.$data['commission'], 'local');
                if (! $stored) {
                    throw new \RuntimeException('Le fichier n’a pas pu être enregistré.');
                }
                $data += ['path' => $stored, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
            }

            return DB::transaction(function () use ($document, $data, $stored) {
                $entry = $document ? Document::query()->lockForUpdate()->findOrFail($document->id) : new Document;
                if ($entry->exists) {
                    abort_unless($entry->commission->canManage(auth()->user()), 403);
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
                Storage::disk('local')->delete($stored);
            }
            throw $e;
        }
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $entry = Document::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($entry->commission->canManage(auth()->user()), 403);
            $path = $entry->path;
            $entry->delete();
            $this->removeAfterCommit($path);
        });
    }

    private function removeAfterCommit(string $path): void
    {
        DB::afterCommit(function () use ($path) {
            try {
                if (! Storage::disk('local')->delete($path)) {
                    Log::warning('Archive à nettoyer', ['path' => $path]);
                }
            } catch (Throwable $e) {
                Log::warning('Archive à nettoyer', ['path' => $path, 'error' => $e->getMessage()]);
            }
        });
    }
}
