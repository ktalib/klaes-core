<?php

namespace App\Support;

/** Missing source-OP details collected before matching; never replaces populated fields. */
class OpMatchDetails
{
    public const LABELS = [
        'transaction_date' => 'Transaction Date',
        'regNo' => 'Reg Particulars',
        'deeds_time' => 'Reg Time',
        'deeds_date' => 'Reg Date',
        'tp_no' => 'TPNo',
    ];

    public static function values(object $row): array
    {
        $first = static function (...$values) {
            foreach ($values as $value) {
                if (trim((string) $value) !== '') return trim((string) $value);
            }
            return '';
        };
        $reg = $first($row->regNo ?? null);
        if ($reg === '' || preg_match('/^0+\s*\/\s*0+\s*\/\s*0+$/', $reg)) {
            $parts = [$row->serialNo ?? '', $row->pageNo ?? '', $row->volumeNo ?? ''];
            $reg = count(array_filter($parts, fn ($v) => preg_match('/^[1-9][0-9]*$/', (string) $v))) === 3
                ? implode('/', $parts) : '';
        }
        return [
            'transaction_date' => $first($row->transaction_date ?? null),
            'regNo' => $reg,
            'deeds_time' => $first($row->deeds_time ?? null, $row->reg_time ?? null),
            'deeds_date' => $first($row->deeds_date ?? null, $row->reg_date ?? null),
            'tp_no' => $first($row->tp_no ?? null),
        ];
    }

    public static function collect(object $row, array $supplied): array
    {
        $values = self::values($row);
        $updates = [];
        $errors = [];
        foreach (self::LABELS as $field => $label) {
            if ($values[$field] !== '') continue;
            $value = is_scalar($supplied[$field] ?? null) ? trim((string) $supplied[$field]) : '';
            $rules = match ($field) {
                'transaction_date', 'deeds_date' => ['required', 'date_format:Y-m-d'],
                'deeds_time' => ['required', 'regex:/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/'],
                'regNo' => ['required', 'max:50', 'regex:/^[1-9][0-9]*\/[1-9][0-9]*\/[1-9][0-9]*$/'],
                default => ['required', 'string', 'max:100'],
            };
            $validator = validator([$field => $value], [$field => $rules], [], [$field => $label]);
            if ($validator->fails()) {
                $errors[$field] = $validator->errors()->first($field);
                continue;
            }
            $updates[$field] = $value;
            if ($field === 'regNo') {
                [$updates['serialNo'], $updates['pageNo'], $updates['volumeNo']] = explode('/', $value);
            }
        }
        return ['values' => $values, 'updates' => $updates, 'errors' => $errors];
    }
}
