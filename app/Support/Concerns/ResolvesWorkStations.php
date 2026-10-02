<?php

namespace App\Support\Concerns;

use Illuminate\Validation\Rule;

trait ResolvesWorkStations
{
    protected function workStationOptions(): array
    {
        return collect($this->workStationDefinitions())
            ->pluck('label', 'label')
            ->toArray();
    }

    protected function workStationValidationRule(bool $required = true): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string'];
        $options = array_keys($this->workStationOptions());

        if (!empty($options)) {
            $rules[] = Rule::in($options);
        }

        return $rules;
    }

    protected function workStationDefinitions(): array
    {
        $raw = config('workstations.options', []);

        return collect($raw)
            ->map(function ($option) {
                if (is_array($option)) {
                    return [
                        'label' => trim((string) ($option['label'] ?? $option['value'] ?? '')),
                        'module_key' => $option['module_key'] ?? $option['module'] ?? null,
                    ];
                }

                return [
                    'label' => trim((string) $option),
                    'module_key' => null,
                ];
            })
            ->filter(fn ($option) => $option['label'] !== '')
            ->values()
            ->all();
    }
}
