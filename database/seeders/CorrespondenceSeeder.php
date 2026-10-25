<?php

namespace Database\Seeders;

use App\Enums\CorrespondenceStatus;
use App\Enums\UserRole;
use App\Models\Choir;
use App\Models\Correspondence;
use App\Models\CorrespondenceEvent;
use App\Models\CorrespondenceService;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Str;

class CorrespondenceSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();

        $services = collect([
            'Bureau pastoral' => 'Réception et acheminement de la correspondance générale.',
            'Commission liturgique' => 'Partitions, calendrier et planning des célébrations.',
            'Commission des instruments' => 'Matériel, prêts et maintenance.',
            'Trésorerie' => 'Demandes de subvention, offrandes et pièces justificatives.',
            'Secrétariat général' => 'Convocations, comptes rendus et archives.',
        ])->map(function (string $description, string $name) {
            return CorrespondenceService::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                ],
            );
        });

        $membres = Member::where('choir_id', $chorale->id)->where('status', 'actif')->get();

        $courriers = Correspondence::factory()->count(38)->make();

        foreach ($courriers as $courrier) {
            $courrier->fill([
                'service_id' => $services->random()->id,
                'assigned_to' => $membres->random()->id,
                'created_by' => User::where('role', UserRole::Admin->value)->value('id'),
            ])->save();

            // Historique de traitement
            CorrespondenceEvent::create([
                'correspondence_id' => $courrier->id,
                'user_id' => User::where('role', UserRole::Admin->value)->value('id'),
                'action' => 'Enregistrement du courrier',
                'note' => 'Courrier enregistré dans le registre.',
            ]);

            if ($courrier->status !== CorrespondenceStatus::Recu) {
                CorrespondenceEvent::create([
                    'correspondence_id' => $courrier->id,
                    'user_id' => User::where('role', UserRole::Admin->value)->value('id'),
                    'action' => 'Affectation',
                    'note' => 'Courrier transmis au service concerné.',
                ]);
            }

            if (in_array($courrier->status, [CorrespondenceStatus::Clos, CorrespondenceStatus::Archive], true)) {
                CorrespondenceEvent::create([
                    'correspondence_id' => $courrier->id,
                    'user_id' => User::where('role', UserRole::Admin->value)->value('id'),
                    'action' => 'Clôture',
                    'note' => 'Traitement achevé, courrier archivé.',
                ]);
            }
        }
    }
}
