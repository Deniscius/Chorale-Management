<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->restrictOnDelete();
            $table->date('incurred_on');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('claimed_on')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'incurred_on']);
            foreach (['member_id' => 'members', 'activity_id' => 'activities'] as $column => $parent) {
                $table->foreign(['tenant_id', $column], 'member_expenses_'.$column.'_tenant_fk')
                    ->references(['tenant_id', 'id'])->on($parent);
                $table->index($column);
            }
            $table->index('decided_by');
            $table->index('created_by');
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('member_expense_id')->nullable()->constrained('member_expenses')->restrictOnDelete();
            $table->uuid('reimbursement_key')->nullable();
            $table->unique(['tenant_id', 'reimbursement_key']);
            $table->index(['tenant_id', 'member_expense_id']);
            $table->index('member_expense_id');
            $table->foreign(['tenant_id', 'member_expense_id'], 'transactions_member_expense_tenant_fk')
                ->references(['tenant_id', 'id'])->on('member_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign('transactions_member_expense_tenant_fk');
            $table->dropUnique(['tenant_id', 'reimbursement_key']);
            $table->dropIndex(['tenant_id', 'member_expense_id']);
            $table->dropIndex(['member_expense_id']);
            $table->dropConstrainedForeignId('member_expense_id');
            $table->dropColumn('reimbursement_key');
        });
        Schema::dropIfExists('member_expenses');
    }
};
