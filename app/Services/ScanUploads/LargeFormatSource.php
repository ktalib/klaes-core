<?php

namespace App\Services\ScanUploads;

use Illuminate\Validation\ValidationException;

class LargeFormatSource
{
    /** Which configured drop folder this instance browses. */
    private string $library;

    public function __construct()
    {
        $this->library = (string) config('large_format_scans.default_library', 'lfs');
    }

    /**
     * Point this instance at one of the configured source folders. An unknown
     * or empty key falls back to the default, so an older caller that sends no
     * library keeps browsing the Master LFS Folder.
     */
    public function use(?string $library): static
    {
        if ($library && is_array(config('large_format_scans.libraries.' . $library))) {
            $this->library = $library;
        }

        return $this;
    }

    public function key(): string
    {
        return $this->library;
    }

    public function label(): string
    {
        return (string) config('large_format_scans.libraries.' . $this->library . '.label', 'Master LFS Folder');
    }

    public function root(): string
    {
        return (string) config(
            'large_format_scans.libraries.' . $this->library . '.folder',
            config('large_format_scans.folder')
        );
    }

    /**
     * What to show the operator in place of the root path. A library may hide
     * the real location behind a plain name; everything else shows its path.
     */
    public function displayRoot(): string
    {
        return (string) config('large_format_scans.libraries.' . $this->library . '.display', $this->root());
    }

    /**
     * How this library arranges its folders. 'file_number' means one folder per
     * file, named after the file number, and nothing meaningful at the root.
     */
    public function layout(): ?string
    {
        $layout = config('large_format_scans.libraries.' . $this->library . '.layout');

        return is_string($layout) && $layout !== '' ? $layout : null;
    }

    /**
     * Whether the root itself may be listed. False for a share whose root holds
     * tens of thousands of folders, where a listing is neither useful nor fast.
     */
    public function allowsRootListing(): bool
    {
        return (bool) config('large_format_scans.libraries.' . $this->library . '.browse_root', true);
    }

    public function maxEntries(): int
    {
        return (int) config(
            'large_format_scans.libraries.' . $this->library . '.max_entries',
            config('large_format_scans.max_entries', 2000)
        );
    }

    public function resolve(string $relative = ''): string
    {
        $root = realpath($this->root());
        if (!$root || !is_dir($root) || !is_readable($root)) {
            throw ValidationException::withMessages(['source' => 'The ' . $this->label() . ' is unavailable. Ask an administrator to configure it.']);
        }
        $relative = str_replace('\\', '/', $relative);
        if (preg_match('~(^/|:|\x00|(^|/)\.\.(/|$))~', $relative)) {
            throw ValidationException::withMessages(['source' => 'Invalid LF source path.']);
        }
        $path = realpath($root . DIRECTORY_SEPARATOR . $relative);
        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalized = $path ? str_replace('\\', '/', $path) : '';
        $compare = DIRECTORY_SEPARATOR === '\\' ? 'strncasecmp' : 'strncmp';
        if (!$path || ($path !== $root && $compare($normalized, $prefix, strlen($prefix)) !== 0) || !is_readable($path)) {
            throw ValidationException::withMessages(['source' => 'The selected image is outside the ' . $this->label() . ' or cannot be read.']);
        }
        return $path;
    }

    /**
     * Resolve a folder, answering null when it simply is not there.
     *
     * resolve() treats a missing path as an error because a chosen image must
     * exist. A folder is different: on the raw-scan share "this file has no
     * folder" is an ordinary answer the picker turns into an empty state, not
     * a fault. A share that is wholly unreachable still raises, because that is
     * a configuration problem an operator cannot work around.
     */
    public function resolveFolder(string $relative = ''): ?string
    {
        try {
            $path = $this->resolve($relative);
        } catch (ValidationException $e) {
            if (!$this->rootAvailable()) {
                throw $e;
            }

            // Missing, or outside the sandbox — both are "no such folder" here,
            // which also keeps a probe from reporting what lies outside.
            return null;
        }

        return is_dir($path) ? $path : null;
    }

    private function rootAvailable(): bool
    {
        $root = realpath($this->root());

        return $root !== false && is_dir($root) && is_readable($root);
    }

    /**
     * Folder names worth trying for one file number, most faithful first.
     *
     * The share names each folder after the file number, with hyphens where the
     * number carries a separator, so an exact hit is the common case. The rest
     * cover numbers recorded with stray spaces, or with a CON-/KN prefix the
     * scanning team did not carry into the folder name.
     */
    public function folderCandidates(string $fileNumber): array
    {
        $fileNumber = trim(preg_replace('/\s+/', ' ', $fileNumber));
        if ($fileNumber === '') {
            return [];
        }

        $hyphenated = trim(preg_replace('~[\s/\\\\]+~', '-', $fileNumber), '-');

        return array_values(array_unique(array_filter([
            $fileNumber,
            $hyphenated,
            strtoupper($hyphenated),
            trim((string) preg_replace('/^(CON|KN)-/i', '', $hyphenated), '-'),
        ])));
    }

    /**
     * The folder holding this file's raw scans, or null when the share has none.
     *
     * Every candidate is a direct existence check costing a few milliseconds.
     * The root is never enumerated, so an unmatched file number is as cheap to
     * answer as a matched one.
     */
    public function startFolder(string $fileNumber): ?string
    {
        foreach ($this->folderCandidates($fileNumber) as $candidate) {
            if ($this->resolveFolder($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    public function image(string $relative): array
    {
        $path = $this->resolve($relative);
        $info = is_file($path) ? @getimagesize($path) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/bmp' => 'bmp'];
        if (!$info || !isset($extensions[$info['mime']]) || filesize($path) > config('large_format_scans.max_bytes')) {
            throw ValidationException::withMessages(['source' => 'Choose a valid JPG, PNG, WebP, GIF or BMP image within 500 MB.']);
        }
        return ['path' => $path, 'mime' => $info['mime'], 'extension' => $extensions[$info['mime']], 'size' => filesize($path)];
    }
}
