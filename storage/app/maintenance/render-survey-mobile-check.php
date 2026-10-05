<?php
chdir(dirname(__DIR__, 3));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array']);
$session = app('session')->driver('array');
$session->start();
$request = Illuminate\Http\Request::create('/survey-module/mobile/register');
$request->setLaravelSession($session);
$app->instance('request', $request);
Illuminate\Support\Facades\Auth::setUser((new App\Models\User())->forceFill(['id'=>1,'name'=>'Preview Officer','email'=>'preview@example.test']));
$projects = collect([
    (new App\Models\Survey\SurveyProject())->forceFill(['id'=>1,'name'=>'Kwali Highway Right-of-Way','project_code'=>'TEST-1','scheme_type'=>'land','purpose'=>'Housing']),
    (new App\Models\Survey\SurveyProject())->forceFill(['id'=>2,'name'=>'Tree Compensation','project_code'=>'TEST-2','scheme_type'=>'monetary']),
]);
$case = (new App\Models\Survey\SurveyCompCase())->forceFill(['survey_project_id'=>1,'scheme_type'=>'land','survey_officer'=>'Preview Officer','case_date'=>'2026-10-04','area_ha'=>1.8,'prop_district'=>'GRA','prop_lga'=>'Dala','prop_state'=>'Kano','num_plots'=>4]);
$data = ['case'=>$case,'projects'=>$projects,'treeTypes'=>collect(),'lgas'=>['Dala'],'states'=>['Kano']];
foreach ([false,true] as $serverError) {
    $errors = new Illuminate\Support\ViewErrorBag();
    if ($serverError) {
        $case->scheme_type = 'monetary';
        $session->flash('_old_input', ['survey_project_id'=>2,'trees'=>[4=>['tree_type'=>'Mango','quantity'=>0,'unit_price'=>50]]]);
        $errors->put('default',new Illuminate\Support\MessageBag(['trees.4.quantity'=>'The tree quantity must be at least 1.']));
    }
    Illuminate\Support\Facades\View::share('errors', $errors);
    $html = view('survey_module.mobile.case_register', $data)->render();
    file_put_contents(storage_path('app/maintenance/survey-mobile-'.($serverError?'errors':'form').'.html'),$html);
    echo ($serverError?'errors':'form').': '.strlen($html).' bytes'.PHP_EOL;
}

$case->forceFill(['id'=>1,'case_ref'=>'PREVIEW-1','status'=>'Review']);
$case->setRelation('project',$projects->first());
$case->setRelation('beneficiaries',collect());
$case->setRelation('trees',collect());
foreach ([false,true] as $canSubmit) {
    $html=view('survey_module.mobile.case_show',['case'=>$case,'location'=>'GRA, Dala, Kano','split'=>null,'cash'=>0,'canSubmit'=>$canSubmit])->render();
    file_put_contents(storage_path('app/maintenance/survey-mobile-case-'.($canSubmit?'submit':'read').'.html'),$html);
    echo 'case '.($canSubmit?'submit':'read').': '.strlen($html).' bytes'.PHP_EOL;
}
