<?php

namespace App\Livewire\Rehearsals;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Rehearsal;
use App\Models\Work;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    public ?Rehearsal $rehearsal = null;

    #[Validate('required|date')]
    public ?string $date = null;

    #[Validate('nullable|date_format:H:i')]
    public ?string $starts_at = null;

    #[Validate('nullable|date_format:H:i|after_or_equal:starts_at')]
    public ?string $ends_at = null;

    #[Validate('nullable|string|max:255')]
    public ?string $venue = null;

    #[Validate]
    public string $type = 'repetition';

    #[Validate]
    public string $status = 'planifiee';

    /**
     * Identifiants des œuvres au programme.
     *
     * @var array<int, int>
     */
    #[Validate('array|max:500')]
    public array $works = [];

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(RehearsalType::class)],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'works.*' => ['integer', 'distinct', Rule::exists('works', 'id')->whereNull('deleted_at')],
        ];
    }

    public function mount(?Rehearsal $rehearsal = null): void
    {
        $this->authorizeManage();

        $rehearsal = $rehearsal?->exists ? $rehearsal : null;
        $this->rehearsal = $rehearsal;

        if ($rehearsal) {
            $this->fill([
                'date' => $rehearsal->date->format('Y-m-d'),
                'starts_at' => $rehearsal->starts_at ? substr($rehearsal->starts_at, 0, 5) : null,
                'ends_at' => $rehearsal->ends_at ? substr($rehearsal->ends_at, 0, 5) : null,
                'venue' => $rehearsal->venue,
                'type' => $rehearsal->type->value,
                'status' => $rehearsal->status->value,
                'works' => $rehearsal->works->pluck('id')->all(),
                'notes' => $rehearsal->notes,
            ]);
        } else {
            $this->date = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'date' => $this->date,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'venue' => $this->venue,
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        $editing = $this->rehearsal !== null;
        $this->rehearsal = DB::transaction(function () use ($data) {
            $entry = $this->rehearsal ? Rehearsal::query()->lockForUpdate()->findOrFail($this->rehearsal->id) : new Rehearsal(['created_by' => auth()->id()]);
            $entry->fill($data)->save();
            $entry->works()->sync(collect($this->works)->map(fn ($id) => (int) $id)->all());

            return $entry;
        });
        $message = $editing ? 'Répétition mise à jour.' : 'Répétition enregistrée.';

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function availableWorks()
    {
        return Work::query()
            ->where(fn ($q) => $q->where('is_repertoire', true)->orWhereIn('id', $this->works))
            ->orderBy('title')
            ->get(['id', 'title', 'type', 'duration_minutes']);
    }

    #[Computed]
    public function typeOptions(): array
    {
        return RehearsalType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return EventStatus::options();
    }

    public function render(): View
    {
        return view('livewire.rehearsals.form');
    }
}
