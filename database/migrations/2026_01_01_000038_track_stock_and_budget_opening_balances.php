<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_movements', fn (Blueprint $t) => $t->unsignedInteger('quantity')->default(1));
        Schema::table('budgets', fn (Blueprint $t) => $t->decimal('opening_realized_amount', 14, 2)->default(0));
        // Preserve the old realized_amount for reconciliation. Only the portion not
        // already represented by posted expenses becomes the opening balance.
        foreach (DB::table('budgets')->orderBy('id')->lazyById() as $b) {
            $posted = DB::table('transactions')->where('budget_id', $b->id)->where('type', 'depense')->whereIn('status', ['validee', 'cloturee'])->sum('amount');
            DB::table('budgets')->where('id', $b->id)->update(['opening_realized_amount' => max(0, round((float) $b->realized_amount - (float) $posted, 2))]);
        }
        // Existing open loans must be considered even when their latest sibling was returned.
        DB::table('instruments')->update(['is_borrowed' => false]);
        DB::table('instruments')->whereIn('id', DB::table('instrument_movements')->select('instrument_id')->whereIn('type', ['sortie', 'pret'])->whereNull('actual_return_on'))->update(['is_borrowed' => true]);
    }

    public function down(): void
    {
        Schema::table('budgets', fn (Blueprint $t) => $t->dropColumn('opening_realized_amount'));
        Schema::table('instrument_movements', fn (Blueprint $t) => $t->dropColumn('quantity'));
    }
};
