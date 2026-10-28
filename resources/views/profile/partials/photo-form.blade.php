<section aria-labelledby="profile-photo-title">
    <h2 id="profile-photo-title" class="text-lg font-semibold text-slate-900">Ma photo d’identité</h2>
    <p class="mt-2 text-sm text-slate-700">Cette photo est stockée dans l’espace privé de votre chorale et n’est jamais affichée sur la page de connexion.</p>

    @if($user->member)
        <div class="mt-5 flex flex-col items-stretch gap-5 sm:flex-row sm:items-center">
            <div class="flex h-32 w-32 shrink-0 self-center items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 text-2xl font-semibold text-slate-500 sm:self-auto">
                @if($user->member->photo_path)
                    <img src="{{ route('profile.photo.show') }}" alt="Photo d’identité de {{ $user->member->full_name }}" class="h-full w-full object-cover" />
                @else
                    {{ $user->member->initials }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <form method="POST" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div><x-input-label for="identity-photo" value="Choisir une photo" /><input id="identity-photo" name="photo" type="file" required accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="mt-1 block w-full max-w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-2 text-sm" /><x-input-error :messages="$errors->get('photo')" class="mt-1" /></div>
                    <p class="text-xs text-slate-600">JPG, PNG ou WebP — 5 Mo maximum.</p>
                    <button type="submit" class="btn btn-primary max-w-full whitespace-normal"><x-icon name="upload" class="h-4 w-4 shrink-0" />{{ $user->member->photo_path ? 'Remplacer ma photo' : 'Importer ma photo' }}</button>
                </form>
                @if($user->member->photo_path)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}" class="mt-3" onsubmit="return confirm('Supprimer votre photo d’identité ?')">@csrf @method('DELETE')<button type="submit" class="btn btn-secondary text-red-700"><x-icon name="trash" class="h-4 w-4" />Supprimer ma photo</button></form>
                @endif
            </div>
        </div>
    @else
        <p class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-950">Enregistrez d’abord vos informations personnelles pour créer votre fiche choriste, puis importez votre photo.</p>
    @endif
</section>
