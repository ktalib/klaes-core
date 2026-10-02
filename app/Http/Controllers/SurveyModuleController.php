<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SurveyModuleController extends Controller
{
    public function dashboard()
    {
        return view('survey_module.dashboard');
    }

    public function compensationDashboard()
    {
        return view('survey_module.compensation.dashboard');
    }

    public function compensationProjects()
    {
        return view('survey_module.compensation.projects');
    }

    public function compensationProjectRegister()
    {
        return view('survey_module.compensation.project_register');
    }

    public function compensationCases()
    {
        return view('survey_module.compensation.cases');
    }

    public function compensationCaseRegister()
    {
        return view('survey_module.compensation.case_register');
    }

    public function compensationBeneficiaries()
    {
        return view('survey_module.compensation.beneficiaries');
    }

    public function compensationTrees()
    {
        return view('survey_module.compensation.trees');
    }

    public function compensationCalculator()
    {
        return view('survey_module.compensation.calculator');
    }

    public function compensationLand()
    {
        return view('survey_module.compensation.land');
    }

    public function compensationOp()
    {
        return view('survey_module.compensation.op');
    }

    public function compensationReports()
    {
        return view('survey_module.compensation.reports');
    }

    public function gknDashboard()
    {
        return view('survey_module.gkn.dashboard');
    }

    public function gknLands()
    {
        return view('survey_module.gkn.lands');
    }

    public function gknRegister()
    {
        return view('survey_module.gkn.register');
    }

    public function gknTracking()
    {
        return view('survey_module.gkn.tracking');
    }

    public function gknReports()
    {
        return view('survey_module.gkn.reports');
    }

    public function recordsMisc()
    {
        return view('survey_module.records.misc');
    }

    public function recordsLpkn()
    {
        return view('survey_module.records.lpkn');
    }

    public function workflowExamination()
    {
        return view('survey_module.workflow.examination');
    }

    public function workflowOccupancy()
    {
        return view('survey_module.workflow.occupancy');
    }

    public function toolsGis()
    {
        return view('survey_module.tools.gis');
    }

    public function toolsPlotAllocation()
    {
        return view('survey_module.tools.plot_allocation');
    }

    public function reports()
    {
        return view('survey_module.reports');
    }
}