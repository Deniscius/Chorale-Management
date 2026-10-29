<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('preview_path')->nullable()->after('path');
            $table->string('preview_status', 20)->default('none')->after('preview_path');
            $table->text('preview_error')->nullable()->after('preview_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn(['preview_path', 'preview_status', 'preview_error']));
    }
};
