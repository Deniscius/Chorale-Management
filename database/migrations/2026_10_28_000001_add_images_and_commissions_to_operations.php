<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('choir_commission_id')->nullable()->index();
            $table->foreign(['tenant_id', 'choir_commission_id'], 'activity_commission_tenant_fk')
                ->references(['tenant_id', 'id'])->on('choir_commissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign('activity_commission_tenant_fk');
            $table->dropColumn('choir_commission_id');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
