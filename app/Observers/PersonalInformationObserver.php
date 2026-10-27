<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class PersonalInformationObserver
{
    public const MEMBER_FIELDS = ['first_name', 'last_name', 'sex', 'birth_date', 'email', 'phone', 'address', 'profession', 'photo_path', 'notify_email', 'notify_sms'];

    public function updating(Model $model): void
    {
        $actor = auth()->user();
        // Account activation and trusted maintenance run without a signed-in user.
        if (! $actor) {
            return;
        }
        $fields = $model instanceof User
            ? ['name', 'username', 'email', 'password', 'member_id']
            : self::MEMBER_FIELDS;
        $owner = $model instanceof User ? $actor->id === $model->id : $actor->member_id === $model->id;
        if ($model->isDirty($fields)) {
            abort_unless($owner && (int) $actor->tenant_id === (int) $model->tenant_id, 403, 'Seul le titulaire peut modifier ses informations personnelles depuis Mon compte.');
        }
        // An account cannot be reassigned to another person's membership record.
        if ($model instanceof User && $model->isDirty('member_id')) {
            abort_unless($model->isCompletingOwnMemberProfile(), 403, 'Le rattachement d’un compte existant ne peut pas être modifié.');
        }
    }
}
