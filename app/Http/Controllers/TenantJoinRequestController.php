<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Mail\JoinRequestReceived;
use App\Mail\TenantAnnouncement;
use App\Models\Member;
use App\Models\MemberJoinRequest;
use App\Models\User;
use App\Rules\PersonName;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TenantJoinRequestController extends Controller
{
    public function create(Request $request): View
    {
        $tenant = $request->attributes->get('access_tenant');

        return app(TenantContext::class)->run($tenant->id, fn () => view('auth.join-choir', [
            'submitAction' => route('tenant.join.store', ['slug' => $tenant->slug]),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->attributes->get('access_tenant');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            // DNS verification rejects valid private and newly-created domains.
            // RFC + PHP filter validation keeps the field strict without making
            // the registration flow depend on an external DNS resolver.
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],
            'voice' => ['nullable', Rule::in(['soprano', 'alto', 'contretenor', 'tenor', 'bass'])],
        ]);
        $data['first_name'] = trim(preg_replace('/\s+/u', ' ', $data['first_name']));
        $data['last_name'] = trim(preg_replace('/\s+/u', ' ', $data['last_name']));
        $data['email'] = mb_strtolower(trim($data['email']));
        $data['phone'] = filled($data['phone'] ?? null) ? trim($data['phone']) : null;

        $alreadyPending = app(TenantContext::class)->run($tenant->id, fn () => MemberJoinRequest::query()
            ->where('email', $data['email'])->where('status', MemberJoinRequest::STATUS_PENDING)->exists());
        if ($alreadyPending) {
            return back()->withInput()->withErrors(['email' => 'Une demande est déjà en cours pour cette adresse. Consultez vos e-mails ou contactez un responsable.']);
        }

        $alreadyMember = app(TenantContext::class)->run($tenant->id, fn () => Member::query()->where('email', $data['email'])->exists());
        if ($alreadyMember) {
            // Do not reveal whether a person belongs to the choir. The generic
            // message still directs legitimate members to the responsible person.
            return back()->with('status', 'Votre demande est en cours de vérification. Un responsable vous contactera par e-mail.');
        }

        app(TenantContext::class)->run($tenant->id, function () use ($data, $tenant): void {
            MemberJoinRequest::create($data);
            if (! config('mail.delivery_enabled')) {
                return;
            }
            try {
                Mail::to($data['email'])->send(new JoinRequestReceived($data['first_name'], $tenant->name));
            } catch (\Throwable $exception) {
                // The request is durable even when a provider is temporarily unavailable.
                Log::warning('Join request acknowledgement delivery failed.', ['tenant_id' => $tenant->id, 'exception' => $exception::class]);
            }
            foreach (User::query()->where('role', UserRole::Admin)->whereNotNull('email')->pluck('email')->unique() as $administratorEmail) {
                try {
                    Mail::to($administratorEmail)->send(new TenantAnnouncement(
                        'Nouvelle demande d’adhésion',
                        'Une personne a demandé à rejoindre la chorale. Vérifiez la demande dans Accueil et suivi avant d’envoyer son accès.',
                        $tenant->name,
                        route('members.onboarding'),
                        'Vérifier les demandes',
                    ));
                } catch (\Throwable $exception) {
                    Log::warning('Join request administrator notification failed.', ['tenant_id' => $tenant->id, 'exception' => $exception::class]);
                }
            }
        });

        return redirect()->route('tenant.entry', ['slug' => $tenant->slug])
            ->with('status', 'Votre demande a été envoyée. Un responsable la vérifiera avant de vous transmettre votre lien personnel d’activation.');
    }
}
