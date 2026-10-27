<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChoirCommission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'name_key', 'kind', 'mission', 'starts_on', 'ends_on', 'archived_at', 'module_key', 'archive_commission', 'leader_id', 'associate_id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'archived_at' => 'datetime'];
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function associate()
    {
        return $this->belongsTo(User::class, 'associate_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'choir_commission_member');
    }

    public function instrumentAssociates(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'instrument_commission_associates');
    }

    public function scopeInStatus(Builder $query, string $status): void
    {
        $today = today()->toDateString();
        match ($status) {
            'archived' => $query->whereNotNull('archived_at'),
            'scheduled' => $query->whereNull('archived_at')->whereDate('starts_on', '>', $today),
            'completed' => $query->whereNull('archived_at')->whereDate('ends_on', '<', $today),
            'active' => $query->whereNull('archived_at')->whereDate('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)),
            default => null,
        };
    }

    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->archived_at !== null => 'archived',
            $this->starts_on->isAfter(today()) => 'scheduled',
            $this->ends_on !== null && $this->ends_on->isBefore(today()) => 'completed',
            default => 'active',
        };
    }

    public static function statuses(): array
    {
        return ['active' => 'En cours', 'scheduled' => 'À venir', 'completed' => 'Terminées', 'archived' => 'Archivées'];
    }

    public static function moduleLabels(): array
    {
        return ['members' => 'Choristes et organisation', 'liturgique' => 'Liturgie, répertoire et célébrations', 'instruments' => 'Instruments et inventaire', 'courriers' => 'Courriers et secrétariat', 'activites' => 'Activités et vie de la chorale', 'comptabilite' => 'Trésorerie et comptabilité'];
    }

    public function featureLinks(): array
    {
        return match ($this->module_key) {
            'members' => ['members.index' => 'Choristes'],
            'liturgique' => ['works.index' => 'Œuvres et partitions', 'rehearsals.index' => 'Répétitions', 'performances.index' => 'Célébrations', 'seasons.index' => 'Temps liturgiques'],
            'instruments' => ['instruments.index' => 'Inventaire', 'instruments.movements' => 'Mouvements', 'instruments.maintenances' => 'Maintenance', 'instruments.ensembles' => 'Ensembles'],
            'courriers' => ['correspondences.index' => 'Courriers'],
            'activites' => ['activities.index' => 'Activités'],
            'comptabilite' => ['transactions.index' => 'Recettes et dépenses', 'budgets.index' => 'Budgets', 'contributions.index' => 'Cotisations', 'donations.index' => 'Dons'],
            default => [],
        };
    }
}
