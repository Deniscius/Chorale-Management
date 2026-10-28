<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Activity;
use App\Models\Choir;
use App\Models\ChoirCommission;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_ACTIVITES;

    public ?Activity $activity = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate]
    public string $type = 'repetition';

    #[Validate('nullable|string|max:3000')]
    public ?string $description = null;

    #[Validate('required|date')]
    public ?string $starts_at = null;

    #[Validate('nullable|date|after_or_equal:starts_at')]
    public ?string $ends_at = null;

    #[Validate('nullable|string|max:255')]
    public ?string $venue = null;

    #[Validate('required|exists:choir_commissions,id')]
    public ?string $choir_commission_id = null;

    #[Validate('nullable|exists:choirs,id')]
    public ?string $choir_id = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $budget_allocated = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $budget_spent = null;

    #[Validate]
    public string $status = 'planifiee';

    #[Validate('nullable|string|max:5000')]
    public ?string $report = null;

    #[Validate('boolean')]
    public bool $is_public = false;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ActivityType::class)],
            'status' => ['required', Rule::enum(ActivityStatus::class)],
        ];
    }

    public function mount(?Activity $activity = null): void
    {
        $this->authorizeManage();

        $activity = $activity?->exists ? $activity : null;
        $this->activity = $activity;

        if ($activity) {
            $this->fill([
                'title' => $activity->title,
                'type' => $activity->type->value,
                'description' => $activity->description,
                'starts_at' => $activity->starts_at?->format('Y-m-d H:i'),
                'ends_at' => $activity->ends_at?->format('Y-m-d H:i'),
                'venue' => $activity->venue,
                'choir_commission_id' => $activity->choir_commission_id,
                'choir_id' => $activity->choir_id,
                'budget_allocated' => $activity->budget_allocated,
                'budget_spent' => $activity->budget_spent,
                'status' => $activity->status->value,
                'report' => $activity->report,
                'is_public' => (bool) $activity->is_public,
            ]);
        } else {
            $this->starts_at = now()->addWeek()->format('Y-m-d H:i');
            $this->choir_id = Choir::query()->value('id');
            $this->choir_commission_id = ChoirCommission::query()
                ->whereNull('archived_at')
                ->orderByRaw("CASE WHEN module_key = 'activites' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->value('id');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'venue' => $this->venue,
            'organizer_id' => null,
            'choir_commission_id' => (int) $this->choir_commission_id,
            'choir_id' => $this->choir_id !== null && $this->choir_id !== '' ? (int) $this->choir_id : null,
            'budget_allocated' => $this->budget_allocated !== null && $this->budget_allocated !== '' ? (float) $this->budget_allocated : 0,
            'budget_spent' => $this->budget_spent !== null && $this->budget_spent !== '' ? (float) $this->budget_spent : 0,
            'status' => $this->status,
            'report' => $this->report,
            'is_public' => $this->is_public,
        ];

        if ($this->activity) {
            $this->activity->update($data);
            $message = 'Activité mise à jour.';
        } else {
            $this->activity = Activity::create($data + ['created_by' => auth()->id()]);
            $message = 'Activité enregistrée.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function commissions()
    {
        return ChoirCommission::query()
            ->where(fn ($query) => $query->whereNull('archived_at')->when(
                $this->activity?->choir_commission_id,
                fn ($query, $id) => $query->orWhere('id', $id),
            ))
            ->orderByRaw("CASE WHEN module_key = 'activites' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return ActivityType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ActivityStatus::options();
    }

    public function render(): View
    {
        return view('livewire.activities.form');
    }
}
