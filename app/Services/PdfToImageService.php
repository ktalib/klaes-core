<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PdfToImageService
{
    private int $dpi;
    private int $timeoutSeconds;

    public function __construct(int $dpi = 150, int $timeoutSeconds = 60)
    {
        $this->dpi = $dpi;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Convert a PDF into PNG pages inside the provided output directory.
     *
     * @param string $pdfPath      Absolute path to the PDF file.
     * @param string $outputDir    Absolute directory where generated PNGs will be stored.
     * @return array{success:bool,pages:array<int,array<string,mixed>>,error:string|null}
     */
    public function convert(string $pdfPath, string $outputDir): array
    {
        if (!is_file($pdfPath)) {
            return ['success' => false, 'pages' => [], 'error' => 'PDF file not found'];
        }

        File::ensureDirectoryExists($outputDir, 0775, true);

        // Try Imagick first (if extension is available), then pdftoppm
        if ($this->isImagickAvailable()) {
            return $this->convertWithImagick($pdfPath, $outputDir);
        }

        if ($this->isPdftoppmAvailable()) {
            return $this->convertWithPdftoppm($pdfPath, $outputDir);
        }

        return [
            'success' => false,
            'pages' => [],
            'error' => 'No PDF-to-image backend available (Imagick extension or pdftoppm required)'
        ];
    }

    private function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }

    private function isPdftoppmAvailable(): bool
    {
        $command = $this->isWindows() ? 'where pdftoppm' : 'command -v pdftoppm';
        $output = @shell_exec($command);
        return !empty($output);
    }

    private function convertWithImagick(string $pdfPath, string $outputDir): array
    {
        try {
            $imagick = new \Imagick();
            $imagick->setResolution($this->dpi, $this->dpi);
            $imagick->readImage($pdfPath);
            $imagick->setImageFormat('png');

            $pages = [];
            foreach ($imagick as $index => $page) {
                $page->setImageFormat('png');
                $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $page->setBackgroundColor('white');

                $pageNumber = $index + 1;
                $filename = $this->buildPageFilename($pdfPath, $pageNumber);
                $destination = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

                $page->writeImage($destination);

                $meta = $this->imageMeta($destination, $pageNumber);
                $pages[] = $meta;
            }

            $imagick->clear();
            $imagick->destroy();

            return ['success' => !empty($pages), 'pages' => $pages, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('PDF conversion via Imagick failed', [
                'pdf' => $pdfPath,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'pages' => [], 'error' => $e->getMessage()];
        }
    }

    private function convertWithPdftoppm(string $pdfPath, string $outputDir): array
    {
        try {
            $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
            $safeBase = Str::slug($baseName) ?: 'page';
            $outputPrefix = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeBase;

            $command = sprintf(
                'pdftoppm -png -r %d %s %s',
                $this->dpi,
                escapeshellarg($pdfPath),
                escapeshellarg($outputPrefix)
            );

            $this->runCommand($command, $this->timeoutSeconds);

            $pages = [];
            $generated = glob($outputPrefix . '-*.png') ?: [];
            sort($generated, SORT_NATURAL);

            foreach ($generated as $idx => $path) {
                $pageNumber = $idx + 1;
                $pages[] = $this->imageMeta($path, $pageNumber);
            }

            return ['success' => !empty($pages), 'pages' => $pages, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('PDF conversion via pdftoppm failed', [
                'pdf' => $pdfPath,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'pages' => [], 'error' => $e->getMessage()];
        }
    }

    private function imageMeta(string $path, int $pageNumber): array
    {
        $size = @getimagesize($path);

        return [
            'path' => $path,
            'page' => $pageNumber,
            'size' => @filesize($path) ?: 0,
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'mime' => $size['mime'] ?? 'image/png',
        ];
    }

    private function buildPageFilename(string $pdfPath, int $pageNumber): string
    {
        $base = pathinfo($pdfPath, PATHINFO_FILENAME);
        $safe = Str::slug($base) ?: 'page';
        return sprintf('%s-page-%03d.png', $safe, $pageNumber);
    }

    private function runCommand(string $command, int $timeoutSeconds): void
    {
        if ($this->isWindows()) {
            // PowerShell Start-Process with timeout
            $psCommand = sprintf(
                "powershell -Command \"Start-Process -FilePath cmd.exe -ArgumentList '/c %s' -NoNewWindow -Wait -PassThru\"",
                $command
            );
            $output = @shell_exec($psCommand);
            if ($output === null) {
                throw new \RuntimeException('pdftoppm execution failed or timed out');
            }
            return;
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start pdftoppm process');
        }

        $start = time();
        $status = proc_get_status($process);
        while ($status['running'] && (time() - $start) < $timeoutSeconds) {
            usleep(250000); // 250ms
            $status = proc_get_status($process);
        }

        if ($status['running']) {
            proc_terminate($process);
            throw new \RuntimeException('pdftoppm conversion timed out');
        }

        $exitCode = $status['exitcode'] ?? 0;
        proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException('pdftoppm exited with code ' . $exitCode);
        }
    }

    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
}
