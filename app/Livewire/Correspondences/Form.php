<?php

namespace App\Livewire\Correspondences;

use App\Enums\Confidentiality;
use App\Enums\CorrespondenceChannel;
use App\Enums\CorrespondenceStatus;
use App\Enums\CorrespondenceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Correspondence;
use App\Models\CorrespondenceService;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_COURRIERS;

    public ?Correspondence $correspondence = null;

    #[Validate('required')]
    public string $type = 'entrant';

    #[Validate('required|date')]
    public ?string $correspondence_date = null;

    #[Validate('nullable|date')]
    public ?string $received_at = null;

    #[Validate('nullable|date')]
    public ?string $sent_at = null;

    #[Validate('required|string|max:255')]
    public string $sender_name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $sender_organization = null;

    #[Validate('nullable|string|max:255')]
    public ?string $recipient_name = null;

    #[Validate('nullable|exists:correspondence_services,id')]
    public ?string $service_id = null;

    #[Validate('required|string|max:255')]
    public string $subject = '';

    #[Validate('nullable|string|max:5000')]
    public ?string $body = null;

    #[Validate('required')]
    public string $channel = 'courrier';

    #[Validate('required')]
    public string $confidentiality = 'normal';

    #[Validate('required')]
    public string $status = 'recu';

    #[Validate('nullable|exists:members,id')]
    public ?string $assigned_to = null;

    #[Validate('nullable|string|max:255')]
    public ?string $document_path = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    public function mount(?Correspondence $correspondence = null): void
    {
        $this->authorizeManage();

        $this->correspondence = $correspondence;

        if ($correspondence) {
            $this->fill([
                'type' => $correspondence->type->value,
                'correspondence_date' => $correspondence->correspondence_date->format('Y-m-d'),
                'received_at' => $correspondence->received_at?->format('Y-m-d'),
                'sent_at' => $correspondence->sent_at?->format('Y-m-d'),
                'sender_name' => $correspondence->sender_name,
                'sender_organization' => $correspondence->sender_organization,
                'recipient_name' => $correspondence->recipient_name,
                'service_id' => $correspondence->service_id,
                'subject' => $correspondence->subject,
                'body' => $correspondence->body,
                'channel' => $correspondence->channel->value,
                'confidentiality' => $correspondence->confidentiality->value,
                'status' => $correspondence->status->value,
                'assigned_to' => $correspondence->assigned_to,
                'document_path' => $correspondence->document_path,
                'notes' => $correspondence->notes,
            ]);
        } else {
            $this->correspondence_date = today()->format('Y-m-d');
            $this->received_at = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'type' => $this->type,
            'correspondence_date' => $this->correspondence_date,
            'received_at' => $this->received_at ?: null,
            'sent_at' => $this->sent_at ?: null,
            'sender_name' => $this->sender_name,
            'sender_organization' => $this->sender_organization,
            'recipient_name' => $this->recipient_name,
            'service_id' => $this->service_id !== null && $this->service_id !== '' ? (int) $this->service_id : null,
            'subject' => $this->subject,
            'body' => $this->body,
            'channel' => $this->channel,
            'confidentiality' => $this->confidentiality,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to !== null && $this->assigned_to !== '' ? (int) $this->assigned_to : null,
            'document_path' => $this->document_path,
            'notes' => $this->notes,
        ];

        if ($this->correspondence) {
            $this->correspondence->update($data);
            $message = 'Courrier mis à jour.';
        } else {
            $this->correspondence = Correspondence::create($data + ['created_by' => auth()->id()]);
            $message = 'Courrier enregistré ('.$this->correspondence->reference.').';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    /**
     * La date de réception suit automatiquement le type de courrier.
     */
    public function updatedType(): void
    {
        if ($this->type === CorrespondenceType::Sortant->value) {
            $this->sent_at = $this->correspondence_date;
            $this->received_at = null;
        } else {
            $this->received_at = $this->correspondence_date;
            $this->sent_at = null;
        }
    }

    #[Computed]
    public function services()
    {
        return CorrespondenceService::orderBy('name')->get();
    }

    #[Computed]
    public function assignees()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return CorrespondenceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return CorrespondenceStatus::options();
    }

    #[Computed]
    public function channelOptions(): array
    {
        return CorrespondenceChannel::options();
    }

    #[Computed]
    public function confidentialityOptions(): array
    {
        return Confidentiality::options();
    }

    public function render(): View
    {
        return view('livewire.correspondences.form');
    }
}
