<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="correspondences-form-type" value="Nature" />
                <select id="correspondences-form-type" aria-describedby="correspondences-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-correspondence_date" value="Date du courrier" />
                <x-text-input id="correspondences-form-correspondence_date" type="date" name="correspondence_date" aria-describedby="correspondences-form-correspondence_date-error" aria-invalid="{{ $errors->has('correspondence_date') ? 'true' : 'false' }}" wire:model="correspondence_date" class="mt-1 block w-full" required />
                <x-input-error id="correspondences-form-correspondence_date-error" :messages="$errors->get('correspondence_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-received_at" value="Reçu le" />
                <x-text-input id="correspondences-form-received_at" type="date" name="received_at" aria-describedby="correspondences-form-received_at-error" aria-invalid="{{ $errors->has('received_at') ? 'true' : 'false' }}" wire:model="received_at" class="mt-1 block w-full" />
                <x-input-error id="correspondences-form-received_at-error" :messages="$errors->get('received_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-sent_at" value="Expédié le" />
                <x-text-input id="correspondences-form-sent_at" type="date" name="sent_at" aria-describedby="correspondences-form-sent_at-error" aria-invalid="{{ $errors->has('sent_at') ? 'true' : 'false' }}" wire:model="sent_at" class="mt-1 block w-full" />
                <x-input-error id="correspondences-form-sent_at-error" :messages="$errors->get('sent_at')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="correspondences-form-sender_name" value="Expéditeur" />
                <x-text-input id="correspondences-form-sender_name" type="text" name="sender_name" aria-describedby="correspondences-form-sender_name-error" aria-invalid="{{ $errors->has('sender_name') ? 'true' : 'false' }}" wire:model="sender_name" class="mt-1 block w-full" required />
                <x-input-error id="correspondences-form-sender_name-error" :messages="$errors->get('sender_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-sender_organization" value="Organisation" />
                <x-text-input id="correspondences-form-sender_organization" type="text" name="sender_organization" aria-describedby="correspondences-form-sender_organization-error" aria-invalid="{{ $errors->has('sender_organization') ? 'true' : 'false' }}" wire:model="sender_organization" class="mt-1 block w-full" />
                <x-input-error id="correspondences-form-sender_organization-error" :messages="$errors->get('sender_organization')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-recipient_name" value="Destinataire" />
                <x-text-input id="correspondences-form-recipient_name" type="text" name="recipient_name" aria-describedby="correspondences-form-recipient_name-error" aria-invalid="{{ $errors->has('recipient_name') ? 'true' : 'false' }}" wire:model="recipient_name" class="mt-1 block w-full" />
                <x-input-error id="correspondences-form-recipient_name-error" :messages="$errors->get('recipient_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-recipient_member_id" value="Membre destinataire" />
                <select id="correspondences-form-recipient_member_id" aria-describedby="correspondences-form-recipient_member_id-error" aria-invalid="{{ $errors->has('recipient_member_id') ? 'true' : 'false' }}" wire:model.live="recipient_member_id" class="form-select mt-1">
                    <option value="">Toute la chorale</option>
                    @foreach ($this->assignees as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Un courrier destiné à un membre apparaît uniquement dans son carnet.</p>
                <x-input-error id="correspondences-form-recipient_member_id-error" :messages="$errors->get('recipient_member_id')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="correspondences-form-subject" value="Objet" />
            <x-text-input id="correspondences-form-subject" type="text" name="subject" aria-describedby="correspondences-form-subject-error" aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" wire:model="subject" class="mt-1 block w-full" required />
            <x-input-error id="correspondences-form-subject-error" :messages="$errors->get('subject')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="correspondences-form-body" value="Contenu / résumé" />
            <textarea id="correspondences-form-body" aria-describedby="correspondences-form-body-error" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}" wire:model="body" rows="5"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="correspondences-form-body-error" :messages="$errors->get('body')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="correspondences-form-channel" value="Canal" />
                <select id="correspondences-form-channel" aria-describedby="correspondences-form-channel-error" aria-invalid="{{ $errors->has('channel') ? 'true' : 'false' }}" wire:model="channel"
                    class="form-select mt-1">
                    @foreach ($this->channelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-channel-error" :messages="$errors->get('channel')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-confidentiality" value="Confidentialité" />
                <select id="correspondences-form-confidentiality" aria-describedby="correspondences-form-confidentiality-error" aria-invalid="{{ $errors->has('confidentiality') ? 'true' : 'false' }}" wire:model="confidentiality"
                    class="form-select mt-1">
                    @foreach ($this->confidentialityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-confidentiality-error" :messages="$errors->get('confidentiality')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-service_id" value="Service destinataire" />
                <select id="correspondences-form-service_id" aria-describedby="correspondences-form-service_id-error" aria-invalid="{{ $errors->has('service_id') ? 'true' : 'false' }}" wire:model="service_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-service_id-error" :messages="$errors->get('service_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="correspondences-form-assigned_to" value="Chargé du suivi" />
                <select id="correspondences-form-assigned_to" aria-describedby="correspondences-form-assigned_to-error" aria-invalid="{{ $errors->has('assigned_to') ? 'true' : 'false' }}" wire:model="assigned_to"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->assignees as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-assigned_to-error" :messages="$errors->get('assigned_to')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="correspondences-form-status" value="Statut" />
                <select id="correspondences-form-status" aria-describedby="correspondences-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="correspondences-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                @if (\App\Enums\Commission::Secretariat->canManageArchive(auth()->user()))
                    <x-input-label for="correspondences-form-attachment" value="Pièce jointe privée" />
                    <input id="correspondences-form-attachment" type="file" aria-describedby="correspondences-form-attachment-error" aria-invalid="{{ $errors->has('attachment') ? 'true' : 'false' }}" wire:model="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp" class="form-input" />
                    <p class="text-xs text-slate-500">10 Mo maximum. Le partage se gère dans les archives du courrier.</p>
                    <x-input-error id="correspondences-form-attachment-error" :messages="$errors->get('attachment')" />
                @else
                    <p class="text-sm text-slate-500">Les pièces jointes sont gérées par le responsable du secrétariat et son associé.</p>
                @endif
            </div>

            <div>
                <x-input-label for="correspondences-form-notes" value="Notes internes" />
                <x-text-input id="correspondences-form-notes" type="text" name="notes" aria-describedby="correspondences-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error id="correspondences-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save,attachment"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
