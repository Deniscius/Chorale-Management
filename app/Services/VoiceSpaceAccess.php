<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\Voice;
use App\Models\User;
use App\Models\VoiceResource;

final class VoiceSpaceAccess
{
    public function isManager(User $user): bool
    {
        return $user->canManageModule(UserRole::MODULE_LITURGIQUE);
    }

    public function selectedVoice(User $user, ?string $requested = null): ?Voice
    {
        $own = $this->ownVoice($user);
        if ($this->isManager($user)) {
            return Voice::tryFrom((string) $requested) ?? $own ?? Voice::Soprano;
        }

        if ($requested !== null && $requested !== '' && $requested !== $own?->value) {
            abort(403);
        }

        return $own;
    }

    public function authorizeVoice(User $user, Voice $voice): void
    {
        abort_unless($this->isManager($user) || $this->ownVoice($user) === $voice, 403);
    }

    public function authorizeEdit(User $user, VoiceResource $resource): void
    {
        $this->authorizeVoice($user, $resource->voice);
        abort_unless($this->isManager($user) || (int) $resource->uploaded_by === (int) $user->id, 403);
    }

    private function ownVoice(User $user): ?Voice
    {
        $user->loadMissing('member');

        return $user->member?->voice;
    }
}
