<section id="papier-entete" class="mt-8 scroll-mt-20" @if($isProcessing) wire:poll.3s @endif>
    <x-card title="Papier à en-tête officiel" subtitle="Importez un document Word ou PDF. L’en-tête est isolé pour cette chorale et appliqué aux documents générés.">
        <form method="POST" action="{{ route('settings.letterheads.upload') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[1fr_12rem_auto] md:items-end" x-data="{ fileName: '' }">
            @csrf
            <div>
                <x-input-label for="letterhead-document" value="Document Word ou PDF (.docx, .pdf)" />
                <input id="letterhead-document" name="document" type="file" required accept=".docx,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf" @change="fileName = $event.target.files[0]?.name || ''" class="mt-1 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-sm" />
                <x-input-error :messages="$errors->get('document')" class="mt-1" />
                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                <p x-cloak x-show="fileName" class="mt-2 text-sm font-medium text-emerald-700">Document sélectionné : <span x-text="fileName"></span></p>
                <p class="mt-1 text-xs text-slate-500">DOCX sans macro, ou PDF statique sans script ni pièce jointe — 10 Mo maximum.</p>
            </div>
            <div>
                <x-input-label for="header-height" value="Hauteur à conserver" />
                <div class="mt-1 flex items-center gap-2"><x-text-input id="header-height" name="header_height_mm" type="number" min="20" max="100" :value="old('header_height_mm', 45)" required class="w-full" /><span class="text-sm text-slate-500">mm</span></div>
                <x-input-error :messages="$errors->get('header_height_mm')" class="mt-1" />
            </div>
            <button type="submit" class="btn btn-primary cursor-pointer">
                <x-icon name="upload" class="h-4 w-4" />
                Importer
            </button>
        </form>
        <x-input-error :messages="$errors->get('activation')" class="mt-3" />

        @if($activeTemplate)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                <div><strong>En-tête actuellement utilisé :</strong> {{ $activeTemplate->original_name }}<p class="mt-1 text-xs text-emerald-800">Il est intégré aux rapports de présence et aux prochains documents officiels.</p></div>
                <a href="{{ route('settings.letterheads.sample') }}" target="_blank" rel="noopener" class="btn btn-secondary">Voir le rendu actif</a>
            </div>
        @elseif($templates->isNotEmpty())
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><strong>L’en-tête importé n’est pas encore actif.</strong> Attendez l’aperçu, vérifiez-le, puis cliquez sur « Activer après vérification ». Tant que cette étape n’est pas faite, il ne peut pas apparaître dans les rapports.</div>
        @endif

        <div class="mt-6 space-y-5">
            @forelse($templates as $template)
                <article wire:key="letterhead-{{ $template->id }}" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-900">{{ $template->original_name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $template->status === 'active' ? 'bg-emerald-100 text-emerald-800' : ($template->status === 'error' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700') }}">{{ $labels[$template->status] ?? $template->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Importé {{ $template->created_at->diffForHumans() }}{{ $template->uploader ? ' par '.$template->uploader->name : '' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a class="btn btn-secondary" href="{{ route('settings.letterheads.file', [$template, 'original']) }}"><x-icon name="download" class="h-4 w-4" />Original</a>
                            <x-confirm-action :call="'delete('.$template->id.')'" title="Supprimer l’en-tête"
                                :message="$template->status === 'active' ? 'Les prochains documents utiliseront l’en-tête standard jusqu’à l’activation d’un autre fichier.' : 'Ce fichier et tous ses aperçus seront supprimés définitivement.'"
                                label="Supprimer" icon="trash" trigger-class="btn btn-secondary text-red-700 hover:border-red-300 hover:bg-red-50"
                                button-class="btn bg-red-600 text-white hover:bg-red-700" />
                        </div>
                    </div>

                    @if($template->error_message)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-red-50 p-3 text-sm text-red-800"><span>{{ $template->error_message }}</span><button type="button" wire:click="reprocess({{ $template->id }})" class="btn btn-secondary btn-sm">Réessayer la préparation</button></div>
                    @endif
                    @if($template->preview_path)
                        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <a href="{{ route('settings.letterheads.file', [$template, 'preview']) }}" target="_blank" class="block overflow-hidden rounded-lg border bg-slate-50" title="Ouvrir l’aperçu">
                                <img src="{{ route('settings.letterheads.file', [$template, 'preview']) }}" alt="Aperçu de la première page" class="mx-auto max-h-96 object-contain" />
                            </a>
                            <div class="space-y-3">
                                <label class="text-sm font-medium text-slate-700" for="height-{{ $template->id }}">Zone d’en-tête</label>
                                <div class="flex items-center gap-2"><x-text-input id="height-{{ $template->id }}" type="number" min="20" max="100" wire:model="heights.{{ $template->id }}" class="w-24" /><span class="text-sm text-slate-500">mm</span></div>
                                <button type="button" wire:click="reprocess({{ $template->id }})" class="btn btn-secondary">Recalculer l’aperçu</button>
                                @if($template->status !== 'active' && $template->isUsable())
                                    <x-confirm-action :call="'activate('.$template->id.')'" title="Activer cet en-tête" message="Il sera utilisé pour les prochains rapports et documents officiels." label="Activer après vérification" trigger-class="btn btn-primary" />
                                @endif
                                @if($template->status === 'active')
                                    <a href="{{ route('settings.letterheads.sample') }}" target="_blank" class="btn btn-secondary">Voir le document d’essai</a>
                                @endif
                            </div>
                        </div>
                    @elseif(in_array($template->status, ['pending', 'processing'], true))
                        <p class="mt-4 text-sm text-slate-600">Préparation sécurisée de l’aperçu en cours…</p>
                    @endif
                </article>
            @empty
                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Aucun papier à en-tête n’a encore été importé.</p>
            @endforelse
        </div>
    </x-card>
</section>
