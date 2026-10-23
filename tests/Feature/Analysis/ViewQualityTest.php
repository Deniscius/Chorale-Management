<?php

namespace Tests\Feature\Analysis;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use ReflectionClass;
use Tests\TestCase;

/**
 * Contrôles de qualité des vues.
 *
 * Vérifie le respect des conventions de rendu : usage des composants
 * partagés, absence d'appels de méthode qui contournent le cache des
 * propriétés calculées Livewire, et cohérence du système de design.
 */
class ViewQualityTest extends TestCase
{
    /**
     * Répertoire des vues.
     */
    protected function cheminVues(): string
    {
        return resource_path('views');
    }

    /**
     * Toutes les vues Blade de l'application.
     *
     * Les vues publiées sous « vendor/ » appartiennent au framework et ne
     * relèvent pas des règles de style du projet : elles sont écartées.
     *
     * @return list<string>
     */
    protected function vues(): array
    {
        $fichiers = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cheminVues(), \FilesystemIterator::SKIP_DOTS)
        );

        $prefixeVendor = $this->cheminVues().DIRECTORY_SEPARATOR.'vendor';

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            if (str_starts_with($fichier->getPathname(), $prefixeVendor)) {
                continue;
            }

            $fichiers[] = $fichier->getPathname();
        }

        sort($fichiers);

        return $fichiers;
    }

    /**
     * Les propriétés calculées Livewire doivent être lues comme des propriétés.
     *
     * Un appel de méthode ($this->stats()) contourne la mise en cache de
     * #[Computed] et relance les requêtes SQL à chaque occurrence.
     */
    public function test_les_proprietes_calculees_sont_lues_comme_des_proprietes(): void
    {
        $calculees = $this->methodesCalculees();

        $this->assertNotEmpty($calculees, 'Aucune propriété calculée détectée.');

        $violations = [];

        foreach ($this->vues() as $fichier) {
            $contenu = File::get($fichier);

            foreach (array_keys($calculees) as $methode) {
                if (preg_match('/\$this->'.preg_quote($methode, '/').'\(\)/', $contenu) === 1) {
                    $violations[] = sprintf(
                        '%s : $this->%s() doit être écrit $this->%s (cache #[Computed])',
                        str_replace($this->cheminVues().'/', '', $fichier),
                        $methode,
                        $methode
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Toutes les vues doivent compiler sans erreur.
     */
    public function test_toutes_les_vues_compilent(): void
    {
        $erreurs = [];

        foreach ($this->vues() as $fichier) {
            $contenu = File::get($fichier);

            try {
                app('blade.compiler')->compileString($contenu);
            } catch (\Throwable $e) {
                $erreurs[] = sprintf('%s : %s', $fichier, $e->getMessage());
            }
        }

        $this->assertSame([], $erreurs, implode("\n", $erreurs));
    }

    /**
     * Les composants Blade utilisés dans les vues doivent exister.
     */
    public function test_les_composants_utilises_existent(): void
    {
        $disponibles = array_map(
            fn (string $fichier): string => basename($fichier, '.blade.php'),
            glob($this->cheminVues().'/components/*.blade.php') ?: []
        );

        // Composants surchargeables (classes PHP).
        foreach (glob(app_path('View/Components/*.php')) ?: [] as $fichier) {
            $disponibles[] = Str::kebab(pathinfo($fichier, PATHINFO_FILENAME));
        }

        // « x-slot » est une construction Blade, pas un composant.
        $disponibles[] = 'slot';

        // « x-mail » est fourni par Laravel (composants de courriel).
        $disponibles[] = 'mail';

        $manquants = [];

        foreach ($this->vues() as $fichier) {
            $contenu = File::get($fichier);

            preg_match_all('/<x-([a-z0-9\-]+)/', $contenu, $correspondances);

            foreach (array_unique($correspondances[1]) as $composant) {
                if (! in_array($composant, $disponibles, true)) {
                    $manquants[] = sprintf(
                        '%s : composant <x-%s> introuvable',
                        str_replace($this->cheminVues().'/', '', $fichier),
                        $composant
                    );
                }
            }
        }

        $this->assertSame([], $manquants, implode("\n", $manquants));
    }

    /**
     * Les écrans de liste doivent s'appuyer sur la bibliothèque de composants
     * (en-tête de page, table de données, état vide) pour garantir
     * l'homogénéité de l'interface.
     */
    public function test_les_ecrans_de_liste_utilisent_la_bibliotheque_de_composants(): void
    {
        $violations = [];

        foreach ($this->vues() as $fichier) {
            $contenu = File::get($fichier);

            // Seuls les écrans présentant un tableau de données sont concernés.
            if (! str_contains($contenu, 'x-data-table')) {
                continue;
            }

            foreach (['x-page-header', 'x-filter-bar'] as $composant) {
                if (! str_contains($contenu, '<'.$composant)) {
                    $violations[] = sprintf(
                        '%s : composant <%s> manquant',
                        str_replace($this->cheminVues().'/', '', $fichier),
                        $composant
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Aucune sortie brute non échappée ne doit être présente : le contenu
     * utilisateur est systématiquement échappé par Blade.
     */
    public function test_aucune_sortie_brute_non_echappee(): void
    {
        $violations = [];

        foreach ($this->vues() as $fichier) {
            $contenu = File::get($fichier);

            if (str_contains($contenu, '{!!')) {
                $violations[] = str_replace($this->cheminVues().'/', '', $fichier);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Ces vues utilisent une sortie brute non échappée :\n".implode("\n", $violations)
        );
    }

    /**
     * La pagination doit être affichée en français.
     */
    public function test_la_pagination_est_en_francais(): void
    {
        $fichier = $this->cheminVues().'/vendor/pagination/tailwind.blade.php';

        $this->assertFileExists($fichier, 'La pagination personnalisée est absente.');

        $contenu = File::get($fichier);

        $this->assertStringNotContainsString('Showing', $contenu);
        $this->assertStringNotContainsString('results', $contenu);
        $this->assertStringContainsString('Affichage de', $contenu);
        $this->assertStringContainsString('Suivant', $contenu);
    }

    /**
     * La pagination Laravel par défaut (non personnalisée) ne doit plus être
     * utilisée : elle s'affiche en anglais.
     */
    public function test_la_pagination_par_defaut_est_remplacee(): void
    {
        $vues = $this->vues();

        $violations = [];

        foreach ($vues as $fichier) {
            $contenu = File::get($fichier);

            if (str_contains($contenu, "->links('pagination::") && ! str_contains($contenu, 'pagination::tailwind')) {
                $violations[] = str_replace($this->cheminVues().'/', '', $fichier);
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * La vue du tableau de bord doit être cohérente avec la coque.
     */
    public function test_le_tableau_de_bord_utilise_la_coque_application(): void
    {
        $this->assertTrue(View::exists('dashboard'));
        $this->assertTrue(View::exists('layouts.app'));
        $this->assertTrue(View::exists('layouts.sidebar'));
        $this->assertTrue(View::exists('layouts.topbar'));
    }

    /**
     * Liste des méthodes annotées #[Computed], tous composants confondus.
     *
     * @return array<string, string> Méthode => classe
     */
    protected function methodesCalculees(): array
    {
        $methodes = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Livewire'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                continue;
            }

            $chemin = $fichier->getPathname();

            if (str_contains($chemin, DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $classe = 'App\\Livewire\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                ltrim(str_replace(app_path('Livewire'), '', $chemin), '/\\')
            );

            if (! class_exists($classe)) {
                continue;
            }

            $reflexion = new ReflectionClass($classe);

            foreach ($reflexion->getMethods() as $methode) {
                foreach ($methode->getAttributes() as $attribut) {
                    if ($attribut->getName() === Computed::class) {
                        $methodes[$methode->getName()] = $classe;
                    }
                }
            }
        }

        return $methodes;
    }
}
