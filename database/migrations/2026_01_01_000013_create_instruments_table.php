<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition')->default('bon');
            $table->date('purchase_on')->nullable();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('current_value', 14, 2)->default(0);
            $table->string('location')->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('members')->nullOnDelete();
            $table->boolean('is_borrowed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'condition']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
