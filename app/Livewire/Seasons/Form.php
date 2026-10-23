<?php

namespace App\Livewire\Seasons;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\LiturgicalSeason;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_LITURGIQUE;

    public ?LiturgicalSeason $season = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|date')]
    public ?string $starts_on = null;

    #[Validate('nullable|date|after_or_equal:starts_on')]
    public ?string $ends_on = null;

    #[Validate('required')]
    public string $color = 'violet';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = null;

    /**
     * Couleurs liturgiques proposées.
     */
    public array $colors = ['violet', 'red', 'green', 'blue', 'amber', 'rose', 'indigo', 'teal'];

    public function mount(?LiturgicalSeason $season = null): void
    {
        $this->authorizeManage();

        $this->season = $season;

        if ($season) {
            $this->fill([
                'name' => $season->name,
                'starts_on' => $season->starts_on?->format('Y-m-d'),
                'ends_on' => $season->ends_on?->format('Y-m-d'),
                'color' => $season->color,
                'description' => $season->description,
            ]);
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'starts_on' => $this->starts_on ?: null,
            'ends_on' => $this->ends_on ?: null,
            'color' => $this->color,
            'description' => $this->description,
        ];

        if ($this->season) {
            $this->season->update($data);
            $message = 'Temps liturgique mis à jour.';
        } else {
            $this->season = LiturgicalSeason::create($data);
            $message = 'Temps liturgique enregistré.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    public function render(): View
    {
        return view('livewire.seasons.form');
    }
}
