<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="type" value="Nature" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondence_date" value="Date du courrier" />
                <x-text-input id="correspondence_date" type="date" wire:model="correspondence_date" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('correspondence_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="received_at" value="Reçu le" />
                <x-text-input id="received_at" type="date" wire:model="received_at" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('received_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="sent_at" value="Expédié le" />
                <x-text-input id="sent_at" type="date" wire:model="sent_at" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('sent_at')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="sender_name" value="Expéditeur" />
                <x-text-input id="sender_name" type="text" wire:model="sender_name" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('sender_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="sender_organization" value="Organisation" />
                <x-text-input id="sender_organization" type="text" wire:model="sender_organization" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('sender_organization')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="recipient_name" value="Destinataire" />
                <x-text-input id="recipient_name" type="text" wire:model="recipient_name" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('recipient_name')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="subject" value="Objet" />
            <x-text-input id="subject" type="text" wire:model="subject" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('subject')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="body" value="Contenu / résumé" />
            <textarea id="body" wire:model="body" rows="5"
                class="form-textarea mt-1"></textarea>
            <x-input-error :messages="$errors->get('body')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="channel" value="Canal" />
                <select id="channel" wire:model="channel"
                    class="form-select mt-1">
                    @foreach ($this->channelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('channel')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="confidentiality" value="Confidentialité" />
                <select id="confidentiality" wire:model="confidentiality"
                    class="form-select mt-1">
                    @foreach ($this->confidentialityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('confidentiality')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="service_id" value="Service destinataire" />
                <select id="service_id" wire:model="service_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('service_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="assigned_to" value="Chargé du suivi" />
                <select id="assigned_to" wire:model="assigned_to"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->assignees as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('assigned_to')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="status" value="Statut" />
                <select id="status" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="document_path" value="DocumentJoint" />
                <x-text-input id="document_path" type="text" wire:model="document_path" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('document_path')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="notes" value="Notes internes" />
                <x-text-input id="notes" type="text" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>
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
