<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ChariowLicense
{
    public function activate(Tenant $tenant, User $user, #[\SensitiveParameter] string $key): void
    {
        abort_unless($user->isAdmin() && (int) $user->tenant_id === $tenant->id && $user->hasVerifiedEmail(), 403);
        if (! config('tenancy.chariow.api_key') || ! config('tenancy.chariow.product_id')) {
            $this->reject('L’activation Chariow n’est pas encore configurée. Contactez le responsable de la plateforme.');
        }
        try {
            $response = Http::withToken(config('tenancy.chariow.api_key'))->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->get('https://api.chariow.com/v1/licenses/'.rawurlencode($key));
        } catch (Throwable $e) {
            $this->reject('Chariow est momentanément indisponible. Réessayez dans quelques instants.');
        }
        if (! $response->successful()) {
            $this->reject('La licence n’a pas pu être vérifiée. Vérifiez votre clé ou réessayez plus tard.');
        }
        $license = $response->json('data');
        if (! is_array($license) || ($license['is_active'] ?? null) !== true || ($license['is_expired'] ?? null) !== false
            || ($license['status'] ?? null) !== 'active' || filled($license['revoked_at'] ?? null)
            || data_get($license, 'product.id') !== config('tenancy.chariow.product_id')
            || mb_strtolower((string) data_get($license, 'customer.email')) !== mb_strtolower($user->email)
            || ! is_string($license['id'] ?? null) || blank($license['id']) || strlen($license['id']) > 255) {
            $this->reject('Cette licence n’est pas valide pour votre compte et cette application.');
        }
        if (! array_key_exists('expires_at', $license) || ($license['expires_at'] !== null && (! is_string($license['expires_at']) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $license['expires_at'])))) {
            $this->reject('La période de validité de la licence ne peut pas être vérifiée.');
        }
        try {
            // Chariow declares null for a lifetime licence; do not invent a billing period.
            $expires = filled($license['expires_at'] ?? null) ? CarbonImmutable::parse($license['expires_at']) : null;
            $parseErrors = CarbonImmutable::getLastErrors();
            if ($expires !== null && $parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
                $this->reject('La date de validité de la licence ne peut pas être vérifiée.');
            }
        } catch (Throwable $e) {
            $this->reject('La date de validité de la licence ne peut pas être vérifiée.');
        }
        if (! array_key_exists('expires_at', $license) || ($expires !== null && ! $expires->isFuture())) {
            $this->reject('La période de validité de cette licence ne peut pas être acceptée.');
        }
        try {
            DB::transaction(function () use ($tenant, $key, $license, $expires) {
                $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                abort_if($locked->suspended_at !== null, 403);
                $hash = hash('sha256', $key);
                $existing = DB::table('tenant_licenses')->where('license_hash', $hash)->orWhere('provider_id', $license['id'])->first();
                if ($existing && (int) $existing->tenant_id !== $tenant->id) {
                    $this->reject('Cette licence est déjà attribuée à un autre espace.');
                }
                DB::table('tenant_licenses')->updateOrInsert(['provider_id' => $license['id']], ['tenant_id' => $tenant->id, 'license_hash' => $hash, 'expires_at' => $expires, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
                if ($expires === null) {
                    $locked->forceFill(['paid_perpetual' => true])->save();
                } elseif ($locked->paid_until === null || $locked->paid_until->lt($expires)) {
                    $locked->forceFill(['paid_until' => $expires])->save();
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->reject('Cette licence est déjà attribuée. Actualisez la page.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['license_key' => $message]);
    }
}
