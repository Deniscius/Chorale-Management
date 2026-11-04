<?php

namespace App\Services;

use App\Models\RehearsalRhythm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RehearsalRhythmRemoval
{
    /**
     * A schedule which has produced rehearsals is retained and disabled.
     * A never-used schedule may be deleted physically. Generated rehearsals,
     * attendance, reminders and validated history are never modified.
     *
     * @return 'archived'|'deleted'
     */
    public function remove(int $id): string
    {
        Gate::authorize('manage-module', \App\Enums\UserRole::MODULE_LITURGIQUE);

        return DB::transaction(function () use ($id): string {
            $rhythm = RehearsalRhythm::query()->lockForUpdate()->findOrFail($id);

            if ($rhythm->rehearsals()->exists()) {
                $rhythm->update(['is_active' => false, 'effective_until' => $rhythm->effective_until && $rhythm->effective_until->lt(today())
                    ? $rhythm->effective_until
                    : today()->subDay()]);

                return 'archived';
            }

            $rhythm->delete();

            return 'deleted';
        }, 3);
    }
}
