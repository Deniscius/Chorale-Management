<?php

namespace App\Livewire\Settings;

use App\Mail\MailDeliveryRejected;
use App\Mail\TenantAnnouncement;
use App\Models\Member;
use App\Models\NotificationDispatch;
use App\Services\MailConfiguration;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class EmailDelivery extends Component
{
    public string $result = '';

    public string $resultType = '';

    public function mount(): void
    {
        Gate::authorize('manage-settings');
    }

    public function sendTest(): void
    {
        Gate::authorize('manage-settings');
        $this->result = '';
        $this->resultType = 'error';
        $recipient = auth()->user()->notificationEmail();
        if (! $recipient) {
            $this->result = 'Renseignez une adresse e-mail valide dans votre fiche ou votre compte.';

            return;
        }
        $key = app(TenantContext::class)->key('mail-diagnostic:'.auth()->id());
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->result = 'Un test vient déjà d’être demandé. Réessayez dans une minute.';

            return;
        }
        try {
            app(MailConfiguration::class)->assertReady();
            RateLimiter::hit($key, 60);
            $sent = Mail::to($recipient)->send(new TenantAnnouncement(
                'Test de réception des notifications',
                'Ceci est le test demandé depuis votre espace de gestion le '.now()->format('d/m/Y à H:i:s').'. Ce message confirme le parcours d’envoi des notifications.',
                'Chorale Management',
            ));
            if ($sent === null) {
                throw new \RuntimeException('Le transport n’a pas confirmé l’acceptation du message.');
            }
            $this->resultType = 'success';
            $this->result = 'Message accepté par le transport pour '.$recipient.'. Vérifiez la réception et les indésirables ; l’acceptation ne confirme pas l’arrivée dans la boîte.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->result = $exception instanceof MailDeliveryRejected
                ? $exception->getMessage()
                : 'Envoi non confirmé. Consultez les événements du fournisseur avant de relancer.';
        }
    }

    #[Computed]
    public function recentDispatches()
    {
        Gate::authorize('manage-settings');

        return NotificationDispatch::query()->with('user:id,name')->latest('id')->limit(30)->get();
    }

    #[Computed]
    public function coverage(): array
    {
        Gate::authorize('manage-settings');
        $members = Member::query()->active()->with('user.member')->get();

        return [
            'active' => $members->count(),
            'automatic' => $members->filter(fn ($member) => $member->user?->notificationEmail())->count(),
            'without_account' => $members->filter(fn ($member) => ! $member->user && $member->canReceiveEmail())->count(),
            'missing_address' => $members->filter(fn ($member) => $member->user ? ! $member->user->notificationEmail() : ! $member->canReceiveEmail())->count(),
        ];
    }

    public function render()
    {
        Gate::authorize('manage-settings');

        return view('livewire.settings.email-delivery', [
            'checks' => app(MailConfiguration::class)->checks(),
            'transport' => config('mail.default'), 'recipient' => auth()->user()->notificationEmail(),
        ]);
    }
}
