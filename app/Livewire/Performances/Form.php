<?php

namespace App\Livewire\Performances;

use App\Enums\EventStatus;
use App\Enums\PerformanceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Performance;
use App\Models\Work;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    public ?Performance $performance = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate]
    public string $type = 'messe';

    #[Validate('required|date')]
    public ?string $scheduled_at = null;

    #[Validate('nullable|string|max:255')]
    public ?string $venue = null;

    #[Validate('nullable|string|max:255')]
    public ?string $audience = null;

    #[Validate]
    public string $status = 'planifiee';

    #[Validate('nullable|numeric|min:0')]
    public ?string $budget = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $revenue = null;

    /**
     * Identifiants des œuvres au programme.
     *
     * @var array<int, int>
     */
    #[Validate('array')]
    public array $works = [];

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PerformanceType::class)],
            'status' => ['required', Rule::enum(EventStatus::class)],
        ];
    }

    public function mount(?Performance $performance = null): void
    {
        $this->authorizeManage();

        $this->performance = $performance;

        if ($performance) {
            $this->fill([
                'title' => $performance->title,
                'type' => $performance->type->value,
                'scheduled_at' => $performance->scheduled_at->format('Y-m-d\TH:i'),
                'venue' => $performance->venue,
                'audience' => $performance->audience,
                'status' => $performance->status->value,
                'budget' => $performance->budget,
                'revenue' => $performance->revenue,
                'works' => $performance->works->pluck('id')->all(),
                'notes' => $performance->notes,
            ]);
        } else {
            $this->scheduled_at = now()->addWeek()->startOfHour()->format('Y-m-d\TH:i');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'title' => $this->title,
            'type' => $this->type,
            'scheduled_at' => $this->scheduled_at,
            'venue' => $this->venue,
            'audience' => $this->audience,
            'status' => $this->status,
            'budget' => $this->budget !== null && $this->budget !== '' ? (float) $this->budget : 0,
            'revenue' => $this->revenue !== null && $this->revenue !== '' ? (float) $this->revenue : 0,
            'notes' => $this->notes,
        ];

        if ($this->performance) {
            $this->performance->update($data);
            $message = 'Célébration mise à jour.';
        } else {
            $this->performance = Performance::create($data + ['created_by' => auth()->id()]);
            $message = 'Célébration enregistrée.';
        }

        $ids = collect($this->works)->map(fn ($id) => (int) $id)->unique()->values();
        $this->performance->works()->sync($ids->all());

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function availableWorks()
    {
        return Work::query()
            ->where('is_repertoire', true)
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return PerformanceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return EventStatus::options();
    }

    public function render(): View
    {
        return view('livewire.performances.form');
    }
}
