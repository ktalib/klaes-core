<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * One place that turns a stored `profile` value into a URL.
 *
 * The column holds three shapes depending on which screen wrote it — a public-disk path
 * ("profiles/x.jpg" or "upload/profile/x.jpg"), a bare filename that lives under
 * upload/profile, or the legacy "avatar.png" placeholder with no file behind it.
 * User::getProfileUrlAttribute() delegates here, and so do the file-tracking screens
 * that read users through the query builder and so never get the model accessor.
 */
class UserPhoto
{
    public const PLACEHOLDER = 'avatar.png';

    /**
     * Resolve a raw `profile` value (optionally with its passport_photo_path fallback).
     *
     * Deliberately does not stat the disk: these run per row on list screens, where a
     * filesystem check cost ~0.8ms each. A row pointing at a deleted file yields a URL
     * that 404s, and the UI falls back to initials.
     *
     * asset(), NOT Storage::disk('public')->url(). The public disk's URL root is
     * config('filesystems.disks.public.url') = APP_URL . '/storage', so Storage::url()
     * hard-codes whatever APP_URL happens to say. .env is gitignored, so after a code
     * upload production keeps a development APP_URL and every avatar is emitted as
     * http://127.0.0.1:8000/storage/... — a URL the viewer's own browser resolves against
     * their machine, which serves nothing. The photo silently renders as an empty circle
     * while the file is present and the path is correct.
     *
     * asset() builds against the CURRENT REQUEST host instead, so the URL is right on
     * whatever hostname the app is actually served from. This matches
     * FilePassportService::describe() and ApplicationController::ossPassportUrl(), which
     * already resolve their stored paths this way.
     */
    public static function url($profile, $passportPath = null): ?string
    {
        foreach ([$profile, $passportPath] as $candidate) {
            $value = trim((string) ($candidate ?? ''));

            if ($value === '' || strtolower($value) === self::PLACEHOLDER) {
                continue;
            }

            $path = self::relativePath($value);

            if ($path === null) {
                continue;
            }

            return asset('storage/' . $path);
        }

        return null;
    }

    /**
     * Is there actually a file behind a stored `profile` value?
     *
     * url() deliberately never stats the disk — list screens render hundreds of avatars.
     * A SINGLE-record screen can afford one stat, and wants it: a row pointing at a file
     * that was never copied to this server should fall back to the placeholder icon rather
     * than render a broken frame.
     *
     * Applies the same shape rules as url(), so the two can never disagree about which
     * file a stored value names.
     */
    public static function existsOnDisk($profile): bool
    {
        $path = self::relativePath($profile);

        return $path !== null && Storage::disk('public')->exists($path);
    }

    /**
     * The public-disk-relative path a stored `profile` value names, or null when it names
     * nothing (blank, or the legacy "avatar.png" placeholder that has no file behind it).
     */
    private static function relativePath($profile): ?string
    {
        $value = trim((string) ($profile ?? ''));

        if ($value === '' || strtolower($value) === self::PLACEHOLDER) {
            return null;
        }

        // Bare filenames were only ever written into upload/profile.
        $path = str_contains($value, '/') ? $value : 'upload/profile/' . $value;

        // Legacy rows carry a "public/" prefix; both shapes name the same file.
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return ltrim($path, '/');
    }

    /**
     * Photo URL for a single user id.
     */
    /**
     * Request-lifetime memo, shared by forId() and prime().
     * Values are ['found' => bool, 'url' => ?string]: "the user exists but has no photo"
     * and "no such user" must stay distinguishable, or forIdOrName() falls back to a
     * name lookup for every photo-less officer.
     */
    private static array $idCache = [];

    /** Request-lifetime memo for name lookups. */
    private static array $nameCache = [];

    /**
     * Resolve many ids in one query and memoise them.
     *
     * List screens decorate every row, so call this with the page's officer ids before
     * the loop — the same pattern the tracker list already uses for indexing timestamps
     * and the commissioning register. Without it, 40 rows cost 40 round trips (~150ms).
     */
    public static function prime(iterable $ids): void
    {
        $ids = collect($ids)
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id) && !array_key_exists($id, self::$idCache))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $found = User::whereIn('id', $ids->map(fn ($id) => (int) $id)->all())
            ->get(['id', 'profile', 'passport_photo_path'])
            ->keyBy('id');

        foreach ($ids as $id) {
            $user = $found->get((int) $id);
            self::$idCache[$id] = [
                'found' => $user !== null,
                'url' => $user ? self::url($user->profile, $user->passport_photo_path) : null,
            ];
        }
    }

    public static function forId($id): ?string
    {
        $id = trim((string) $id);

        if ($id === '' || !ctype_digit($id)) {
            return null;
        }

        // The tracker list decorates every row, and the same officer holds many files —
        // memoise for the life of the request so it stays one query per person.
        if (!array_key_exists($id, self::$idCache)) {
            $user = User::find((int) $id, ['id', 'profile', 'passport_photo_path']);
            self::$idCache[$id] = [
                'found' => $user !== null,
                'url' => $user ? self::url($user->profile, $user->passport_photo_path) : null,
            ];
        }

        return self::$idCache[$id]['url'];
    }

    /**
     * Did an id resolve to a real user row (whether or not they have a photo)?
     */
    private static function idResolved($id): bool
    {
        $id = trim((string) $id);

        return $id !== '' && ctype_digit($id) && (self::$idCache[$id]['found'] ?? false);
    }

    /**
     * Photo URL for a display name — file tracking stores the officer's name on some rows.
     */
    public static function forName(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        if (array_key_exists($name, self::$nameCache)) {
            return self::$nameCache[$name];
        }

        $user = User::query()
            ->where(function ($query) use ($name) {
                $query->whereRaw("LTRIM(RTRIM(COALESCE(first_name, '') + ' ' + COALESCE(last_name, ''))) = ?", [$name])
                    ->orWhere('username', $name);
            })
            ->first(['id', 'profile', 'passport_photo_path']);

        return self::$nameCache[$name] = $user ? self::url($user->profile, $user->passport_photo_path) : null;
    }

    /**
     * Either shape, id first — the file-tracking tables hold both.
     */
    public static function forIdOrName($id, ?string $name): ?string
    {
        $url = self::forId($id);

        // Only fall back to the name when the id names no one. An officer who simply has
        // no photo must not trigger a second lookup — which would also risk matching a
        // different person who happens to share the display name.
        if ($url !== null || self::idResolved($id)) {
            return $url;
        }

        return self::forName($name);
    }

    /**
     * Bulk lookup for list screens: [id => url|null]. One query, no per-row N+1.
     */
    public static function mapForIds(iterable $ids): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::whereIn('id', $ids->all())
            ->get(['id', 'profile', 'passport_photo_path'])
            ->mapWithKeys(fn ($user) => [$user->id => self::url($user->profile, $user->passport_photo_path)])
            ->all();
    }
}
