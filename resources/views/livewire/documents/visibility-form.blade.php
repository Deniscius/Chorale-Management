<form wire:submit="save" class="space-y-5">
    <x-validation-summary />
    <fieldset class="space-y-3">
        <legend class="mb-3 font-semibold text-slate-900">Qui peut consulter ce document ?</legend>
        @foreach(\App\Enums\DocumentVisibility::cases() as $option)
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 p-4">
                <input type="radio" wire:model="visibility" value="{{ $option->value }}" name="document-visibility" class="mt-1 h-5 w-5 shrink-0">
                <span><strong class="block text-slate-900">{{ $option->label() }}</strong><span class="mt-1 block text-sm text-slate-700">{{ $option->description() }}</span></span>
            </label>
        @endforeach
    </fieldset>
    <p class="text-sm text-slate-700">Le titre, la liste, l’aperçu et le téléchargement suivent ce choix. Les personnes habilitées à gérer le document conservent cet accès. Un fichier déjà téléchargé ne peut pas être rappelé.</p>
    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-4">
        <button type="button" x-on:click="requestClose()" class="btn btn-secondary">Annuler</button>
        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary max-w-full whitespace-normal">Enregistrer la visibilité</button>
    </div>
</form>
