@props(['link'])
<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="status">
    <p class="font-medium text-emerald-950">L’accès est prêt</p>
    <p class="mt-1 text-sm text-emerald-900">Transmettez ce lien personnel au membre. Il est valable 48 heures et utilisable une seule fois.</p>
    <label for="member-activation-link" class="mt-3 block text-sm font-medium text-emerald-950">Lien d’activation à transmettre</label>
    <input id="member-activation-link" type="text" readonly value="{{ $link }}" class="form-input mt-1 w-full text-sm" x-on:click="$el.select()" autocomplete="off" spellcheck="false" />
    <p class="mt-2 text-xs text-emerald-900">Cliquez sur le lien pour le sélectionner, puis copiez-le. Transmettez-le uniquement au membre concerné. Vous pourrez en renouveler un depuis la gestion des accès ; l’ancien sera alors désactivé.</p>
</div>
