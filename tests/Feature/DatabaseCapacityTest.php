<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseCapacityTest extends TestCase
{
    public static function budgets(): array
    {
        return [
            'ancien banc insuffisant' => [25, 1, 0, 1],
            'marge suffisante' => [100, 1, 0, 0],
            'instances cumulées' => [100, 2, 0, 1],
            'connexions réservées supplémentaires' => [100, 1, 21, 1],
            'sans marge' => [79, 1, 0, 1],
        ];
    }

    #[DataProvider('budgets')]
    public function test_capacity_accounts_for_all_workers_and_reserved_slots(int $maximum, int $instances, int $reserved, int $exit): void
    {
        $connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('select')->once()->with(
            'SELECT name, setting FROM pg_settings WHERE name IN (?, ?, ?)',
            ['max_connections', 'superuser_reserved_connections', 'reserved_connections']
        )->andReturn([
            (object) ['name' => 'max_connections', 'setting' => (string) $maximum],
            (object) ['name' => 'superuser_reserved_connections', 'setting' => '3'],
            (object) ['name' => 'reserved_connections', 'setting' => (string) $reserved],
        ]);
        $this->artisan('chorale:check-capacity', ['--web-workers' => 64, '--instances' => $instances, '--queue-workers' => 4, '--other-connections' => 8])->assertExitCode($exit);
    }

    public function test_missing_or_invalid_worker_count_is_rejected_before_querying(): void
    {
        DB::shouldReceive('connection')->never();
        foreach ([null, '0', '-1', '1.5', 'abc', '100000'] as $workers) {
            $this->artisan('chorale:check-capacity', ['--web-workers' => $workers])->assertExitCode(2);
        }
    }

    public function test_sqlite_is_rejected_without_executing_postgres_sql(): void
    {
        $connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $connection->shouldNotReceive('select');
        $this->artisan('chorale:check-capacity', ['--web-workers' => 64])->assertExitCode(2);
    }
}
