<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

abstract class DemoSeeder extends Seeder
{
    protected function assertDemoEnvironment(): void
    {
        if (app()->isProduction()) {
            throw new \LogicException('Les jeux de démonstration sont interdits en production.');
        }
    }
}
