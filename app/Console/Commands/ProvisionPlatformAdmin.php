<?php

namespace App\Console\Commands;

use App\Models\Platform\Admin;
use App\Services\Platform\Totp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class ProvisionPlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {--name=} {--reset : Réinitialiser les accès existants et révoquer les sessions}';

    protected $description = 'Créer ou récupérer un compte propriétaire avec double authentification obligatoire';

    public function handle(Totp $totp): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Exécutez cette commande dans un terminal interactif sécurisé.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = $this->option('name') ?: $this->ask('Votre nom');
        $password = $this->secret('Mot de passe (16 caractères minimum, lettres et chiffres)');
        $confirmation = $this->secret('Confirmez le mot de passe');
        $validation = Validator::make(['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation], ['email' => 'required|email|max:255', 'name' => 'required|string|max:120', 'password' => ['required', 'confirmed', Password::min(16)->letters()->numbers()]]);
        if ($validation->fails()) {
            $this->error('Informations invalides. Vérifiez l’adresse et les exigences du mot de passe.');

            return self::FAILURE;
        }
        $existing = Admin::where('email', $email)->first();
        if ($existing && (! $this->option('reset') || ! $this->confirm('Réinitialiser ce compte et invalider ses sessions ?'))) {
            $this->error('Le compte existe déjà. Utilisez --reset pour une récupération explicite.');

            return self::FAILURE;
        }
        $secret = $totp->secret();
        $this->warn('Ajoutez cette clé à votre application d’authentification. Ne la partagez pas et ne capturez pas ce terminal.');
        $this->line($secret);
        $this->line('Compte : '.$email.' · Type TOTP · SHA-1 · 6 chiffres · 30 secondes');
        $code = (string) $this->secret('Code affiché par votre application pour confirmer la configuration');
        $counter = $totp->counter($secret, $code, -1);
        if ($counter === null) {
            $this->error('Code incorrect. Aucun accès créé ou modifié.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $name, $password, $secret, $counter, $existing) {
            $admin = Admin::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'totp_secret' => $secret, 'last_totp_counter' => $counter, 'enabled' => true]);
            DB::table('platform_audit_events')->insert(['platform_admin_id' => $admin->id, 'action' => $existing ? 'credentials_reset' : 'admin_created', 'reason' => 'Provisionnement depuis le terminal du serveur', 'created_at' => now()]);
        });
        $this->info('Accès configuré. Ouvrez /plateforme/connexion et utilisez le prochain code de votre application.');

        return self::SUCCESS;
    }
}
