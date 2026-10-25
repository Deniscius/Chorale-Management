<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\Instrument;
use App\Models\InstrumentUnit;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InstrumentIdentity
{
    // Called within the stock transaction as well as by the idempotent release backfill.
    public function synchronize(int $instrumentId): int
    {
        return DB::transaction(function () use ($instrumentId) {
            $instrument = Instrument::lockForUpdate()->findOrFail($instrumentId);
            $active = $instrument->units()->whereNull('retired_at')->count();
            if ($active > $instrument->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Retirez les exemplaires concernés depuis Articles et étiquettes avant de réduire cette quantité. Leurs codes seront conservés.']);
            }
            $missing = $instrument->quantity - $active;
            $ordinal = (int) $instrument->units()->max('ordinal');
            $rows = [];
            for ($i = 0; $i < $missing; $i++) {
                $ordinal++;
                $rows[] = ['tenant_id' => app(TenantContext::class)->requireId(), 'instrument_id' => $instrumentId, 'ordinal' => $ordinal,
                    'inventory_code' => 'CM'.str_pad((string) $instrumentId, 8, '0', STR_PAD_LEFT).'-'.str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT),
                    'retired_at' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('instrument_units')->insert($batch);
            }
            if ($missing) {
                $this->audit($instrument, ['identified_units' => $active], ['identified_units' => $active + $missing]);
            }

            return $missing;
        }, 3);
    }

    public function generate(int $instrumentId): int
    {
        Gate::authorize('manage-module', UserRole::MODULE_INSTRUMENTS);

        return $this->synchronize($instrumentId);
    }

    public function setRetired(int $id, bool $retired): void
    {
        Gate::authorize('manage-module', UserRole::MODULE_INSTRUMENTS);
        DB::transaction(function () use ($id, $retired) {
            $reference = InstrumentUnit::findOrFail($id)->instrument_id;
            $instrument = Instrument::lockForUpdate()->findOrFail($reference);
            $unit = InstrumentUnit::lockForUpdate()->findOrFail($id);
            if ((bool) $unit->retired_at === $retired) {
                return;
            }
            if ($instrument->movements()->whereIn('type', ['pret', 'sortie'])->whereNull('actual_return_on')->exists()) {
                throw ValidationException::withMessages(['operation' => 'Clôturez les prêts ou sorties en cours avant de modifier les exemplaires de cette référence.']);
            }
            if ($retired && $instrument->quantity <= 1) {
                throw ValidationException::withMessages(['operation' => 'Pour retirer le dernier exemplaire, retirez la référence depuis l’inventaire.']);
            }
            if (! $retired && $instrument->quantity >= 999) {
                throw ValidationException::withMessages(['operation' => 'La référence contient déjà le maximum de 999 pièces. Réorganisez son stock avant cette réintégration.']);
            }
            $old = $instrument->quantity;
            $unit->update(['retired_at' => $retired ? now() : null]);
            $instrument->update(['quantity' => $old + ($retired ? -1 : 1)]);
            $this->audit($instrument, ['quantity' => $old], ['quantity' => $instrument->quantity, 'unit_code' => $unit->inventory_code, 'unit_status' => $retired ? 'Retiré' : 'En inventaire']);
        }, 3);
    }

    private function audit(Instrument $instrument, array $before, array $after): void
    {
        $actor = auth()->user();
        AuditEvent::create(['actor_id' => $actor && (int) $actor->tenant_id === (int) $instrument->tenant_id ? $actor->id : null, 'subject_type' => Instrument::class, 'subject_id' => $instrument->id, 'action' => 'updated', 'changes' => compact('before', 'after'), 'created_at' => now()]);
    }

    public function synchronizeTenant(): void
    {
        Instrument::orderBy('id')->eachById(fn ($instrument) => $this->synchronize($instrument->id));
    }
}
