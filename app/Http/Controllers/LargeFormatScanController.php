<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\PageTyping;
use App\Models\Scanning;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\ScanUploads\LargeFormatSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LargeFormatScanController extends Controller
{
    public function browse(Request $request, LargeFormatSource $source)
    {
        $data = $request->validate([
            'path' => 'nullable|string|max:2000',
            'library' => 'nullable|string|max:40',
            // A per-file library has no useful root, so the picker names the
            // file it is repairing and lets the server find where to land.
            'file_indexing_id' => 'nullable|integer',
        ]);
        $source->use($data['library'] ?? null);

        // Decide where to open before anything else: without a path of its own
        // the picker would otherwise fall back to the root it must never list.
        $fileNumber = null;
        $start = null;
        if ($source->layout() === 'file_number' && !empty($data['file_indexing_id'])) {
            $fileNumber = FileIndexing::on('sqlsrv')->whereKey($data['file_indexing_id'])->value('file_number');
            if ($fileNumber) {
                $start = $source->startFolder($fileNumber);
            }
        }

        $relative = $data['path'] ?? $start ?? '';
        $directory = $relative === '' && !$source->allowsRootListing() ? null : $source->resolveFolder($relative);

        // Nothing to list: either the file has no folder on the share, or the
        // operator named one that is not there. Both are routine, so they come
        // back as an empty picker explaining itself rather than as an error.
        if ($directory === null) {
            return response()->json([
                'folder' => $source->displayRoot(), 'label' => $source->label(), 'library' => $source->key(),
                'path' => '', 'locked_root' => '', 'file_number' => $fileNumber, 'matched' => false,
                'can_locate' => $source->layout() === 'file_number', 'entries' => [],
                'message' => $this->noFolderMessage($relative, $fileNumber),
            ]);
        }

        $max = $source->maxEntries();
        $entries = [];
        $truncated = false;
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || $entry->isLink()) continue;
            if (!$entry->isDir() && !in_array(strtolower($entry->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'])) continue;
            if (count($entries) >= $max) { $truncated = true; break; }
            $path = ltrim(str_replace('\\', '/', $relative) . '/' . $entry->getFilename(), '/');
            $entries[] = ['name' => $entry->getFilename(), 'path' => $path, 'directory' => $entry->isDir(),
                'url' => $entry->isDir() ? null : route('large-format.image', ['source' => $path, 'library' => $source->key()])];
        }
        usort($entries, fn ($a, $b) => ($b['directory'] <=> $a['directory']) ?: strnatcasecmp($a['name'], $b['name']));

        // Where "up one folder" stops. On a per-file share that is the file's
        // own folder: climbing past it reaches only the unlistable root.
        $lockedRoot = $source->allowsRootListing() ? '' : explode('/', str_replace('\\', '/', $relative))[0];

        return response()->json([
            'folder' => $source->displayRoot(), 'label' => $source->label(), 'library' => $source->key(),
            'path' => $relative, 'locked_root' => $lockedRoot, 'file_number' => $fileNumber, 'matched' => true,
            'can_locate' => $source->layout() === 'file_number', 'entries' => $entries,
            'message' => $truncated ? 'Showing the first ' . $max . ' items in this folder.' : null,
        ]);
    }

    private function noFolderMessage(string $relative, ?string $fileNumber): string
    {
        if ($relative !== '') {
            return 'No folder named "' . $relative . '" on the scan server.';
        }

        return $fileNumber
            ? 'No scan folder for ' . $fileNumber . ' on the scan server. Enter the folder name if you know it.'
            : 'This page is not linked to a file number, so its scan folder cannot be found. Enter the folder name to browse.';
    }

    public function image(Request $request, LargeFormatSource $source)
    {
        $data = $request->validate(['source' => 'required|string|max:2000', 'library' => 'nullable|string|max:40']);
        $image = $source->use($data['library'] ?? null)->image($data['source']);
        return response()->file($image['path'], ['Content-Type' => $image['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function save(Request $request, LargeFormatSource $source, EdmsDocumentPathResolver $paths)
    {
        $data = $request->validate([
            'source' => 'required|string|max:2000',
            'file_indexing_id' => 'required|integer',
            'scanning_id' => 'nullable|integer',
            'expected_path' => 'nullable|required_with:scanning_id|string',
            'registry' => 'nullable|string|max:100',
            // Which drop folder the chosen image came from.
            'library' => 'nullable|string|max:40',
            // File Archive: the page being replaced is one whose image has gone
            // missing from disk, so "the original must still be there" cannot hold.
            'allow_missing_original' => 'nullable|boolean',
        ]);
        $image = $source->use($data['library'] ?? null)->image($data['source']);
        $disk = Storage::disk('public');
        $created = [];
        try {
            $scan = DB::connection('sqlsrv')->transaction(function () use ($data, $image, $paths, $disk, &$created, $request) {
                // Serialize additions to this file so new folios cannot collide.
                $file = FileIndexing::on('sqlsrv')->lockForUpdate()->findOrFail($data['file_indexing_id']);
                $scan = !empty($data['scanning_id'])
                    ? Scanning::on('sqlsrv')->where('file_indexing_id', $file->id)->lockForUpdate()->findOrFail($data['scanning_id']) : null;
                if ($scan) {
                    abort_unless($scan->document_path === $data['expected_path'], 409, 'This page changed. Refresh before replacing it.');
                }
                $registry = $scan ? $scan->registry : ($data['registry'] ?? $file->general_registry ?? $file->registry);
                abort_unless($registry, 422, 'Select the file registry first.');
                $directory = $paths->scanUploadFolder($registry, $file->file_number, $scan->paper_size ?? null, $scan->edms_file_type ?? $file->edms_file_type);
                $newPath = $directory . '/lf_' . Str::uuid() . '.' . $image['extension'];
                $stream = fopen($image['path'], 'rb');
                try {
                    $created[] = $newPath;
                    if (!$disk->put($newPath, $stream)) throw new \RuntimeException('Could not save the LF image.');
                } finally {
                    if (is_resource($stream)) fclose($stream);
                }
                if ($disk->size($newPath) !== $image['size'] || !hash_equals(hash_file('sha256', $image['path']), hash_file('sha256', $disk->path($newPath)))) {
                    throw new \RuntimeException('LF image verification failed. The original page is unchanged.');
                }
                if ($scan) {
                    $typed = PageTyping::on('sqlsrv')->where('scanning_id', $scan->id)->lockForUpdate()->get();
                    $context = $paths->contextFromScanning($scan, $file);
                    $oldPath = $paths->resolveRelative($scan->document_path, $context);
                    // Normally the image being replaced has to be on disk, so the
                    // version ledger records something recoverable. A File Archive
                    // page with a missing image is the exception: the caller opts
                    // in, and the ledger keeps the path the page pointed at.
                    $originalOnDisk = $oldPath && $disk->exists($oldPath);
                    abort_unless($originalOnDisk || $request->boolean('allow_missing_original'), 422, 'The original image is unavailable; replacement was not saved.');
                    DB::connection('sqlsrv')->table('scan_image_versions')->insert([
                        'scanning_id' => $scan->id, 'replaced_by' => $request->user()->id,
                        'old_path' => $oldPath ?: (string) $scan->document_path, 'new_path' => $newPath,
                        'old_metadata' => json_encode(['scan' => $scan->getAttributes(), 'page_typings' => $typed->toArray(), 'original_on_disk' => $originalOnDisk], JSON_THROW_ON_ERROR),
                        'source_path' => $data['source'], 'created_at' => now(),
                    ]);
                    foreach ($typed as $typing) {
                        // Keep typed copies in their current registry/FileNo directory.
                        // A typed image that has gone missing is restored under its
                        // own "{n}-{page_code}" name so the archive folder keeps the
                        // naming the rest of the page belongs to; an image that is
                        // still there is never overwritten.
                        $storedTyped = $paths->normalize($typing->file_path);
                        $typedMissing = $storedTyped && !$disk->exists($storedTyped) && !$paths->resolveRelative($typing->file_path, $context);
                        $typedPath = $typedMissing
                            ? dirname($storedTyped) . '/' . pathinfo($storedTyped, PATHINFO_FILENAME) . '.' . $image['extension']
                            : dirname($typing->file_path ?: $newPath) . '/lf_' . Str::uuid() . '.' . $image['extension'];
                        $created[] = $typedPath;
                        if (!$disk->copy($newPath, $typedPath)) throw new \RuntimeException('Could not save the typed image copy.');
                        $typing->file_path = $typedPath;
                        $typing->save();
                    }
                    $scan->document_path = $newPath;
                    $scan->original_filename = basename($image['path']);
                    $scan->file_size = $image['size'];
                    $scan->save();
                } else {
                    $scans = Scanning::on('sqlsrv')->where('file_indexing_id', $file->id);
                    $order = (clone $scans)->exists() ? ((int) (clone $scans)->max('display_order')) + 1 : 0;
                    $definition = max($order + 1, ((int) (clone $scans)->max('definition')) + 1);
                    $scan = Scanning::on('sqlsrv')->create([
                        'file_indexing_id' => $file->id, 'document_path' => $newPath,
                        'original_filename' => basename($image['path']), 'file_size' => $image['size'],
                        'uploaded_by' => $request->user()->id, 'status' => 'pending', 'registry' => $registry,
                        'edms_file_type' => $file->edms_file_type, 'display_order' => $order,
                        'definition' => $definition, 'definition_code' => $definition . '-' . $file->file_number,
                        'paper_size' => 'Custom', 'document_type' => 'Document',
                    ]);
                }
                return $scan;
            });
        } catch (\Throwable $e) {
            foreach ($created as $path) $disk->delete($path);
            throw $e;
        }
        return response()->json(['success' => true, 'document' => array_merge($scan->toArray(), ['file_url' => $disk->url($scan->document_path)])]);
    }
}
