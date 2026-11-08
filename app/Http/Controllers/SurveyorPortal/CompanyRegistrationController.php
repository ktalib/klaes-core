<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Survey\SurveyCompanyRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CompanyRegistrationController extends Controller
{
    public function create()
    {
        if (! Schema::connection('sqlsrv')->hasColumn('survey_companies', 'status')) {
            abort(503, 'Company registration is temporarily unavailable while the company registry is being set up.');
        }

        return view('surveyor_portal.auth.company_register');
    }

    public function store(Request $request, SurveyCompanyRegistry $registry)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'company_rc_no' => 'required|string|max:50',
            'phone' => 'required|string|max:30',
            'email' => 'required|email|max:150',
            'addr_district' => 'nullable|string|max:255',
        ] + CadastralAddress::rules('addr_'), CadastralAddress::messages('addr_') + [
            'name.required' => 'Enter the registered company name.',
            'company_rc_no.required' => 'Enter the company RC number.',
        ]);
        if (! CadastralAddress::lgaBelongsToState($data['addr_lga'] ?? null, $data['addr_state'] ?? null)) {
            throw ValidationException::withMessages([
                'addr_lga' => 'Choose an LGA that belongs to the selected state.',
            ]);
        }

        $name = preg_replace('/\s+/', ' ', trim($data['name']));
        $rc = mb_strtoupper(preg_replace('/\s+/', ' ', trim($data['company_rc_no'])));
        if (! preg_match('/\d/', $rc)) {
            throw ValidationException::withMessages(['company_rc_no' => 'Enter a valid RC number, e.g. RC 123456.']);
        }

        $address = array_intersect_key(CadastralAddress::normalise($data, 'addr_'), array_flip([
            'addr_house', 'addr_plot', 'addr_street', 'addr_street_other', 'addr_district', 'addr_district_other', 'addr_lga', 'addr_state',
        ]));

        $registry->register($address + [
            'name' => $name,
            'company_rc_no' => $rc,
            'phone' => trim($data['phone']),
            'email' => mb_strtolower(trim($data['email'])),
        ]);

        return redirect()->route('surveyor-portal.company.register')
            ->with('status', 'Company registration submitted. The Office of the Surveyor General will review it before it appears in the Private company list.');
    }
}
