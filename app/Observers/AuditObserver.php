<?php

namespace App\Observers;

use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\ChoirCommission;
use App\Models\CommissionTeam;
use App\Models\Contribution;
use App\Models\Document;
use App\Models\Donation;
use App\Models\FinancialType;
use App\Models\MemberInvitation;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

final class AuditObserver
{
    private function fields(Model $model): array
    {
        return match ($model::class) {
            ChoirCommission::class => ['name', 'kind', 'mission', 'starts_on', 'ends_on', 'archived_at'],
            User::class => ['role', 'member_id'],
            FinancialType::class => ['kind', 'label', 'default_amount', 'is_active', 'base_type'],
            MemberInvitation::class => ['role', 'member_id', 'expires_at', 'accepted_at'],
            Document::class => ['commission', 'path', 'size', 'visibility', 'correspondence_id'],
            CommissionTeam::class => ['commission', 'leader_id', 'associate_id'],
            Donation::class => ['type_code', 'amount', 'donor_type', 'member_id', 'transaction_id', 'donated_on'],
            Contribution::class => ['type_code', 'member_id', 'year', 'month', 'due_amount', 'paid_amount', 'paid_on', 'status'],
            Budget::class => ['year', 'category', 'allocated_amount', 'committed_amount', 'opening_realized_amount'],
            Transaction::class => ['reference', 'type', 'amount', 'transaction_date', 'status', 'budget_id', 'member_id', 'validated_by'],
            Reminder::class => ['status', 'attempts'],
            default => [],
        };
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        $keys = array_flip($this->fields($model));
        $after = $action === 'deleted' ? [] : array_intersect_key($action === 'updated' ? $model->getChanges() : $model->getAttributes(), $keys);
        $before = $action === 'created' ? [] : array_intersect_key($model->getRawOriginal(), $action === 'updated' ? $after : $keys);
        if ($action === 'updated' && $after === []) {
            return;
        }
        AuditEvent::create(['actor_id' => ! TenantContext::isolated() || (int) auth()->user()?->tenant_id === app(TenantContext::class)->id() ? auth()->id() : null, 'subject_type' => $model->getMorphClass(), 'subject_id' => $model->getKey(), 'action' => $action, 'changes' => ['before' => $before, 'after' => $after], 'created_at' => now()]);
    }
}
