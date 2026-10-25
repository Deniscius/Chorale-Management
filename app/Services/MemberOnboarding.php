<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class MemberOnboarding
{
    public function suggestUsername(Member $member): string
    {
        $first = preg_split('/\s+/u', trim($member->first_name))[0] ?? '';
        $base = substr(Str::slug($first), 0, 40) ?: 'choriste';
        $candidate = $base;
        $suffix = 2;
        while (Validator::make(['username' => $candidate], ['username' => 'unique:users,username'])->fails()) {
            $candidate = $base.'.'.$suffix++;
        }

        return $candidate;
    }

    public function issue(Member $member, UserRole $role = UserRole::Choriste): string
    {
        return DB::transaction(function () use ($member, $role) {
            $this->lockAccounts();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            abort_if($member->user()->exists(), 409, 'Ce membre dispose déjà d’un compte.');
            $token = bin2hex(random_bytes(32));
            $invitation = MemberInvitation::where('member_id', $member->id)->first() ?? new MemberInvitation;
            $invitation->forceFill(['member_id' => $member->id, 'role' => $role, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'accepted_at' => null])->save();

            return route('member-activation.show', ['token' => $token]);
        });
    }

    public function details(string $token): array
    {
        $invitation = $this->resolve($token);

        return app(TenantContext::class)->run($invitation->tenant_id, function () use ($invitation) {
            $member = Member::findOrFail($invitation->member_id);
            abort_if($member->user()->exists(), 410, 'Ce lien n’est plus disponible.');

            return ['member' => $member, 'organization' => Tenant::findOrFail($invitation->tenant_id)->name, 'username' => $this->suggestUsername($member), 'expires' => $invitation->expires_at];
        });
    }

    public function activate(string $token, array $data): User
    {
        $invitation = $this->resolve($token);

        return app(TenantContext::class)->run($invitation->tenant_id, fn () => DB::transaction(function () use ($invitation, $token, $data) {
            $this->lockAccounts();
            $locked = MemberInvitation::lockForUpdate()->findOrFail($invitation->id);
            $this->assertAvailable($locked, $token);
            $member = Member::lockForUpdate()->findOrFail($locked->member_id);
            abort_if($member->user()->exists(), 410, 'Ce lien n’est plus disponible.');
            $data['username'] = mb_strtolower(trim((string) ($data['username'] ?? '')));
            $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
            $validated = Validator::make($data, [
                'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('users', 'username')],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('members', 'email')->ignore($member)],
                'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(12)->letters()->numbers()],
            ], ['username.unique' => 'Cet identifiant est déjà utilisé. Choisissez-en un autre.', 'username.regex' => 'Utilisez des lettres minuscules, chiffres, points ou tirets.'])->validate();
            $user = app(AccountWriter::class)->save(null, [
                'name' => $member->full_name, 'username' => $validated['username'], 'email' => $validated['email'],
                'password' => $validated['password'], 'role' => $locked->role, 'member_id' => $member->id,
            ]);
            $member->update(['email' => $validated['email']]);
            $locked->forceFill(['accepted_at' => now()])->save();

            return $user;
        }));
    }

    public function revoke(Member $member): void
    {
        DB::transaction(function () use ($member) {
            $this->lockAccounts();
            MemberInvitation::where('member_id', $member->id)->first()?->delete();
            if ($user = $member->user()->first()) {
                app(AccountWriter::class)->delete($user);
            }
        });
    }

    private function resolve(string $token): MemberInvitation
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 410, 'Ce lien n’est plus disponible.');
        // The random bearer token is the only global lookup. All subsequent
        // member, validation and account operations use its stored tenant.
        $invitation = MemberInvitation::withoutGlobalScope(TenantScope::class)->where('token_hash', hash('sha256', $token))->first();
        abort_unless($invitation, 410, 'Ce lien n’est plus disponible.');
        $this->assertAvailable($invitation, $token);

        return $invitation;
    }

    private function assertAvailable(MemberInvitation $invitation, string $token): void
    {
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)) && ! $invitation->accepted_at && $invitation->expires_at->isFuture(), 410, 'Ce lien a expiré ou a déjà été utilisé. Demandez un nouveau lien au responsable.');
    }

    private function lockAccounts(): void
    {
        app(TenantContext::class)->table('operation_locks')->where('key', 'accounts')->lockForUpdate()->first();
    }
}
