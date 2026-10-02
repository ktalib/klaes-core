<?php

namespace App\Services;

use App\Http\Controllers\MlsFileNoController;
use App\Models\ChangeOfPurposeApplication;
use App\Support\FileNumberLandUse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commissions a Change of Purpose whose file covers several plots.
 *
 * One source file, N plots, N new file numbers. The source is retired ONCE and
 * every plot becomes its own property under the new purpose, with the source as
 * its parent - the same shape as a subdivision, with a change of land use.
 *
 * The one rule this service exists to honour, the same one DuplexCommitService
 * states: there is only ONE commissioning engine. The numbers are minted by
 * MlsFileNoController::generateBatch(), and the source is retired through
 * PlotWorkflowService::decommissionFiles() - the identical call the subdivision
 * branch of that engine makes. Nothing about lineage, archiving or the registry
 * is re-implemented here.
 *
 * Why not the single Change of Purpose path
 * -----------------------------------------
 * That path renames ONE parcel in place: it retires the file it is given and
 * mints exactly one successor, keeping the prop_id because it is the same piece
 * of land. Calling it N times would try to retire the same file N times over and
 * hand every plot the same prop_id. A multi-plot holding is a fan-out, not N
 * renames, so it goes through the batch engine and the source is retired once.
 */
class ChangeOfPurposeBatchCommitService
{
    /**
     * Commission an approved multi-plot Change of Purpose.
     *
     * @param  array $meta  commissioned_by / commission_date / commission_time /
     *                      customer_type / gender / phone_no, as the modal collects them.
     * @return array{file_no:string,plot_count:int,files:array}
     */
    public function commit(ChangeOfPurposeApplication $record, array $meta = []): array
    {
        if ($record->status !== ChangeOfPurposeApplication::STATUS_APPROVED) {
            throw new \RuntimeException(
                "This application must be approved before it can be commissioned. Current status: {$record->status}"
            );
        }

        $quantity = $record->plotCount();

        if ($quantity < 2) {
            throw new \RuntimeException(
                'This application covers a single plot; commission it through the ordinary Change of Purpose path.'
            );
        }

        $sourceFile = trim((string) $record->file_no);

        if ($sourceFile === '') {
            throw new \RuntimeException('This application names no file to change.');
        }

        $newLandUse = strtoupper(trim((string) ($record->purpose ?? '')));

        if ($newLandUse === '') {
            throw new \RuntimeException("Application {$record->id} has no new land use.");
        }

        // A container prefix belongs to the parcel, not to the purpose: a Change of
        // Purpose on CON-AG-1995-15 produces CON-COM-..., never COM-....
        $prefix = FileNumberLandUse::prefixFor($sourceFile);
        $landUse = $prefix !== '' ? $prefix . '-' . $newLandUse : $newLandUse;

        // The engine requires a number and rejects the call without one. Said here,
        // rather than as a validation error surfacing from three layers down.
        $phone = trim((string) ($record->phone ?: ($meta['phone_no'] ?? '')));

        if ($phone === '') {
            throw new \RuntimeException(
                'This application has no applicant phone number, which commissioning requires.'
            );
        }

        $year = (int) date('Y');
        $commissionedBy = ($meta['commissioned_by'] ?? null)
            ?: (Auth::user()->name ?? Auth::user()->email ?? 'System');

        // Every plot inherits the file's own details: the application captured them
        // once because one file describes the whole holding.
        $entry = [
            'plotNo'      => $record->plot_no,
            'tpNo'        => $record->plan_no,
            'location'    => $record->location,
            'lga'         => $record->lga,
            'district'    => $record->district,
            'file_name'   => $record->applicant_name,
            'phone_no'    => $phone,
            'address'     => $record->residential_address,
            // Left empty on purpose: the batch path mints a fresh unique tracking id
            // per entry, and reuses the grouping record's where one exists.
            'tracking_id' => null,
        ];

        $files = $this->callBatch([
            'batch_mode'              => true,
            'batch_quantity'          => $quantity,
            'application_type'        => 'change_of_purpose',
            'file_option'             => 'normal',
            'land_use'                => $landUse,
            'year'                    => $year,
            'serial_start'            => $this->nextSerial($landUse, $year),
            'file_name'               => $record->applicant_name,
            'phone_no'                => $phone,
            'location_entries'        => array_fill(0, $quantity, $entry),
            // Every plot points back at the file it came out of, which is what the
            // engine writes onto each child's related_fileno.
            'related_files'           => json_encode([[
                'file_no' => $sourceFile,
                'title'   => $record->applicant_name,
                'type'    => 'Change of Purpose',
            ]]),
            'customer_type'           => $meta['customer_type'] ?? 'Individual',
            'gender'                  => $meta['gender'] ?? 'Male',
            'purpose_id'              => $meta['purpose_id'] ?? null,
            'commissioned_by'         => $commissionedBy,
            'commission_date'         => $meta['commission_date'] ?? now()->toDateString(),
            'commission_time'         => $meta['commission_time'] ?? now()->format('H:i'),
            'allocated_by_filter'     => '',
            'default_allocation_type' => null,
        ]);

        if (empty($files)) {
            throw new \RuntimeException('Commissioning returned no file numbers.');
        }

        // The source is retired ONCE, naming every plot it became. Same call, same
        // reason shape and same successor CSV the subdivision branch uses, so the
        // Decommissioned Files list shows all the successors rather than just one.
        $workflow = app(PlotWorkflowService::class);

        if ($workflow->isDecommissioned($sourceFile)) {
            // Already retired by an earlier run: widen its successor list rather than
            // writing a second archive row for a single event.
            $workflow->appendSuccessors($sourceFile, $files);
        } else {
            $workflow->decommissionFiles(
                [$sourceFile],
                'Change of Purpose to ' . $newLandUse . ' over ' . $quantity . ' plots',
                $commissionedBy,
                implode(',', $files),
                false
            );
        }

        $record->update([
            'status'     => ChangeOfPurposeApplication::STATUS_COMMISSIONED,
            'remarks'    => trim(($record->remarks ?? '')
                . "\nCommissioned: {$sourceFile} -> " . implode(', ', $files)
                . ' on ' . now()->toDateTimeString()),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Change of Purpose commissioned over several plots', [
            'application_id' => $record->id,
            'source'         => $sourceFile,
            'plot_count'     => $quantity,
            'files'          => $files,
        ]);

        return [
            'file_no'    => $sourceFile,
            'plot_count' => $quantity,
            'files'      => $files,
        ];
    }

    protected function callBatch(array $payload): array
    {
        $response = app(MlsFileNoController::class)->generateBatch($this->request($payload));
        $data = json_decode($response->getContent(), true) ?: [];

        if (empty($data['success'])) {
            throw new \RuntimeException($data['message'] ?? 'Batch commissioning failed.');
        }

        return array_values((array) ($data['files'] ?? []));
    }

    /** An internal request carrying the caller's session, so Auth::id() still resolves. */
    protected function request(array $payload): Request
    {
        $request = Request::create('/internal/cop-plot-commission', 'POST', $payload);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }

    /** The next free serial in this land use's series, as DuplexCommitService reads it. */
    protected function nextSerial(string $landUse, int $year): int
    {
        $max = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('land_use', $landUse)
            ->where('year', $year)
            ->max('serial_number');

        return ((int) $max) + 1;
    }
}
