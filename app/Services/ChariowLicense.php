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
        try {
            DB::transaction(function () use ($tenant, $user, $key) {
                $locked = Tenant::lockForUpdate()->findOrFail($tenant->id);
                abort_if($locked->suspended_at !== null, 403);
                $license = $this->fetch($key);
                $this->assertProduct($license);
                if (! $this->active($license) || mb_strtolower((string) data_get($license, 'customer.email')) !== mb_strtolower($user->email)) {
                    $this->reject('Cette licence n’est pas valide pour votre compte et cette application.');
                }
                $expires = $this->expiration($license);
                if (! $expires->isFuture()) {
                    $this->reject('La période de validité de cette licence est terminée.');
                }
                $hash = hash('sha256', $key);
                $existing = DB::table('tenant_licenses')->where('license_hash', $hash)->orWhere('provider_id', $license['id'])->first();
                if ($existing && (int) $existing->tenant_id !== $tenant->id) {
                    $this->reject('Cette licence est déjà attribuée à un autre espace.');
                }
                DB::table('tenant_licenses')->updateOrInsert(['provider_id' => $license['id']], [
                    'tenant_id' => $tenant->id, 'license_hash' => $hash, 'expires_at' => $expires, 'status' => 'active',
                    'last_checked_at' => now(), 'sync_status' => 'ok', 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
                ]);
                $this->updateAccess($locked);
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->reject('Cette licence est déjà attribuée. Actualisez la page.');
        }
    }

    public function sync(int $licenseId): bool
    {
        $record = DB::table('tenant_licenses')->find($licenseId);
        if (! $record) {
            return true;
        }
        try {
            // Serialize billing updates per space, including the bounded API call,
            // so an older response cannot overwrite a newer renewal or revocation.
            return DB::transaction(function () use ($record) {
                $tenant = Tenant::lockForUpdate()->findOrFail($record->tenant_id);
                $current = DB::table('tenant_licenses')->where('tenant_id', $tenant->id)->where('id', $record->id)->lockForUpdate()->first();
                if (! $current) {
                    return true;
                }
                $license = $this->fetch($current->provider_id);
                $this->assertProduct($license);
                if ($license['id'] !== $current->provider_id) {
                    $this->reject('La licence reçue ne correspond pas à cet abonnement.');
                }
                if ($this->active($license)) {
                    $expires = $this->expiration($license);
                    $status = $expires->isFuture() ? 'active' : 'expired';
                } elseif (in_array($license['status'] ?? null, ['expired', 'revoked', 'pending_activation'], true)) {
                    $expires = now();
                    $status = $license['status'] === 'revoked' ? 'revoked' : 'expired';
                } else {
                    $this->reject('L’état de la licence ne peut pas être vérifié.');
                }
                DB::table('tenant_licenses')->where('id', $current->id)->update(['status' => $status, 'expires_at' => $expires, 'last_checked_at' => now(), 'sync_status' => 'ok', 'updated_at' => now()]);
                $this->updateAccess($tenant);

                return true;
            });
        } catch (Throwable $e) {
            // Outages never add time or erase an already verified paid period.
            // Persist only an error state, never the provider body, key or message.
            DB::table('tenant_licenses')->where('id', $record->id)->where('tenant_id', $record->tenant_id)->update(['last_checked_at' => now(), 'sync_status' => 'error', 'updated_at' => now()]);

            return false;
        }
    }

    private function fetch(#[\SensitiveParameter] string $identifier): array
    {
        if (! config('tenancy.chariow.api_key') || ! config('tenancy.chariow.product_id')) {
            $this->reject('L’activation Chariow n’est pas encore configurée. Contactez le responsable de la plateforme.');
        }
        try {
            $response = Http::withToken(config('tenancy.chariow.api_key'))->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->get('https://api.chariow.com/v1/licenses/'.rawurlencode($identifier));
        } catch (Throwable $e) {
            $this->reject('Chariow est momentanément indisponible. Réessayez dans quelques instants.');
        }
        if (! $response->successful() || ! is_array($response->json('data'))) {
            $this->reject('La licence n’a pas pu être vérifiée. Vérifiez votre clé ou réessayez plus tard.');
        }

        return $response->json('data');
    }

    private function assertProduct(array $license): void
    {
        if (data_get($license, 'product.id') !== config('tenancy.chariow.product_id')
            || ! is_string($license['id'] ?? null) || blank($license['id']) || strlen($license['id']) > 255
            || ! SubscriptionPlan::matches($license)) {
            $this->reject('Cette licence ne correspond pas à l’offre de '.SubscriptionPlan::price().' par mois pour une chorale.');
        }
    }

    private function active(array $license): bool
    {
        return ($license['is_active'] ?? null) === true && ($license['is_expired'] ?? null) === false
            && ($license['status'] ?? null) === 'active' && ! filled($license['revoked_at'] ?? null);
    }

    private function expiration(array $license): CarbonImmutable
    {
        $value = $license['expires_at'] ?? null;
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            $this->reject('Une échéance vérifiable est obligatoire pour l’abonnement mensuel. Les licences à vie ne sont pas acceptées.');
        }
        try {
            $expires = CarbonImmutable::parse($value);
            $errors = CarbonImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                $this->reject('La date de validité de la licence ne peut pas être vérifiée.');
            }
        } catch (Throwable $e) {
            $this->reject('La date de validité de la licence ne peut pas être vérifiée.');
        }

        return $expires;
    }

    private function updateAccess(Tenant $tenant): void
    {
        $expires = DB::table('tenant_licenses')->where('tenant_id', $tenant->id)->where('status', 'active')->max('expires_at');
        $tenant->forceFill(['paid_until' => $expires])->save();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['license_key' => $message]);
    }
}
