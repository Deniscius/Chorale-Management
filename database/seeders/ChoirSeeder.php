<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\Voice;
use App\Models\Choir;
use App\Models\Member;
use App\Models\User;

class ChoirSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->assertDemoEnvironment();
        $chorale = Choir::firstOrCreate(
            ['slug' => 'chorale-saint-joseph'],
            [
                'name' => 'Chorale Saint-Joseph',
                'description' => 'Chorale paroissiale recibida le 3 octobre 1987, au service liturgique de la paroisse Saint-Joseph.',
                'parish' => 'Paroisse Saint-Joseph',
                'patron' => 'Saint-Joseph',
                'target_size' => 60,
                'founded_on' => '1987-10-03',
                'is_active' => true,
            ],
        );

        $choraleEnfants = Choir::firstOrCreate(
            ['slug' => 'chorale-des-enfants'],
            [
                'name' => 'Chorale des enfants',
                'description' => 'Éveil musical et formation liturgique des enfants du catéchisme.',
                'parish' => 'Paroisse Saint-Joseph',
                'patron' => 'Saint-Joseph',
                'target_size' => 25,
                'founded_on' => '2015-09-01',
                'is_active' => true,
            ],
        );

        // Membres de la chorale principale
        $actifs = Member::factory()->count(42)->create([
            'choir_id' => $chorale->id,
            'status' => MemberStatus::Actif->value,
        ]);

        Member::factory()->count(6)->create([
            'choir_id' => $chorale->id,
            'status' => MemberStatus::Invite->value,
            'joined_on' => null,
        ]);

        Member::factory()->count(4)->create([
            'choir_id' => $chorale->id,
            'status' => MemberStatus::Inactif->value,
            'left_on' => now()->subMonths(random_int(2, 24))->toDateString(),
        ]);

        // Répartition équilibrée des voix
        $voix = [Voice::Soprano, Voice::Alto, Voice::Tenor, Voice::Bass];
        $i = 0;

        foreach ($actifs as $membre) {
            $voixChoisie = $voix[$i % count($voix)];
            $pupitres = $voixChoisie->parts();

            $membre->update([
                'voice' => $voixChoisie->value,
                'part' => $pupitres[array_rand($pupitres)],
            ]);

            $i++;
        }

        // Membres de la chorale des enfants
        Member::factory()->count(14)->create([
            'choir_id' => $choraleEnfants->id,
            'status' => MemberStatus::Actif->value,
            'voice' => Voice::Soprano->value,
        ]);

        // Direction de la chorale
        $directeur = Member::factory()->create([
            'choir_id' => $chorale->id,
            'first_name' => 'Pierre',
            'last_name' => 'Koffi',
            'email' => 'pierre.koffi@chorale.ci',
            'voice' => Voice::Tenor->value,
            'part' => 'T',
            'status' => MemberStatus::Actif->value,
            'profession' => 'Chef de chœur',
            'joined_on' => '2005-01-15',
        ]);

        $chorale->update(['director_id' => $directeur->id]);

        // Responsables de commission
        $liturgique = Member::factory()->create([
            'choir_id' => $chorale->id,
            'first_name' => 'Marie-Claire',
            'last_name' => 'N’Guessan',
            'email' => 'mc.nguessan@chorale.ci',
            'voice' => Voice::Alto->value,
            'part' => 'A1',
            'status' => MemberStatus::Actif->value,
            'commission' => 'liturgique',
            'joined_on' => '2009-04-12',
        ]);

        $instruments = Member::factory()->create([
            'choir_id' => $chorale->id,
            'first_name' => 'Emmanuel',
            'last_name' => 'Bamba',
            'email' => 'emmanuel.bamba@chorale.ci',
            'voice' => Voice::Bass->value,
            'part' => 'B',
            'status' => MemberStatus::Actif->value,
            'commission' => 'instruments',
            'joined_on' => '2011-02-20',
        ]);

        $tresorier = Member::factory()->create([
            'choir_id' => $chorale->id,
            'first_name' => 'Sylvie',
            'last_name' => 'Traoré',
            'email' => 'sylvie.traore@chorale.ci',
            'voice' => Voice::Soprano->value,
            'part' => 'S1',
            'status' => MemberStatus::Actif->value,
            'commission' => 'tresorerie',
            'joined_on' => '2013-09-01',
        ]);

        $secretaire = Member::factory()->create([
            'choir_id' => $chorale->id,
            'first_name' => 'Bernadette',
            'last_name' => 'Diallo',
            'email' => 'bernadette.diallo@chorale.ci',
            'voice' => Voice::Alto->value,
            'part' => 'A2',
            'status' => MemberStatus::Actif->value,
            'commission' => 'secretariat',
            'joined_on' => '2016-01-08',
        ]);

        // Rattachement des comptes utilisateurs aux fiches de choristes
        User::where('email', 'directeur@chorale.ci')->update(['member_id' => $directeur->id]);
        User::where('email', 'liturgique@chorale.ci')->update(['member_id' => $liturgique->id]);
        User::where('email', 'instruments@chorale.ci')->update(['member_id' => $instruments->id]);
        User::where('email', 'tresorerie@chorale.ci')->update(['member_id' => $tresorier->id]);
        User::where('email', 'secretariat@chorale.ci')->update(['member_id' => $secretaire->id]);
    }
}
