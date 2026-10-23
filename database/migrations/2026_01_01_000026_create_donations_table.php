<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->string('donor_type');
            $table->string('donor_name')->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('donated_on');
            $table->string('purpose')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['donor_type', 'donated_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
