<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ValuationCompensation;

class MigrateVfcCompensatedItemsToJson extends Command
{
    protected $signature = 'vfc:migrate-compensated-items
                            {--dry-run : Preview changes without saving}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Convert legacy compensated_items strings to structured JSON format';

    public function handle(): int
    {
        $records = ValuationCompensation::whereNotNull('compensated_items')
            ->where('compensated_items', '!=', '')
            ->get();

        $total     = $records->count();
        $converted = 0;
        $skipped   = 0;
        $dryRun    = $this->option('dry-run');

        if ($total === 0) {
            $this->info('No records with compensated_items found.');
            return 0;
        }

        $this->info("Found {$total} records with compensated_items.");
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm("Convert {$total} records to JSON format?")) {
                $this->line('Aborted.');
                return 0;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $record) {
            $raw = $record->compensated_items;

            // Already valid JSON with buildings key → skip
            $existing = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($existing['buildings'])) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $json = $this->convert($record, $raw);

            if ($dryRun) {
                $this->newLine();
                $this->line("  ID {$record->id} ({$record->owner_name}):");
                $this->line("    OLD: {$raw}");
                $this->line("    NEW: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $record->compensated_items = json_encode($json, JSON_UNESCAPED_UNICODE);
                $record->saveQuietly();
            }

            $converted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Converted: {$converted}  |  Already JSON: {$skipped}  |  Total: {$total}");

        return 0;
    }

    private function convert(ValuationCompensation $record, string $raw): array
    {
        // ── Format A: "Building N: Type [Stage] (L:…, B:…, Area:…, Rate:…) (₦Amount)" ──
        // Produced by a short-lived intermediate JS version before full JSON was adopted.
        if (preg_match('/^Building\s+\d+\s*:/i', $raw)) {
            return $this->convertVerboseBuildingFormat($record, $raw);
        }

        // ── Format B: legacy "[Label: Value], Item (₦amount), …" ──
        return $this->convertLegacyStringFormat($record, $raw);
    }

    private function convertVerboseBuildingFormat(ValuationCompensation $record, string $raw): array
    {
        $buildings = [];
        $items     = [];

        // Match each "Building N: Type [Stage] (L: Xm, B: Ym, Area: Zm², Rate: ₦R/m²) (₦Amount)"
        $buildingPattern = '/Building\s+\d+\s*:\s*([^\[]+)\[([^\]]+)\]\s*\(L:\s*([\d.]+)m,\s*B:\s*([\d.]+)m,\s*Area:\s*([\d.]+)m²,\s*Rate:\s*₦([\d.]+)\/m²\)\s*\(₦([\d,]+)\)/u';
        preg_match_all($buildingPattern, $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $buildings[] = [
                'building_type' => trim($m[1]),
                'stage'         => trim($m[2]),
                'length'        => (float) $m[3],
                'breadth'       => (float) $m[4],
                'area'          => (float) $m[5],
                'rate'          => (float) $m[6],
                'amount'        => (float) str_replace(',', '', $m[7]),
            ];
        }

        // Anything after the last building entry = compensated items
        // Strip all matched building substrings, then parse the remainder
        $remainder = preg_replace($buildingPattern, '', $raw);
        $remainder = preg_replace('/^[\s,]+/', '', $remainder); // trim leading ", "

        foreach (array_map('trim', explode(', ', $remainder)) as $part) {
            if ($part === '') continue;
            $name   = $part;
            $amount = 0.0;
            if (str_contains($part, ' (₦')) {
                [$namePart, $amtPart] = explode(' (₦', $part, 2);
                $name   = trim($namePart);
                $amount = (float) str_replace([')', ','], '', $amtPart);
            }
            if ($name !== '') {
                $items[] = ['name' => $name, 'amount' => $amount];
            }
        }

        // Derive structure_type from the first building type
        $structureType = '';
        if (!empty($buildings)) {
            $first = strtolower($buildings[0]['building_type']);
            if (str_contains($first, 'permanent') && str_contains($first, 'semi')) {
                $structureType = 'Semi-Permanent';
            } elseif (str_contains($first, 'permanent')) {
                $structureType = 'Permanent';
            }
        }

        return ['structure_type' => $structureType, 'buildings' => $buildings, 'items' => $items];
    }

    private function convertLegacyStringFormat(ValuationCompensation $record, string $raw): array
    {
        $structureType = '';
        $items         = [];

        $parts = array_map('trim', explode(', ', $raw));

        foreach ($parts as $part) {
            // [Label: Value] → extract structure_type
            if (str_starts_with($part, '[') && str_ends_with($part, ']')) {
                $inner = substr($part, 1, -1);
                if (str_contains($inner, ': ')) {
                    [, $val] = explode(': ', $inner, 2);
                    if (!$structureType) {
                        $structureType = trim($val);
                    }
                }
                continue;
            }

            $name   = $part;
            $amount = 0.0;
            if (str_contains($part, ' (₦')) {
                [$namePart, $amtPart] = explode(' (₦', $part, 2);
                $name   = trim($namePart);
                $amount = (float) str_replace([')', ','], '', $amtPart);
            }
            if ($name !== '') {
                $items[] = ['name' => $name, 'amount' => $amount];
            }
        }

        // Build one aggregate building entry from the database columns
        $itemsTotal     = array_sum(array_column($items, 'amount'));
        $buildingAmount = max(0.0, (float) $record->compensation_amount - $itemsTotal);

        $buildings = [[
            'building_type' => $record->building_type ?? '',
            'stage'         => $record->completion_stage ?? '',
            'length'        => (float) ($record->length ?? 0),
            'breadth'       => (float) ($record->breadth ?? 0),
            'area'          => (float) ($record->area_covered ?? 0),
            'rate'          => (float) ($record->rate_of_cost ?? 0),
            'amount'        => $buildingAmount,
        ]];

        return ['structure_type' => $structureType, 'buildings' => $buildings, 'items' => $items];
    }
}
