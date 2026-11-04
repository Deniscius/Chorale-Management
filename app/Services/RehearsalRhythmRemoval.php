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
                $previous = $rhythm->getRawOriginal();
                $rhythm->update(['is_active' => false]);
                app(RehearsalRhythmSynchronizer::class)->synchronize($rhythm, $previous);

                return 'archived';
            }

            $rhythm->delete();

            return 'deleted';
        }, 3);
    }
}
