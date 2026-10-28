<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <x-input-label for="instruments-form-image" value="Photo de l’instrument" />
            <input id="instruments-form-image" type="file" wire:model="image" accept="image/jpeg,image/png,image/webp"
                class="form-input mt-1 block w-full" aria-describedby="instruments-form-image-help instruments-form-image-error">
            <p id="instruments-form-image-help" class="mt-1 text-xs text-slate-600">JPEG, PNG ou WebP, 5 Mo maximum. La photo reste privée dans l’espace de la chorale.</p>
            <x-input-error id="instruments-form-image-error" :messages="$errors->get('image')" class="mt-1" />
            <div wire:loading wire:target="image" class="mt-2 text-sm text-slate-600">Chargement de l’image…</div>
            @if($image)
                <img src="{{ $image->temporaryUrl() }}" alt="Nouvelle photo de l’instrument" class="mt-3 h-32 w-48 rounded-lg object-cover">
            @elseif($instrument?->image_path && !$remove_image)
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <img src="{{ route('instruments.image', $instrument) }}" alt="Photo actuelle de {{ $instrument->name }}" class="h-32 w-48 rounded-lg object-cover">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.live="remove_image" class="form-checkbox rounded"> Retirer cette photo</label>
                </div>
            @endif
        </div>
        <x-material-step number="1" title="Identifier le matériel" icon="cube"
            help="Donnez un nom au matériel, choisissez sa catégorie et indiquez le nombre d’objets identiques. Par exemple, trois microphones donnent trois codes différents.">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="instruments-form-name" value="Nom du matériel" />
                    <x-text-input id="instruments-form-name" type="text" name="name" aria-describedby="instruments-form-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" wire:model="name" class="mt-1 block w-full" required placeholder="Ex. : Microphone de chant" />
                    <x-input-error id="instruments-form-name-error" :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="instruments-form-category" value="Catégorie" />
                    <select id="instruments-form-category" aria-describedby="instruments-form-category-error" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" wire:model="category"
                        class="form-select mt-1">
                        @foreach ($this->categoryOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error id="instruments-form-category-error" :messages="$errors->get('category')" class="mt-1" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="instruments-form-serial_number" value="Numéro de série du fabricant" />
                    <x-text-input id="instruments-form-serial_number" type="text" name="serial_number" aria-describedby="material-serial-help instruments-form-serial_number-error" aria-invalid="{{ $errors->has('serial_number') ? 'true' : 'false' }}" wire:model="serial_number" class="mt-1 block w-full" />
                    <p id="material-serial-help" class="mt-1 text-sm text-slate-600">Si le fabricant a inscrit un numéro sur l’objet, indiquez-le ici. Le code de l’étiquette est créé séparément.</p>
                    <x-input-error id="instruments-form-serial_number-error" :messages="$errors->get('serial_number')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="instruments-form-quantity" value="Nombre d’objets identiques" />
                    <x-text-input id="instruments-form-quantity" type="number" name="quantity" aria-describedby="instruments-form-quantity-help instruments-form-quantity-error" aria-invalid="{{ $errors->has('quantity') ? 'true' : 'false' }}" wire:model="quantity" class="mt-1 block w-full" min="1" />
                    <p id="instruments-form-quantity-help" class="mt-1 text-sm text-slate-600">Exemple : pour 3 microphones identiques, indiquez 3. Chaque objet reçoit un code différent. Pour en retirer un, utilisez la page des étiquettes.</p><x-input-error id="instruments-form-quantity-error" :messages="$errors->get('quantity')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="instruments-form-condition" value="État" />
                    <select id="instruments-form-condition" aria-describedby="instruments-form-condition-error" aria-invalid="{{ $errors->has('condition') ? 'true' : 'false' }}" wire:model="condition"
                        class="form-select mt-1">
                        @foreach ($this->conditionOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error id="instruments-form-condition-error" :messages="$errors->get('condition')" class="mt-1" />
                </div>
            </div>

        </x-material-step>
        <x-material-step number="2" title="Savoir où le retrouver" icon="users"
            help="Indiquez le lieu de rangement et la personne responsable, si vous les connaissez. La disponibilité est calculée à partir des prêts enregistrés.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="instruments-form-location" value="Emplacement" />
                    <x-text-input id="instruments-form-location" type="text" name="location" aria-describedby="instruments-form-location-error" aria-invalid="{{ $errors->has('location') ? 'true' : 'false' }}" wire:model="location" class="mt-1 block w-full" />
                    <x-input-error id="instruments-form-location-error" :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="instruments-form-custodian_id" value="Responsable" />
                    <select id="instruments-form-custodian_id" aria-describedby="instruments-form-custodian_id-error" aria-invalid="{{ $errors->has('custodian_id') ? 'true' : 'false' }}" wire:model="custodian_id"
                        class="form-select mt-1">
                        <option value="">—</option>
                        @foreach ($this->custodians as $member)
                            <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                    <x-input-error id="instruments-form-custodian_id-error" :messages="$errors->get('custodian_id')" class="mt-1" />
                </div>
            </div>

            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <input type="checkbox" disabled @checked($is_borrowed) title="Disponibilité calculée depuis les mouvements"
                    class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                <span class="text-sm font-medium text-slate-700">Instrument actuellement sorti ou prêté</span>
            </label>

            <div>
                <x-input-label for="instruments-form-notes" value="Notes" />
                <textarea id="instruments-form-notes" aria-describedby="instruments-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                    class="form-textarea mt-1"></textarea>
                <x-input-error id="instruments-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
            </div>

        </x-material-step>
        <details class="rounded-2xl border border-slate-200 p-4" @if($errors->hasAny(['purchase_on', 'purchase_price', 'current_value'])) open @endif>
            <summary class="help-control cursor-pointer font-semibold text-slate-900">Informations d’achat et valeur — facultatif</summary>
            <div class="mt-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="instruments-form-purchase_on" value="Date d'acquisition" />
                <x-text-input id="instruments-form-purchase_on" type="date" name="purchase_on" aria-describedby="instruments-form-purchase_on-error" aria-invalid="{{ $errors->has('purchase_on') ? 'true' : 'false' }}" wire:model="purchase_on" class="mt-1 block w-full" />
                <x-input-error id="instruments-form-purchase_on-error" :messages="$errors->get('purchase_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-purchase_price" value="Prix d'achat" />
                <x-text-input id="instruments-form-purchase_price" type="number" step="0.01" name="purchase_price" aria-describedby="instruments-form-purchase_price-error" aria-invalid="{{ $errors->has('purchase_price') ? 'true' : 'false' }}" wire:model="purchase_price" class="mt-1 block w-full" min="0" />
                <x-input-error id="instruments-form-purchase_price-error" :messages="$errors->get('purchase_price')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-current_value" value="Valeur actuelle" />
                <x-text-input id="instruments-form-current_value" type="number" step="0.01" name="current_value" aria-describedby="instruments-form-current_value-error" aria-invalid="{{ $errors->has('current_value') ? 'true' : 'false' }}" wire:model="current_value" class="mt-1 block w-full" min="0" />
                <x-input-error id="instruments-form-current_value-error" :messages="$errors->get('current_value')" class="mt-1" />
            </div>
        </div>

            </div>
        </details>
        <p class="text-sm text-slate-700">Vérifiez le nom et le nombre d’objets avant d’enregistrer. Les codes sont attribués automatiquement.</p>
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
