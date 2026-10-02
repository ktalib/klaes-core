<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One transactional SMS message's live switch and wording.
 *
 * WHY THIS TABLE EXISTS AT ALL
 * Every SMS switch in this codebase used to be an .env key. .env is gitignored,
 * so it does NOT travel with a code upload -- turning a message on meant editing
 * a file on the server and restarting the web server, and a fresh deployment
 * silently lost whatever the Ministry had configured. Putting the switches in
 * the database means the SMS Control Centre can change them, they survive a
 * deploy, and the config file becomes a set of sane defaults rather than the
 * only source of truth.
 *
 * READS ARE CACHED. Several of these are consulted inside request paths that
 * already do a lot of work (commissioning writes to a dozen tables), so the
 * whole table is loaded once and kept until a write invalidates it -- see
 * booted() below. The cache is keyed by nothing but this class, so any save or
 * delete clears the lot; the table has at most a couple of dozen rows.
 *
 * MISSING ROW == THE CONFIG DEFAULT. A key added by a later code upload has no
 * row until somebody saves it, and a fresh server has no rows at all. Both fall
 * back to config('klaes_sms.messages.<key>'), where default_enabled is false --
 * so the safe direction (send nothing) is also the automatic one.
 */
class SmsSetting extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'sms_settings';

    /** The master switch, consulted before any individual message. */
    public const KEY_MASTER = 'master';

    public const KEY_LAND_FC = 'land_fc';
    public const KEY_OSS_FC = 'oss_fc';
    public const KEY_ST_FC = 'st_fc';
    public const KEY_ROFO_GENERATED = 'rofo_generated';
    public const KEY_FILE_REQUEST_RECEIVED = 'file_request_received';
    public const KEY_FILE_REQUEST_LOGGED = 'file_request_logged';
    public const KEY_DEED_REGISTERED = 'deed_registered';
    public const KEY_CAVEAT_PLACED = 'caveat_placed';
    public const KEY_CAVEAT_LIFTED = 'caveat_lifted';
    public const KEY_CAVEAT_AUTO_LIFTED = 'caveat_auto_lifted';

    /*
     | Staff attendance. These two are dispatched by StaffAttendanceSmsService,
     | not by KlaesSmsDispatcher -- it keeps its own once-a-day claim and its own
     | shift-end rule. They appear here only so one page controls every message.
     */
    public const KEY_ATTENDANCE_LOGIN = 'attendance_login';
    public const KEY_ATTENDANCE_LOGOUT = 'attendance_logout';

    /*
     | The phone-verification code. Listed here so its wording is editable and
     | its sends are visible alongside everything else -- but it is declared
     | `always_on` in the catalogue and CANNOT be switched off from the page.
     | It is the code people need to get into the system: a toggle that silences
     | it locks the Ministry out of its own application, and the person who
     | would have to undo that is on the far side of the gate.
     */
    public const KEY_PHONE_OTP = 'phone_otp';

    private const CACHE_KEY = 'klaes_sms.settings';

    protected $fillable = [
        'message_key',
        'enabled',
        'template',
        // Used when one message covers several files -- see the batch note in
        // config/klaes_sms.php. Null means "use the shipped plural form".
        'plural_template',
        // 'staff' | 'department' -- the GROUP, not the literal sender ID.
        'sender',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Any write invalidates the whole map. Cheaper and far harder to get
        // wrong than per-key invalidation on a table this small.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Every stored row, keyed by message_key.
     *
     * Returns an empty map -- not an error -- when the table is missing, which
     * is the state of a server where the deploy SQL has not been run yet. The
     * caller then falls through to the config defaults and sends nothing.
     *
     * @return array<string, array{enabled:bool, template:?string}>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return static::query()
                    ->get(['message_key', 'enabled', 'template', 'plural_template', 'sender'])
                    ->keyBy('message_key')
                    ->map(fn (self $row) => [
                        'enabled' => (bool) $row->enabled,
                        'template' => $row->template,
                        'plural_template' => $row->plural_template,
                        'sender' => $row->sender,
                    ])
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Is this message switched on?
     *
     * The master switch gates everything except itself, so turning `master` off
     * silences the lot without disturbing the individual settings underneath --
     * which is what you want when the wallet runs dry.
     */
    public static function isEnabled(string $key): bool
    {
        /*
         | An always-on message ignores both its own row and the master switch.
         | Only the phone-verification code is declared this way: everything else
         | can be silenced, but a sign-in code cannot, because switching it off
         | leaves nobody able to sign in and switch it back on.
         */
        if (self::isAlwaysOn($key)) {
            return true;
        }

        if ($key !== self::KEY_MASTER && !self::isEnabled(self::KEY_MASTER)) {
            return false;
        }

        $map = self::map();

        if (array_key_exists($key, $map)) {
            return $map[$key]['enabled'];
        }

        if ($key === self::KEY_MASTER) {
            return (bool) config('klaes_sms.enabled', false);
        }

        return (bool) config('klaes_sms.messages.' . $key . '.default_enabled', false);
    }

    /**
     * The wording to send: the Ministry's override if one was saved, otherwise
     * the shipped default.
     */
    public static function templateFor(string $key, bool $plural = false): ?string
    {
        $map = self::map();
        $column = $plural ? 'plural_template' : 'template';
        $override = $map[$key][$column] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        $shipped = config('klaes_sms.messages.' . $key . '.' . $column);

        /*
         | A message with no plural form of its own falls back to its singular
         | one. That is right for anything never sent about more than one thing
         | at a time -- a caveat, a Letter of Grant -- and it makes adding a
         | plural form later purely additive.
         */
        if ($plural && ($shipped === null || trim((string) $shipped) === '')) {
            return self::templateFor($key, false);
        }

        return $shipped;
    }

    /**
     * The sender ID this message goes out under.
     *
     * Resolved in three steps: an override saved on the control page, else the
     * group the message is declared under in config/klaes_sms.php, else the
     * server-wide default in config/services.php. Returning null is meaningful --
     * it tells BulkSmsNgService to use whatever this server is configured for,
     * which is what every caller written before sender IDs existed still gets.
     */
    public static function senderFor(string $key): ?string
    {
        $map = self::map();

        $group = $map[$key]['sender']
            ?? config('klaes_sms.messages.' . $key . '.sender');

        if (!$group) {
            return null;
        }

        // A group key resolves through the senders table; anything else is
        // already a literal sender ID and is passed straight through.
        return config('klaes_sms.senders.' . $group, $group);
    }

    /** Which sender GROUP this message is set to ('staff' | 'department'). */
    public static function senderGroupFor(string $key): ?string
    {
        $map = self::map();

        return $map[$key]['sender']
            ?? config('klaes_sms.messages.' . $key . '.sender');
    }

    /** The sender IDs this deployment sends under, keyed by group. */
    public static function senderOptions(): array
    {
        return (array) config('klaes_sms.senders', []);
    }

    /**
     * Which sender group a URL segment names.
     *
     * Accepts the group key ('department') OR the sender ID as it reads in the
     * menu ('kanomlpp'). The readable form is what the SMS Management links use;
     * the group key is accepted as well so a bookmarked page still opens after
     * somebody changes KLAES_SMS_SENDER_DEPARTMENT on the server.
     *
     * Returns null for anything else -- the caller 404s rather than quietly
     * showing the unfiltered page.
     */
    public static function senderGroupForSlug(string $slug): ?string
    {
        $slug = strtolower(trim($slug));

        foreach (self::senderOptions() as $group => $id) {
            if ($slug === strtolower($group) || $slug === strtolower((string) $id)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The group a message that declares no sender actually goes out under.
     *
     * senderFor() returns null for such a message, which tells the gateway to
     * use the server-wide default -- so on the sender pages it has to be listed
     * under whichever group that default names, not hidden from all of them.
     */
    public static function defaultSenderGroup(): ?string
    {
        $default = (string) config('services.bulk_sms_ng.sender', '');

        foreach (self::senderOptions() as $group => $id) {
            if ($default !== '' && strcasecmp($default, (string) $id) === 0) {
                return $group;
            }
        }

        return array_key_first(self::senderOptions()) ?: null;
    }

    /**
     * The group this message is listed under on the SMS Management pages.
     *
     * Never null, so no message can fall off every page.
     */
    public static function effectiveSenderGroup(string $key): ?string
    {
        return self::senderGroupFor($key) ?? self::defaultSenderGroup();
    }

    /**
     * Has Bulk-SMS.ng confirmed this group's sender ID?
     *
     * Display only -- see the note in config/klaes_sms.php. An unconfirmed ID is
     * accepted and billed by the gateway and then delivered to nobody, so the
     * page says so rather than letting the traffic look healthy.
     */
    public static function isRegisteredSenderGroup(string $group): bool
    {
        $registered = (array) config('klaes_sms.registered_senders', []);

        if (empty($registered)) {
            return true;
        }

        $id = self::senderOptions()[$group] ?? null;

        foreach ($registered as $entry) {
            if (strcasecmp((string) $entry, $group) === 0
                || ($id !== null && strcasecmp((string) $entry, (string) $id) === 0)) {
                return true;
            }
        }

        return false;
    }

    /** The plainer wording tried only when the gateway refuses the first. */
    public static function fallbackFor(string $key, bool $plural = false): ?string
    {
        if ($plural) {
            $shipped = config('klaes_sms.messages.' . $key . '.plural_fallback');

            if ($shipped !== null && trim((string) $shipped) !== '') {
                return $shipped;
            }
        }

        return config('klaes_sms.messages.' . $key . '.fallback');
    }

    /** Does this message ship a distinct wording for the batch case? */
    public static function hasPluralForm(string $key): bool
    {
        $shipped = config('klaes_sms.messages.' . $key . '.plural_template');

        return is_string($shipped) && trim($shipped) !== '';
    }

    /** The catalogue entry for one key, or null if the key is not defined. */
    public static function definition(string $key): ?array
    {
        return config('klaes_sms.messages.' . $key);
    }

    /** The whole catalogue, in the order config/klaes_sms.php declares it. */
    public static function catalogue(): array
    {
        return (array) config('klaes_sms.messages', []);
    }

    /** Is this message one that must never be switched off? */
    public static function isAlwaysOn(string $key): bool
    {
        return (bool) config('klaes_sms.messages.' . $key . '.always_on', false);
    }

    /**
     * Has the Ministry actually saved a setting for this key?
     *
     * The difference matters to callers that already had their own default
     * before this table existed -- the staff attendance SMS, whose switches used
     * to live in config/staff_sms.php. "No row" must mean "nobody has expressed a
     * preference here", so those callers keep their old behaviour rather than
     * being silently switched off by an empty table.
     */
    public static function hasRowFor(string $key): bool
    {
        return array_key_exists($key, self::map());
    }

    /** Does the given key name a message we know about? */
    public static function isKnownKey(string $key): bool
    {
        return array_key_exists($key, self::catalogue());
    }
}
