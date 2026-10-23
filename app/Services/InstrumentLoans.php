<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\InstrumentMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InstrumentLoans
{
    public function save(?InstrumentMovement $movement, array $data): InstrumentMovement
    {
        return DB::transaction(function () use ($movement, $data) {
            // Serialize all movements for affected stock, in stable order.
            $old = $movement?->exists ? InstrumentMovement::findOrFail($movement->id) : null;
            $ids = array_unique(array_filter([$old?->instrument_id, (int) $data['instrument_id']]));
            sort($ids);
            $instruments = Instrument::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $entry = $old ? InstrumentMovement::query()->lockForUpdate()->findOrFail($old->id) : new InstrumentMovement;
            $entry->fill($data);
            foreach (['expected_return_on', 'actual_return_on'] as $date) {
                if ($entry->$date && $entry->$date->lt($entry->moved_on)) {
                    throw ValidationException::withMessages([$date => 'Le retour ne peut pas précéder la sortie.']);
                }
            }
            if (in_array($entry->type->value, ['pret', 'sortie'], true) && ! $entry->actual_return_on) {
                $stock = $instruments->get($entry->instrument_id);
                abort_unless($stock, 404);
                $out = InstrumentMovement::where('instrument_id', $stock->id)->whereIn('type', ['pret', 'sortie'])->whereNull('actual_return_on')->when($entry->exists, fn ($q) => $q->whereKeyNot($entry->id))->sum('quantity');
                if ($entry->quantity < 1 || $out + $entry->quantity > $stock->quantity) {
                    throw ValidationException::withMessages(['quantity' => 'La quantité demandée dépasse le stock disponible.']);
                }
            }
            if (! $entry->exists) {
                $entry->created_by = auth()->id();
            }
            $entry->save();

            return $entry;
        }, 3);
    }

    public function close(int $id): void
    {
        DB::transaction(function () use ($id) {
            $entry = InstrumentMovement::findOrFail($id);
            Instrument::query()->whereKey($entry->instrument_id)->lockForUpdate()->firstOrFail();
            $entry = InstrumentMovement::query()->lockForUpdate()->findOrFail($id);
            if ($entry->actual_return_on) {
                return;
            }
            if ($entry->moved_on->gt(today())) {
                throw ValidationException::withMessages(['operation' => 'Ce mouvement futur ne peut pas être clôturé aujourd’hui.']);
            }
            $entry->update(['actual_return_on' => today(), 'condition_on_return' => $entry->condition_on_return?->value ?? $entry->instrument?->condition?->value]);
        }, 3);
    }
}
