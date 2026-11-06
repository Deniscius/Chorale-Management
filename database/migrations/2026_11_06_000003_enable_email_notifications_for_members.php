<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Email is the default delivery channel for every chorister with an address.
        DB::table('members')->where('notify_email', false)->update(['notify_email' => true]);
    }
};
