<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->select('member_id')->whereNotNull('member_id')->groupBy('member_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Plusieurs comptes sont liés au même choriste. Rapprochez ces comptes avant de relancer la migration ; aucune donnée n’a été supprimée.');
        }
        Schema::table('users', fn (Blueprint $t) => $t->unique('member_id'));
        Schema::create('operation_locks', function (Blueprint $t) {
            $t->string('key')->primary();
        });
        DB::table('operation_locks')->insert(['key' => 'accounts']);
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable()->index();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('action', 20);
            $t->json('changes');
            $t->timestamp('created_at');
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('operation_locks');
        Schema::table('users', fn (Blueprint $t) => $t->dropUnique(['member_id']));
    }
};
