<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
        if (app()->isProduction()) {
            throw new \LogicException('Les comptes de démonstration sont interdits en production.');
        }
        $users = [
            ['name' => 'Administrateur', 'username' => 'admin', 'email' => 'admin@chorale.ci', 'role' => UserRole::Admin],
            ['name' => 'Pierre Koffi', 'username' => 'directeur', 'email' => 'directeur@chorale.ci', 'role' => UserRole::Directeur],
            ['name' => 'Marie-Claire N’Guessan', 'username' => 'liturgique', 'email' => 'liturgique@chorale.ci', 'role' => UserRole::CommissionLiturgique],
            ['name' => 'Emmanuel Bamba', 'username' => 'instruments', 'email' => 'instruments@chorale.ci', 'role' => UserRole::CommissionInstruments],
            ['name' => 'Sylvie Traoré', 'username' => 'tresorerie', 'email' => 'tresorerie@chorale.ci', 'role' => UserRole::Tresorier],
            ['name' => 'Bernadette Diallo', 'username' => 'secretariat', 'email' => 'secretariat@chorale.ci', 'role' => UserRole::Secretaire],
            ['name' => 'Jean-Paul Yao', 'username' => 'choriste', 'email' => 'choriste@chorale.ci', 'role' => UserRole::Choriste],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
