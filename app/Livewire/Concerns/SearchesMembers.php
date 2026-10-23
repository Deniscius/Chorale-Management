<?php

namespace App\Livewire\Concerns;

use App\Models\Member;
use Livewire\Attributes\Computed;

trait SearchesMembers
{
    public string $memberSearch = '';

    #[Computed]
    public function members()
    {
        $search = mb_substr(trim($this->memberSearch), 0, 100);
        $members = Member::query()->select(['id', 'first_name', 'last_name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('first_name', '%'.$search.'%')->orWhereLike('last_name', '%'.$search.'%')))
            ->orderBy('last_name')->orderBy('first_name')->limit(30)->get();
        if ($this->member_id && ! $members->contains('id', (int) $this->member_id)) {
            $selected = Member::query()->select(['id', 'first_name', 'last_name'])->find($this->member_id);
            if ($selected) {
                $members->prepend($selected);
            }
        }

        return $members;
    }
}
