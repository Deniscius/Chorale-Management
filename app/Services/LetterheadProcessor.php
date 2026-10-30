<?php

namespace App\Services;

use App\Models\LetterheadTemplate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class LetterheadProcessor
{
    public function __construct(
        private readonly PrivateFiles $files,
        private readonly TenantContext $tenant,
    ) {}

    public function process(LetterheadTemplate $template): void
    {
        $template->update(['status' => LetterheadTemplate::STATUS_PROCESSING, 'error_message' => null]);
        $directory = storage_path('framework/tmp/letterheads/'.Str::uuid());

        try {
            File::ensureDirectoryExists($directory, 0700, true);
            $extension = strtolower(pathinfo($template->original_name, PATHINFO_EXTENSION));
            $source = $directory.'/source.'.($extension === 'pdf' ? 'pdf' : 'docx');
            File::put($source, $this->files->contents($template->original_path));
            $pdf = $directory.'/source.pdf';
            if ($extension !== 'pdf') {
                $profile = $directory.'/profile';
                File::ensureDirectoryExists($profile, 0700, true);
                $this->run([
                    'soffice', '--headless', '--nologo', '--nodefault', '--nofirststartwizard',
                    '-env:UserInstallation=file://'.$profile, '--convert-to', 'pdf', '--outdir', $directory, $source,
                ], 90);
            }
            if (! File::isFile($pdf)) {
                throw new RuntimeException('Conversion PDF absente.');
            }

            $info = $this->run(['pdfinfo', $pdf], 15);
            if (! preg_match('/Page size:\s+([\d.]+) x ([\d.]+) pts/i', $info, $matches)) {
                throw new RuntimeException('Dimensions de page inconnues.');
            }
            $widthPoints = (float) $matches[1];
            $heightPoints = (float) $matches[2];
            $previewBase = $directory.'/preview';
            $this->run(['pdftoppm', '-f', '1', '-l', '1', '-singlefile', '-png', '-r', '110', $pdf, $previewBase], 30);

            // Une résolution plus élevée préserve les logos fins, cachets et
            // petites coordonnées lors de l'impression des rapports.
            $dpi = 240;
            $headerPoints = min($heightPoints, $template->header_height_mm / 25.4 * 72);
            $headerBase = $directory.'/header';
            $this->run([
                'pdftoppm', '-f', '1', '-l', '1', '-singlefile', '-png', '-r', (string) $dpi,
                '-x', '0', '-y', '0', '-W', (string) max(1, (int) round($widthPoints / 72 * $dpi)),
                '-H', (string) max(1, (int) round($headerPoints / 72 * $dpi)), $pdf, $headerBase,
            ], 30);

            $preview = $previewBase.'.png';
            $header = $headerBase.'.png';
            if (! File::isFile($preview) || ! File::isFile($header)) {
                throw new RuntimeException('Aperçu incomplet.');
            }
            $base = $this->tenant->path("letterheads/{$template->id}");
            $previewPath = $this->files->storeContents($base.'/preview.png', File::get($preview), 'image/png');
            $headerPath = $this->files->storeContents($base.'/header.png', File::get($header), 'image/png');
            $updated = LetterheadTemplate::query()->whereKey($template->id)->update([
                'preview_path' => $previewPath,
                'header_path' => $headerPath,
                'status' => LetterheadTemplate::STATUS_READY,
                'error_message' => null,
            ]);
            if ($updated === 0) {
                $this->files->delete($previewPath);
                $this->files->delete($headerPath);
            }
        } catch (\Throwable $exception) {
            Log::warning('Échec du traitement du papier à en-tête.', [
                'tenant_id' => $this->tenant->id(), 'template_id' => $template->id,
                'exception' => $exception::class,
            ]);
            LetterheadTemplate::query()->whereKey($template->id)->update([
                'status' => LetterheadTemplate::STATUS_ERROR,
                'error_message' => 'Le document n’a pas pu être préparé. Vérifiez qu’il s’ouvre correctement dans Word ou dans un lecteur PDF, puis réessayez.',
            ]);
        } finally {
            if (str_starts_with($directory, storage_path('framework/tmp/letterheads/'))) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function run(array $command, int $timeout): string
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->mustRun();

        return $process->getOutput();
    }
}
