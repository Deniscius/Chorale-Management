<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Activity;
use App\Models\Choir;
use App\Models\ChoirCommission;
use App\Rules\ReadableText;
use App\Services\ActivityParticipationFee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_ACTIVITES;

    public ?Activity $activity = null;

    #[Validate]
    public string $title = '';

    #[Validate]
    public string $type = 'repetition';

    #[Validate('nullable|string|max:3000')]
    public ?string $description = null;

    #[Validate('required|date')]
    public ?string $starts_at = null;

    #[Validate('nullable|date|after_or_equal:starts_at')]
    public ?string $ends_at = null;

    #[Validate]
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

    #[Validate('boolean')]
    public bool $participation_fee_enabled = false;

    #[Validate('nullable|numeric|min:1')]
    public ?string $participation_fee_amount = null;

    #[Validate('nullable|date')]
    public ?string $participation_fee_due_on = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', new ReadableText],
            'venue' => ['nullable', 'string', 'max:255', new ReadableText],
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
                'starts_at' => $activity->starts_at?->format('Y-m-d\\TH:i'),
                'ends_at' => $activity->ends_at?->format('Y-m-d\\TH:i'),
                'venue' => $activity->venue,
                'choir_commission_id' => $activity->choir_commission_id,
                'choir_id' => $activity->choir_id,
                'budget_allocated' => $activity->budget_allocated,
                'budget_spent' => $activity->budget_spent,
                'status' => $activity->status->value,
                'report' => $activity->report,
                'is_public' => (bool) $activity->is_public,
                'participation_fee_enabled' => (bool) $activity->participation_fee_enabled,
                'participation_fee_amount' => $activity->participation_fee_amount,
                'participation_fee_due_on' => $activity->participation_fee_due_on?->format('Y-m-d'),
            ]);
        } else {
            $this->starts_at = now()->addWeek()->format('Y-m-d\\TH:i');
            $this->choir_id = Choir::query()->value('id');
            $this->choir_commission_id = ChoirCommission::query()
                ->whereNull('archived_at')
                ->orderByRaw("CASE WHEN module_key = 'activites' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->value('id');
        }
    }

    /** Fill the native date picker with a useful future appointment. */
    public function useSuggestedDate(string $preset): void
    {
        $startsAt = match ($preset) {
            'today' => now()->startOfHour(),
            'tomorrow' => now()->addDay()->setTime(19, 0),
            'next_week' => now()->addWeek()->setTime(19, 0),
            default => throw new \InvalidArgumentException('Suggestion de date inconnue.'),
        };

        $this->starts_at = $startsAt->format('Y-m-d\\TH:i');
        if (blank($this->ends_at)) {
            $this->ends_at = $startsAt->copy()->addHours(2)->format('Y-m-d\\TH:i');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->title = trim(preg_replace('/\\s+/u', ' ', $this->title));
        $this->venue = filled($this->venue) ? trim(preg_replace('/\\s+/u', ' ', $this->venue)) : null;
        $this->validate();
        if ($this->participation_fee_enabled && blank($this->participation_fee_amount)) {
            $this->addError('participation_fee_amount', 'Indiquez le montant demandé pour participer à cette activité.');

            return;
        }

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
            'participation_fee_enabled' => $this->participation_fee_enabled,
            'participation_fee_amount' => $this->participation_fee_enabled ? (float) $this->participation_fee_amount : null,
            'participation_fee_due_on' => $this->participation_fee_enabled ? ($this->participation_fee_due_on ?: substr((string) $this->starts_at, 0, 10)) : null,
        ];

        $editing = $this->activity !== null;
        try {
            $this->activity = DB::transaction(function () use ($data): Activity {
                $activity = $this->activity;
                if ($activity) {
                    $activity->update($data);
                } else {
                    $activity = Activity::create($data + ['created_by' => auth()->id()]);
                }
                if ($activity->participation_fee_enabled) {
                    app(ActivityParticipationFee::class)->syncExisting($activity);
                }

                return $activity;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Activity participation fee transaction failed.', [
                'tenant_id' => app(\App\Tenancy\TenantContext::class)->id(),
                'activity_id' => $this->activity?->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            report($exception);
            $this->addError('participation_fee_amount', 'La cotisation n’a pas pu être créée. L’activité n’a pas été modifiée.');

            return;
        }
        $message = $editing ? 'Activité mise à jour.' : 'Activité enregistrée.';

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
