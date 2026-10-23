<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

/**
 * Nature du don reçu par la chorale (à distinguer du type de donateur).
 */
enum DonationType: string
{
    case OffrandeMesse = 'offrande_messe';
    case SupportCaisse = 'support_caisse';
    case DonMateriel = 'don_materiel';
    case CotisationExceptionnelle = 'cotisation_exceptionnelle';
    case Subvention = 'subvention';
    case Collecte = 'collecte';
    case Mecenat = 'mecenat';
    case Autre = 'autre';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::OffrandeMesse->value => 'Offrande de messe',
            self::SupportCaisse->value => 'Support de caisse',
            self::DonMateriel->value => 'Don en nature / matériel',
            self::CotisationExceptionnelle->value => 'Cotisation exceptionnelle',
            self::Subvention->value => 'Subvention',
            self::Collecte->value => 'Collecte',
            self::Mecenat->value => 'Mécénat / parrainage',
            self::Autre->value => 'Autre',
        ];
    }

    public static function colors(): array
    {
        return [
            self::OffrandeMesse->value => 'amber',
            self::SupportCaisse->value => 'emerald',
            self::DonMateriel->value => 'orange',
            self::CotisationExceptionnelle->value => 'blue',
            self::Subvention->value => 'violet',
            self::Collecte->value => 'cyan',
            self::Mecenat->value => 'pink',
            self::Autre->value => 'gray',
        ];
    }

    /**
     * Description courte, affichée en aide du formulaire.
     */
    public function description(): string
    {
        return match ($this) {
            self::OffrandeMesse => 'Offrande recueillie lors d\'une célébration.',
            self::SupportCaisse => 'Contribution destinée au fonds de caisse de la chorale.',
            self::DonMateriel => 'Bien, équipement ou fourniture remis en nature.',
            self::CotisationExceptionnelle => 'Cotisation ponctuelle hors campagne annuelle.',
            self::Subvention => 'Aide financière d\'une institution ou d\'une paroisse.',
            self::Collecte => 'Collecte organisée lors d\'un événement.',
            self::Mecenat => 'Soutien d\'un mécène ou d\'un parrain.',
            self::Autre => 'Autre nature de don non listée.',
        };
    }
}
