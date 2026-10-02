<?php
//!Survey Department Controller

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OtherDepartmentsController extends Controller
{
    private function getApplication($id)
    {
        // Modified to join subapplications with mother_applications to get primary application details
        $application = DB::connection('sqlsrv')->table('subapplications')
            ->select(
                'subapplications.*', 
                'subapplications.id as applicationID', // Add alias for applicationID
                'subapplications.main_application_id as main_application_id', // Add main_application_id if it exists
                'mother_applications.fileno as primary_fileno',
                'mother_applications.fileno as primary_applicant_type',
                'mother_applications.first_name as primary_first_name',
                'mother_applications.surname as primary_surname',
                'mother_applications.applicant_title as primary_applicant_title',
                'mother_applications.application_status as primary_application_status',
                'mother_applications.land_use as primary_land_use',
                'mother_applications.id as main_application_id',

                // Property fields with proper aliases
                'mother_applications.property_house_no as property_house_no',
                'mother_applications.property_plot_no as property_plot_no',
                'mother_applications.property_street_name as property_street_name',
                'mother_applications.property_lga as property_lga',
                 
                'mother_applications.applicationID as primary_applicationID' // Get primary app's applicationID
            )
            ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
            ->where('subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Sub application not found'], 404);
        }

        return $application;
    }

    private function getSecondaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
                'dbo.subapplications.*',
                'dbo.subapplications.id as id',
                'dbo.mother_applications.fileno as primary_fileno', 
                'dbo.mother_applications.fileno as primary_applicant_type',  
                'dbo.mother_applications.passport as mother_passport',
                'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
                'dbo.mother_applications.applicant_title as primary_applicant_title',  
                'dbo.mother_applications.first_name as primary_first_name',  
                'dbo.mother_applications.surname as primary_surname',  
                'dbo.mother_applications.corporate_name as mother_corporate_name',
                'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
                'dbo.mother_applications.land_use',
                'dbo.mother_applications.property_house_no',
                'dbo.mother_applications.property_plot_no',
                'dbo.mother_applications.property_street_name',
                'dbo.mother_applications.property_district',
                'dbo.mother_applications.property_lga',
                'dbo.mother_applications.id as main_application_id'  
            )
            ->where('dbo.subapplications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Application not found'], 404);
        }

        return $application;
    }

    private function getPrimaryApplication($id)
    {
        $application = DB::connection('sqlsrv')->table('mother_applications')
            ->select(
                'mother_applications.*',
                'mother_applications.id as main_application_id',
                'mother_applications.id as applicationID'
            )
            ->where('mother_applications.id', $id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'Primary application not found'], 404);
        }

        return $application;
    }

    public function Survey_Primary(Request $request)
    {
        $PageTitle = 'Other Departments';
        $PageDescription = '';
        
        if ($request->has('id')) {
            $application = $this->getApplication($request->get('id'));
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            return view('other_departments.survey', compact('application', 'PageTitle', 'PageDescription'));
        }
        
        $PrimaryApplications = DB::connection('sqlsrv')->table('dbo.mother_applications')->get();
        return view('other_departments.primary_survey', compact('PrimaryApplications', 'PageTitle', 'PageDescription'));
    }

    public function Survey_Secondary(Request $request)
    {
        $PageTitle = 'Other Departments';
        $PageDescription = '';
        if ($request->has('id')) {
            $application = $this->getSecondaryApplication($request->get('id'));
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }
            
            return view('other_departments.secondary_survey_view', compact('application', 'PageTitle', 'PageDescription'));
        }

        $SecondaryApplications = DB::connection('sqlsrv')->table('dbo.subapplications')
            ->leftJoin('dbo.mother_applications', 'dbo.subapplications.main_application_id', '=', 'dbo.mother_applications.id')
            ->select(
            'dbo.subapplications.fileno', 
            'dbo.subapplications.applicant_type',
            'dbo.subapplications.scheme_no',
            'dbo.subapplications.id',
            'dbo.subapplications.main_application_id',
            'dbo.subapplications.applicant_title',
            'dbo.subapplications.first_name',
            'dbo.subapplications.surname',
            'dbo.subapplications.corporate_name',
            'dbo.subapplications.multiple_owners_names',
            'dbo.subapplications.phone_number',
            'dbo.subapplications.planning_recommendation_status',
            'dbo.subapplications.application_status',
            'dbo.subapplications.created_at',
            'dbo.subapplications.unit_number',
            'dbo.subapplications.main_id',

            'dbo.subapplications.passport',
            'dbo.subapplications.multiple_owners_passport',
            'dbo.mother_applications.fileno as mother_fileno', 
            'dbo.mother_applications.id as mother_id',
             'dbo.mother_applications.passport as mother_passport',
            'dbo.mother_applications.multiple_owners_passport as mother_multiple_owners_passport',
            'dbo.mother_applications.applicant_title as mother_applicant_title',
            'dbo.mother_applications.first_name as mother_first_name',
            'dbo.mother_applications.surname as mother_surname',
            'dbo.mother_applications.corporate_name as mother_corporate_name',
            'dbo.mother_applications.multiple_owners_names as mother_multiple_owners_names',
            'dbo.mother_applications.land_use',
            'dbo.mother_applications.property_house_no',
            'dbo.mother_applications.property_plot_no',
            'dbo.mother_applications.property_street_name',
            'dbo.mother_applications.property_district',
            'dbo.mother_applications.property_lga'
            )
            ->get();
         
        return view('other_departments.secondary_survey', compact('SecondaryApplications', 'PageTitle', 'PageDescription')); 
    }

    public function Survey($d)
    {
        $PageTitle = 'Other Departments';
        $PageDescription = '';
        
        $application = $this->getPrimaryApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('other_departments.survey', compact('application', 'PageTitle', 'PageDescription'));
    }   
    
    public function SecondarySurveyView($d)
    {
        $PageTitle = 'SECTIONAL TITLING  SURVEY';
        $PageDescription = 'processing of sectional title survey applications for secondary applications';
        
        $application = $this->getSecondaryApplication($d);
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        return view('other_departments.secondary_survey_view', compact('application', 'PageTitle', 'PageDescription'));
    }
}