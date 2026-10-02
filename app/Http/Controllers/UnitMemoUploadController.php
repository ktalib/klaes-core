<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class UnitMemoUploadController extends Controller
{
    public function create($unitId)
    {
        $unitApplication = DB::connection('sqlsrv')->table('subapplications')
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $unitId)
            ->select(
                'subapplications.id',
                'subapplications.fileno',
                'subapplications.scheme_no',
                'subapplications.main_application_id',
                'subapplications.applicant_title',
                'subapplications.applicant_type',
                'subapplications.first_name',
                'subapplications.surname',
                'subapplications.corporate_name',
                'subapplications.multiple_owners_names',
                'subapplications.block_number',
                'subapplications.floor_number',
                'subapplications.unit_number',
                'mother_applications.fileno as primary_fileno',
                'mother_applications.np_fileno as primary_np_fileno',
                'mother_applications.land_use',
                'mother_applications.property_lga'
            )
            ->first();

        if (!$unitApplication) {
            return redirect()->route('programmes.unit_scheme_memo')->with('error', 'Unit application not found.');
        }

        $ownership = $this->resolveOwnerData($unitApplication);
        $unitApplication->owner_name = $ownership['display'] ?? 'N/A';
        $unitApplication->owner_names_list = $ownership['names'];

        $primaryMemoExists = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $unitApplication->main_application_id)
            ->where('memo_type', 'primary')
            ->exists();

        $storageInitialized = Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads');

        $existingUpload = null;
        if ($storageInitialized) {
            $existingUpload = DB::connection('sqlsrv')->table('unit_st_memo_uploads')
                ->where('unit_application_id', $unitId)
                ->orderBy('uploaded_at', 'desc')
                ->first();
        }

        $existingUploadUser = null;
        if ($existingUpload && $storageInitialized) {
            $existingUploadUser = User::find($existingUpload->uploaded_by);
        }

        $PageTitle = 'Upload Unit ST Memo';
        $PageDescription = 'Upload and manage the sectional titling memo document for this unit.';
        $canUpload = $storageInitialized && $primaryMemoExists && !$existingUpload;

        return view('programmes.unit_memo_upload', compact(
            'unitApplication',
            'existingUpload',
            'existingUploadUser',
            'primaryMemoExists',
            'storageInitialized',
            'canUpload',
            'PageTitle',
            'PageDescription'
        ));
    }

    public function store(Request $request, $unitId)
    {
        $request->validate([
            'memo_file' => 'required|file|mimes:pdf,jpg,jpeg,png,bmp,gif,tif,tiff|max:10240',
        ]);

        $unitApplication = DB::connection('sqlsrv')->table('subapplications')
            ->where('id', $unitId)
            ->first();

        if (!$unitApplication) {
            return redirect()->route('programmes.unit_scheme_memo')->with('error', 'Unit application not found.');
        }

        $primaryMemoExists = DB::connection('sqlsrv')->table('memos')
            ->where('application_id', $unitApplication->main_application_id)
            ->where('memo_type', 'primary')
            ->exists();

        if (!$primaryMemoExists) {
            return redirect()->back()->withInput()->with('error', 'Generate the primary ST memo before uploading a unit memo.');
        }

        if (!Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
            return redirect()->back()->with('error', 'Unit memo upload storage is not initialized. Please run the latest database migrations.');
        }

        $alreadyUploaded = DB::connection('sqlsrv')->table('unit_st_memo_uploads')
            ->where('unit_application_id', $unitId)
            ->exists();

        if ($alreadyUploaded) {
            return redirect()->back()->with('error', 'A memo has already been uploaded for this unit.');
        }

        try {
            $file = $request->file('memo_file');
            $extension = $file->getClientOriginalExtension();
            $fileName = 'unit_memo_' . $unitId . '_' . time() . '.' . $extension;
            $filePath = $file->storeAs('unit_memos', $fileName, 'public');

            DB::connection('sqlsrv')->table('unit_st_memo_uploads')->insert([
                'unit_application_id' => $unitId,
                'file_path' => $filePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('programmes.unit_scheme_memo')->with('success', 'ST memo uploaded successfully.');
        } catch (\Throwable $exception) {
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return redirect()->back()->withInput()->with('error', 'Failed to upload memo: ' . $exception->getMessage());
        }
    }

    private function resolveOwnerData($record): array
    {
        $applicantType = strtolower(trim($record->applicant_type ?? ''));

        $names = $this->parseOwnerNameEntries($record->multiple_owners_names ?? null);

        if (empty($names)) {
            $corporate = trim((string) ($record->corporate_name ?? ''));
            if ($corporate !== '' && (Str::contains($applicantType, 'corporate') || $applicantType === '')) {
                $names[] = $corporate;
            }
        }

        if (empty($names)) {
            $personal = trim(implode(' ', array_filter([
                $record->applicant_title ?? null,
                $record->first_name ?? null,
                $record->surname ?? null,
            ])));

            if ($personal !== '') {
                $names[] = $personal;
            }
        }

        if (!empty($names)) {
            $names = array_values(array_unique($names));
        }

        return [
            'names' => $names,
            'display' => count($names) ? implode(', ', $names) : null,
        ];
    }

    private function parseOwnerNameEntries($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $decoded = preg_split('/[\r\n,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (!is_array($decoded)) {
            return [];
        }

        $names = [];
        foreach ($decoded as $entry) {
            if (is_string($entry)) {
                $name = trim($entry);
                if ($name !== '') {
                    $names[] = $name;
                }
                continue;
            }

            if (is_array($entry)) {
                foreach (['full_name', 'fullName', 'name', 'owner_name'] as $key) {
                    if (!empty($entry[$key])) {
                        $name = trim((string) $entry[$key]);
                        if ($name !== '') {
                            $names[] = $name;
                        }
                        break;
                    }
                }
                continue;
            }

            if (is_object($entry)) {
                foreach (['full_name', 'fullName', 'name', 'owner_name'] as $key) {
                    if (!empty($entry->{$key})) {
                        $name = trim((string) $entry->{$key});
                        if ($name !== '') {
                            $names[] = $name;
                        }
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }
}
