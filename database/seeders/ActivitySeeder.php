<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Choir;
use App\Models\Member;
use App\Models\User;

class ActivitySeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();
        $membres = Member::where('choir_id', $chorale->id)->where('status', 'actif')->get();

        $activites = Activity::factory()->count(16)->make();

        foreach ($activites as $activite) {
            $activite->fill([
                'choir_id' => $chorale->id,
                'organizer_id' => $membres->random()->id,
                'created_by' => User::where('role', UserRole::Admin->value)->value('id'),
            ])->save();

            $participants = $membres->random(random_int(5, 20));

            foreach ($participants as $participant) {
                $activite->participants()->attach($participant->id, [
                    'role' => 'participant',
                    'status' => match (true) {
                        $activite->status === ActivityStatus::Terminee => 'present',
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
