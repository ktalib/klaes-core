<?php

namespace App\Http\Controllers;

use App\Models\ApplicationMother;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FileIndexCreatePageController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $PageTitle = 'Create File Index';
            $PageDescription = 'Create a new file index record';

            $availableApplications = $this->getAvailableApplications();

            // Fetch dynamic registries
            $registries = \App\Models\Registry::orderBy('name')->get();
            $lgas = \App\Models\Lga::orderBy('name')->pluck('name');
            $districts = \App\Models\District::orderBy('name')->pluck('name');
            $physicalRegistries = \App\Models\PhysicalRegistry::orderBy('name')->get();
            $landUseTypes = \App\Models\LandUseType::orderBy('name')->get()->pluck('name', 'name');
            $streetNames = \App\Models\StreetName::orderBy('name')->get();

            $source = strtolower((string) $request->query('url'));
            $backButton = null;
            $isNewKnMode = $source === 'new_kn';
            $prefillFileNumber = (string) $request->query('file_number', '');
            $prefillTrackingId = (string) $request->query('tracking_id', '');
            $returnTo = (string) $request->query('return_to', '');

            if ($source === 'st') {
                $backButton = [
                    'label' => 'Back to ST Shadow File Commissioning',
                    'route' => route('st-file-no-matching.index'),
                ];
            } elseif ($source === 'lands') {
                $backButton = [
                    'label' => 'Back to Lands Shadow File Commissioning',
                    'route' => route('lands-file-no-matching.index'),
                ];
            } elseif ($source === 'sltr') {
                $backButton = [
                    'label' => 'Back to SLTR Shadow File Commissioning',
                    'route' => route('sltr-file-no-matching.index'),
                ];
            } elseif ($isNewKnMode) {
                $backButton = [
                    'label' => 'Back to KANGIS New Files',
                    'route' => url('/create-file-tracker?url=new_kangis'),
                ];
            }

            return view('fileindexing.addons.create_indexing', compact('PageTitle', 'PageDescription', 'availableApplications', 'registries', 'lgas', 'districts', 'physicalRegistries', 'landUseTypes', 'streetNames', 'backButton', 'isNewKnMode', 'prefillFileNumber', 'prefillTrackingId', 'returnTo'));
        } catch (Exception $e) {
            Log::error('Error loading file indexing standalone create page', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('fileindexing.index')
                ->with('error', 'Error loading create form: ' . $e->getMessage());
        }
    }

    private function getAvailableApplications()
    {
        return ApplicationMother::on('sqlsrv')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('file_indexings')
                    ->whereRaw('file_indexings.main_application_id = mother_applications.id');
            })
            ->select('id', 'fileno', 'np_fileno', 'first_name', 'middle_name', 'surname', 'corporate_name', 'applicant_type')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
}
