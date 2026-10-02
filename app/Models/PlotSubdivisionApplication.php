<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotSubdivisionApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_subdivision_applications';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_COMMISSIONED = 'commissioned';

    protected $fillable = [
        'source_module',
        'file_no',
        'file_title',
        'applicant_name',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'residential_address',
        'correspondence_address',
        'nationality',
        'occupation',
        'land_use',
        'num_plots',
        'status',
        'remarks',
        'captured_by',
        'updated_by',
        'knupda_status',
        'knupda_fee',
        'land_value',
        'knupda_remarks',
        'application_generated_at',
        'recommendation_generated_at',
        'site_plan',
        'ownership_document',
        'application_letter',
        'means_of_id',
        'tax_clearance',
        'commissioned_count',
        'commissioned_batches',
        'commissioning_completed_at',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * Plots already minted for this application across every commissioning run.
     */
    public function commissionedCount(): int
    {
        return max((int) ($this->commissioned_count ?? 0), 0);
    }

    /**
     * Plots still to be commissioned. The generator's batch mode caps at 200 files
     * per run, so a 500-plot subdivision is worked off in chunks (200 + 200 + 100)
     * and this is what each run has left to take from.
     */
    public function remainingPlots(): int
    {
        $planned = max((int) ($this->num_plots ?? 0), 0);

        return max($planned - $this->commissionedCount(), 0);
    }

    public function isCommissioningComplete(): bool
    {
        return $this->num_plots !== null && $this->remainingPlots() === 0;
    }

    /**
     * Chunks already run, newest last: {batch, quantity, first_file, last_file, files, at, by}.
     */
    public function commissionedBatches(): array
    {
        $decoded = json_decode((string) ($this->commissioned_batches ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Every fragment file number minted so far, across all chunks.
     */
    public function commissionedFileNumbers(): array
    {
        $files = [];
        foreach ($this->commissionedBatches() as $batch) {
            foreach ((array) ($batch['files'] ?? []) as $file) {
                if ($file !== null && $file !== '') {
                    $files[] = $file;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Book one commissioning run against this application.
     *
     * Status only flips to 'commissioned' when the last plot is minted — until then
     * the application stays 'approved' so the generator's find-by-file lookup keeps
     * returning it and the next chunk can be run. Remarks accumulate one line per
     * chunk instead of the last chunk overwriting the earlier ones.
     */
    public function recordCommissionedBatch(array $fileNumbers, ?string $by = null): array
    {
        $fileNumbers = array_values(array_filter($fileNumbers, fn ($f) => $f !== null && $f !== ''));
        if (empty($fileNumbers)) {
            return $this->commissionedBatches();
        }

        $batches = $this->commissionedBatches();
        $batchNo = count($batches) + 1;
        $at      = now();

        $batches[] = [
            'batch'      => $batchNo,
            'quantity'   => count($fileNumbers),
            'first_file' => $fileNumbers[0],
            'last_file'  => $fileNumbers[count($fileNumbers) - 1],
            'files'      => $fileNumbers,
            'at'         => $at->toDateTimeString(),
            'by'         => $by,
        ];

        $count     = $this->commissionedCount() + count($fileNumbers);
        $planned   = max((int) ($this->num_plots ?? 0), 0);
        $complete  = $planned > 0 && $count >= $planned;

        $line = $planned > 0
            ? "Batch {$batchNo}: {$fileNumbers[0]} ... {$fileNumbers[count($fileNumbers) - 1]} (" . count($fileNumbers) . " files, {$count}/{$planned}) on {$at->toDateTimeString()}"
            : "Batch {$batchNo}: " . count($fileNumbers) . " files on {$at->toDateTimeString()}";

        $this->update([
            'status'                    => $complete ? self::STATUS_COMMISSIONED : self::STATUS_APPROVED,
            'commissioned_count'        => $count,
            'commissioned_batches'      => json_encode($batches),
            'commissioning_completed_at' => $complete ? $at : null,
            'remarks'                   => trim(($this->remarks ? $this->remarks . PHP_EOL : '') . $line),
            'updated_by'                => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return $batches;
    }

    public function plotSizes()
    {
        return $this->hasMany(PlotApplicationSize::class, 'application_id')->where('application_type', 'subdivision');
    }
}
