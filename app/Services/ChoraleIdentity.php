<?php

namespace App\Services;

use App\Models\Choir;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Identité de la chorale : nom affiché, logo et informations de contact.
 *
 * Centralise l'accès aux paramètres d'identité afin que la coque de
 * l'application (sidebar, topbar, layouts, page de connexion) ne dépende
 * pas directement du modèle Choir ni de la table settings.
 *
 * L'instance est un singleton et mémorise les valeurs lues pendant la
 * requête : la coque affiche l'identité plusieurs fois par page.
 */
class ChoraleIdentity
{
    public const KEY_NAME = 'chorale.name';

    public const KEY_TAGLINE = 'chorale.tagline';

    public const KEY_EMAIL = 'chorale.email';

    public const KEY_PHONE = 'chorale.phone';

    public const KEY_ADDRESS = 'chorale.address';

    public const KEY_WEBSITE = 'chorale.website';

    public const KEY_LOGO_PATH = 'chorale.logo_path';

    /**
     * Mémoire de requête.
     *
     * @var array<string, mixed>
     */
    protected array $memo = [];

    /**
     * Nom affiché de la chorale.
     */
    public function name(): string
    {
        return (string) $this->remember('name', fn (): ?string => Setting::get(self::KEY_NAME) ?: $this->choir()?->name)
            ?: config('app.name', 'Gestion Chorale');
    }

    /**
     * Slogan ou sous-titre affiché sur la page de connexion.
     */
    public function tagline(): string
    {
        return (string) ($this->remember('tagline', fn (): ?string => Setting::get(self::KEY_TAGLINE)) ?: 'Espace de gestion interne');
    }

    public function email(): ?string
    {
        return $this->remember('email', fn (): ?string => Setting::get(self::KEY_EMAIL) ?: $this->choir()?->email);
    }

    public function phone(): ?string
    {
        return $this->remember('phone', fn (): ?string => Setting::get(self::KEY_PHONE) ?: $this->choir()?->phone);
    }

    public function address(): ?string
    {
        return $this->remember('address', fn (): ?string => Setting::get(self::KEY_ADDRESS) ?: $this->choir()?->address);
    }

    public function website(): ?string
    {
        return $this->remember('website', fn (): ?string => Setting::get(self::KEY_WEBSITE));
    }

    /**
     * Chemin de stockage du logo, s'il est défini et présent.
     */
    public function logoPath(): ?string
    {
        return $this->remember('logo_path', function (): ?string {
            $path = Setting::get(self::KEY_LOGO_PATH) ?: null;

            if (! $path) {
                return null;
            }

            return Storage::disk('local')->exists($path) ? $path : null;
        });
    }

    public function hasLogo(): bool
    {
        return $this->logoPath() !== null;
    }

    /**
     * Empreinte courte du logo, utilisée pour éviter le cache navigateur
     * après un remplacement de fichier.
     */
    public function logoVersion(): ?string
    {
        $path = $this->logoPath();

        return $path !== null ? substr(md5($path), 0, 10) : null;
    }

    /**
     * URL versionnée du logo (le paramètre « v » invalide le cache navigateur
     * lorsque le fichier est remplacé).
     */
    public function logoUrl(): ?string
    {
        $version = $this->logoVersion();

        return $version !== null ? route('chorale.logo', ['v' => $version]) : null;
    }

    /**
     * Les informations de contact renseignées, sous forme de lignes prêtes à afficher.
     *
     * @return array<int, array{icon: string, label: string, value: string}>
     */
    public function contactLines(): array
    {
        return array_values(array_filter([
            $this->address() ? ['icon' => 'home', 'label' => 'Adresse', 'value' => $this->address()] : null,
            $this->phone() ? ['icon' => 'phone', 'label' => 'Téléphone', 'value' => $this->phone()] : null,
            $this->email() ? ['icon' => 'mail', 'label' => 'E-mail', 'value' => $this->email()] : null,
            $this->website() ? ['icon' => 'globe', 'label' => 'Site web', 'value' => $this->website()] : null,
        ]));
    }

    /**
     * Oublie les valeurs mémorisées (après une modification en session).
     */
    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function remember(string $key, callable $callback): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $callback();
        }

        return $this->memo[$key];
    }

    protected function choir(): ?Choir
    {
        return $this->remember('choir', fn (): ?Choir => Choir::query()->orderBy('id')->first());
    }
}
