<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AccountWriter
{
    public function save(?User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $this->lock();
                $entry = $user?->exists ? User::query()->lockForUpdate()->findOrFail($user->id) : new User;
                $role = $data['role'] ?? $entry->role;
                $role = $role instanceof UserRole ? $role : UserRole::from($role);
                if ($entry->exists && $entry->role === UserRole::Admin && $role !== UserRole::Admin) {
                    $this->assertAnotherAdmin($entry);
                }
                $entry->fill($data);
                if ($entry->exists && $entry->isDirty('email')) {
                    $entry->email_verified_at = null;
                }
                $entry->save();

                return $entry;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Recheck outside the rolled-back transaction and attach a concurrent
            // uniqueness conflict to its actual field, without exposing SQL details.
            Validator::make($data, [
                'username' => [Rule::unique('users', 'username')->ignore($user)],
                'email' => [Rule::unique('users', 'email')->ignore($user)],
                'member_id' => ['nullable', Rule::unique('users', 'member_id')->ignore($user)],
            ])->validate();
            throw ValidationException::withMessages(['operation' => 'Ce compte a été modifié simultanément. Rechargez la page avant de réessayer.']);
        }
    }

    public function delete(User $user, string $errorBag = 'default'): void
    {
        try {
            DB::transaction(function () use ($user) {
                $this->lock();
                $entry = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($entry->role === UserRole::Admin) {
                    $this->assertAnotherAdmin($entry);
                }
                if (ChoirCommission::where(fn ($q) => $q->where('leader_id', $entry->id)->orWhere('associate_id', $entry->id))->exists()) {
                    throw ValidationException::withMessages(['operation' => 'Réattribuez les responsabilités et les accès aux archives de ce compte avant de le supprimer.']);
                }
                $entry->delete();
            }, 3);
        } catch (ValidationException $e) {
            $e->errorBag = $errorBag;
            throw $e;
        }
    }

    private function lock(): void
    {
        app(TenantContext::class)->table('operation_locks')->where('key', 'accounts')->lockForUpdate()->first();
    }

    private function assertAnotherAdmin(User $user): void
    {
        if (! User::where('role', UserRole::Admin->value)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['role' => 'Conservez au moins un administrateur. Attribuez ce rôle à un autre compte avant de poursuivre.']);
        }
    }
}
