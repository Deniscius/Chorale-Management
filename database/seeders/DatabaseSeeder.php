<?php

namespace Database\Seeders;

class DatabaseSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
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
