<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \LogicException('Les jeux de démonstration sont interdits en production.');
        }
        $this->call([
            AdminUserSeeder::class,
            ChoirSeeder::class,
            LiturgicalSeeder::class,
            InstrumentsSeeder::class,
            CorrespondenceSeeder::class,
            ActivitySeeder::class,
            AccountingSeeder::class,
        ]);
    }
}
