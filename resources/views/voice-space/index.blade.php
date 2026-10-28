<x-app-layout>
    <x-page-header title="Mon pupitre" icon="music" subtitle="Paroles, notes de travail et fichiers audio réservés à votre voix." />

    <div class="space-y-6">
        @if($isManager)
            <x-card title="Choisir un pupitre" subtitle="Chaque pupitre reste isolé. Les choristes ne voient que les ressources de leur propre voix.">
                <nav class="flex flex-wrap gap-2" aria-label="Pupitres">
                    @foreach($voices as $option)
                        <a href="{{ route('voice-space.index', ['voice' => $option->value]) }}" wire:navigate @class(['btn', 'btn-primary' => $voice === $option, 'btn-secondary' => $voice !== $option]) aria-current="{{ $voice === $option ? 'page' : 'false' }}">{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </x-card>
        @endif

        @if(!$voice)
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                <h2 class="font-semibold">Votre pupitre n’est pas encore renseigné</h2>
                <p class="mt-1 text-sm">Demandez à la direction de sélectionner votre voix dans votre fiche de choriste. Votre espace apparaîtra ensuite automatiquement.</p>
            </div>
        @else
            <x-card :title="'Pupitre '.$voice->label()" subtitle="Tous les membres de ce pupitre voient ces morceaux. Seul l’auteur ou un responsable liturgique peut les modifier.">
                <form method="POST" action="{{ route('voice-space.store') }}" enctype="multipart/form-data" class="grid gap-4 lg:grid-cols-2">
                    @csrf
                    <input type="hidden" name="voice" value="{{ $voice->value }}">
                    <div class="lg:col-span-2">
                        <x-input-label for="voice-title" value="Nom du morceau" />
                        <x-text-input id="voice-title" name="title" :value="old('title')" required maxlength="180" class="mt-1 block w-full" placeholder="Exemple : Gloria — voix basse" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="voice-lyrics" value="Paroles ou notes de répétition" />
                        <textarea id="voice-lyrics" name="lyrics" rows="10" maxlength="20000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Saisissez les paroles, les entrées ou les indications propres à ce pupitre…">{{ old('lyrics') }}</textarea>
                        <x-input-error :messages="$errors->get('lyrics')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="voice-audio" value="Fichier audio du morceau" />
                        <input id="voice-audio" name="audio" type="file" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" class="mt-1 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-sm" />
                        <x-input-error :messages="$errors->get('audio')" class="mt-1" />
                        <p class="mt-2 text-sm text-slate-600">MP3, WAV, OGG, M4A ou AAC — 10 Mo maximum. Ajoutez des paroles, un audio, ou les deux.</p>
                    </div>
                    <div class="lg:col-span-2">
                        <button type="submit" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" />Ajouter au pupitre {{ $voice->label() }}</button>
                    </div>
                </form>
            </x-card>

            <section aria-labelledby="voice-library-title" class="space-y-4">
                <div>
                    <h2 id="voice-library-title" class="text-lg font-semibold text-slate-900">Morceaux du pupitre {{ $voice->label() }}</h2>
                    <p class="text-sm text-slate-600">{{ $resources->total() }} ressource(s) partagée(s) avec ce pupitre.</p>
                </div>

                @forelse($resources as $resource)
                    @php($canEdit = $isManager || (int)$resource->uploaded_by === (int)auth()->id())
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $resource->title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Ajouté {{ $resource->created_at->diffForHumans() }}{{ $resource->uploader ? ' par '.$resource->uploader->name : '' }}</p>
                            </div>
                            <x-enum-badge :enum="$resource->voice" />
                        </div>

                        @if($resource->lyrics)
                            <div class="mt-4 rounded-lg bg-slate-50 p-4">
                                <h4 class="text-sm font-semibold text-slate-900">Paroles et notes</h4>
                                <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-800">{{ $resource->lyrics }}</p>
                            </div>
                        @endif

                        @if($resource->audio_path)
                            <div class="mt-4">
                                <p class="mb-2 text-sm font-medium text-slate-700">{{ $resource->audio_original_name }}</p>
                                <audio controls preload="none" class="w-full" src="{{ route('voice-space.audio', $resource) }}">Votre navigateur ne permet pas la lecture audio.</audio>
                            </div>
                        @endif

                        @if($canEdit)
                            <details class="mt-5 rounded-lg border border-slate-200 p-4">
                                <summary class="cursor-pointer font-semibold text-slate-800">Modifier ce morceau</summary>
                                <form method="POST" action="{{ route('voice-space.update', $resource) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                                    @csrf @method('PATCH')
                                    <div><x-input-label :for="'title-'.$resource->id" value="Nom du morceau" /><x-text-input :id="'title-'.$resource->id" name="title" :value="$resource->title" required maxlength="180" class="mt-1 block w-full" /></div>
                                    <div><x-input-label :for="'lyrics-'.$resource->id" value="Paroles ou notes" /><textarea id="lyrics-{{ $resource->id }}" name="lyrics" rows="8" maxlength="20000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm">{{ $resource->lyrics }}</textarea></div>
                                    <div><x-input-label :for="'audio-'.$resource->id" value="Remplacer l’audio (facultatif)" /><input id="audio-{{ $resource->id }}" name="audio" type="file" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" class="mt-1 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-sm" /></div>
                                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                </form>
                                <form method="POST" action="{{ route('voice-space.destroy', $resource) }}" class="mt-4" onsubmit="return confirm('Supprimer ce morceau, ses paroles et son fichier audio ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary text-red-700"><x-icon name="trash" class="h-4 w-4" />Supprimer</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-600">Aucun morceau n’a encore été ajouté à ce pupitre.</p>
                @endforelse

                @if(method_exists($resources, 'links')){{ $resources->links() }}@endif
            </section>
        @endif
    </div>
</x-app-layout>
