@php($notifications = app(\App\Support\FlashNotifications::class)->all())
@if($notifications !== [])
    <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite">
        @foreach($notifications as $notification)
            <div data-notification-popup role="{{ $notification['type'] === 'error' ? 'alert' : 'status' }}" class="pointer-events-auto flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-xl ring-1 ring-slate-900/5">
                <span @class([
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                    'bg-emerald-50 text-emerald-600' => $notification['type'] === 'success',
                    'bg-rose-50 text-rose-600' => $notification['type'] === 'error',
                    'bg-amber-50 text-amber-600' => $notification['type'] === 'warning',
                    'bg-sky-50 text-sky-600' => $notification['type'] === 'info',
                ])>
                    <x-icon :name="match($notification['type']) { 'success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'warning', default => 'info' }" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1 leading-relaxed">
                    <strong class="block text-slate-900">{{ match($notification['type']) { 'success' => 'Réussi', 'error' => 'Erreur', 'warning' => 'Attention', default => 'Information' } }}</strong>
                    <p>{{ $notification['message'] }}</p>
                </div>
                <button type="button" data-notification-close aria-label="Fermer la notification" class="-m-1 shrink-0 rounded-lg p-1 text-slate-600 hover:bg-slate-100"><x-icon name="x" class="h-4 w-4" /></button>
            </div>
        @endforeach
    </div>
@endif
