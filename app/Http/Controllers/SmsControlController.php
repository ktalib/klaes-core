<?php

namespace App\Http\Controllers;

use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\BulkSmsNgService;
use App\Services\Sms\ApplicantPhoneResolver;
use App\Services\Sms\KlaesSmsDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The SMS Control Centre.
 *
 * Every transactional SMS switch used to be an .env key, which meant the Ministry
 * could not turn a message on without someone editing a file on the server and
 * restarting it -- and the next code upload silently lost the setting, because
 * .env is gitignored and does not travel. This page moves the switches, and the
 * wordings, into the database.
 *
 * It also answers the questions that decide whether switching one on is a good
 * idea: what the message will cost per send, what the wallet holds, and what the
 * last few sends actually did.
 */
class SmsControlController extends Controller
{
    public function __construct(private KlaesSmsDispatcher $dispatcher)
    {
        $this->middleware('auth');
    }

    /**
     * The control page, either whole or narrowed to one sender ID.
     *
     * $sender is null for /sms-control -- every message, the way this page has
     * always worked. The SMS Management sub-module passes one of the sender
     * names (KLAES, KANOMLPP, KANGIS) and gets the same page holding only the
     * messages that go out under it.
     */
    public function index(Request $request, ?string $sender = null)
    {
        $this->authoriseControl($request);

        $group = null;

        if ($sender !== null) {
            $group = SmsSetting::senderGroupForSlug($sender);

            if ($group === null) {
                abort(404, 'There is no SMS sender by that name.');
            }
        }

        $senderId = $group !== null ? (SmsSetting::senderOptions()[$group] ?? $group) : null;

        return view('sms_control.index', [
            'PageTitle' => $group !== null ? 'SMS Management - ' . $senderId : 'SMS Control Centre',
            'PageDescription' => $group !== null
                ? 'The transactional messages that go out as ' . $senderId . '.'
                : 'Switch each transactional message on or off, edit its wording, and see what it costs.',
            'senderGroup' => $group,
            'senderId' => $senderId,
            /*
             | Credits are left off the per-sender pages deliberately, and not
             | only because they were not asked for: the wallet is ONE prepaid
             | account shared by every sender ID, so a balance printed on a page
             | headed KANGIS reads as that sender's credit and is wrong on all
             | three. It stays on the unfiltered /sms-control page, where it
             | means what it says.
             */
            'showCredits' => $group === null,
            'senderRegistered' => $group === null || SmsSetting::isRegisteredSenderGroup($group),
        ]);
    }

    /**
     * Everything the page renders, in one call.
     *
     * Built server-side rather than in the blade so the page can refresh itself
     * after a save without a reload, and so the preview the officer reads is
     * rendered by exactly the code that will render the real message.
     */
    public function state(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        // Narrow to one sender ID for the SMS Management pages. Absent means
        // every message, which is what /sms-control asks for.
        $senderGroup = null;

        if ($request->filled('sender')) {
            $senderGroup = SmsSetting::senderGroupForSlug((string) $request->input('sender'));

            if ($senderGroup === null) {
                return response()->json(['success' => false, 'message' => 'Unknown sender.'], 404);
            }
        }

        $groups = [];

        foreach (SmsSetting::catalogue() as $key => $definition) {
            /*
             | effectiveSenderGroup(), not senderGroupFor(): a message that
             | declares no sender still goes out -- under the server-wide
             | default -- so it has to appear on that sender's page rather than
             | on none of them.
             */
            if ($senderGroup !== null && SmsSetting::effectiveSenderGroup($key) !== $senderGroup) {
                continue;
            }

            $template = SmsSetting::templateFor($key);
            $preview = $this->dispatcher->preview($key);

            // Batch messages carry a second wording -- see the note on
            // sms_settings.plural_template.
            $hasPlural = SmsSetting::hasPluralForm($key);
            $pluralPreview = $hasPlural ? $this->dispatcher->preview($key, null, true) : null;

            $groups[$definition['group'] ?? 'Other'][] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'audience' => $definition['audience'] ?? 'applicant',
                'recipient' => $definition['recipient'] ?? null,
                'enabled' => SmsSetting::isEnabled($key),
                'template' => $template,
                // A message whose wording lives in code (staff attendance) can be
                // switched but not edited, so the page hides its editor.
                'toggle_only' => (bool) ($definition['toggle_only'] ?? false),
                // Shown with its switch locked. See SmsSetting::KEY_PHONE_OTP.
                'always_on' => SmsSetting::isAlwaysOn($key),
                // Which name this message goes out under, and the choices.
                'sender_group' => SmsSetting::senderGroupFor($key),
                'sender_id' => SmsSetting::senderFor($key),
                'has_plural' => $hasPlural,
                'plural_template' => $hasPlural ? SmsSetting::templateFor($key, true) : null,
                'plural_preview' => $pluralPreview['body'] ?? null,
                'plural_characters' => $pluralPreview['characters'] ?? 0,
                'plural_pages' => $pluralPreview['pages'] ?? 0,
                'tokens' => array_values((array) ($definition['tokens'] ?? [])),
                'preview' => $preview['body'],
                'characters' => $preview['characters'],
                'pages' => $preview['pages'],
                'is_default' => $template === ($definition['template'] ?? null),
                'recent' => $this->recentFor($key),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'master' => SmsSetting::isEnabled(SmsSetting::KEY_MASTER),
                'senders' => SmsSetting::senderOptions(),
                // No balance on a sender page: the wallet is shared, so a figure
                // there would read as that sender's own credit.
                'gateway' => $this->gatewayStatus($senderGroup === null),
                'sender' => $senderGroup,
                'sender_id' => $senderGroup !== null
                    ? (SmsSetting::senderOptions()[$senderGroup] ?? $senderGroup)
                    : null,
                'sender_registered' => $senderGroup === null
                    || SmsSetting::isRegisteredSenderGroup($senderGroup),
                'groups' => $groups,
                'today' => $this->todayCounts($senderGroup),
            ],
        ]);
    }

    /**
     * Save one message's switch and, optionally, its wording.
     *
     * A blank template CLEARS the override and returns the message to the wording
     * shipped in config/klaes_sms.php -- which is what you want when a later code
     * upload corrects the Ministry's wording and a stale override would keep
     * hiding it.
     */
    public function updateMessage(Request $request, string $key): JsonResponse
    {
        $this->authoriseControl($request);

        if ($key !== SmsSetting::KEY_MASTER && !SmsSetting::isKnownKey($key)) {
            return response()->json(['success' => false, 'message' => 'Unknown message.'], 404);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'template' => 'nullable|string|max:1000',
            'plural_template' => 'nullable|string|max:1000',
            // The sender GROUP, not a free-text ID: allowing anything here would
            // let somebody send under a name the vendor has not registered, and
            // an unregistered sender is accepted, billed and never delivered.
            'sender' => 'nullable|string|in:' . implode(',', array_keys(SmsSetting::senderOptions())),
        ]);

        /*
         | An always-on message may have its wording edited but never its switch.
         | Enforced here as well as in the page, because the page is only a
         | courtesy -- this endpoint is reachable directly, and switching off the
         | sign-in code would lock everybody out of the system that turns it back
         | on. The gate itself is paused with PHONE_VERIFICATION_ENABLED=false.
         */
        if (SmsSetting::isAlwaysOn($key) && !$validated['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'This message cannot be switched off — it is the code people need in order to sign in. '
                    . 'To pause phone verification entirely, set PHONE_VERIFICATION_ENABLED=false.',
            ], 422);
        }

        $template = trim((string) ($validated['template'] ?? ''));
        $pluralTemplate = trim((string) ($validated['plural_template'] ?? ''));

        foreach ([$template, $pluralTemplate] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $unknown = $this->unknownTokens($key, $candidate);

            if (!empty($unknown)) {
                /*
                 | A token nothing supplies renders as a literal "[Foo]" in
                 | somebody's inbox, so this is refused rather than silently
                 | stripped -- the officer needs to know they mistyped it.
                 */
                return response()->json([
                    'success' => false,
                    'message' => 'This message does not provide ' . implode(', ', $unknown)
                        . '. Available placeholders: ' . implode(', ', $this->tokensFor($key)) . '.',
                ], 422);
            }
        }

        SmsSetting::updateOrCreate(
            ['message_key' => $key],
            [
                'enabled' => (bool) $validated['enabled'],
                'template' => $template !== '' ? $template : null,
                'plural_template' => $pluralTemplate !== '' ? $pluralTemplate : null,
                // Null returns the message to the group it ships under, the
                // same way a blank template returns it to the shipped wording.
                'sender' => $validated['sender'] ?? null,
                'updated_by' => Auth::user()->name ?? null,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Send one message to a nominated number, so it can be read on a handset
     * before it is switched on for the public.
     *
     * Rendered with the message's sample values, and sent even when the message
     * is switched off -- testing before enabling is the whole point.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        $validated = $request->validate([
            'key' => 'required|string',
            'phone' => 'required|string|max:30',
        ]);

        $key = $validated['key'];

        if (!SmsSetting::isKnownKey($key)) {
            return response()->json(['success' => false, 'message' => 'Unknown message.'], 404);
        }

        $normalised = BulkSmsNgService::normalizeNumber($validated['phone']);

        if ($normalised === null) {
            return response()->json([
                'success' => false,
                'message' => '"' . $validated['phone'] . '" is not a usable Nigerian mobile number.',
            ], 422);
        }

        $body = $this->dispatcher->preview($key)['body'];

        if ($body === null) {
            return response()->json([
                'success' => false,
                'message' => 'This message has no wording to send. Its text lives in code, not here.',
            ], 422);
        }

        // Straight to the gateway, not through the dispatcher: a test must work
        // while the message is still switched off, and must not claim the event's
        // dedupe slot or be counted as a real send.
        $gateway = new BulkSmsNgService();
        $sent = $gateway->send($normalised, $body);

        SmsDispatchLog::create([
            'message_key' => $key,
            'phone' => $normalised,
            'message' => $body,
            /*
             | The file number the test was rendered with.
             |
             | Without this the log showed a blank File against a row whose
             | wording plainly contained one -- every Quick Search row in the
             | log so far is a test, which is why that column looked broken for
             | file requests. A real send has always carried its own number
             | through the dispatcher's context.
             */
            'file_number' => $this->sampleFileNumber($key),
            'status' => $sent ? SmsDispatchLog::STATUS_SENT : SmsDispatchLog::STATUS_FAILED,
            'gateway_code' => $gateway->lastStatusCode(),
            'failure_reason' => $sent ? null : $gateway->lastFailureReason(),
            'attempts' => 1,
            'subject_type' => 'test',
            // No dedupe key: an officer may quite reasonably send the same test
            // twice, and the index is filtered so NULLs do not collide.
            'dedupe_key' => null,
            'event_at' => now(config('klaes_sms.timezone', 'Africa/Lagos'))->toDateTimeString(),
            'created_by' => Auth::user()->name ?? null,
        ]);

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'Accepted by the gateway. Accepted is not delivered: a DND-blocked handset receives nothing, and code 609 means it is held until 08:30.'
                : ($gateway->lastFailureReason() ?: 'The gateway refused the message.'),
            'code' => $gateway->lastStatusCode(),
        ]);
    }

    /**
     * The dispatch log -- every send, newest first, for the table under the
     * message cards.
     *
     * Narrowed by sender on the SMS Management pages, and by message, status or
     * file number from the table's own filters. The status tallies are computed
     * over the WHOLE filtered set, not the page on screen, because "how many
     * failed" is the question the table exists to answer and a per-page count
     * would answer a different one.
     */
    public function log(Request $request): JsonResponse
    {
        $this->authoriseControl($request);

        $senderGroup = null;

        if ($request->filled('sender')) {
            $senderGroup = SmsSetting::senderGroupForSlug((string) $request->input('sender'));

            if ($senderGroup === null) {
                return response()->json(['success' => false, 'message' => 'Unknown sender.'], 404);
            }
        }

        // Which messages this page is allowed to show at all. On a sender page
        // that is the sender's own; on /sms-control it is everything.
        $scopeKeys = $senderGroup !== null ? $this->keysForSender($senderGroup) : null;

        try {
            $query = SmsDispatchLog::query()->orderByDesc('id');

            if ($scopeKeys !== null) {
                /*
                 | An empty scope is a real answer, not "no filter". KANGIS starts
                 | with no messages, and whereIn([]) is what makes the table say
                 | so instead of showing every other sender's traffic.
                 */
                $query->whereIn('message_key', $scopeKeys);
            }

            if ($request->filled('key')) {
                $key = (string) $request->input('key');

                // A key outside this page's scope must not widen it.
                if ($scopeKeys !== null && !in_array($key, $scopeKeys, true)) {
                    $key = '__none__';
                }

                $query->where('message_key', $key);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('file_number')) {
                $term = '%' . $request->input('file_number') . '%';

                // Searched across both, because the commonest thing an officer
                // has in hand is one or the other, not the message key.
                $query->where(function ($q) use ($term) {
                    $q->where('file_number', 'like', $term)
                      ->orWhere('phone', 'like', $term);
                });
            }

            // Tallies over the filtered set, before paging.
            $counts = (clone $query)
                ->reorder()
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($n) => (int) $n)
                ->all();

            $perPage = min(max((int) $request->input('per_page', 25), 5), 100);
            $page = $query->paginate($perPage, ['*'], 'page', (int) $request->input('page', 1));

            $labels = $this->messageLabels();

            return response()->json([
                'success' => true,
                'data' => [
                    'rows' => collect($page->items())->map(fn ($row) => [
                        'id' => $row->id,
                        'key' => $row->message_key,
                        'label' => $labels[$row->message_key] ?? $row->message_key,
                        'status' => $row->status,
                        'phone' => $row->phone,
                        'file_number' => $row->file_number,
                        'code' => $row->gateway_code,
                        'reason' => $row->failure_reason,
                        // What the column shows, and what the expanded panel
                        // shows underneath it.
                        'outcome_text' => $this->gatewayWording($row->status, $row->gateway_code, $row->failure_reason),
                        'gateway_detail' => $this->gatewayDetail($row->gateway_code, $row->failure_reason),
                        'attempts' => $row->attempts,
                        // The wording actually sent, which is not necessarily
                        // the wording configured now -- somebody may have
                        // edited it since.
                        'message' => $row->message,
                        'is_test' => $row->subject_type === 'test',
                        'by' => $row->created_by,
                        'at' => optional($row->created_at)->format('d/m/Y H:i'),
                    ])->all(),
                    'counts' => $counts,
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    // Drives the table's message filter, so it only ever offers
                    // messages this page is scoped to.
                    'messages' => $scopeKeys !== null
                        ? array_intersect_key($labels, array_flip($scopeKeys))
                        : $labels,
                ],
            ]);
        } catch (\Throwable $e) {
            /*
             | The table may not be deployed on this server yet. Say so, rather
             | than showing an empty log that reads as "nothing has ever been
             | sent" -- klaes:sms-doctor reports the same condition.
             */
            return response()->json([
                'success' => false,
                'message' => 'The dispatch log could not be read. The sms_dispatch_logs table may not exist on this server yet '
                    . '- run php artisan klaes:sms-doctor to confirm.',
            ], 500);
        }
    }

    /**
     * What the log's "Gateway said" column reads, in plain words.
     *
     * The Ministry asked for outcomes an officer can read at a glance rather
     * than the vendor's codes, so 604 reads "Failed" and 100 reads "Delivered".
     *
     * TWO THINGS ARE DELIBERATELY NOT COLLAPSED INTO THOSE.
     *
     * 609 keeps its own wording. It is not a delivery: Nigerian telcos take no
     * promotional traffic between 19:45 and 08:00, so the gateway holds the
     * message until 08:30. Calling that "Delivered" would say a handset had it
     * hours before it did.
     *
     * A skipped row keeps its reason. "No phone number on file" is the
     * commonest reason an applicant is never told anything, and it is a
     * decision this system made, not something the gateway said.
     *
     * The raw code and the vendor's own phrasing are not lost -- gatewayDetail()
     * puts them on the row's expanded panel. That matters most for 604, which is
     * the one failure with an obvious remedy (top the account up) and would
     * otherwise be indistinguishable from a refusal.
     */
    private function gatewayWording(?string $status, ?string $code, ?string $reason): string
    {
        if ($status === SmsDispatchLog::STATUS_SKIPPED) {
            return $reason ?: 'Not sent';
        }

        if ($status === SmsDispatchLog::STATUS_PENDING) {
            return 'In flight';
        }

        if ($status === SmsDispatchLog::STATUS_FAILED) {
            return 'Failed';
        }

        if ((string) $code === '609') {
            return 'Held until 08:30';
        }

        /*
         | "Delivered" is the Ministry's word for an accepted send. This account
         | is on the promotional route and the gateway returns no delivery
         | receipt, so what is actually known is that it was accepted -- a
         | DND-blocked handset receives nothing and still lands here. The note
         | under the table's heading says so.
         */
        return 'Delivered';
    }

    /** The vendor's own account of a row, for its expanded panel. */
    private function gatewayDetail(?string $code, ?string $reason): ?string
    {
        $parts = [];

        if ($code !== null && $code !== '') {
            $parts[] = 'code ' . $code;
        }

        if ($reason !== null && $reason !== '') {
            $parts[] = $reason;
        }

        $meaning = BulkSmsNgService::describeCode($code);

        if ($meaning !== null && $meaning !== $reason) {
            $parts[] = $meaning;
        }

        return $parts ? implode(' - ', $parts) : null;
    }

    /** Human names for every message key, for the log table. */
    private function messageLabels(): array
    {
        $labels = [];

        foreach (SmsSetting::catalogue() as $key => $definition) {
            $labels[$key] = $definition['label'] ?? $key;
        }

        return $labels;
    }

    /**
     * Which number a file would be texted on -- the endpoint every capture form
     * calls to prefill its phone field.
     *
     * Returns the shared-number verdict alongside the number, because a number on
     * a thousand files is an indexing clerk and the officer needs to be told that
     * before they let a message go to it.
     */
    public function resolvePhone(Request $request, ApplicantPhoneResolver $phones): JsonResponse
    {
        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
        ]);

        $verdict = $phones->forFileNumber($validated['file_number']);

        return response()->json([
            'success' => true,
            'data' => [
                // The storage form (0803...) is what the officer expects to read
                // and what the form field should hold; the gateway normalises it
                // again on the way out.
                'phone' => $verdict['raw'] ?: $verdict['phone'],
                'source' => $verdict['source'],
                'shared' => $verdict['shared'],
                'shared_count' => $verdict['shared_count'],
                'warning' => $verdict['shared']
                    ? 'This number is on file against ' . $verdict['shared_count']
                        . ' files, so it may belong to an agent or an indexing officer rather than this applicant. Confirm it before it is used.'
                    : null,
            ],
        ]);
    }

    /**
     * Only staff who administer the system may change what the Ministry sends to
     * the public. 'System Settings' is the existing role that already gates the
     * other system-administration screens.
     *
     * Roles here are the app's own -- users.assign_role resolved through
     * User::assignedRoleNames(), with a super-admin short circuit -- not Spatie's
     * trait, which this User model does not use.
     */
    private function authoriseControl(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $roles = method_exists($user, 'assignedRoleNames')
            ? array_map('strtolower', (array) $user->assignedRoleNames())
            : [];

        $allowed = array_intersect($roles, [
            'system settings',
            'super admin',
            // The seeded role name really is misspelled in this database.
            'supper admin',
        ]);

        if (empty($allowed) && !$user->can('manage general settings')) {
            abort(403, 'You do not have permission to manage SMS settings.');
        }
    }

    /**
     * The file number a test send is rendered with, or null for a message that
     * is not about a file (the sign-in code, staff attendance).
     */
    private function sampleFileNumber(string $key): ?string
    {
        $sample = (array) (SmsSetting::definition($key)['sample'] ?? []);

        // Both spellings are in use: the file-request wordings say "FileNo(s)"
        // because the Ministry's text is plural, though the data is one number.
        foreach (['FileNo', 'FileNo(s)'] as $token) {
            if (!empty($sample[$token])) {
                return (string) $sample[$token];
            }
        }

        return null;
    }

    /** The last few sends for one message, for its card. */
    private function recentFor(string $key): array
    {
        try {
            return SmsDispatchLog::where('message_key', $key)
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'status', 'phone', 'file_number', 'gateway_code', 'failure_reason', 'created_at'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'status' => $row->status,
                    'phone' => $row->phone,
                    'file_number' => $row->file_number,
                    'code' => $row->gateway_code,
                    // The same wording as the table below, so a card and the log
                    // never describe one send two different ways.
                    'reason' => $this->gatewayWording($row->status, $row->gateway_code, $row->failure_reason),
                    'at' => optional($row->created_at)->format('d/m/Y H:i'),
                ])
                ->all();
        } catch (\Throwable $e) {
            // The table may not be deployed yet. An empty list is the honest
            // answer, and reportTables() in the doctor command says why.
            return [];
        }
    }

    /**
     * Whether this server can send at all, in plain language.
     *
     * The three ways it cannot are indistinguishable from the outside -- nothing
     * arrives -- so the page names which one it is rather than showing a toggle
     * that will silently do nothing.
     */
    private function gatewayStatus(bool $withBalance = true): array
    {
        $email = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');

        if (!$email || !$password) {
            return [
                'ready' => false,
                'sender' => config('services.bulk_sms_ng.sender'),
                'balance' => null,
                'problem' => 'BULK_SMS_NG_EMAIL and BULK_SMS_NG_PASSWORD are not set on this server. '
                    . '.env is not carried across by a code upload, so they have to be set again after a deployment.',
            ];
        }

        $balance = null;

        // Skipped, not merely hidden, when the page will not show it -- reading
        // it is a call out to the gateway on every page load.
        if ($withBalance) {
            try {
                $balance = (new BulkSmsNgService())->balance();
            } catch (\Throwable $e) {
                $balance = null;
            }
        }

        return [
            'ready' => true,
            'sender' => config('services.bulk_sms_ng.sender'),
            'account' => $email,
            'balance' => $balance,
            'problem' => ($withBalance && $balance === null)
                ? 'The wallet balance could not be read. The credentials may be wrong, or this server may not be able to reach the gateway.'
                : null,
        ];
    }

    /**
     * Today's traffic, by status -- for one sender's messages when the page is
     * narrowed to one.
     *
     * Counted by which messages BELONG to that sender now, not by what each row
     * was sent as: the log records no sender ID, so a message moved between
     * senders takes its history with it. That is the honest reading of the data
     * that exists, rather than a number that looks per-sender and is not.
     */
    private function todayCounts(?string $senderGroup = null): array
    {
        try {
            $query = SmsDispatchLog::query()
                ->whereDate('created_at', now()->toDateString());

            if ($senderGroup !== null) {
                $query->whereIn('message_key', $this->keysForSender($senderGroup));
            }

            return $query
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Every catalogue key currently going out under one sender group. */
    private function keysForSender(string $senderGroup): array
    {
        return array_values(array_filter(
            array_keys(SmsSetting::catalogue()),
            fn ($key) => SmsSetting::effectiveSenderGroup($key) === $senderGroup
        ));
    }

    /** Placeholders this message supplies, as they appear in a template. */
    private function tokensFor(string $key): array
    {
        return array_map(
            fn ($token) => '[' . $token . ']',
            (array) (SmsSetting::definition($key)['tokens'] ?? [])
        );
    }

    /** Placeholders used in a template that this message does not supply. */
    private function unknownTokens(string $key, string $template): array
    {
        preg_match_all('/\[[^\]\[]{1,40}\]/', $template, $matches);

        return array_values(array_unique(array_diff($matches[0] ?? [], $this->tokensFor($key))));
    }
}
