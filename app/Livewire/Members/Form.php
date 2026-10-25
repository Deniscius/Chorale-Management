<?php

namespace App\Livewire\Members;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Enums\Voice;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Choir;
use App\Models\Member;
use App\Observers\PersonalInformationObserver;
use App\Services\MemberOnboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_MEMBERS;

    public ?Member $member = null;

    #[Locked]
    public ?string $activationLink = null;

    #[Validate('required|string|max:100')]
    public string $first_name = '';

    #[Validate('required|string|max:100')]
    public string $last_name = '';

    #[Validate]
    public ?string $email = null;

    #[Validate('nullable|string|max:30')]
    public ?string $phone = null;

    #[Validate('boolean')]
    public bool $notify_email = true;

    #[Validate('boolean')]
    public bool $notify_sms = false;

    #[Validate('nullable|date|before:today')]
    public ?string $birth_date = null;

    #[Validate]
    public ?string $voice = null;

    #[Validate('nullable')]
    public ?string $part = null;

    #[Validate]
    public ?string $status = 'actif';

    #[Validate('nullable|exists:choirs,id')]
    public ?int $choir_id = null;

    #[Validate('nullable|date|after_or_equal:1900-01-01')]
    public ?string $joined_on = null;

    #[Validate('nullable|string|max:255')]
    public ?string $profession = null;

    #[Validate('nullable|string|max:255')]
    public ?string $address = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('members', 'email')->ignore($this->member)],
            'voice' => ['nullable', Rule::enum(Voice::class)],
            'status' => ['required', Rule::enum(MemberStatus::class)],
        ];
    }

    public function mount(?Member $member = null): void
    {
        $this->authorizeManage();

        $member = $member?->exists ? $member : null;
        $this->member = $member;

        if ($member) {
            $this->fill([
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone,
                'notify_email' => (bool) $member->notify_email,
                'notify_sms' => (bool) $member->notify_sms,
                'birth_date' => $member->birth_date?->format('Y-m-d'),
                'voice' => $member->voice?->value,
                'part' => $member->part,
                'status' => $member->status->value,
                'choir_id' => $member->choir_id,
                'joined_on' => $member->joined_on?->format('Y-m-d'),
                'profession' => $member->profession,
                'address' => $member->address,
                'notes' => $member->notes,
            ]);
        } else {
            $this->joined_on = now()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        if ($this->activationLink !== null) {
            return;
        }

        $this->validate();

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'notify_email' => $this->notify_email,
            'notify_sms' => $this->notify_sms,
            'birth_date' => $this->birth_date ?: null,
            'voice' => $this->voice,
            'part' => $this->part,
            'status' => $this->status,
            'choir_id' => $this->choir_id,
            'joined_on' => $this->joined_on ?: null,
            'profession' => $this->profession,
            'address' => $this->address,
            'notes' => $this->notes,
        ];

        if ($this->member) {
            $current = Member::findOrFail($this->member->id);
            $personalFields = PersonalInformationObserver::MEMBER_FIELDS;
            foreach (Arr::only($data, $personalFields) as $field => $value) {
                $original = $field === 'birth_date' ? $current->birth_date?->format('Y-m-d') : $current->getAttribute($field);
                abort_unless($original === $value || (blank($original) && blank($value)), 403, 'Les informations personnelles se modifient dans Mon profil.');
            }
            $this->member->update(Arr::except($data, $personalFields));
            $message = 'Choriste mis à jour.';
        } else {
            DB::transaction(function () use ($data) {
                $this->member = Member::create($data);
                if (auth()->user()?->can('manage-users')) {
                    $this->activationLink = app(MemberOnboarding::class)->issue($this->member);
                }
            });
            $message = 'Choriste enregistré.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        if (! $this->activationLink) {
            $this->dispatch('close-form');
        }
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function voiceOptions(): array
    {
        return Voice::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return MemberStatus::options();
    }

    public function render(): View
    {
        return view('livewire.members.form');
    }
}
