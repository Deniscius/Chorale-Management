<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\ChoirCommission;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChoirCommissionWriter
{
    public function save(?int $id, array $data): ChoirCommission
    {
        Gate::authorize('manage-commissions');
        $data['name'] = preg_replace('/\s+/u', ' ', trim($data['name'] ?? ''));
        $data['name_key'] = mb_strtolower($data['name']);
        $validator = Validator::make($data, [
            'name' => 'required|string|max:120',
            'name_key' => ['required', Rule::unique('choir_commissions', 'name_key')->ignore($id)],
            'kind' => ['required', Rule::in(['permanent', 'ad_hoc'])],
            'mission' => 'required|string|max:3000',
            'starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'nullable|required_if:kind,ad_hoc|date_format:Y-m-d|after_or_equal:starts_on',
            'member_ids' => 'present|array|max:1000',
            'member_ids.*' => 'integer|distinct|exists:members,id',
        ], ['ends_on.required_if' => 'Indiquez une échéance pour cette commission ad hoc.', 'name_key.unique' => 'Une commission porte déjà ce nom dans votre chorale.']);
        if ($validator->fails()) {
            $errors = $validator->errors()->messages();
            if (isset($errors['name_key'])) {
                $errors['name'] = $errors['name_key'];
                unset($errors['name_key']);
            }
            throw ValidationException::withMessages($errors);
        }
        $data = $validator->validated();
        try {
            return DB::transaction(function () use ($id, $data) {
                $entry = $id ? ChoirCommission::lockForUpdate()->findOrFail($id) : new ChoirCommission;
                // Recheck the selected memberships inside the transaction, including soft deletes.
                if (Member::whereKey($data['member_ids'])->lockForUpdate()->get()->count() !== count($data['member_ids'])) {
                    throw ValidationException::withMessages(['member_ids' => 'Sélectionnez des choristes disponibles dans votre chorale.']);
                }
                $entry->fill(collect($data)->except('member_ids')->all())->save();
                $changes = $entry->members()->sync($data['member_ids']);
                if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                    AuditEvent::create(['actor_id' => auth()->id(), 'subject_type' => ChoirCommission::class, 'subject_id' => $entry->id, 'action' => 'updated', 'changes' => ['membership' => $changes], 'created_at' => now()]);
                }

                return $entry;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['name' => 'Une commission porte déjà ce nom dans votre chorale.']);
        }
    }

    public function toggleArchive(int $id): void
    {
        Gate::authorize('manage-commissions');
        DB::transaction(function () use ($id) {
            $entry = ChoirCommission::lockForUpdate()->findOrFail($id);
            $entry->update(['archived_at' => $entry->archived_at ? null : now()]);
        }, 3);
    }
}
