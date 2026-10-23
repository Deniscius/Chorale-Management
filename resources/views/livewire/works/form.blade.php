<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="title" value="Titre de l'œuvre" />
                <x-text-input id="title" type="text" wire:model="title" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="type" value="Type d'œuvre" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="season_id" value="Temps liturgique" />
                <select id="season_id" wire:model="season_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->seasons as $season)
                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('season_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="composer" value="Compositeur" />
                <x-text-input id="composer" type="text" wire:model="composer" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('composer')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="arranger" value="Arrangeur" />
                <x-text-input id="arranger" type="text" wire:model="arranger" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('arranger')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="text_author" value="Auteur du texte" />
                <x-text-input id="text_author" type="text" wire:model="text_author" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('text_author')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="language" value="Langue" />
                <x-text-input id="language" type="text" wire:model="language" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('language')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="key_signature" value="Tonalité" />
                <x-text-input id="key_signature" type="text" wire:model="key_signature" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('key_signature')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="mode" value="Mode" />
                <x-text-input id="mode" type="text" wire:model="mode" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('mode')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="tempo" value="Tempo (BPM)" />
                <x-text-input id="tempo" type="number" wire:model="tempo" class="mt-1 block w-full" min="20" max="300" />
                <x-input-error :messages="$errors->get('tempo')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="duration_minutes" value="Durée (minutes)" />
                <x-text-input id="duration_minutes" type="number" wire:model="duration_minutes" class="mt-1 block w-full" min="1" />
                <x-input-error :messages="$errors->get('duration_minutes')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="difficulty" value="Niveau de difficulté" />
                <select id="difficulty" wire:model="difficulty"
                    class="form-select mt-1">
                    @foreach ($this->difficultyOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('difficulty')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="min_voices" value="Nombre minimum de voix" />
                <x-text-input id="min_voices" type="number" wire:model="min_voices" class="mt-1 block w-full" min="1" />
                <x-input-error :messages="$errors->get('min_voices')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="required_voices" value="Voix requises" />
                <x-text-input id="required_voices" type="text" wire:model="required_voices" class="mt-1 block w-full"
                    placeholder="soprano, alto, ténor" />
                <x-input-error :messages="$errors->get('required_voices')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Séparez les voix par des virgules.</p>
            </div>

            <div>
                <x-input-label for="instrumentation" value="Instrumentation" />
                <x-text-input id="instrumentation" type="text" wire:model="instrumentation" class="mt-1 block w-full"
                    placeholder="orgue, violon, violoncelle" />
                <x-input-error :messages="$errors->get('instrumentation')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Séparez les instruments par des virgules.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="pdf_path" value="Chemin de la partition" />
                <x-text-input id="pdf_path" type="text" wire:model="pdf_path" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('pdf_path')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="midi_path" value="Chemin du fichier MIDI" />
                <x-text-input id="midi_path" type="text" wire:model="midi_path" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('midi_path')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="lyrics_path" value="Chemin des paroles" />
                <x-text-input id="lyrics_path" type="text" wire:model="lyrics_path" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('lyrics_path')" class="mt-1" />
            </div>
        </div>

        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <input type="checkbox" wire:model="is_repertoire"
                class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
            <span class="text-sm font-medium text-slate-700">Cette œuvre fait partie du répertoire</span>
        </label>

        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
