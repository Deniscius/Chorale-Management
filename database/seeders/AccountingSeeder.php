<?php

namespace Database\Seeders;

use App\Enums\DonorType;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Choir;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $chorale = Choir::where('slug', 'chorale-saint-joseph')->firstOrFail();
        $annee = now()->year;

        // Plan comptable
        $comptes = collect([
            ['501', 'Caisse de la chorale', 'tresorerie'],
            ['502', 'Caisse des offrandes', 'tresorerie'],
            ['503', 'Compte bancaire', 'tresorerie'],
            ['61', 'Achats de partitions', 'charge'],
            ['62', 'Maintenance des instruments', 'charge'],
            ['63', 'Location de salle', 'charge'],
            ['64', 'Frais de déplacement', 'charge'],
            ['65', 'Frais divers', 'charge'],
            ['71', 'Produits des concerts', 'produit'],
            ['72', 'Cotisations des choristes', 'produit'],
            ['73', 'Subventions et dons', 'produit'],
            ['74', 'Offrandes', 'produit'],
        ])->map(function (array $ligne) {
            [$code, $nom, $type] = $ligne;

            return Account::updateOrCreate(['code' => $code], [
                'name' => $nom,
                'type' => $type,
                'is_active' => true,
            ]);
        });

        $compteCaisse = $comptes->firstWhere('code', '501');

        // Budgets
        foreach (['Fonctionnement', 'Partitions', 'Instruments', 'Formation', 'Sorties', 'Concerts'] as $categorie) {
            Budget::updateOrCreate(
                ['choir_id' => $chorale->id, 'year' => $annee, 'category' => $categorie],
                [
                    'allocated_amount' => random_int(3, 40) * 25000,
                    'committed_amount' => random_int(1, 25) * 25000,
                    'realized_amount' => random_int(1, 20) * 25000,
                ],
            );
        }

        // Opérations
        $budgets = Budget::where('choir_id', $chorale->id)->get();
        $membres = Member::where('choir_id', $chorale->id)->where('status', 'actif')->get();

        $transactions = Transaction::factory()->count(48)->make();

        foreach ($transactions as $transaction) {
            $transaction->fill([
                'choir_id' => $chorale->id,
                'account_id' => $compteCaisse->id,
                'budget_id' => $budgets->random()->id,
                'member_id' => $transaction->type === 'recette' ? $membres->random()->id : null,
                'created_by' => User::where('role', UserRole::Admin->value)->value('id'),
                'validated_by' => $transaction->status === TransactionStatus::Validee ? User::where('role', UserRole::Tresorier->value)->value('id') : null,
                'validated_at' => $transaction->status === TransactionStatus::Validee ? now() : null,
            ])->save();
        }

        // Cotisations des choristes
        $membresActifs = Member::where('choir_id', $chorale->id)->where('status', 'actif')->get();

        foreach ($membresActifs as $membre) {
            for ($mois = 1; $mois <= min(12, (int) now()->month); $mois++) {
                Contribution::factory()->create([
                    'member_id' => $membre->id,
                    'choir_id' => $chorale->id,
                    'year' => $annee,
                    'month' => $mois,
                ]);
            }
        }

        // Dons
        foreach (Donation::factory()->count(14)->make() as $don) {
            $don->fill([
                'choir_id' => $chorale->id,
                'member_id' => $don->donor_type === DonorType::Choriste ? $membres->random()->id : null,
            ])->save();
        }
    }
}
