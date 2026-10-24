<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseCapacity extends Command
{
    protected $signature = 'chorale:check-capacity
        {--web-workers= : Nombre maximal de connexions web par instance}
        {--instances=1 : Nombre d’instances web}
        {--queue-workers=0 : Total des workers de file utilisant cette base}
        {--other-connections=0 : Budget des autres services et opérations}';

    protected $description = 'Vérifie le budget de connexions PostgreSQL sans modifier le serveur';

    public function handle(): int
    {
        $counts = [];
        foreach (['web-workers', 'instances', 'queue-workers', 'other-connections'] as $option) {
            $raw = (string) $this->option($option);
            $minimum = in_array($option, ['web-workers', 'instances'], true) ? 1 : 0;
            if (! preg_match('/^\d{1,5}$/D', $raw) || (int) $raw < $minimum) {
                $this->error('Option --'.$option.' : entier attendu, minimum '.$minimum.'.');

                return self::INVALID;
            }
            $counts[$option] = (int) $raw;
        }

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('Ce contrôle nécessite PostgreSQL.');

            return self::INVALID;
        }

        $settings = collect($connection->select(
            'SELECT name, setting FROM pg_settings WHERE name IN (?, ?, ?)',
            ['max_connections', 'superuser_reserved_connections', 'reserved_connections']
        ))->pluck('setting', 'name');
        $maximum = (int) $settings->get('max_connections');
        $reserved = (int) $settings->get('superuser_reserved_connections', 0) + (int) $settings->get('reserved_connections', 0);
        $available = $maximum - $reserved;
        $required = $counts['web-workers'] * $counts['instances'] + $counts['queue-workers'] + $counts['other-connections'];
        $this->table(['Maximum PostgreSQL', 'Réservées', 'Budget disponible', 'Budget demandé', 'Marge'], [[$maximum, $reserved, $available, $required, $available - $required]]);

        if ($available <= $required) {
            $this->error('Budget insuffisant : conserver une marge de connexions.');

            return self::FAILURE;
        }

        $this->info('Budget de connexions compatible. La mémoire et la capacité réelle restent à mesurer sous charge.');

        return self::SUCCESS;
    }
}
