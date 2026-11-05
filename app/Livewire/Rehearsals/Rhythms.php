<?php

namespace App\Livewire\Rehearsals;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Choir;
use App\Models\RehearsalRhythm;
use App\Services\RehearsalPlanner;
use App\Services\RehearsalRhythmRemoval;
use App\Services\RehearsalRhythmSynchronizer;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Rhythms extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    public string $choir_id = '';

    public string $weekday = '6';

    public string $starts_at = '15:00';

    public string $ends_at = '18:00';

    public string $venue = '';

    public string $effective_from = '';

    public string $effective_until = '';

    public bool $is_active = true;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeManage();
        $this->effective_from = today()->toDateString();
        $this->choir_id = (string) (Choir::query()->orderBy('id')->value('id') ?? '');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $rhythm = RehearsalRhythm::findOrFail($id);
        $this->editingId = $rhythm->id;
        $this->choir_id = (string) $rhythm->choir_id;
        $this->weekday = (string) $rhythm->weekday;
        $this->starts_at = substr($rhythm->starts_at, 0, 5);
        $this->ends_at = $rhythm->ends_at ? substr($rhythm->ends_at, 0, 5) : '';
        $this->venue = $rhythm->venue ?? '';
        $this->effective_from = $rhythm->effective_from->toDateString();
        $this->effective_until = $rhythm->effective_until?->toDateString() ?? '';
        $this->is_active = $rhythm->is_active;
    }

    public function save(RehearsalRhythmSynchronizer $synchronizer): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'choir_id' => ['required', 'integer', Rule::exists('choirs', 'id')->where('tenant_id', app(TenantContext::class)->requireId())],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'venue' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);
        $data['ends_at'] = $data['ends_at'] ?: null;
        $data['effective_until'] = $data['effective_until'] ?: null;
        try {
            $sync = DB::transaction(function () use ($data, $synchronizer): array {
                $rhythm = $this->editingId
                    ? RehearsalRhythm::query()->lockForUpdate()->findOrFail($this->editingId)
                    : new RehearsalRhythm;
                $previous = $rhythm->exists ? $rhythm->getRawOriginal() : null;

                // A future effective date creates a new version of the schedule.
                // The previous version remains valid through the day before, so
                // there is no unexplained gap in the choir calendar.
                if ($previous !== null
                    && CarbonImmutable::parse($data['effective_from'])->gt(CarbonImmutable::parse($previous['effective_from']))
                    && $this->scheduleChanged($data, $previous)) {
                    $until = CarbonImmutable::parse($data['effective_from'])->subDay()->toDateString();
                    $rhythm->update(['effective_until' => $until]);
                    $old = $synchronizer->synchronize($rhythm, $previous, true);

                    $replacement = new RehearsalRhythm;
                    $replacement->fill($data)->save();
                    $new = $synchronizer->synchronize($replacement, null, true);

                    return collect([$old, $new])->reduce(fn (array $total, array $counts) => [
                        'created' => $total['created'] + $counts['created'],
                        'updated' => $total['updated'] + $counts['updated'],
                        'cancelled' => $total['cancelled'] + $counts['cancelled'],
                        'protected' => $total['protected'] + $counts['protected'],
                    ], ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'protected' => 0]);
                }
                $rhythm->fill($data)->save();

                // Include appointments produced before rhythm linking existed.
                return $synchronizer->synchronize($rhythm, $previous, true);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->addError('weekday', 'Ce rythme est déjà enregistré pour cette chorale.');

            return;
        }

        $this->editingId = null;
        $this->reset('weekday', 'starts_at', 'ends_at', 'venue', 'effective_from', 'effective_until', 'is_active');
        $this->weekday = '6';
        $this->starts_at = '15:00';
        $this->ends_at = '18:00';
        $this->effective_from = today()->toDateString();
        $this->is_active = true;
        $this->dispatch('toast', type: 'success', message: 'Rythme enregistré et carnet synchronisé : '.$sync['created'].' séance(s) créée(s), '.$sync['updated'].' actualisée(s), '.$sync['cancelled'].' ancienne(s) annulée(s).'.($sync['protected'] ? ' '.$sync['protected'].' séance(s) personnalisée(s) ou avec historique conservée(s) : vérifiez-les dans les répétitions.' : ''));
    }

    public function remove(int $id, RehearsalRhythmRemoval $remover): void
    {
        $this->authorizeManage();
        $outcome = $remover->remove($id);
        if ($this->editingId === $id) {
            $this->editingId = null;
        }
        $this->dispatch('toast', type: 'success', message: $outcome === 'archived'
            ? 'Rythme désactivé et conservé pour les répétitions déjà programmées.'
            : 'Rythme sans historique supprimé définitivement.');
    }

    public function repairNotebook(int $id, RehearsalRhythmSynchronizer $synchronizer): void
    {
        $this->authorizeManage();
        $rhythm = RehearsalRhythm::query()->findOrFail($id);
        $result = $synchronizer->repairPreviouslyGenerated($rhythm);
        $this->dispatch('toast', type: 'success', message: 'Carnets vérifiés : '.$result['updated'].' séance(s) corrigée(s), '.
            $result['cancelled'].' ancienne(s) annulée(s), '.
            $result['created'].' nouvelle(s) programmée(s).'.
            ($result['protected'] ? ' '.$result['protected'].' séance(s) avec modifications ou historique à vérifier manuellement.' : '')
        );
    }

    public function generate(RehearsalPlanner $planner): void
    {
        $this->authorizeManage();
        $count = $planner->generate(CarbonImmutable::today(), CarbonImmutable::today()->addDays(45));
        $this->dispatch('toast', type: 'success', message: $count.' séance(s) ajoutée(s), aucune séance existante modifiée.');
    }

    public function render()
    {
        return view('livewire.rehearsals.rhythms', [
            'choirs' => Choir::query()->orderBy('name')->get(['id', 'name']),
            'rhythms' => RehearsalRhythm::query()->with('choir')->withCount('rehearsals')->orderBy('weekday')->orderBy('starts_at')->get(),
        ]);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $previous */
    private function scheduleChanged(array $data, array $previous): bool
    {
        foreach (['choir_id', 'weekday', 'starts_at', 'ends_at', 'venue', 'is_active'] as $key) {
            if ((string) ($data[$key] ?? '') !== (string) ($previous[$key] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
