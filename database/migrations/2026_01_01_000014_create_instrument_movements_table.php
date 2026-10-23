<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('type');
            $table->date('moved_on');
            $table->string('reason')->nullable();
            $table->string('destination')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('members')->nullOnDelete();
            $table->date('expected_return_on')->nullable();
            $table->date('actual_return_on')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['instrument_id', 'moved_on']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_movements');
    }
};
