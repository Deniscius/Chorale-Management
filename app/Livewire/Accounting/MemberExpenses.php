<?php

namespace App\Livewire\Accounting;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Livewire\Concerns\SearchesMembers;
use App\Models\Account;
use App\Models\Activity;
use App\Models\MemberExpense;
use App\Services\MemberExpenseWriter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class MemberExpenses extends Component
{
    use InteractsWithModule;
    use SearchesMembers;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    #[Url(as: 'activite')]
    public ?int $activityId = null;

    #[Url(as: 'statut', except: '')]
    public string $statusFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public string $reimbursementKey = '';

    public string $member_id = '';

    public string $activity_id = '';

    public string $incurred_on = '';

    public string $description = '';

    public string $amount = '';

    public string $claimed_on = '';

    public string $evidence_reference = '';

    public string $notes = '';

    public string $decision = 'approved';

    public string $decision_note = '';

    public string $paymentAmount = '';

    public string $paymentDate = '';

    public string $account_id = '';

    public string $payment_method = 'especes';

    public string $paymentNotes = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['activityId', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->reset(['member_id', 'activity_id', 'description', 'amount', 'claimed_on', 'evidence_reference', 'notes', 'memberSearch', 'selectedId']);
        $this->incurred_on = today()->toDateString();
        $this->activity_id = $this->activityId ? (string) $this->activityId : '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        app(MemberExpenseWriter::class)->create([
            'member_id' => $this->member_id, 'activity_id' => $this->activity_id ?: null,
            'incurred_on' => $this->incurred_on, 'description' => trim($this->description),
            'amount' => $this->amount, 'claimed_on' => $this->claimed_on ?: null,
            'evidence_reference' => $this->evidence_reference, 'notes' => $this->notes,
        ]);
        $this->showForm = false;
        unset($this->expenses);
        $this->dispatch('toast', type: 'success', message: 'Dépense avancée enregistrée, en attente de décision.');
    }

    public function selectExpense(int $id): void
    {
        $this->authorizeManage();
        $expense = MemberExpense::query()->findOrFail($id);
        $this->selectedId = $id;
        $this->showForm = false;
        $this->reset(['decision_note', 'account_id', 'paymentNotes']);
        $this->decision = 'approved';
        $this->paymentAmount = (string) $expense->outstandingAmount();
        $this->paymentDate = today()->toDateString();
        $this->reimbursementKey = (string) Str::uuid();
        $this->resetValidation();
        unset($this->selectedExpense);
    }

    public function decide(): void
    {
        $this->authorizeManage();
        abort_unless($this->selectedId, 422);
        app(MemberExpenseWriter::class)->decide($this->selectedId, $this->decision, $this->decision_note);
        unset($this->selectedExpense, $this->expenses);
        $this->paymentAmount = (string) $this->selectedExpense->outstandingAmount();
        $this->dispatch('toast', type: 'success', message: 'Décision enregistrée. Aucun paiement n’a été effectué.');
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
        unset($this->selectedExpense);
    }

    public function recordClaim(int $id): void
    {
        $this->authorizeManage();
        app(MemberExpenseWriter::class)->recordClaim($id);
        unset($this->expenses, $this->selectedExpense);
        $this->dispatch('toast', type: 'success', message: 'Réclamation du choriste enregistrée.');
    }

    public function reimburse(): void
    {
        $this->authorizeManage();
        abort_unless($this->selectedId, 422);
        app(MemberExpenseWriter::class)->reimburse($this->selectedId, [
            'amount' => $this->paymentAmount, 'transaction_date' => $this->paymentDate,
            'account_id' => $this->account_id, 'payment_method' => $this->payment_method,
            'notes' => $this->paymentNotes, 'reimbursement_key' => $this->reimbursementKey,
        ]);
        $this->selectedId = null;
        unset($this->expenses, $this->selectedExpense);
        $this->dispatch('toast', type: 'success', message: 'Remboursement enregistré dans les dépenses comptables.');
    }

    #[Computed]
    public function expenses()
    {
        return MemberExpense::query()->with(['member', 'activity'])->withSum('repayments', 'amount')
            ->when($this->activityId, fn ($q) => $q->where('activity_id', $this->activityId))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('incurred_on')->latest('id')->paginate(20);
    }

    #[Computed]
    public function selectedExpense(): ?MemberExpense
    {
        return $this->selectedId ? MemberExpense::query()->with(['member', 'activity', 'decisionMaker', 'repayments.account'])
            ->withSum('repayments', 'amount')->findOrFail($this->selectedId) : null;
    }

    #[Computed]
    public function activities()
    {
        return Activity::query()->orderByDesc('starts_at')->get();
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()->where('is_active', true)->orderBy('code')->get();
    }

    public function render()
    {
        return view('livewire.accounting.member-expenses', ['statuses' => MemberExpense::STATUSES, 'paymentMethods' => PaymentMethod::options()]);
    }
}
