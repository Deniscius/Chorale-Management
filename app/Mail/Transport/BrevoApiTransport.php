<?php

namespace App\Mail\Transport;

use App\Mail\MailDeliveryRejected;
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
            throw new MailDeliveryRejected('La clé API Brevo n’est pas configurée (BREVO_API_KEY).');
        }

        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException('Brevo supports MIME e-mail messages only.');
        }

        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new MailDeliveryRejected('L’adresse d’expédition n’est pas configurée (MAIL_FROM_ADDRESS).');
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
            // Explain actionable failures without leaking the response or credentials.
            $reason = match ($response->status()) {
                400 => 'Adresse d’expédition, destinataire ou contenu refusé.',
                401 => 'Clé API Brevo invalide ou expirée.',
                403 => 'Envoi non autorisé : vérifier le compte Brevo, l’expéditeur et les restrictions d’accès.',
                429 => 'Quota ou limite d’envoi Brevo atteint.',
                default => 'Service d’envoi indisponible.',
            };
            throw new MailDeliveryRejected('Brevo a refusé l’e-mail (HTTP '.$response->status().'). '.$reason);
        }

        if (is_string($response->json('messageId')) && $response->json('messageId') !== '') {
            $message->setMessageId($response->json('messageId'));
        } else {
            throw new TransportException('Brevo n’a pas retourné de référence de message. Vérifiez les événements du fournisseur avant toute relance.');
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
