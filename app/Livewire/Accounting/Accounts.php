<?php

namespace App\Livewire\Accounting;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Account;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class Accounts extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $code = '';

    public string $name = '';

    public string $type = 'tresorerie';

    public bool $is_active = true;

    public string $description = '';

    public string $search = '';

    public string $state = '';

    public function create(): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'code', 'name', 'type', 'is_active', 'description']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $account = Account::query()->findOrFail($id);
        $this->editingId = $id;
        $this->fill(['code' => $account->code, 'name' => $account->name, 'type' => $account->type->value,
            'is_active' => $account->is_active, 'description' => $account->description ?? '']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->code = trim($this->code);
        $this->name = trim($this->name);
        $data = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('accounts', 'code')->where('tenant_id', app(TenantContext::class)->requireId())->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::enum(AccountType::class)],
            'is_active' => ['boolean'], 'description' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            DB::transaction(function () use ($data): void {
                $account = $this->editingId ? Account::query()->lockForUpdate()->findOrFail($this->editingId) : new Account;
                $account->fill($data)->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Ce code de compte existe déjà dans votre chorale.']);
        }
        $this->showForm = false;
        unset($this->accounts);
        $this->dispatch('toast', type: 'success', message: 'Compte d’opération enregistré.');
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()->withCount('transactions')
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('code', '%'.trim($this->search).'%')->orWhereLike('name', '%'.trim($this->search).'%')))
            ->when($this->state !== '', fn ($q) => $q->where('is_active', $this->state === 'active'))
            ->orderBy('code')->get();
    }

    public function render()
    {
        return view('livewire.accounting.accounts', ['types' => AccountType::options()]);
    }
}
