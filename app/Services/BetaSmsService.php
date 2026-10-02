<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * BetaSMS gateway (login.betasms.com).
 *
 * The vendor ships a PHP proxy script that forwards a form POST to their API;
 * this is that same call made directly, so no extra hop is involved.
 *
 * Messages are NOT truncated at 160 characters: the statutory notice texts run
 * past one page on purpose, and cutting them mid-sentence would change their
 * legal meaning. Longer texts simply bill as multiple pages.
 */
class BetaSmsService
{
    /**
     * The gateway URL, and optionally a proxy to reach it through.
     *
     * Both are configurable because the API is plain HTTP on port 80 and some
     * production hosts block outbound :80 while allowing :443 — the same code
     * that sends locally then cannot resolve or connect at all. Rather than
     * hard-code a guess, BETASMS_ENDPOINT lets the working route be set per
     * server, and BETASMS_PROXY covers boxes that only reach the internet
     * through a proxy. `spa:sms-doctor` reports which routes work on the
     * machine it is run on.
     */
    private string $endpoint;
    private ?string $proxy;
    private ?string $pinnedIp;

    public function __construct()
    {
        $this->endpoint = config('services.betasms.endpoint') ?: 'http://login.betasms.com/api/';
        $this->proxy    = config('services.betasms.proxy') ?: null;
        $this->pinnedIp = config('services.betasms.ip') ?: null;
    }

    /**
     * curl options for whichever route this server is configured to use.
     *
     * Kept in one place so send() and the doctor's probes cannot drift: a route
     * the doctor calls "working" has to be the same one send() then takes.
     *
     * Note there is no CURLOPT_FOLLOWLOCATION here, deliberately. The gateway's
     * https:// URL answers a 301 back to http://, so following redirects would
     * make an https route look usable on a box where only :443 is open, while
     * every real send still had to complete over :80.
     *
     * @return array<int,mixed>
     */
    private function routeOptions(): array
    {
        $options = [];

        if ($this->proxy) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }

        if ($this->pinnedIp) {
            $parts = parse_url($this->endpoint);
            $host  = $parts['host'] ?? 'login.betasms.com';
            $port  = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
            // CURLOPT_RESOLVE rather than putting the IP in the URL: the Host
            // header stays correct, which the gateway's vhost needs.
            $options[CURLOPT_RESOLVE] = [$host.':'.$port.':'.$this->pinnedIp];
        }

        return $options;
    }

    /**
     * Send an SMS to a single Nigerian phone number.
     *
     * @param  string  $phone    Raw number (0xx, +234xx, or 234xx)
     * @param  string  $message  Text to send; sent whole, across pages if needed
     * @return bool              True only when the gateway accepts the message
     */
    public function send(string $phone, string $message): bool
    {
        $this->lastCode   = null;
        $this->lastReason = null;

        $username = config('services.betasms.username');
        $password = config('services.betasms.password');
        $sender   = config('services.betasms.sender', 'KLASE');

        if (!$username || !$password) {
            $this->lastReason = 'BETASMS_USERNAME / BETASMS_PASSWORD are not set in this environment';
            Log::warning('BetaSmsService: username or password not configured.');
            return false;
        }

        $mobile = $this->normalizePhone($phone);
        if (!$mobile) {
            $this->lastReason = 'the phone number "'.$phone.'" is not a usable Nigerian mobile number';
            Log::warning('BetaSmsService: invalid phone number', ['phone' => $phone]);
            return false;
        }

        $postData = http_build_query([
            'username' => $username,
            'password' => $password,
            'message'  => $message,
            'mobiles'  => $mobile,
            'sender'   => substr($sender, 0, 11),
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        curl_setopt_array($ch, $this->routeOptions());

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $response === false) {
            // The usual production cause: outbound HTTP to login.betasms.com is
            // blocked by the server's firewall, which never happens on a dev box.
            $this->lastReason = 'could not reach '.$this->endpoint.($curlError ? ' ('.$curlError.')' : '')
                .' — run "php artisan spa:sms-doctor" on this server to find a route that works';
            Log::error('BetaSmsService: request failed', [
                'phone'    => $mobile,
                'endpoint' => $this->endpoint,
                'proxy'    => $this->proxy ? 'set' : 'none',
                'error'    => $curlError,
            ]);
            return false;
        }

        $this->lastCode = trim(explode('|', trim((string) $response))[0]) ?: null;

        $ok = $this->isSuccess($response);
        $this->lastReason = $ok ? null : $this->statusText((string) $response);

        Log::log($ok ? 'info' : 'warning', 'BetaSmsService: send result', [
            'phone'     => $mobile,
            'sender'    => $sender,
            'http_code' => $httpCode,
            'response'  => trim((string) $response),
            'reason'    => $this->statusText((string) $response),
            'success'   => $ok,
            'pages'     => (int) ceil(mb_strlen($message) / 160),
        ]);

        return $ok;
    }

    /**
     * Status codes, established by probing the live endpoint with deliberately
     * bad inputs (the vendor's sample script documents none of them):
     *
     *   1701  accepted for delivery
     *   1702  invalid username or password
     *   1703  invalid mobile number
     *   1704  invalid sender ID
     *   1706  invalid / empty message
     *   1713  message content refused by the gateway's filter
     *
     * 1713 is content-based, not account-based, and gives no hint which word
     * tripped it: "notice" is refused outright, while the same sentence with
     * that one word removed is accepted. Neither statutory notice text contains
     * it, but keep it in mind before editing the wording in SpaNotice.
     *
     * 1701 means the gateway QUEUED the message — not that the handset got it.
     * Delivery can still fail afterwards for account reasons the API does not
     * report here (unapproved sender ID, exhausted credit, DND-blocked number);
     * there is no balance or delivery-report endpoint on this API to check.
     */
    private const STATUS_TEXT = [
        '1701' => 'accepted for delivery',
        '1702' => 'invalid username or password',
        '1703' => 'invalid mobile number',
        // The vendor's page (betasms.com/bulk-sms-api-nigeria) documents 1704
        // and 1706 differently from what probing suggested. Both readings are
        // kept because the vendor is authoritative on intent while the probed
        // behaviour is what we actually observed — if you hit one of these,
        // check the account balance FIRST, it is the cheaper explanation.
        '1704' => 'insufficient credit (vendor) / invalid sender ID (observed)',
        '1705' => 'invalid URL or recipient list too long',
        '1706' => 'gateway internal error (vendor) / invalid or empty message (observed)',
        '1025' => 'insufficient credit',
        '1713' => 'message content refused by the gateway filter',
    ];

    /**
     * Raw status code from the most recent send, for callers that must react to
     * it. Protected rather than private so a test double can override send()
     * and still drive the sendFirstAccepted() walk, which branches on this.
     */
    protected ?string $lastCode = null;

    /**
     * Why the most recent send() failed, in plain language. Set for the failures
     * that never reach the gateway too (missing config, unusable number, host
     * unreachable) — those have no status code, and they are exactly the ones
     * that differ between a dev box and production.
     */
    protected ?string $lastReason = null;

    public const CODE_CONTENT_REFUSED = '1713';

    /** Plain-language reason the last send() failed, or null if it succeeded. */
    public function lastFailureReason(): ?string
    {
        return $this->lastReason;
    }

    /**
     * Status code returned by the last send() on this instance, or null if no
     * request was made (bad config, unusable number). Resolve the service from
     * the container per-call if you intend to read this.
     */
    public function lastStatusCode(): ?string
    {
        return $this->lastCode;
    }

    /**
     * Send the first wording the gateway will accept, and report which one won.
     *
     * BetaSMS refuses messages on content (1713) without naming the offending
     * word, and the vendor documents neither the code nor any transactional
     * route to bypass it. Observed on this account: "your application ... has
     * been received and processing has started" is accepted, while the same
     * sentence shape carrying "approved by the Director" or "assigned File
     * Number ... quote this number" is refused. So callers that must get
     * through supply a rich wording followed by a plainer one.
     *
     * A 1713 means the gateway delivered NOTHING, so falling through cannot
     * double-send. Any other failure stops the walk — a bad number or an empty
     * account will not be fixed by rephrasing, and retrying would only burn
     * credit and time.
     *
     * @param  array<int,string>  $messages  Best wording first.
     * @return string|null  The message that was accepted, or null if none was.
     */
    public function sendFirstAccepted(string $phone, array $messages): ?string
    {
        foreach (array_values(array_filter($messages)) as $i => $message) {
            if ($this->send($phone, $message)) {
                return $message;
            }

            if ($this->lastCode !== self::CODE_CONTENT_REFUSED) {
                return null;
            }

            Log::warning('BetaSmsService: wording refused by the content filter, trying the next one', [
                'phone'     => $this->normalizePhone($phone),
                'variant'   => $i,
                'remaining' => count($messages) - $i - 1,
            ]);
        }

        return null;
    }

    private function isSuccess(string $response): bool
    {
        $body = trim($response);

        if ($body === '') {
            return false;
        }

        $code = trim(explode('|', $body)[0]);

        return $code === '1701' || strcasecmp($body, 'OK') === 0;
    }

    /** Human-readable reason for a response code, for the log. */
    private function statusText(string $response): string
    {
        $code = trim(explode('|', trim($response))[0]);

        return self::STATUS_TEXT[$code] ?? 'unrecognised gateway response';
    }

    /**
     * Normalise to the 234XXXXXXXXXX form the gateway expects.
     */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return $digits;
        }

        // 0803… → 234803…
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234' . substr($digits, 1);
        }

        // 803… (leading zero already stripped somewhere upstream)
        if (strlen($digits) === 10) {
            return '234' . $digits;
        }

        return null;
    }
}
