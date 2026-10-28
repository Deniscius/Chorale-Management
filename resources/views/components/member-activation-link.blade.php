@props(['link'])
<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="status" x-data="{ copied: false, failed: false, async copy() { this.failed = false; try { await navigator.clipboard.writeText(this.$refs.link.value); this.copied = true; } catch (error) { this.$refs.link.select(); this.copied = document.execCommand('copy'); this.failed = !this.copied; } } }">
    <p class="font-medium text-emerald-950">L’accès est prêt</p>
    <p class="mt-1 text-sm text-emerald-900">Transmettez ce lien personnel au membre. Il est valable 48 heures et utilisable une seule fois.</p>
    <label for="member-activation-link" class="mt-3 block text-sm font-medium text-emerald-950">Lien d’activation à transmettre</label>
    <div class="mt-1 flex gap-2">
        <input x-ref="link" id="member-activation-link" type="text" readonly value="{{ $link }}" class="form-input min-w-0 flex-1 text-sm" x-on:click="$el.select()" autocomplete="off" spellcheck="false" />
        <button type="button" x-on:click="copy()" class="btn btn-primary shrink-0" aria-describedby="member-activation-copy-status">
            <x-icon name="copy" class="h-4 w-4" />
            <span x-text="copied ? 'Copié' : 'Copier le lien'">Copier le lien</span>
        </button>
    </div>
    <p id="member-activation-copy-status" class="mt-2 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-950" aria-live="polite">
        <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
        <span x-show="!copied && !failed">Copiez maintenant ce lien : il ne sera plus affiché après la fermeture de cette fenêtre.</span>
        <span x-show="copied" x-cloak>Lien copié. Transmettez-le uniquement au membre concerné.</span>
        <span x-show="failed" x-cloak>La copie automatique a échoué. Sélectionnez le lien puis utilisez Ctrl+C.</span>
    </p>
    <p class="mt-2 text-xs text-emerald-900">Le lien est personnel, valable 48 heures et utilisable une seule fois. Un renouvellement désactive immédiatement l’ancien lien.</p>
</div>
