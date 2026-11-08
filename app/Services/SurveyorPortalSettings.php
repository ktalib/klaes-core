<?php

namespace App\Services;

use App\Models\Setting;
use App\Rules\NigerianPhone;
use Illuminate\Support\Facades\DB;

class SurveyorPortalSettings
{
    public const DEFAULT_LOGO_PATHS = [
        'wordmark' => 'assets/logo/knssgof-wordmark.png',
        'seal' => 'assets/logo/knssgof-logo.png',
        'registration_banner' => 'assets/logo/knssgof-register-banner.png',
        'favicon' => 'assets/logo/knssgof-logo.png',
    ];

    public const DEFAULTS = [
        'brand_name' => 'KNSSGOF',
        'portal_name' => 'KNSSGOF Portal',
        'office_name' => "Kano State Surveyor General's Office",
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
        'private_example' => 'A0001B',
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
        $stored = Setting::query()
            ->where('parent_id', 1)
            ->whereIn('name', $names)
            ->get(['name', 'value'])
            ->pluck('value', 'name');

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

        return $this->cachedValues = $values;
    }

    public function save(array $values): void
    {
        $defaults = $this->defaults();
        DB::transaction(function () use ($values, $defaults) {
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, $defaults)) {
                    continue;
                }

                $setting = Setting::query()->firstOrNew([
                    'parent_id' => 1,
                    'name' => $this->settingName($key),
                ]);
                $setting->value = is_array($value)
                    ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                    : (string) $value;
                if (!$setting->exists) {
                    $setting->type = 'system';
                }
                $setting->save();
            }
        });
        $this->cachedValues = null;
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
