<?php

namespace App\Livewire\Works;

use App\Enums\Difficulty;
use App\Enums\UserRole;
use App\Enums\Voice;
use App\Enums\WorkType;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\LiturgicalSeason;
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

    public ?Work $work = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate]
    public string $type = 'motet';

    #[Validate('nullable|exists:liturgical_seasons,id')]
    public ?int $season_id = null;

    #[Validate('nullable|string|max:255')]
    public ?string $composer = null;

    #[Validate('nullable|string|max:255')]
    public ?string $arranger = null;

    #[Validate('nullable|string|max:255')]
    public ?string $text_author = null;

    #[Validate('nullable|string|max:100')]
    public ?string $language = null;

    #[Validate('nullable|string|max:20')]
    public ?string $key_signature = null;

    #[Validate('nullable|string|max:50')]
    public ?string $mode = null;

    #[Validate('nullable|integer|min:20|max:300')]
    public ?string $tempo = null;

    #[Validate('nullable|integer|min:1|max:600')]
    public ?string $duration_minutes = null;

    #[Validate('nullable|integer|min:1|max:5')]
    public int $difficulty = 3;

    #[Validate('nullable|integer|min:1|max:12')]
    public ?string $min_voices = null;

    /**
     * Voix requises, séparées par des virgules.
     */
    #[Validate('nullable|string|max:255')]
    public ?string $required_voices = null;

    /**
     * Instrumentation, séparée par des virgules.
     */
    #[Validate('nullable|string|max:255')]
    public ?string $instrumentation = null;

    #[Validate('boolean')]
    public bool $is_repertoire = true;

    #[Validate('nullable|string|max:255')]
    public ?string $pdf_path = null;

    #[Validate('nullable|string|max:255')]
    public ?string $midi_path = null;

    #[Validate('nullable|string|max:255')]
    public ?string $lyrics_path = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(WorkType::class)],
        ];
    }

    public function mount(?Work $work = null): void
    {
        $this->authorizeManage();

        $this->work = $work;

        if ($work) {
            $this->fill([
                'title' => $work->title,
                'type' => $work->type->value,
                'season_id' => $work->season_id,
                'composer' => $work->composer,
                'arranger' => $work->arranger,
                'text_author' => $work->text_author,
                'language' => $work->language,
                'key_signature' => $work->key_signature,
                'mode' => $work->mode,
                'tempo' => $work->tempo,
                'duration_minutes' => $work->duration_minutes,
                'difficulty' => $work->difficulty->value,
                'min_voices' => $work->min_voices,
                'required_voices' => $work->required_voices ? implode(', ', $work->required_voices) : null,
                'instrumentation' => $work->instrumentation ? implode(', ', $work->instrumentation) : null,
                'is_repertoire' => $work->is_repertoire,
                'pdf_path' => $work->pdf_path,
                'midi_path' => $work->midi_path,
                'lyrics_path' => $work->lyrics_path,
                'notes' => $work->notes,
            ]);
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'title' => $this->title,
            'type' => $this->type,
            'season_id' => $this->season_id,
            'composer' => $this->composer,
            'arranger' => $this->arranger,
            'text_author' => $this->text_author,
            'language' => $this->language,
            'key_signature' => $this->key_signature,
            'mode' => $this->mode,
            'tempo' => $this->tempo !== null && $this->tempo !== '' ? (int) $this->tempo : null,
            'duration_minutes' => $this->duration_minutes !== null && $this->duration_minutes !== '' ? (int) $this->duration_minutes : null,
            'difficulty' => $this->difficulty,
            'min_voices' => $this->min_voices !== null && $this->min_voices !== '' ? (int) $this->min_voices : null,
            'required_voices' => $this->toList($this->required_voices),
            'instrumentation' => $this->toList($this->instrumentation),
            'is_repertoire' => $this->is_repertoire,
            'pdf_path' => $this->pdf_path,
            'midi_path' => $this->midi_path,
            'lyrics_path' => $this->lyrics_path,
            'notes' => $this->notes,
        ];

        if ($this->work) {
            $this->work->update($data);
            $message = 'Œuvre mise à jour.';
        } else {
            $this->work = Work::create($data);
            $message = 'Œuvre enregistrée.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    /**
     * Transforme une saisie séparée par des virgules en tableau.
     */
    private function toList(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function seasons()
    {
        return LiturgicalSeason::orderBy('starts_on')->orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return WorkType::options();
    }

    #[Computed]
    public function difficultyOptions(): array
    {
        return Difficulty::options();
    }

    #[Computed]
    public function voiceOptions(): array
    {
        return Voice::options();
    }

    public function render(): View
    {
        return view('livewire.works.form');
    }
}
