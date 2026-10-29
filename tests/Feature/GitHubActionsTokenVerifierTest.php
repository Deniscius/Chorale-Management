<?php

namespace Tests\Feature;

use App\Services\GitHubActionsTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubActionsTokenVerifierTest extends TestCase
{
    public function test_it_verifies_signature_audience_repository_branch_and_workflow(): void
    {
        Cache::forget('github-actions-oidc-jwks');
        $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($private);
        $jwk = ['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig',
            'n' => $this->base64Url($details['rsa']['n']), 'e' => $this->base64Url($details['rsa']['e'])];
        Http::fake(['https://token.actions.githubusercontent.com/.well-known/jwks' => Http::response(['keys' => [$jwk]])]);
        $claims = [
            'iss' => 'https://token.actions.githubusercontent.com', 'aud' => 'chorale-management-reminders',
            'iat' => time() - 5, 'nbf' => time() - 5, 'exp' => time() + 300,
            'repository' => 'Deniscius/Chorale-Management', 'ref' => 'refs/heads/main',
            'workflow_ref' => 'Deniscius/Chorale-Management/.github/workflows/reminders.yml@refs/heads/main',
            'event_name' => 'schedule',
        ];
        $token = JWT::encode($claims, $private, 'RS256', 'test-key');

        $this->assertTrue(app(GitHubActionsTokenVerifier::class)->verify($token));
        $claims['repository'] = 'attacker/repository';
        $this->assertFalse(app(GitHubActionsTokenVerifier::class)->verify(JWT::encode($claims, $private, 'RS256', 'test-key')));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
