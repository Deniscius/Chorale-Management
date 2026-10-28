<?php

namespace App\Livewire\Settings;

use App\Models\LetterheadTemplate;
use App\Services\LetterheadManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Letterheads extends Component
{
    public array $heights = [];

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function reprocess(int $id, LetterheadManager $manager): void
    {
        $this->authorizeManage();
        $template = LetterheadTemplate::query()->findOrFail($id);
        $height = (int) ($this->heights[$id] ?? $template->header_height_mm);
        validator(['height' => $height], ['height' => ['integer', 'min:20', 'max:100']])->validate();
        $manager->reprocess($template, $height);
        $this->dispatch('toast', type: 'success', message: 'Nouvel aperçu en préparation.');
    }

    public function activate(int $id, LetterheadManager $manager): void
    {
        $this->authorizeManage();
        $manager->activate(LetterheadTemplate::query()->findOrFail($id));
        $this->dispatch('toast', type: 'success', message: 'Ce papier à en-tête est désormais utilisé pour les documents générés.');
    }

    public function delete(int $id, LetterheadManager $manager): void
    {
        $this->authorizeManage();
        $manager->delete(LetterheadTemplate::query()->findOrFail($id));
        unset($this->heights[$id]);
        $this->dispatch('toast', type: 'success', message: 'Le fichier et ses aperçus ont été supprimés.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);
    }

    public function render(): View
    {
        $templates = LetterheadTemplate::query()->with('uploader:id,name')->latest()->get();
        foreach ($templates as $template) {
            $this->heights[$template->id] ??= $template->header_height_mm;
        }

        return view('livewire.settings.letterheads', [
            'templates' => $templates,
            'activeTemplate' => $templates->firstWhere('status', LetterheadTemplate::STATUS_ACTIVE),
            'isProcessing' => $templates->contains(fn ($item) => in_array($item->status, [LetterheadTemplate::STATUS_PENDING, LetterheadTemplate::STATUS_PROCESSING], true)),
            'labels' => LetterheadTemplate::statusLabels(),
        ]);
    }
}
