<?php

namespace App\Http\Controllers;

use App\Services\GitHubActionsTokenVerifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RunReminderSchedulerController extends Controller
{
    public function __invoke(Request $request, Kernel $console, GitHubActionsTokenVerifier $oidc): JsonResponse
    {
        $provided = $request->bearerToken();
        abort_unless(is_string($provided) && $oidc->verify($provided), 401, 'Authentification requise.');

        $dryRun = $request->boolean('dry_run');
        $exit = $console->call('chorale:send-reminders', $dryRun ? ['--dry-run' => true] : []);

        return response()->json([
            'ok' => $exit === 0,
            'mode' => $dryRun ? 'simulation' : 'delivery',
            'ran_at' => now()->toIso8601String(),
        ], $exit === 0 ? 200 : 500)->header('Cache-Control', 'no-store, private');
    }
}
