<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->timestamp('attendance_finalized_at')->nullable();
            $table->foreignId('attendance_finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('attendance_finalized_by');
        });

        Schema::table('rehearsal_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->timestamp('attendance_finalized_at')->nullable();
            $table->foreignId('attendance_finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('attendance_finalized_by');
        });

        Schema::table('activity_participants', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->text('excuse_reason')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });

        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->boolean('financial_tracking_enabled')->default(false);
            $table->decimal('rehearsal_absence_amount', 12, 2)->default(0);
            $table->decimal('activity_absence_amount', 12, 2)->default(0);
            $table->decimal('late_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('late_threshold_minutes')->default(1);
            $table->date('effective_from')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('tenant_id');
            $table->unique(['tenant_id', 'id']);
            $table->index('updated_by');
        });

        Schema::create('attendance_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('rehearsal_attendance_id')->nullable()->constrained('rehearsal_attendances')->cascadeOnDelete();
            $table->foreignId('activity_participant_id')->nullable()->constrained('activity_participants')->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->date('occurred_on');
            $table->string('event_label');
            $table->string('incident_type', 20);
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->json('rule_snapshot')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'rehearsal_attendance_id']);
            $table->unique(['tenant_id', 'activity_participant_id']);
            $table->index(['tenant_id', 'status', 'occurred_on']);
            $table->index(['tenant_id', 'member_id', 'occurred_on']);
            $table->index('member_id');
            $table->index('rehearsal_attendance_id');
            $table->index('activity_participant_id');
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_incidents');
        Schema::dropIfExists('attendance_rules');

        Schema::table('activity_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['minutes_late', 'excuse_reason', 'checked_at']);
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_finalized_by');
            $table->dropColumn('attendance_finalized_at');
        });
        Schema::table('rehearsal_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn('minutes_late');
        });
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_finalized_by');
            $table->dropColumn('attendance_finalized_at');
        });
    }
};
