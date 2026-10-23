<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('choirs', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('email')->nullable()->after('parish');
            $table->string('phone')->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('country')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('choirs', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'email', 'phone', 'address', 'city', 'country']);
        });
    }
};
