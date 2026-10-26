<?php

namespace Tests\Feature;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NeonDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgres_url_supports_persisted_sessions_cache_locks_and_job_execution(): void
    {
        $source = DB::connection();
        if ($source->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL integration; exercised by the pgsql CI matrix.');
        }
        $base = $source->getConfig();
        $url = sprintf('postgresql://%s:%s@%s:%s/%s?sslmode=%s',
            rawurlencode($base['username']), rawurlencode($base['password'] ?? ''),
            $base['host'], $base['port'], rawurlencode($base['database']), $base['sslmode']);
        config(['database.connections.neon_contract' => (new ConfigurationUrlParser)->parseConfiguration(array_replace($base, ['url' => $url]))]);
        $connection = DB::connection('neon_contract');
        $id = Str::random(40);
        $key = 'neon-job-'.Str::uuid();
        try {
            $this->assertSame($source->getDatabaseName(), $connection->selectOne('select current_database() as name')->name);
            $session = new DatabaseSessionHandler($connection, 'sessions', 120, $this->app);
            $payload = Crypt::encryptString('existing session payload');
            $session->write($id, $payload);
            $restored = new DatabaseSessionHandler($connection, 'sessions', 120, $this->app);
            $this->assertSame('existing session payload', Crypt::decryptString($restored->read($id)));

            $cache = new DatabaseStore($connection, 'cache', 'neon-contract-');
            $cache->put('probe', 'retained', 60);
            $this->assertSame('retained', $cache->get('probe'));
            $lock = $cache->lock('scheduler', 60);
            $this->assertTrue($lock->get());
            $this->assertFalse($cache->lock('scheduler', 60)->get());
            $this->assertTrue($lock->release());

            $queue = new DatabaseQueue($connection, 'jobs', $key, 120);
            $queue->setContainer($this->app);
            $queue->setConnectionName('database');
            $queue->push(new NeonDatabaseProbe($key));
            $this->assertSame(1, $queue->size());
            $job = $queue->pop();
            $this->assertNotNull($job);
            $job->fire();
            $this->assertSame(0, $queue->size());
            $this->assertSame('executed', $connection->table('cache')->where('key', $key)->value('value'));
        } finally {
            // Scheduler cache locks run in autocommit, not inside a business transaction.
            $connection->table('sessions')->where('id', $id)->delete();
            $connection->table('cache')->whereIn('key', ['neon-contract-probe', $key])->delete();
            $connection->table('cache_locks')->where('key', 'neon-contract-scheduler')->delete();
            $connection->table('jobs')->where('queue', $key)->delete();
            DB::purge('neon_contract');
        }
    }
}

class NeonDatabaseProbe implements ShouldQueue
{
    public function __construct(public string $key) {}

    public function handle(): void
    {
        DB::connection('neon_contract')->table('cache')->insert(['key' => $this->key, 'value' => 'executed', 'expiration' => time() + 60]);
    }
}
