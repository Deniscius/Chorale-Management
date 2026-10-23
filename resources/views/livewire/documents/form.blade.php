<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="commission" value="Commission" />
                <select id="commission" wire:model="commission"
                    class="form-select mt-1" @disabled($document !== null)>
                    @foreach ($this->commissionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('commission')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="category" value="Catégorie" />
                <select id="category" wire:model="category"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->categories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="document_date" label="Date du document / de la messe" type="date" wire:model="document_date" />
            @if (\App\Enums\Commission::tryFrom($commission)?->hasPrivateArchive())
                <x-select name="visibility" label="Visibilité" wire:model="visibility">
                    <option value="private">Privé — responsable et associé</option>
                    <option value="published">Partagé — utilisateurs connectés</option>
                </x-select>
            @endif
        </div>
        <div>
            <x-input-label for="title" value="Titre du document" />
            <x-text-input id="title" type="text" wire:model="title" class="mt-1 block w-full" required autofocus />
            <x-input-error :messages="$errors->get('title')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" wire:model="description" rows="3"
                class="form-textarea mt-1" placeholder="Objet du document, contexte, référence…"></textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="file" value="Fichier" />
            <div class="mt-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <input id="file" type="file" wire:model="file"
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
            <x-input-error :messages="$errors->get('file')" class="mt-1" />
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
