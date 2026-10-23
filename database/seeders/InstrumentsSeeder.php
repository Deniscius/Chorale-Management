<?php

namespace Database\Seeders;

use App\Models\Choir;
use App\Models\Instrument;
use App\Models\InstrumentEnsemble;
use App\Models\InstrumentMaintenance;
use App\Models\InstrumentMovement;
use App\Models\Member;
use Illuminate\Database\Seeder;

class InstrumentsSeeder extends Seeder
{
    public function run(): void
    {
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();

        $custodians = Member::where('choir_id', $chorale->id)
            ->where('status', 'actif')
            ->inRandomOrder()
            ->limit(4)
            ->get();

        $instruments = Instrument::factory()->count(22)->make();

        foreach ($instruments as $index => $instrument) {
            $instrument->save([
                'choir_id' => $chorale->id,
                'custodian_id' => $custodians[$index % max(1, $custodians->count())]->id ?? null,
            ]);
        }

        // Mouvements de matériel
        foreach ($instruments as $instrument) {
            $nbMouvements = random_int(0, 4);

            for ($i = 0; $i < $nbMouvements; $i++) {
                InstrumentMovement::factory()->create([
                    'instrument_id' => $instrument->id,
                    'responsible_id' => $custodians->random()->id ?? null,
                    'created_by' => 1,
                ]);
            }
        }

        // Entretiens et accord
        foreach ($instruments->random(12) as $instrument) {
            InstrumentMaintenance::factory()->count(random_int(1, 3))->create([
                'instrument_id' => $instrument->id,
                'created_by' => 1,
            ]);
        }

        // Ensembles instrumentaux
        InstrumentEnsemble::factory()->count(5)->create([
            'choir_id' => $chorale->id,
            'conductor_id' => $custodians->random()->id ?? null,
        ]);
    }
}
