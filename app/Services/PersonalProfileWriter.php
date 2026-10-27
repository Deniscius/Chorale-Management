<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Observers\PersonalInformationObserver;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class PersonalProfileWriter
{
    public function save(User $actor, array $data): User
    {
        abort_unless(auth()->id() === $actor->id, 403);

        return DB::transaction(function () use ($actor, $data) {
            app(TenantContext::class)->lockForAccountChanges();
            // Match AccountWriter's operation-lock-before-user order.
            app(TenantContext::class)->table('operation_locks')->where('key', 'accounts')->lockForUpdate()->first();
            $user = User::lockForUpdate()->findOrFail($actor->id);
            auth()->user()->refresh();
            $member = $user->member_id ? Member::lockForUpdate()->findOrFail($user->member_id) : null;
            $personal = Arr::only($data, PersonalInformationObserver::MEMBER_FIELDS);
            if (! $member && isset($data['first_name'], $data['last_name'])) {
                // Never infer a name, date, voice, choir assignment or notification consent.
                $member = Member::create($personal + ['notify_email' => false, 'notify_sms' => false]);
                $user->attachNewPersonalMember($member);
            } elseif ($member) {
                $member->fill($personal)->save();
            }
            if ($member) {
                $data['name'] = trim($member->first_name.' '.$member->last_name);
            }

            return app(AccountWriter::class)->save($user, Arr::only($data, ['name', 'username', 'email']));
        }, 3);
    }
}
