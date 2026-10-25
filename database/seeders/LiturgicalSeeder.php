<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Choir;
use App\Models\LiturgicalSeason;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\User;
use App\Models\Work;
use Illuminate\Support\Str;

class LiturgicalSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();
        $annee = now()->year;

        $saisons = collect([
            ['Avent', '11-01', '12-24', 'violet', 'Temps de préparation de Noël.'],
            ['Noël', '12-25', '01-05', 'red', 'Solennité de la Nativité du Seigneur.'],
            ['Carême', '02-17', '04-11', 'violet', 'Temps de préparation pascale.'],
            ['Pâques', '04-12', '05-24', 'white', 'Temps pascal.'],
            ['Temps de Pentecôte', '05-25', '06-28', 'red', 'Cinquième temps de la année liturgique.'],
            ['Temps ordinaire', '06-29', '11-30', 'green', 'Temps ordinaire de l’année liturgique.'],
            ['Saints', '11-01', '11-30', 'white', 'Fêtes des saints et de la Vierge.'],
        ])->map(function (array $ligne) use ($annee) {
            [$nom, $debut, $fin, $couleur, $description] = $ligne;

            $moisDebut = (int) substr($debut, 0, 2);
            $jourDebut = (int) substr($debut, 3, 2);
            $moisFin = (int) substr($fin, 0, 2);
            $jourFin = (int) substr($fin, 3, 2);

            return LiturgicalSeason::updateOrCreate(
                ['slug' => Str::slug($nom)],
                [
                    'name' => $nom,
                    'starts_on' => sprintf('%d-%02d-%02d', $annee, $moisDebut, $jourDebut),
                    'ends_on' => sprintf('%d-%02d-%02d', $moisFin >= $moisDebut ? $annee : $annee + 1, $moisFin, $jourFin),
                    'color' => $couleur,
                    'description' => $description,
                ],
            );
        });

        // Répertoire musical
        $oeuvres = collect();
        $toutes = Work::factory()->count(26)->make();

        foreach ($toutes as $index => $oeuvre) {
            $saison = $saisons->random();

            $oeuvre->fill([
                'choir_id' => $chorale->id,
                'season_id' => $saison->id,
                'is_repertoire' => $index < 20,
            ])->save();

            $oeuvres->push($oeuvre);
        }

        $repertoire = $oeuvres->where('is_repertoire', true);

        // Répétitions passées et à venir
        $repetitions = collect([
            Rehearsal::factory()->past()->count(6)->create(),
            Rehearsal::factory()->upcoming()->count(6)->create(),
        ])->flatten();

        foreach ($repetitions as $repetition) {
            $repetition->update([
                'choir_id' => $chorale->id,
                'created_by' => User::where('role', UserRole::Admin->value)->value('id'),
            ]);

            // Programme de la répétition
            $oeuvresDuJour = $repertoire->random(min(3, $repertoire->count()));

            foreach ($oeuvresDuJour as $ordre => $oeuvre) {
                $repetition->works()->attach($oeuvre->id, [
                    'order' => $ordre + 1,
                    'key_signature' => $oeuvre->key_signature,
                    'tempo' => $oeuvre->tempo,
                    'focus' => $oeuvre->difficulty->value >= 4 ? 'Mise au point du passage difficile' : 'Polissage et équilibre',
                ]);
            }

            // Feuille de présence
            $participants = Member::where('choir_id', $chorale->id)
                ->where('status', 'actif')
                ->inRandomOrder()
                ->limit(random_int(18, 34))
                ->get();

            foreach ($participants as $participant) {
                $tirage = random_int(1, 100);

                RehearsalAttendance::create([
                    'rehearsal_id' => $repetition->id,
                    'member_id' => $participant->id,
                    'status' => match (true) {
                        $tirage <= 78 => 'present',
                        $tirage <= 88 => 'late',
                        $tirage <= 94 => 'excuse',
                        default => 'absent',
                    },
                    'excuse_reason' => null,
                    'checked_at' => now(),
                ]);
            }
        }

        // Célébrations et concerts
        $celebrations = Performance::factory()->count(10)->create([
            'choir_id' => $chorale->id,
            'created_by' => User::where('role', UserRole::Admin->value)->value('id'),
        ]);

        foreach ($celebrations as $celebration) {
            $programme = $oeuvres->random(random_int(4, 8));
            $roles = ['entree', 'psaume', 'offertory', 'communion', 'sortie', 'final'];

            foreach ($programme->values() as $index => $oeuvre) {
                $celebration->works()->attach($oeuvre->id, [
                    'order' => $index + 1,
                    'role' => $roles[$index % count($roles)],
                    'duration_minutes' => $oeuvre->duration_minutes,
                ]);
            }
        }
    }
}
