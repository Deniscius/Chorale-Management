<x-app-layout>
    <x-page-header title="Intégrations API" icon="cog"
        subtitle="Créez un accès limité pour church_project_spring ou un autre service de confiance." />

    @if(session('new_api_token'))
        <section x-data="{ copied: false }" class="mb-5 rounded-xl border-2 border-amber-400 bg-amber-50 p-5 shadow-sm">
            <h2 class="font-semibold text-amber-950">Copiez ce jeton maintenant</h2>
            <p class="mt-1 text-sm text-amber-900">Pour votre sécurité, il ne sera plus affiché après avoir quitté cette page.</p>
            <div class="mt-3 flex gap-2">
                <input x-ref="token" readonly value="{{ session('new_api_token') }}" class="form-input font-mono text-xs" aria-label="Nouveau jeton API">
                <button type="button" class="btn btn-primary shrink-0" @click="navigator.clipboard.writeText($refs.token.value); copied=true; window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: 'Jeton copié.' } }))">
                    <x-icon name="copy" class="h-4 w-4" /><span x-text="copied ? 'Copié' : 'Copier'"></span>
                </button>
            </div>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-900">Nouvel accès</h2>
            <form method="POST" action="{{ route('settings.api-integrations.store') }}" class="mt-4 space-y-4">
                @csrf
                <div><label for="api-name" class="form-label">Nom de l’application</label><input id="api-name" name="name" value="{{ old('name', 'church_project_spring') }}" required maxlength="100" class="form-input"><x-input-error :messages="$errors->get('name')" /></div>
                <fieldset><legend class="form-label">Données autorisées</legend><div class="space-y-2 rounded-lg border p-3">
                    @foreach(['members:read'=>'Liste et identité des choristes', 'attendance:read'=>'Synthèse des présences', 'finance:read'=>'Synthèse des cotisations et incidents'] as $ability => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked($ability === 'members:read' || in_array($ability, old('abilities', []), true)) class="rounded border-slate-300">{{ $label }}</label>
                    @endforeach
                </div><x-input-error :messages="$errors->get('abilities')" /></fieldset>
                <div><label for="api-expiry" class="form-label">Expiration (recommandée)</label><input id="api-expiry" type="date" min="{{ today()->addDay()->toDateString() }}" name="expires_at" value="{{ old('expires_at', today()->addYear()->toDateString()) }}" class="form-input"><x-input-error :messages="$errors->get('expires_at')" /></div>
                <button class="btn btn-primary w-full">Créer le jeton</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-200 p-5"><h2 class="font-semibold text-slate-900">Accès créés</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Application</th><th class="px-4 py-3 text-left">Droits</th><th class="px-4 py-3 text-left">Dernier accès</th><th class="px-4 py-3 text-left">État</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($clients as $client)
                    <tr><td class="px-4 py-3 font-semibold">{{ $client->name }}</td><td class="px-4 py-3 text-xs">{{ implode(', ', $client->abilities) }}</td><td class="px-4 py-3">{{ $client->last_used_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td><td class="px-4 py-3">{{ $client->revoked_at ? 'Révoqué' : ($client->expires_at?->isPast() ? 'Expiré' : 'Actif') }}</td><td class="px-4 py-3 text-right">@if(!$client->revoked_at)<form method="POST" action="{{ route('settings.api-integrations.destroy', $client) }}" onsubmit="return confirm('Révoquer immédiatement cet accès ?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Révoquer</button></form>@endif</td></tr>
                @empty <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Aucun accès API créé.</td></tr> @endforelse
            </tbody></table></div>
        </section>
    </div>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
        <h2 class="font-semibold text-slate-900">Contrat pour Spring</h2>
        <p class="mt-2 text-slate-600">Envoyez le jeton dans <code>Authorization: Bearer …</code>. Les routes disponibles sont <code>GET /api/v1/members</code> et <code>GET /api/v1/members/{id}</code>. Aucun accès direct à la base de données n’est nécessaire.</p>
    </section>
</x-app-layout>
