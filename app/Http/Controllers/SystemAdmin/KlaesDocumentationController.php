<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * System Admin > KLAES Documentation: manuals, SoPs, system documentation and
 * the quarterly reports, read from config('klaes_documentation') folders.
 */
class KlaesDocumentationController extends Controller
{
    public function index()
    {
        $sections = [];
        foreach (config('klaes_documentation.sections', []) as $key => $meta) {
            $sections[] = $meta + ['key' => $key, 'files' => $this->filesIn($key)];
        }

        return view('system-admin.klaes-documentation', [
            'PageTitle' => 'KLAES Documentation',
            'PageDescription' => 'Manuals, SoPs, system documentation and quarterly reports',
            'sections' => $sections,
        ]);
    }

    /** Stream one document inline (or as a download with ?download=1). */
    public function file(Request $request, string $section, string $name)
    {
        abort_unless(array_key_exists($section, config('klaes_documentation.sections', [])), 404);

        // Only a file actually listed in the section, never a path.
        $name = basename($name);
        $path = $this->folder($section) . DIRECTORY_SEPARATOR . $name;
        abort_unless(strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf' && is_file($path), 404);

        return $request->boolean('download')
            ? response()->download($path, $name)
            : response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $name) . '"',
            ]);
    }

    private function folder(string $section): string
    {
        return file_storage_path(config('klaes_documentation.root') . '/' . $section);
    }

    private function filesIn(string $section): array
    {
        $files = [];
        foreach (glob($this->folder($section) . '/*.pdf') ?: [] as $path) {
            $name = basename($path);
            $title = pathinfo($name, PATHINFO_FILENAME);

            $files[] = [
                'name' => $title,
                'quarter' => $section === 'reports' ? $this->quarter($title, (int) filemtime($path)) : null,
                'size' => filesize($path),
                'updated' => filemtime($path),
                'url' => route('system-admin.klaes-documentation.file', ['section' => $section, 'name' => $name]),
                'download_url' => route('system-admin.klaes-documentation.file', ['section' => $section, 'name' => $name, 'download' => 1]),
            ];
        }

        // Reports newest quarter first; everything else by name.
        usort($files, function ($a, $b) {
            return [$this->quarterSort($b['quarter']), $a['name']] <=> [$this->quarterSort($a['quarter']), $b['name']];
        });

        return $files;
    }

    /** "Q3 2026" from names like "... Q3", "Quarter 3 (Q3) 2026", "First Quarter ...". */
    private function quarter(string $title, int $modified): ?string
    {
        $override = config('klaes_documentation.report_quarters.' . $title);
        if ($override) {
            return $override;
        }

        $words = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4];
        $q = null;
        if (preg_match('/\bQ([1-4])\b/i', $title, $m) || preg_match('/\bquarter\s*([1-4])\b/i', $title, $m)) {
            $q = (int) $m[1];
        } elseif (preg_match('/\b(first|second|third|fourth)\s+quarter\b/i', $title, $m)) {
            $q = $words[strtolower($m[1])];
        }
        if (!$q) {
            return null;
        }

        // No year in the name: the year the report was saved.
        $year = preg_match('/\b(20\d{2})\b/', $title, $y) ? $y[1] : date('Y', $modified);

        return "Q{$q} {$year}";
    }

    private function quarterSort(?string $quarter): int
    {
        return $quarter && preg_match('/^Q([1-4]) (\d{4})$/', $quarter, $m) ? ((int) $m[2]) * 10 + (int) $m[1] : 0;
    }
}
