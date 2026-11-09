<?php

namespace App\Livewire\Communications;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Mail\TenantAnnouncement;
use App\Models\Activity;
use App\Models\AuditEvent;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\PlatformReminderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Compose extends Component
{
    public string $audience = 'commission';

    public string $commissionId = '';

    public string $activityId = '';

    public string $recipientMode = 'all';

    public array $memberIds = [];

    public string $title = '';

    public string $message = '';

    public function mount(): void
    {
        abort_unless($this->allowed(), 403);
        $this->commissionId = (string) ($this->commissions->first()?->id ?? '');
    }

    public function hydrate(): void
    {
        abort_unless($this->allowed(), 403);
    }

    public function updatedAudience(): void
    {
        $this->memberIds = [];
        $this->resetValidation();
    }

    public function updatedCommissionId(): void
    {
        $this->memberIds = [];
    }

    public function updatedActivityId(): void
    {
        $this->memberIds = [];
    }

    public function send(): void
    {
        $data = $this->validate(['audience' => 'required|in:chorale,commission,activity', 'commissionId' => 'nullable|integer', 'activityId' => 'nullable|integer', 'recipientMode' => 'required|in:all,selected', 'memberIds' => 'array|max:500', 'memberIds.*' => 'integer|distinct', 'title' => 'required|string|max:140', 'message' => 'required|string|max:2000']);
        abort_unless($this->canSend($data['audience']), 403);
        $members = $this->candidates;
        if ($this->recipientMode === 'selected') {
            $members = $members->whereIn('id', array_map('intval', $this->memberIds));
        }
        $deliverable = $members->filter(fn (Member $member) => $member->user !== null || (config('mail.delivery_enabled') && $member->canReceiveEmail()));
        if ($deliverable->isEmpty()) {
            $this->addError('memberIds', 'Choisissez au moins un choriste ayant un compte ou une adresse e-mail.');

            return;
        }
        $tag = 'message-groupe:'.Str::uuid();
        $sent = 0;
        $emailed = 0;
        $failed = 0;
        $delivered = [];
        $body = trim($this->message)."\n\n".$this->signature();
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        foreach ($deliverable as $member) {
            try {
                if ($user = $member->user) {
                    $user->loadMissing('member');
                    $notification = new PlatformReminderNotification($this->title, $body, route('personal-notebook.show', absolute: false), 'group-message', $tag, 'mail');
                    Notification::sendNow($user, $notification, ['database', WebPushChannel::class]);
                    $sent++;
                    $delivered[$member->id] = true;
                    if (config('mail.delivery_enabled') && $user->notificationEmail()) {
                        Notification::sendNow($user, $notification, ['mail']);
                        $emailed++;
                    }
                } else {
                    Mail::to(trim($member->email))->send(new TenantAnnouncement($this->title, $body, $tenant->name, route('tenant.entry', ['slug' => $tenant->slug]), 'Ouvrir la chorale'));
                    $emailed++;
                    $delivered[$member->id] = true;
                }
            } catch (\Throwable $exception) {
                $failed++;
                report($exception);
            }
        }
        AuditEvent::create(['actor_id' => auth()->id(), 'subject_type' => User::class, 'subject_id' => auth()->id(), 'action' => 'created', 'changes' => ['after' => ['group_message' => ['audience' => $this->audience, 'commission_id' => $this->commissionId ?: null, 'activity_id' => $this->activityId ?: null, 'title' => $this->title, 'recipient_count' => count($delivered), 'application_count' => $sent, 'email_count' => $emailed, 'failure_count' => $failed]]], 'created_at' => now()]);
        if ($failed === 0) {
            $this->reset('title', 'message', 'memberIds');
        }
        $summary = $sent.' notification(s) dans l’application ; '.$emailed.' e-mail(s) accepté(s) par le transport ; '.$failed.' échec(s).';
        if (! config('mail.delivery_enabled')) {
            $summary .= ' Envoi e-mail désactivé dans la configuration.';
        }
        $this->dispatch('toast', type: $failed > 0 ? 'error' : ($emailed > 0 ? 'success' : 'warning'), message: $summary);
    }

    #[Computed]
    public function commissions(): Collection
    {
        $user = auth()->user();

        return ChoirCommission::inStatus('active')->when(! in_array($user->role, [UserRole::Admin, UserRole::Directeur], true), fn ($q) => $q->where(fn ($x) => $x->where('leader_id', $user->id)->orWhere('associate_id', $user->id)))->orderBy('name')->get(['id', 'name', 'module_key', 'leader_id', 'associate_id']);
    }

    #[Computed]
    public function activities(): Collection
    {
        $user = auth()->user();

        return Activity::query()->when(! in_array($user->role, [UserRole::Admin, UserRole::Directeur], true), fn ($q) => $q->whereIn('choir_commission_id', $this->commissions->pluck('id')))->orderByDesc('starts_at')->limit(100)->get(['id', 'title', 'starts_at']);
    }

    #[Computed]
    public function candidates(): Collection
    {
        $query = Member::query()->with('user');
        if ($this->audience === 'activity') {
            $query->activityEligible();
        } else {
            $query->where('status', MemberStatus::Actif);
        }
        if ($this->audience === 'commission') {
            if (! $this->commissions->contains('id', (int) $this->commissionId)) {
                return collect();
            } $query->whereHas('commissions', fn ($q) => $q->whereKey((int) $this->commissionId));
        }
        if ($this->audience === 'activity') {
            if (! $this->activities->contains('id', (int) $this->activityId)) {
                return collect();
            } $query->whereHas('activityParticipants', fn ($q) => $q->where('activity_id', (int) $this->activityId));
        }

        return $query->orderBy('last_name')->orderBy('first_name')->get();
    }

    private function allowed(): bool
    {
        return auth()->user()?->canManageModule(UserRole::MODULE_ACTIVITES) || auth()->user()?->canManageModule(UserRole::MODULE_COURRIERS);
    }

    private function canSend(string $audience): bool
    {
        return $audience !== 'chorale' || in_array(auth()->user()->role, [UserRole::Admin, UserRole::Directeur], true);
    }

    private function signature(): string
    {
        $author = auth()->user();
        $commission = $this->commissions->firstWhere('id', (int) $this->commissionId);
        $function = $commission ? ((int) $commission->leader_id === (int) $author->id ? 'Responsable' : 'Associé').' — '.$commission->name : $author->role->label();

        return '— '.$author->name.', '.$function;
    }

    public function render()
    {
        return view('livewire.communications.compose');
    }
}
