<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Choir;
use App\Models\Member;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();
        $membres = Member::where('choir_id', $chorale->id)->where('status', 'actif')->get();

        $activites = Activity::factory()->count(16)->make();

        foreach ($activites as $activite) {
            $activite->save([
                'choir_id' => $chorale->id,
                'organizer_id' => $membres->random()->id,
                'created_by' => 1,
            ]);

            $participants = $membres->random(random_int(5, 20));

            foreach ($participants as $participant) {
                $activite->participants()->attach($participant->id, [
                    'role' => 'participant',
                    'status' => match (true) {
                        $activite->status === 'terminee' => 'present',
                        random_int(1, 100) <= 70 => 'present',
                        random_int(1, 100) <= 85 => 'excuse',
                        default => 'absent',
                    },
                    'hours' => random_int(2, 8),
                ]);
            }
        }
    }
}
