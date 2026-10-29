<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id');
            $table->string('fingerprint', 64);
            $table->string('kind', 40);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'fingerprint'], 'notification_dispatch_unique');
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index(['tenant_id', 'kind', 'scheduled_for']);
            $table->index(['tenant_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
