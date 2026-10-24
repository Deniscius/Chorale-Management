<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
                $entry->fill($data)->save();

                return $entry;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent account creation may have passed the form validation.
            throw ValidationException::withMessages(['member_id' => 'Le choriste, l’identifiant ou l’adresse e-mail est déjà lié à un compte.']);
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
