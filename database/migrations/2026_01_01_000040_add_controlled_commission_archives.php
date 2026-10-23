<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_teams', function (Blueprint $t) {
            $t->id();
            $t->string('commission')->unique();
            $t->foreignId('leader_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->foreignId('associate_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        foreach (['commission_liturgique' => 'commission_liturgique', 'commission_secretariat' => 'secretaire'] as $commission => $role) {
            $ids = DB::table('users')->where('role', $role)->pluck('id');
            DB::table('commission_teams')->insert(['commission' => $commission, 'leader_id' => $ids->count() === 1 ? $ids->first() : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('documents', function (Blueprint $t) {
            $t->string('visibility', 20)->default('private');
            $t->date('document_date')->nullable();
            $t->foreignId('correspondence_id')->nullable()->index()->constrained('correspondences')->restrictOnDelete();
            $t->index(['commission', 'visibility', 'document_date']);
        });
        DB::table('documents')->whereNotIn('commission', ['commission_liturgique', 'commission_secretariat'])->update(['visibility' => 'commission']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropIndex(['commission', 'visibility', 'document_date']);
            $t->dropConstrainedForeignId('correspondence_id');
            $t->dropColumn(['visibility', 'document_date']);
        });
        Schema::dropIfExists('commission_teams');
    }
};
