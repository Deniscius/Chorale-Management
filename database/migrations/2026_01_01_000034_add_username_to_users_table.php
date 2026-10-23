<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Identifiant initial dérivé de l'e-mail : la partie avant « @ ».
        // Un suffixe numérique est ajouté en cas de collision.
        $used = [];

        User::query()->orderBy('id')->chunkById(100, function ($users) use (&$used) {
            foreach ($users as $user) {
                $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($user->email, '@', true) ?: 'utilisateur'));
                $base = $base !== '' ? $base : 'utilisateur'.$user->id;

                $username = $base;
                $suffix = 1;

                while (in_array($username, $used, true)) {
                    $username = $base.$suffix++;
                }

                $used[] = $username;

                $user->forceFill(['username' => $username])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
