<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('path', 512);
            $t->string('mime_type', 150);
            $t->unsignedBigInteger('size');
            $t->unsignedBigInteger('stored_bytes');
            $t->string('sha256', 64);
            $t->longText('payload');
            $t->timestamps();
            $t->unique(['tenant_id', 'path']);
        });
        Schema::create('private_file_mutex', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('private_file_mutex')->insert(['id' => 1]);
        Schema::table('choir_commissions', function (Blueprint $t) {
            $t->string('module_key', 40)->nullable();
            $t->string('archive_commission', 60)->nullable();
            $t->foreignId('leader_id')->nullable()->index();
            $t->foreignId('associate_id')->nullable()->index();
            $t->unique(['tenant_id', 'archive_commission']);
            foreach (['leader_id', 'associate_id'] as $key) {
                $t->foreign(['tenant_id', $key], 'commission_'.$key.'_tenant_fk')->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Les fichiers persistants et les délégations nécessitent une restauration cohérente, pas une suppression automatique.');
    }
};
