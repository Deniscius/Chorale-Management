<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="documents-form-commission" value="Commission" />
                <select id="documents-form-commission" aria-describedby="documents-form-commission-error" aria-invalid="{{ $errors->has('commission') ? 'true' : 'false' }}" wire:model="commission"
                    class="form-select mt-1" @disabled($document !== null)>
                    @foreach ($this->commissionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="documents-form-commission-error" :messages="$errors->get('commission')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="documents-form-category" value="Catégorie" />
                <select id="documents-form-category" aria-describedby="documents-form-category-error" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" wire:model="category"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->categories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="documents-form-category-error" :messages="$errors->get('category')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-input id="documents-form-document_date" name="document_date" label="Date du document / de la messe" type="date" wire:model="document_date" />
            <x-select id="documents-form-visibility" name="visibility" label="Qui peut consulter ce document ?" wire:model.live="visibility">
                @foreach(\App\Enums\DocumentVisibility::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </x-select>
        </div>
        <p class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">{{ \App\Enums\DocumentVisibility::tryFrom($visibility)?->description() }} Le choix protège le titre, la liste, l’aperçu et le téléchargement. Un fichier déjà téléchargé ne peut pas être rappelé.</p>
        <div>
            <x-input-label for="documents-form-title" value="Titre du document" />
            <x-text-input id="documents-form-title" type="text" name="title" aria-describedby="documents-form-title-error" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" wire:model="title" class="mt-1 block w-full" required autofocus />
            <x-input-error id="documents-form-title-error" :messages="$errors->get('title')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="documents-form-description" value="Description" />
            <textarea id="documents-form-description" aria-describedby="documents-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" rows="3"
                class="form-textarea mt-1" placeholder="Objet du document, contexte, référence…"></textarea>
            <x-input-error id="documents-form-description-error" :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="documents-form-file" value="Fichier" />
            <div class="mt-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <input id="documents-form-file" type="file" aria-describedby="documents-form-file-error" aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}" wire:model="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp"
                    class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-700" />

                <p class="mt-2 text-xs text-slate-500">
                    PDF, Word, Excel, PowerPoint, texte ou image — 10 Mo maximum.
                    @if ($document)
                        Laissez vide pour conserver le fichier actuel.
                    @endif
                </p>

                <p wire:loading wire:target="file" class="mt-2 text-xs font-medium text-slate-600">
                    Téléversement en cours…
                </p>

                @if ($file)
                    <p class="mt-2 text-xs font-medium text-emerald-700">
                        {{ $file->getClientOriginalName() }} — prêt à être archivé.
                    </p>
                @endif
            </div>
            <x-input-error id="documents-form-file-error" :messages="$errors->get('file')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save,file"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
