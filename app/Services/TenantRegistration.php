<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Choir;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantRegistration
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = new Tenant([
                'name' => $data['organization'],
                'slug' => Str::slug($data['organization']).'-'.Str::lower(Str::random(10)),
                'church_project_chorale_id' => $data['church_project_chorale_id'] ?? null,
                'church_project_paroisse_id' => $data['church_project_paroisse_id'] ?? null,
                'church_project_doyenne_id' => $data['church_project_doyenne_id'] ?? null,
            ]);
            $tenant->forceFill(['trial_started_at' => now(), 'trial_ends_at' => now()->addHours(72)])->save();

            return app(TenantContext::class)->run($tenant->id, function () use ($data) {
                $user = User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'password' => $data['password'], 'role' => UserRole::Admin]);
                Choir::create(['name' => $data['organization'], 'slug' => 'principale', 'is_active' => true]);
                Setting::setMany(['chorale.name' => $data['organization']]);
                app(TenantDefaults::class)->ensure();

                return $user;
            });
        });
    }
}
