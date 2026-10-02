<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a rendered print page into a PDF with headless Chrome.
 *
 * The print templates (the RofO letter, the recommendation letters) are written for
 * the browser: flexbox, background letterheads, border images, print-media rules.
 * DomPDF supports none of that faithfully, so a PDF from it is a different-looking
 * document from the one that prints. Chrome renders the very page the browser would
 * print, through its own print pipeline, so the PDF is that print.
 *
 * The HTML is written to a temporary file and loaded from there, with a <base> so
 * root-relative URLs (/assets/...) still resolve against the application.
 */
class ChromePdfRenderer
{
    /** Candidate executables, first found wins. CHROME_PATH in .env overrides them. */
    private const CANDIDATES = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ];

    public function binary(): ?string
    {
        $configured = config('services.chrome.path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        foreach (self::CANDIDATES as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * @param  string  $html     the full rendered page
     * @param  string  $baseUrl  what root-relative URLs resolve against
     * @return string  the PDF bytes
     */
    public function render(string $html, string $baseUrl, int $timeoutSeconds = 90): string
    {
        $binary = $this->binary();
        if (!$binary) {
            throw new RuntimeException('Chrome was not found on the server. Set CHROME_PATH in .env.');
        }

        $workDir = storage_path('app/chrome-pdf/' . Str::uuid());
        File::ensureDirectoryExists($workDir);

        $htmlPath = $workDir . DIRECTORY_SEPARATOR . 'page.html';
        $pdfPath  = $workDir . DIRECTORY_SEPARATOR . 'page.pdf';

        // Directly after <head>, so it precedes every URL the page uses.
        $base = '<base href="' . e(rtrim($baseUrl, '/') . '/') . '">';
        $html = preg_match('/<head[^>]*>/i', $html)
            ? preg_replace('/<head[^>]*>/i', '$0' . $base, $html, 1)
            : $base . $html;

        file_put_contents($htmlPath, $html);

        $command = [
            $binary,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--hide-scrollbars',
            '--no-pdf-header-footer',
            // Its own profile per render: two officers generating at once would
            // otherwise fight over one profile lock and the second render would fail.
            '--user-data-dir=' . $workDir . DIRECTORY_SEPARATOR . 'profile',
            // Lets the page's own scripts (fonts, frame images) settle before printing.
            '--virtual-time-budget=5000',
            '--print-to-pdf=' . $pdfPath,
            'file:///' . str_replace('\\', '/', $htmlPath),
        ];

        try {
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, $workDir, null, ['bypass_shell' => true]);

            if (!is_resource($process)) {
                throw new RuntimeException('Chrome could not be started.');
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $output   = '';
            $deadline = microtime(true) + $timeoutSeconds;

            while (true) {
                $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('Chrome timed out generating the PDF.');
                }
                usleep(100000);
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
                Log::error('Chrome PDF render produced no file', ['output' => Str::limit($output, 2000)]);
                throw new RuntimeException('Chrome did not produce a PDF.');
            }

            return file_get_contents($pdfPath);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** A download response for the PDF bytes. */
    public function download(string $pdf, string $filename)
    {
        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length'      => strlen($pdf),
            'Cache-Control'       => 'no-store, private',
        ]);
    }
}
