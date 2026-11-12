<?php

namespace App\Services;

use App\Models\Setting;
use App\Rules\NigerianPhone;
use Illuminate\Support\Facades\DB;

class SurveyorPortalSettings
{
    public const DEFAULT_LOGO_PATHS = [
        // MLPP-SG artwork (user, 2026-11-10); the KNSSGOF files stay on disk, unused.
        'wordmark' => 'assets/logo/mlppsg-wordmark.jpg',
        'seal' => 'assets/logo/mlppsg-logo.png',
        'registration_banner' => 'assets/logo/mlppsg-register-banner.jpg',
        'favicon' => 'assets/logo/mlppsg-favicon.png',
    ];

    public const DEFAULTS = [
        // Renamed from KNSSGOF (user, 2026-11-10): "MLPP- Surveyor General Portal", acronym "MLPP-SG portal".
        'brand_name' => 'MLPP-SG',
        'portal_name' => 'MLPP - Surveyor General Portal',
        'office_name' => "Ministry of Land and Physical Planning – Surveyor General's Office",   // wording image, 2026-11-10
        // Survey Update 5j (user, 2026-11-10): shown on the portal's pages.
        'ministry_line' => 'Under the Kano State Ministry of Land and Physical Planning',
        'ministry_address' => 'No. 2 Dr Bala Mohammed Road, Kano State, Nigeria',
        'primary_color' => '#0B3A7E',
        'primary_dark_color' => '#082B5E',
        'green_color' => '#15803D',
        'gold_color' => '#C99A1E',
        'support_email' => '',
        'support_phone' => '',
        'beacon_request_sms_phone' => '+234 803 073 3666',
        'how_title' => 'How it works',
        'how_subtitle' => 'Four steps from registration to a printed slip.',
        'how_steps' => [
            [
                'title' => 'Register',
                'text' => 'Confirm your company is registered, then give your name, phone, email and SURCON number. You get a Registration ID.',
            ],
            [
                'title' => 'Get approved',
                'text' => "The Surveyor General's office checks your details and activates your account with a Surveyor ID. You can sign in from then on.",
            ],
            [
                'title' => 'Request beacons',
                'text' => 'Say how many beacons you need and where the survey is: plot, district and LGA.',
            ],
            [
                'title' => 'Accept and print',
                'text' => 'The SG issues the next available numbers. Accept them in the portal and print your Beacon Issue Slip.',
            ],
        ],
        'private_title' => 'Private surveyors',
        'private_description' => "For surveys by a registered company. Numbers carry your company's letters and continue its own series.",
        'private_example' => 'B0001',
        /*
         | Flash banners (CR 11112026-2, item 11): NIS and SURCON notices rotating at the top of the portal's
         | landing page and signed-in pages. Edited in Configurable Entries > Surveyor Portal. The defaults are
         | general reminders only; the SG's office replaces them with the bodies' current notices and links.
         */
        'flash_banners_on' => '1',
        'flash_banners' => [
            [
                'org' => 'SURCON',
                'title' => 'Surveyors Council of Nigeria (SURCON)',
                'text' => 'Only surveyors registered with SURCON may sign and submit survey plans. Keep your SURCON registration and annual practising licence current.',
                'link' => '',
            ],
            [
                'org' => 'NIS',
                'title' => 'Nigerian Institution of Surveyors (NIS)',
                'text' => 'Keep your NIS membership and continuing professional development (CPD) up to date.',
                'link' => '',
            ],
            // Third notice (user, 2026-11-12: three logos, three flashes); shows the second SURCON logo.
            [
                'org' => 'SURCON',
                'title' => 'SURCON: Seal and Signature',
                'text' => 'Every survey plan must carry the SURCON seal and signature of the registered surveyor who made it.',
                'link' => '',
            ],
        ],
        'ready_title' => 'Have these ready',
        'ready_items' => [
            'Your SURCON registration number',
            'A mobile number you can be reached on',
            'An email address',
            "Your company's name, if private",
        ],
    ];

    private ?array $cachedValues = null;

    public function values(): array
    {
        if ($this->cachedValues !== null) {
            return $this->cachedValues;
        }

        $defaults = $this->defaults();
        $names = array_map(fn (string $key) => $this->settingName($key), array_keys($defaults));
        // SQL Server since 2026-11-11 (user: configurable entries belong there, not in MySQL).
        $stored = $this->installed()
            ? DB::connection('sqlsrv')->table(self::TABLE)->whereIn('key', $names)->pluck('value', 'key')
            : collect();

        $values = $defaults;
        foreach (array_keys($defaults) as $key) {
            $settingName = $this->settingName($key);
            if ($stored->has($settingName)) {
                $storedValue = (string) $stored->get($settingName);
                if (is_array($defaults[$key])) {
                    $decoded = json_decode($storedValue, true);
                    if (is_array($decoded)) {
                        $values[$key] = $decoded;
                    }
                } else {
                    $values[$key] = $storedValue;
                }
            }
        }

        $beaconSmsPhone = trim((string) $values['beacon_request_sms_phone']);
        if (!(new NigerianPhone())->passes('beacon_request_sms_phone', NigerianPhone::normalize($beaconSmsPhone))) {
            $values['beacon_request_sms_phone'] = self::DEFAULTS['beacon_request_sms_phone'];
        } else {
            $values['beacon_request_sms_phone'] = $beaconSmsPhone;
        }

        foreach (['primary_color', 'primary_dark_color', 'green_color', 'gold_color'] as $key) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $values[$key])) {
                $values[$key] = self::DEFAULTS[$key];
            }
        }

        foreach (self::DEFAULT_LOGO_PATHS as $logo => $defaultPath) {
            $key = 'logo_' . $logo;
            if (!$this->isPortalLogoPath($values[$key]) || !is_file(public_path($values[$key]))) {
                $values[$key] = $defaultPath;
            }
        }

        if (!$this->hasValidSteps($values['how_steps'])) {
            $values['how_steps'] = $defaults['how_steps'];
        }
        if (!is_array($values['ready_items'])
            || count($values['ready_items']) !== 4
            || count(array_filter($values['ready_items'], 'is_string')) !== 4) {
            $values['ready_items'] = $defaults['ready_items'];
        }

        $values['flash_banners'] = $this->cleanBanners($values['flash_banners']);

        return $this->cachedValues = $values;
    }

    public function save(array $values): void
    {
        $defaults = $this->defaults();
        $db = DB::connection('sqlsrv');
        $db->transaction(function () use ($db, $values, $defaults) {
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $defaults)) {
                    continue;
                }

                $name = $this->settingName($key);
                $text = is_array($value)
                    ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : (string) $value;
                $row = ['value' => $text, 'updated_by' => auth()->id(), 'updated_at' => now()];
                if ($db->table(self::TABLE)->where('key', $name)->exists()) {
                    $db->table(self::TABLE)->where('key', $name)->update($row);
                } else {
                    $db->table(self::TABLE)->insert($row + ['key' => $name, 'created_at' => now()]);
                }
            }
        });
        $this->cachedValues = null;
    }

    public const TABLE = 'surveyor_portal_settings';

    private function installed(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable(self::TABLE);
    }

    private function settingName(string $key): string
    {
        return 'surveyor_portal_' . $key;
    }

    private function isPortalLogoPath(string $path): bool
    {
        return in_array($path, self::DEFAULT_LOGO_PATHS, true)
            || preg_match('#^assets/logo/surveyor-portal/[A-Za-z0-9._-]+$#', $path) === 1;
    }

    private function defaults(): array
    {
        $defaults = self::DEFAULTS;
        foreach (self::DEFAULT_LOGO_PATHS as $key => $path) {
            $defaults['logo_' . $key] = $path;
        }

        return $defaults;
    }

    /** Banner bodies the portal shows a badge for. */
    public const BANNER_ORGS = ['SURCON', 'NIS', 'MLPP-SG'];

    /** Only well-formed banners: a known body, a title, text, and an http(s) link or none. */
    public function cleanBanners($banners): array
    {
        if (!is_array($banners)) {
            return [];
        }
        $out = [];
        foreach ($banners as $b) {
            if (!is_array($b)) continue;
            $org = (string) ($b['org'] ?? '');
            $title = trim((string) ($b['title'] ?? ''));
            $text = trim((string) ($b['text'] ?? ''));
            $link = trim((string) ($b['link'] ?? ''));
            if (!in_array($org, self::BANNER_ORGS, true) || $title === '') continue;
            if ($link !== '' && !preg_match('#^https?://#i', $link)) $link = '';
            $out[] = ['org' => $org, 'title' => mb_substr($title, 0, 120), 'text' => mb_substr($text, 0, 300), 'link' => mb_substr($link, 0, 255)];
        }

        return array_slice($out, 0, 10);
    }

    private function hasValidSteps($steps): bool
    {
        if (!is_array($steps) || count($steps) !== 4) {
            return false;
        }

        foreach ($steps as $step) {
            if (!is_array($step)
                || !isset($step['title'], $step['text'])
                || !is_string($step['title'])
                || !is_string($step['text'])) {
                return false;
            }
        }

        return true;
    }
}
