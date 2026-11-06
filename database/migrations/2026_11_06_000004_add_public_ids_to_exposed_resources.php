<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users', 'members', 'documents', 'instruments', 'voice_resources',
        'choir_commissions', 'letterhead_templates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('public_id')->nullable()->unique();
            });

            DB::table($table)->whereNull('public_id')->orderBy('id')->each(function (object $row) use ($table): void {
                DB::table($table)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique($this->uniqueName($table));
                $blueprint->dropColumn('public_id');
            });
        }
    }

    private function uniqueName(string $table): string
    {
        return $table.'_public_id_unique';
    }
};
