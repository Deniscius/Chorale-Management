<?php

namespace Tests\Feature;

use App\Mail\Transport\BrevoApiTransport;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class BrevoApiTransportTest extends TestCase
{
    public function test_a_success_response_without_a_provider_reference_is_not_reported_as_delivered(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response([], 200)]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo n’a pas retourné de référence');
        (new BrevoApiTransport('test-api-key'))->send((new Email)
            ->from('notifications@example.test')->to('choriste@example.test')->subject('Test')->text('Test'));
    }

    public function test_a_provider_rejection_explains_the_status_without_disclosing_its_body(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['message' => 'private-provider-body-test'], 403)]);
        try {
            (new BrevoApiTransport('test-api-key'))->send((new Email)
                ->from('notifications@example.test')->to('choriste@example.test')->subject('Test')->text('Test'));
            $this->fail('Le refus du fournisseur doit être signalé.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('403', $exception->getMessage());
            $this->assertStringNotContainsString('private-provider-body-test', $exception->getMessage());
            $this->assertStringNotContainsString('test-api-key', $exception->getMessage());
        }
    }

    public function test_it_delivers_rendered_mail_through_brevo_https_api(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<brevo-test-123>'], 201),
        ]);

        $sent = (new BrevoApiTransport('test-api-key'))->send(
            (new Email)
                ->from(new Address('notifications@example.test', 'Chorale Management'))
                ->to(new Address('choriste@example.test', 'Marie'))
                ->subject('Répétition à venir')
                ->html('<p>Bonjour Marie</p>')
                ->text('Bonjour Marie')
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-api-key')
                && $request['sender']['email'] === 'notifications@example.test'
                && $request['to'][0]['email'] === 'choriste@example.test'
                && $request['subject'] === 'Répétition à venir';
        });
        $this->assertSame('<brevo-test-123>', $sent?->getMessageId());
    }
}
