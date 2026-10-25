<?php

namespace App\Console\Commands;

use App\Models\Platform\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class BootstrapDemoOwner extends Command
{
    protected $signature = 'platform:bootstrap-demo';

    protected $description = 'Initialiser une seule fois le propriétaire de la démo sans terminal interactif';

    public function handle(): int
    {
        if (! config('deployment.demo')) {
            $this->error('Cette initialisation est réservée au mode démonstration.');

            return self::FAILURE;
        }

        return DB::transaction(function () {
            // Serialize first provisioning on the reserved workspace row.
            DB::table('tenants')->where('id', 1)->lockForUpdate()->firstOrFail();
            if (Admin::exists()) {
                $this->info('Propriétaire existant conservé.');

                return self::SUCCESS;
            }
            $data = config('deployment.bootstrap');
            $validation = Validator::make($data, [
                'email' => 'required|email|max:255',
                'password' => ['required', Password::min(16)->letters()->numbers()],
                'totp_secret' => ['required', 'regex:/^[A-Z2-7]{32}$/D'],
            ]);
            if ($validation->fails()) {
                $this->error('Configurez les trois variables PLATFORM_BOOTSTRAP dans les secrets Render. Aucun accès créé.');

                return self::FAILURE;
            }
            $admin = Admin::create(['email' => mb_strtolower(trim($data['email'])), 'name' => 'Propriétaire', 'password' => $data['password'], 'totp_secret' => $data['totp_secret'], 'last_totp_counter' => -1, 'enabled' => true]);
            DB::table('platform_audit_events')->insert(['platform_admin_id' => $admin->id, 'action' => 'admin_created', 'reason' => 'Initialisation unique de la démonstration depuis les secrets hébergeur', 'created_at' => now()]);
            $this->info('Propriétaire initialisé. Retirez les variables PLATFORM_BOOTSTRAP des secrets hébergeur.');

            return self::SUCCESS;
        });
    }
}
