<?php

namespace App\Enums;

use App\Enums\Concerns\HasColor;
use App\Enums\Concerns\HasLabel;
use App\Models\CommissionTeam;
use App\Models\User;

/**
 * Commissions de la chorale auxquelles un document peut être rattaché.
 *
 * Chaque commission correspond à un module fonctionnel : l'accès aux
 * documents suit donc les mêmes règles d'autorisation que le module.
 */
enum Commission: string
{
    case VieCulturelle = 'commission_vie_culturelle';
    case SuivieFormation = 'commission_suivie_formation';
    case Liturgique = 'commission_liturgique';
    case Instruments = 'commission_instruments';
    case Secretariat = 'commission_secretariat';
    case Tresorerie = 'commission_tresorerie';
    case Direction = 'commission_direction';

    use HasColor;
    use HasLabel;

    public static function labels(): array
    {
        return [
            self::VieCulturelle->value => 'Commission Vie culturelle',
            self::SuivieFormation->value => 'Commission Suivi et Formation',
            self::Liturgique->value => 'Commission liturgique',
            self::Instruments->value => 'Commission des instruments',
            self::Secretariat->value => 'Secrétariat',
            self::Tresorerie->value => 'Trésorerie',
            self::Direction->value => 'Direction',
        ];
    }

    public static function colors(): array
    {
        return [
            self::VieCulturelle->value => 'pink',
            self::SuivieFormation->value => 'indigo',
            self::Liturgique->value => 'purple',
            self::Instruments->value => 'orange',
            self::Secretariat->value => 'cyan',
            self::Tresorerie->value => 'emerald',
            self::Direction->value => 'blue',
        ];
    }

    /**
     * Module fonctionnel associé à la commission.
     */
    public function module(): string
    {
        return match ($this) {
            self::VieCulturelle => UserRole::MODULE_ACTIVITES,
            self::SuivieFormation => UserRole::MODULE_LITURGIQUE,
            self::Liturgique => UserRole::MODULE_LITURGIQUE,
            self::Instruments => UserRole::MODULE_INSTRUMENTS,
            self::Secretariat => UserRole::MODULE_COURRIERS,
            self::Tresorerie => UserRole::MODULE_COMPTABILITE,
            self::Direction => UserRole::MODULE_MEMBERS,
        };
    }

    /**
     * Commissions accessibles pour un rôle donné (selon ses modules).
     *
     * @return array<int, self>
     */
    public function hasPrivateArchive(): bool
    {
        return in_array($this, [self::Liturgique, self::Secretariat], true);
    }

    public function canManageArchive(?User $user, ?CommissionTeam $team = null): bool
    {
        if (! $this->hasPrivateArchive()) {
            return $this->canManage($user);
        }
        if (! $user) {
            return false;
        }
        $team ??= CommissionTeam::where('commission', $this->value)->first();

        return $team?->allows($user) ?? false;
    }

    public function canView(?User $user): bool
    {
        return $user !== null && ($this === self::Direction
            ? in_array($user->role, [UserRole::Admin, UserRole::Directeur], true)
            : $user->canAccessModule($this->module()));
    }

    public function canManage(?User $user): bool
    {
        return $this->canView($user) && $user->canManageModule($this->module());
    }

    public static function forUser(User $user): array
    {
        return array_values(array_filter(self::cases(), fn (self $commission) => $commission->canView($user)));
    }

    public static function forRole(UserRole $role): array
    {
        if ($role === UserRole::Admin || $role === UserRole::Directeur) {
            return self::cases();
        }

        return array_values(array_filter(
            self::cases(),
            fn (self $commission): bool => $commission !== self::Direction
                && $role->canAccessModule($commission->module()),
        ));
    }
}
