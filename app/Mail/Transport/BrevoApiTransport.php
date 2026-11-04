<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Brevo transactional e-mail transport over HTTPS (port 443). */
final class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly ?string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (blank($this->apiKey)) {
            throw new TransportException('Brevo API key is not configured.');
        }

        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException('Brevo supports MIME e-mail messages only.');
        }

        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new TransportException('A sender address is required.');
        }

        $payload = [
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];
        if ($email->getCc()) {
            $payload['cc'] = $this->addresses($email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = $this->addresses($email->getBcc());
        }
        if ($email->getReplyTo()) {
            $payload['replyTo'] = $this->address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['api-key' => $this->apiKey])
                ->connectTimeout(5)
                ->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', $payload);
        } catch (\Throwable $exception) {
            throw new TransportException('Brevo API request failed.', 0, $exception);
        }

        if (! $response->successful()) {
            // Do not include Brevo's response: it can contain provider details.
            throw new TransportException('Brevo API rejected the e-mail (HTTP '.$response->status().').');
        }

        if (is_string($response->json('messageId')) && $response->json('messageId') !== '') {
            $message->setMessageId($response->json('messageId'));
        }
    }

    public function __toString(): string
    {
        return 'brevo+https://api.brevo.com';
    }

    /** @return array{email:string,name?:string} */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], fn (mixed $value): bool => $value !== '');
    }

    /** @param Address[] $addresses */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => $this->address($address), $addresses);
    }
}
