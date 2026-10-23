<?php

namespace Database\Factories;

use App\Models\Correspondence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Correspondence>
 */
class CorrespondenceFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['entrant', 'sortant']);

        $senders = [
            'Mgr Jean-Baptiste KOFFI', 'Curé de la paroisse', 'Diocèse d’Abidjan', 'Paroisse Sainte-Anne',
            'Ministère de la Culture', 'Association des choristes', 'UNESCO', 'Mairie de la commune',
            'Direction diocésaine des œuvres', 'Comité paroissial',
        ];

        $subjects = [
            'Demande de participation à la fête patronale', 'Convocation à l’assemblée générale',
            'Remerciements pour la prestation du 12 mars', 'Réorganisation du calendrier liturgique',
            'Demande de prêt de matériel', 'Attestation de participation', 'Rapport d’activité annuel',
            'Invitation à la formation de chant liturgique', 'Demande de partition', 'Avenant au budget',
        ];

        $date = fake()->dateTimeBetween('-18 months', 'now');

        return [
            'type' => $type,
            'correspondence_date' => $date,
            'received_at' => $type === 'entrant' ? $date : null,
            'sent_at' => $type === 'sortant' ? $date : null,
            'sender_name' => fake()->randomElement($senders),
            'sender_organization' => fake()->boolean(60) ? fake()->company() : null,
            'recipient_name' => $type === 'sortant' ? fake()->randomElement($senders) : null,
            'subject' => fake()->randomElement($subjects),
            'body' => fake()->paragraph(),
            'channel' => fake()->randomElement(['courrier', 'recommande', 'email', 'main_propre', 'telephone']),
            'confidentiality' => fake()->randomElement(['normal', 'normal', 'normal', 'confidentiel']),
            'status' => fake()->randomElement(['recu', 'en_cours', 'traite', 'clos', 'archive']),
            'document_path' => null,
            'notes' => null,
        ];
    }

    public function entrant(): static
    {
        return $this->state(fn () => ['type' => 'entrant', 'sent_at' => null, 'status' => 'recu']);
    }

    public function sortant(): static
    {
        return $this->state(fn () => ['type' => 'sortant', 'received_at' => null, 'status' => 'clos']);
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => fake()->randomElement(['recu', 'en_cours'])]);
    }
}
