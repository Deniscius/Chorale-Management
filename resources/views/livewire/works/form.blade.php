<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="works-form-title" value="Titre de l'œuvre" />
                <x-text-input id="works-form-title" type="text" name="title" aria-describedby="works-form-title-error" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" wire:model="title" class="mt-1 block w-full" required autofocus />
                <x-input-error id="works-form-title-error" :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-type" value="Type d'œuvre" />
                <select id="works-form-type" aria-describedby="works-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="works-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-season_id" value="Temps liturgique" />
                <select id="works-form-season_id" aria-describedby="works-form-season_id-error" aria-invalid="{{ $errors->has('season_id') ? 'true' : 'false' }}" wire:model="season_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->seasons as $season)
                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="works-form-season_id-error" :messages="$errors->get('season_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="works-form-composer" value="Compositeur" />
                <x-text-input id="works-form-composer" type="text" name="composer" aria-describedby="works-form-composer-error" aria-invalid="{{ $errors->has('composer') ? 'true' : 'false' }}" wire:model="composer" class="mt-1 block w-full" />
                <x-input-error id="works-form-composer-error" :messages="$errors->get('composer')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-arranger" value="Arrangeur" />
                <x-text-input id="works-form-arranger" type="text" name="arranger" aria-describedby="works-form-arranger-error" aria-invalid="{{ $errors->has('arranger') ? 'true' : 'false' }}" wire:model="arranger" class="mt-1 block w-full" />
                <x-input-error id="works-form-arranger-error" :messages="$errors->get('arranger')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-text_author" value="Auteur du texte" />
                <x-text-input id="works-form-text_author" type="text" name="text_author" aria-describedby="works-form-text_author-error" aria-invalid="{{ $errors->has('text_author') ? 'true' : 'false' }}" wire:model="text_author" class="mt-1 block w-full" />
                <x-input-error id="works-form-text_author-error" :messages="$errors->get('text_author')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="works-form-language" value="Langue" />
                <x-text-input id="works-form-language" type="text" name="language" aria-describedby="works-form-language-error" aria-invalid="{{ $errors->has('language') ? 'true' : 'false' }}" wire:model="language" class="mt-1 block w-full" />
                <x-input-error id="works-form-language-error" :messages="$errors->get('language')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-key_signature" value="Tonalité" />
                <x-text-input id="works-form-key_signature" type="text" name="key_signature" aria-describedby="works-form-key_signature-error" aria-invalid="{{ $errors->has('key_signature') ? 'true' : 'false' }}" wire:model="key_signature" class="mt-1 block w-full" />
                <x-input-error id="works-form-key_signature-error" :messages="$errors->get('key_signature')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-mode" value="Mode" />
                <x-text-input id="works-form-mode" type="text" name="mode" aria-describedby="works-form-mode-error" aria-invalid="{{ $errors->has('mode') ? 'true' : 'false' }}" wire:model="mode" class="mt-1 block w-full" />
                <x-input-error id="works-form-mode-error" :messages="$errors->get('mode')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-tempo" value="Tempo (BPM)" />
                <x-text-input id="works-form-tempo" type="number" name="tempo" aria-describedby="works-form-tempo-error" aria-invalid="{{ $errors->has('tempo') ? 'true' : 'false' }}" wire:model="tempo" class="mt-1 block w-full" min="20" max="300" />
                <x-input-error id="works-form-tempo-error" :messages="$errors->get('tempo')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="works-form-duration_minutes" value="Durée (minutes)" />
                <x-text-input id="works-form-duration_minutes" type="number" name="duration_minutes" aria-describedby="works-form-duration_minutes-error" aria-invalid="{{ $errors->has('duration_minutes') ? 'true' : 'false' }}" wire:model="duration_minutes" class="mt-1 block w-full" min="1" />
                <x-input-error id="works-form-duration_minutes-error" :messages="$errors->get('duration_minutes')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-difficulty" value="Niveau de difficulté" />
                <select id="works-form-difficulty" aria-describedby="works-form-difficulty-error" aria-invalid="{{ $errors->has('difficulty') ? 'true' : 'false' }}" wire:model="difficulty"
                    class="form-select mt-1">
                    @foreach ($this->difficultyOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="works-form-difficulty-error" :messages="$errors->get('difficulty')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-min_voices" value="Nombre minimum de voix" />
                <x-text-input id="works-form-min_voices" type="number" name="min_voices" aria-describedby="works-form-min_voices-error" aria-invalid="{{ $errors->has('min_voices') ? 'true' : 'false' }}" wire:model="min_voices" class="mt-1 block w-full" min="1" />
                <x-input-error id="works-form-min_voices-error" :messages="$errors->get('min_voices')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="works-form-required_voices" value="Voix requises" />
                <x-text-input id="works-form-required_voices" type="text" name="required_voices" aria-describedby="works-form-required_voices-error" aria-invalid="{{ $errors->has('required_voices') ? 'true' : 'false' }}" wire:model="required_voices" class="mt-1 block w-full"
                    placeholder="soprano, alto, ténor" />
                <x-input-error id="works-form-required_voices-error" :messages="$errors->get('required_voices')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Séparez les voix par des virgules.</p>
            </div>

            <div>
                <x-input-label for="works-form-instrumentation" value="Instrumentation" />
                <x-text-input id="works-form-instrumentation" type="text" name="instrumentation" aria-describedby="works-form-instrumentation-error" aria-invalid="{{ $errors->has('instrumentation') ? 'true' : 'false' }}" wire:model="instrumentation" class="mt-1 block w-full"
                    placeholder="orgue, violon, violoncelle" />
                <x-input-error id="works-form-instrumentation-error" :messages="$errors->get('instrumentation')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Séparez les instruments par des virgules.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="works-form-pdf_path" value="Chemin de la partition" />
                <x-text-input id="works-form-pdf_path" type="text" name="pdf_path" aria-describedby="works-form-pdf_path-error" aria-invalid="{{ $errors->has('pdf_path') ? 'true' : 'false' }}" wire:model="pdf_path" class="mt-1 block w-full" />
                <x-input-error id="works-form-pdf_path-error" :messages="$errors->get('pdf_path')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-midi_path" value="Chemin du fichier MIDI" />
                <x-text-input id="works-form-midi_path" type="text" name="midi_path" aria-describedby="works-form-midi_path-error" aria-invalid="{{ $errors->has('midi_path') ? 'true' : 'false' }}" wire:model="midi_path" class="mt-1 block w-full" />
                <x-input-error id="works-form-midi_path-error" :messages="$errors->get('midi_path')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="works-form-lyrics_path" value="Chemin des paroles" />
                <x-text-input id="works-form-lyrics_path" type="text" name="lyrics_path" aria-describedby="works-form-lyrics_path-error" aria-invalid="{{ $errors->has('lyrics_path') ? 'true' : 'false' }}" wire:model="lyrics_path" class="mt-1 block w-full" />
                <x-input-error id="works-form-lyrics_path-error" :messages="$errors->get('lyrics_path')" class="mt-1" />
            </div>
        </div>

        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <input type="checkbox" aria-describedby="works-form-is_repertoire-error" aria-invalid="{{ $errors->has('is_repertoire') ? 'true' : 'false' }}" wire:model="is_repertoire"
                class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
            <span class="text-sm font-medium text-slate-700">Cette œuvre fait partie du répertoire</span>
        </label>

        <div>
            <x-input-label for="works-form-notes" value="Notes" />
            <textarea id="works-form-notes" aria-describedby="works-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="works-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
