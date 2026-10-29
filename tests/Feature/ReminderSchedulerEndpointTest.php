<?php

namespace Tests\Feature;

use App\Services\GitHubActionsTokenVerifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReminderSchedulerEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_rejects_missing_or_invalid_credentials(): void
    {
        $this->mock(GitHubActionsTokenVerifier::class)->shouldReceive('verify')->once()->with('wrong-token')->andReturnFalse();

        $this->postJson(route('internal.reminders.run'))->assertUnauthorized();
        $this->withToken('wrong-token')->postJson(route('internal.reminders.run'))->assertUnauthorized();
    }

    public function test_authenticated_scheduler_can_run_a_safe_simulation(): void
    {
        $this->mock(GitHubActionsTokenVerifier::class)->shouldReceive('verify')->once()->with('valid-oidc-token')->andReturnTrue();
        $this->mock(Kernel::class, function (MockInterface $mock): void {
            $mock->shouldReceive('call')->once()->with('chorale:send-reminders', ['--dry-run' => true])->andReturn(0);
        });

        $this->withToken('valid-oidc-token')
            ->postJson(route('internal.reminders.run', ['dry_run' => 1]))
            ->assertOk()
            ->assertJson(['ok' => true, 'mode' => 'simulation'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
