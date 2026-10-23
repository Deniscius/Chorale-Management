<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;

enum UserRole: string
{
    case Admin = 'admin';
    case Directeur = 'directeur';
    case CommissionLiturgique = 'commission_liturgique';
    case CommissionInstruments = 'commission_instruments';
    case CommissionVieCulturelle = 'commission_vie_culturelle';
    case CommissionSuivieFormation = 'commission_suivie_formation';
    case Tresorier = 'tresorier';
    case Secretaire = 'secretaire';
    case Choriste = 'choriste';

    use HasColor;
    use HasLabel;

    /**
     * Modules fonctionnels de l'application.
     */
    public const MODULE_MEMBERS = 'members';

    public const MODULE_LITURGIQUE = 'liturgique';

    public const MODULE_INSTRUMENTS = 'instruments';

    public const MODULE_COURRIERS = 'courriers';

    public const MODULE_ACTIVITES = 'activites';

    public const MODULE_COMPTABILITE = 'comptabilite';

    public static function labels(): array
    {
        return [
            self::Admin->value => 'Administrateur',
            self::Directeur->value => 'Directeur de la chorale',
            self::CommissionLiturgique->value => 'Commission liturgique',
            self::CommissionInstruments->value => 'Commission des instruments',
            self::CommissionVieCulturelle->value => 'Commission Vie culturelle',
            self::CommissionSuivieFormation->value => 'Commission Suivi et Formation',
            self::Tresorier->value => 'Trésorier',
            self::Secretaire->value => 'Secrétaire',
            self::Choriste->value => 'Choriste',
        ];
    }

    public static function colors(): array
    {
        return [
            self::Admin->value => 'red',
            self::Directeur->value => 'blue',
            self::CommissionLiturgique->value => 'purple',
            self::CommissionInstruments->value => 'orange',
            self::CommissionVieCulturelle->value => 'pink',
            self::CommissionSuivieFormation->value => 'indigo',
            self::Tresorier->value => 'emerald',
            self::Secretaire->value => 'cyan',
            self::Choriste->value => 'slate',
        ];
    }

    /**
     * Modules accessibles pour ce rôle.
     *
     * @return array<int, string>
     */
    public function modules(): array
    {
        return match ($this) {
            self::Admin => self::allModules(),
            self::Directeur => self::allModules(),
            self::CommissionLiturgique => [
                self::MODULE_MEMBERS, self::MODULE_LITURGIQUE,
                self::MODULE_INSTRUMENTS, self::MODULE_ACTIVITES,
            ],
            self::CommissionInstruments => [
                self::MODULE_MEMBERS, self::MODULE_INSTRUMENTS, self::MODULE_ACTIVITES,
            ],
            self::CommissionVieCulturelle => [
                self::MODULE_MEMBERS, self::MODULE_ACTIVITES, self::MODULE_COURRIERS,
            ],
            self::CommissionSuivieFormation => [
                self::MODULE_MEMBERS, self::MODULE_LITURGIQUE, self::MODULE_ACTIVITES,
            ],
            self::Tresorier => [
                self::MODULE_MEMBERS, self::MODULE_COURRIERS,
                self::MODULE_ACTIVITES, self::MODULE_COMPTABILITE,
            ],
            self::Secretaire => [
                self::MODULE_MEMBERS, self::MODULE_COURRIERS,
                self::MODULE_ACTIVITES, self::MODULE_LITURGIQUE,
            ],
            self::Choriste => [
                self::MODULE_MEMBERS, self::MODULE_LITURGIQUE, self::MODULE_ACTIVITES,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function allModules(): array
    {
        return [
            self::MODULE_MEMBERS, self::MODULE_LITURGIQUE, self::MODULE_INSTRUMENTS,
            self::MODULE_COURRIERS, self::MODULE_ACTIVITES, self::MODULE_COMPTABILITE,
        ];
    }

    public function canAccessModule(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }

    /**
     * Modules sur lesquels ce rôle a un droit d'écriture.
     *
     * @return array<int, string>
     */
    public function manageModules(): array
    {
        return match ($this) {
            self::Admin, self::Directeur => self::allModules(),
            self::CommissionLiturgique => [self::MODULE_LITURGIQUE],
            self::CommissionInstruments => [self::MODULE_INSTRUMENTS],
            self::CommissionVieCulturelle => [self::MODULE_ACTIVITES],
            self::CommissionSuivieFormation => [self::MODULE_LITURGIQUE],
            self::Tresorier => [self::MODULE_COMPTABILITE],
            self::Secretaire => [self::MODULE_COURRIERS, self::MODULE_MEMBERS],
            self::Choriste => [],
        };
    }

    public function canManageModule(string $module): bool
    {
        return in_array($module, $this->manageModules(), true);
    }
}
