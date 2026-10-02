<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PrimaryActionsController;
use App\Http\Controllers\SubApplicationModalController;
use App\Http\Controllers\PrimaryFormDraftController;
use App\Http\Controllers\PrimaryApplicationController;
use App\Http\Controllers\SubApplicationDraftController;
use App\Http\Controllers\ActionsController;
use App\Http\Controllers\ProgrammesController;
use App\Http\Controllers\CustomerCareController;
use App\Http\Controllers\SecondaryFormController;
use App\Http\Controllers\SubActionsController;
use App\Http\Controllers\MemoController;
use App\Http\Controllers\UnitMemoUploadController;
use App\Http\Controllers\RofoController;
use App\Http\Controllers\CofoController;
use App\Http\Controllers\FinalBillController;
use App\Http\Controllers\SubFinalBillController;
use App\Http\Controllers\SectionalCofORegistrationController;
use App\Http\Controllers\BettermentBillController;
use App\Http\Controllers\PlanningRecommendationController;
use App\Http\Controllers\JointSiteInspectionEditController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\OtherDepartmentsController;
use App\Http\Controllers\STMemoController;
use App\Http\Controllers\STTransferOfTitleController;
use App\Http\Controllers\DeedsDepartmentController;
use App\Http\Controllers\LandsDepartmentController;
use App\Http\Controllers\SubPlanningRecommendationController;
use App\Http\Controllers\FileViewerController;


// Public routes
Route::get('/primary-applications/{id}', [PrimaryActionsController::class, 'show']);

// Authenticated routes
Route::middleware(['auth'])->group(function () {

    Route::post('/primary-applications', [PrimaryActionsController::class, 'store'])->name('primary-applications.store');
    Route::get('/survey/{applicationId}', [PrimaryActionsController::class, 'getSurvey'])->name('survey.get');
    Route::post('/survey/update', [PrimaryActionsController::class, 'updateSurvey'])->name('survey.update');
    Route::post('/primary-applications/storeDeeds', [PrimaryActionsController::class, 'storeDeeds'])->name('primary-applications.storeDeeds');
    Route::post('/planning-recommendation/update', [PrimaryActionsController::class, 'updatePlanningRecommendation'])->name('planning-recommendation.update');
    Route::post('/planning-recommendation/joint-site-inspection', [PlanningRecommendationController::class, 'storeJointSiteInspectionReport'])->name('planning-recommendation.joint-inspection.store');
    Route::post('/planning-recommendation/joint-site-inspection/generate', [PlanningRecommendationController::class, 'generateJointSiteInspectionReport'])->name('planning-recommendation.joint-inspection.generate');
    Route::post('/planning-recommendation/joint-site-inspection/submit', [PlanningRecommendationController::class, 'submitJointSiteInspectionReport'])->name('planning-recommendation.joint-inspection.submit');
    Route::post('/planning-recommendation/joint-site-inspection/approve', [PlanningRecommendationController::class, 'approveJointSiteInspectionReport'])->name('planning-recommendation.joint-inspection.approve');
    Route::get('/planning-recommendation/joint-site-inspection/{application}/edit', [JointSiteInspectionEditController::class, 'editPrimary'])->name('planning-recommendation.joint-inspection.edit');
    Route::get('/planning-recommendation/joint-site-inspection/{application}/details', [JointSiteInspectionEditController::class, 'detailsPrimary'])->name('planning-recommendation.joint-inspection.details');
    Route::get('/planning-recommendation/joint-site-inspection/{application}', [PlanningRecommendationController::class, 'showJointSiteInspectionReport'])->name('planning-recommendation.joint-inspection.show');
    Route::post('/sub-actions/planning-recommendation/joint-site-inspection', [PlanningRecommendationController::class, 'storeJointSiteInspectionReport'])->name('sub-actions.planning-recommendation.joint-inspection.store');
    Route::post('/sub-actions/planning-recommendation/joint-site-inspection/generate', [PlanningRecommendationController::class, 'generateJointSiteInspectionReport'])->name('sub-actions.planning-recommendation.joint-inspection.generate');
    Route::post('/sub-actions/planning-recommendation/joint-site-inspection/submit', [PlanningRecommendationController::class, 'submitJointSiteInspectionReport'])->name('sub-actions.planning-recommendation.joint-inspection.submit');
    Route::post('/sub-actions/planning-recommendation/joint-site-inspection/approve', [PlanningRecommendationController::class, 'approveJointSiteInspectionReport'])->name('sub-actions.planning-recommendation.joint-inspection.approve');
    Route::get('/sub-actions/planning-recommendation/joint-site-inspection/{subApplication}/edit', [JointSiteInspectionEditController::class, 'editUnit'])->name('sub-actions.planning-recommendation.joint-inspection.edit');
    Route::get('/sub-actions/planning-recommendation/joint-site-inspection/{subApplication}/details', [JointSiteInspectionEditController::class, 'detailsUnit'])->name('sub-actions.planning-recommendation.joint-inspection.details');
    Route::get('/sub-actions/planning-recommendation/joint-site-inspection/{subApplication}', [PlanningRecommendationController::class, 'showSubJointSiteInspectionReport'])->name('sub-actions.planning-recommendation.joint-inspection.show');
    Route::post('/joint-inspection/update-status', [PlanningRecommendationController::class, 'updateJointInspectionStatus'])->name('joint-inspection.update-status');
    Route::get('/api/subapplication/{subApplicationId}/unit-data', [PlanningRecommendationController::class, 'getUnitData'])->name('api.subapplication.unit-data');
    Route::post('/director-approval/update', [PrimaryActionsController::class, 'updateDirectorApproval'])->name('director-approval.update');
    Route::get('/conveyance/{id}', [PrimaryActionsController::class, 'getConveyance'])->name('conveyance.get');
    Route::post('/conveyance/update', [PrimaryActionsController::class, 'updateConveyance'])->name('conveyance.update');
    Route::post('/conveyance/finalize', [PrimaryActionsController::class, 'finalizeConveyance'])->name('conveyance.finalize');
    Route::post('/conveyance/add-buyer', [PrimaryActionsController::class, 'addBuyer'])->name('conveyance.add-buyer');
    Route::post('/conveyance/delete-buyer', [PrimaryActionsController::class, 'deleteBuyer'])->name('conveyance.delete.buyer');
    Route::post('/conveyance/update-buyer', [PrimaryActionsController::class, 'updateSingleBuyer'])->name('conveyance.update.buyer');
    Route::post('/render-buyers-list', [PrimaryActionsController::class, 'renderBuyersList'])->name('render-buyers-list');

    Route::get('/primaryform', [PrimaryFormDraftController::class, 'index'])->name('primaryform.index');
    Route::post('/primaryform', [PrimaryApplicationController::class, 'store'])->name('primaryform.store');
    Route::get('/primaryform/print', [PrimaryFormDraftController::class, 'printPage'])->name('primaryform.print');

    Route::prefix('draft')->name('primaryform.draft.')->group(function () {
        Route::post('/save', [PrimaryFormDraftController::class, 'saveDraft'])->name('save');
        Route::get('/load/{draftId}', [PrimaryFormDraftController::class, 'loadDraft'])->name('load');
        Route::post('/submit', [PrimaryFormDraftController::class, 'submitDraft'])->name('submit');
        Route::delete('/delete/{draftId}', [PrimaryFormDraftController::class, 'deleteDraft'])->name('delete');
        Route::get('/check/{applicationId}', [PrimaryFormDraftController::class, 'checkDraft'])->name('check');
        Route::get('/export/{draftId}', [PrimaryFormDraftController::class, 'exportDraft'])->name('export');
        Route::post('/share', [PrimaryFormDraftController::class, 'shareDraft'])->name('share');
        Route::get('/analytics/{draftId}', [PrimaryFormDraftController::class, 'analytics'])->name('analytics');
        Route::post('/start', [PrimaryFormDraftController::class, 'startFresh'])->name('start');
        Route::get('/mine', [PrimaryFormDraftController::class, 'myDrafts'])->name('mine');
    });

    // Sub-Application Draft Routes
    Route::get('/sub-application-form', [SubApplicationDraftController::class, 'index'])->name('subapplication.index');
    Route::post('/sub-application-form', [SubApplicationDraftController::class, 'submitDraft'])->name('subapplication.store');

    Route::prefix('sub-application-draft')->name('subapplication.draft.')->group(function () {
        Route::post('/save', [SubApplicationDraftController::class, 'saveDraft'])->name('save');
        Route::get('/load/{draftId}', [SubApplicationDraftController::class, 'loadDraft'])->name('load');
        Route::post('/submit', [SubApplicationDraftController::class, 'submitDraft'])->name('submit');
        Route::delete('/delete/{draftId}', [SubApplicationDraftController::class, 'deleteDraft'])->name('delete');
        Route::get('/check/{subApplicationId}', [SubApplicationDraftController::class, 'checkDraft'])->name('check');
        Route::get('/export/{draftId}', [SubApplicationDraftController::class, 'exportDraft'])->name('export');
        Route::post('/share', [SubApplicationDraftController::class, 'shareDraft'])->name('share');
        Route::get('/analytics/{draftId}', [SubApplicationDraftController::class, 'analytics'])->name('analytics');
        Route::post('/start', [SubApplicationDraftController::class, 'startFresh'])->name('start');
        Route::get('/mine', [SubApplicationDraftController::class, 'myDrafts'])->name('mine');
    });

    // Additional sub-application routes
    Route::prefix('sub-application')->group(function () {
        Route::post('/draft/load', [SubApplicationDraftController::class, 'loadDraftPost'])->name('subapplication.draft.load.post');
    });

    Route::post('/secondaryform', [SecondaryFormController::class, 'save'])->name('secondaryform.save');
    Route::get('/secondaryform/print', [SecondaryFormController::class, 'printPage'])->name('secondaryform.print');
    Route::get('/sectionaltitling/edit_sub/{id}', [SecondaryFormController::class, 'edit'])->name('sectionaltitling.edit_sub');
    Route::put('/sectionaltitling/update_sub/{id}', [SecondaryFormController::class, 'update'])->name('sectionaltitling.update_sub');
    
    // Mother applications route
    Route::get('/sectionaltitling/mother', [\App\Http\Controllers\SectionalTitlingController::class, 'mother'])->name('sectionaltitling.mother');

    Route::prefix('sub-application')->group(function () {
        Route::get('/{id}', [SubApplicationModalController::class, 'showSubApplication']);
        Route::post('/survey', [SubApplicationModalController::class, 'storeSurvey'])->name('sub-application.survey.store');
        Route::post('/deeds', [SubApplicationModalController::class, 'storeSubApplicationDeeds'])->name('sub-application.deeds.store');
        Route::post('/planning-recommendation', [SubApplicationModalController::class, 'updateSubPlanningRecommendation'])->name('sub-application.planning-recommendation.update');
        Route::post('/director-approval', [SubApplicationModalController::class, 'updateSubDirectorApproval'])->name('sub-application.director-approval.update');
        Route::get('/conveyance/{applicationId}', [SubApplicationModalController::class, 'getSubConveyance'])->name('sub-application.conveyance.get');
        Route::post('/conveyance', [SubApplicationModalController::class, 'updateSubConveyance'])->name('sub-application.conveyance.update');
        Route::post('/buyers-list', [SubApplicationModalController::class, 'renderSubBuyersList'])->name('sub-application.buyers-list.render');
    });

    Route::prefix('actions')->group(function () {
        Route::get('/other-departments/{id}', [ActionsController::class, 'OtherDepartments'])->name('actions.other-departments');
        Route::get('/bill/{id}', [ActionsController::class, 'Bill'])->name('actions.bill');
        Route::get('/payments/{id}', [ActionsController::class, 'Payment'])->name('actions.payments');
        Route::get('/recommendation/{id}', [ActionsController::class, 'Recommendation'])->name('actions.recommendation');
        Route::get('/final-conveyance/{id}', [ActionsController::class, 'FinalConveyance'])->name('actions.final-conveyance');
        Route::get('/buyers_list/{id}', [ActionsController::class, 'BuyersList'])->name('actions.buyers_list');
        Route::post('/{application}/update-architectural-design', [ActionsController::class, 'updateArchitecturalDesign'])->name('actions.update-architectural-design');
        Route::get('/director-approval/{id}', [ActionsController::class, 'DirectorApproval'])->name('actions.director-approval');
    Route::get('/action-sheet-summary/{id}', [ActionsController::class, 'getActionSheetSummary'])->name('actions.action-sheet-summary');
        Route::post('/generate-action-sheet/{id}', [ActionsController::class, 'generateActionSheet'])->name('actions.generate-action-sheet');
        Route::get('/view-action-sheet/{id}', [ActionsController::class, 'viewActionSheet'])->name('actions.view-action-sheet');
    });

    Route::prefix('sub-actions')->group(function () {
        Route::get('/other-departments/{id}', [SubActionsController::class, 'OtherDepartments'])->name('sub-actions.other-departments');
        Route::get('/bill/{id}', [SubActionsController::class, 'Bill'])->name('sub-actions.bill');
        Route::get('/payments/{id}', [SubActionsController::class, 'Payment'])->name('sub-actions.payments');
        Route::get('/recommendation/{id}', [SubActionsController::class, 'Recommendation'])->name('sub-actions.recommendation');
        Route::get('/final-conveyance/{id}', [SubActionsController::class, 'FinalConveyance'])->name('sub-actions.final-conveyance');
        Route::post('/{application}/update-architectural-design', [SubActionsController::class, 'updateArchitecturalDesign'])->name('sub-actions.update-architectural-design');
        Route::get('/director-approval/{id}', [SubActionsController::class, 'DirectorApproval'])->name('sub-actions.director-approval');
    Route::get('/action-sheet-summary/{id}', [SubActionsController::class, 'getActionSheetSummary'])->name('sub-actions.action-sheet-summary');
        Route::post('/generate-action-sheet/{id}', [SubActionsController::class, 'generateActionSheet'])->name('sub-actions.generate-action-sheet');
        Route::get('/view-action-sheet/{id}', [SubActionsController::class, 'viewActionSheet'])->name('sub-actions.view-action-sheet');

        // New AJAX endpoints
        Route::post('/planning-recommendation/update', [SubActionsController::class, 'updatePlanningRecommendation'])->name('sub-actions.update-planning-recommendation');
        Route::post('/director-approval/update', [SubActionsController::class, 'updateDirectorApproval'])->name('sub-actions.update-director-approval');
        Route::post('/survey/store', [SubActionsController::class, 'storeSurvey'])->name('sub-actions.store-survey');
        Route::post('/deeds/store', [SubActionsController::class, 'storeDeeds'])->name('sub-actions.store-deeds');
        Route::get('/related/{primaryId}', [SubActionsController::class, 'getRelatedSubApplications'])->name('sub-actions.get-related');

        // Add new routes for final conveyance AJAX operations
        Route::post('/conveyance/update', [SubActionsController::class, 'updateFinalConveyance'])->name('sub-actions.conveyance.update');
        Route::post('/conveyance/finalize', [SubActionsController::class, 'finalizeFinalConveyance'])->name('sub-actions.conveyance.finalize');
        Route::get('/conveyance/{applicationId}', [SubActionsController::class, 'getConveyance'])->name('sub-actions.conveyance.get');
        Route::get('/planning-recommendation/print/{id}', [SubActionsController::class, 'printPlanningRecommendation'])->name('sub-actions.planning-recommendation.print');
    });

    Route::prefix('programmes')->group(function () {
        Route::get('/field-data', [ProgrammesController::class, 'FieldData'])->name('programmes.field-data');
        Route::get('/bills', [ProgrammesController::class, 'bills'])->name('programmes.bills');
        Route::get('/bill/view/{type}/{id}', [ProgrammesController::class, 'viewBill'])->name('programmes.bill.view');
        Route::get('/bill/print/{type}/{id}', [ProgrammesController::class, 'printBill'])->name('programmes.bill.print');
        Route::get('/bill/download/{type}/{id}', [ProgrammesController::class, 'downloadBill'])->name('programmes.bill.download');
        Route::get('/payments', [ProgrammesController::class, 'Payments'])->name('programmes.payments');
        Route::post('/payments/save-specific-receipt', [ProgrammesController::class, 'saveSpecificReceipt'])->name('programmes.payments.save-specific-receipt');
        Route::get('/approvals/other-departments', [ProgrammesController::class, 'Others'])->name('programmes.approvals.other-departments');
        Route::get('/approvals/deeds', [ProgrammesController::class, 'Deeds'])->name('programmes.approvals.deeds');
        Route::get('/approvals/lands', [ProgrammesController::class, 'Lands'])->name('programmes.approvals.lands');
        Route::get('/eRegistry', [ProgrammesController::class, 'eRegistryNew'])->name('programmes.eRegistry');
        Route::get('/approvals/planning_recomm', [ProgrammesController::class, 'PlanningRecomm'])->name('programmes.approvals.planning_recomm');
        Route::get('/approvals/director', [ProgrammesController::class, 'Director_approval'])->name('programmes.approvals.director');
        Route::get('/report', [ProgrammesController::class, 'ST_Report'])->name('programmes.report');
        Route::get('/certificates', [CofoController::class, 'CertificateOfOccupancy'])->name('programmes.certificates');
        Route::get('/generate_cofo/{id}', [CofoController::class, 'generateCofO'])->name('programmes.generate_cofo');
        Route::post('/save_cofo', [CofoController::class, 'saveCofO'])->name('programmes.save_cofo');
        Route::get('/cofo/pua-units/{primary}', [CofoController::class, 'puaUnitsModal'])->name('programmes.cofo.pua-units');
        Route::get('/entity/{applicationId?}', [ProgrammesController::class, 'Entity'])->name('programmes.entity');

        Route::get('/memo', [MemoController::class, 'Memo'])->name('programmes.memo');
        Route::get('/view_memo/{id}', [MemoController::class, 'viewMemo'])->name('programmes.view_memo');
        Route::get('/view_memo_primary/{id}', [MemoController::class, 'viewMemoPrimary'])->name('programmes.view_memo_primary');
        Route::get('/view_memo_new/{id}', [MemoController::class, 'viewMemoNew'])->name('programmes.view_memo_new');
        Route::get('/export_memo_word_new/{id}', [MemoController::class, 'exportMemoToWordNew'])->name('programmes.export_memo_word_new');

        // New routes for memo generation
        Route::get('/generate_memo/{id}', [MemoController::class, 'generateMemo'])->name('programmes.generate_memo');
        Route::post('/save_memo', [MemoController::class, 'saveMemo'])->name('programmes.save_memo');
    Route::post('/upload-memo', [MemoController::class, 'uploadMemo'])->name('programmes.upload_memo');
    Route::get('/view-uploaded-memo/{uploadId}', [MemoController::class, 'viewUploadedMemo'])->name('programmes.view_uploaded_memo');
        Route::get('/download-memo/{uploadId}', [MemoController::class, 'downloadMemo'])->name('programmes.download_memo');

        // Unit (Scheme) memo routes
    Route::get('/unit-scheme-memo', [MemoController::class, 'UnitSchemeMemo'])->name('programmes.unit_scheme_memo');
    Route::get('/unit-scheme-memo/upload/{unitId}', [UnitMemoUploadController::class, 'create'])->name('programmes.unit_scheme_memo.upload');
    Route::post('/unit-scheme-memo/upload/{unitId}', [UnitMemoUploadController::class, 'store'])->name('programmes.unit_scheme_memo.upload.store');
    Route::get('/view-unit-st-memo/{unitId}', [MemoController::class, 'viewUnitSTMemo'])->name('programmes.view_unit_st_memo');

        // Unit (Non-Scheme) memo routes for SUA
        Route::get('/unit-nonscheme-memo', [MemoController::class, 'UnitNonSchemeMemo'])->name('programmes.unit_nonscheme_memo');
        Route::get('/generate-sua-memo-form/{unitId}', [MemoController::class, 'generateSUAMemoForm'])->name('programmes.generate_sua_memo_form');
        Route::post('/generate-sua-memo', [MemoController::class, 'generateSUAMemo'])->name('programmes.generate_sua_memo');
        Route::get('/view-sua-memo/{unitId}', [MemoController::class, 'viewSUAMemo'])->name('programmes.view_sua_memo');
 
        Route::get('/rofo', [RofoController::class, 'RofO'])->name('programmes.rofo');
        Route::get('/generate_rofo/{id}', [RofoController::class, 'generateRofO'])->name('programmes.generate_rofo');
        Route::post('/save_rofo', [RofoController::class, 'saveRofO'])->name('programmes.save_rofo');
        Route::get('/view_rofo/{id}', [RofoController::class, 'viewRofO'])->name('programmes.view_rofo');
        Route::get('/view_rofo/{id}/print', [RofoController::class, 'printRofO'])->name('programmes.print_rofo');
        Route::get('/rofo/pua-units/{primary}', [RofoController::class, 'showPuaUnits'])->name('programmes.rofo.pua-units');
        Route::get('/rofo/sua-units', [RofoController::class, 'showSuaUnits'])->name('programmes.rofo.sua-units');
        Route::post('/rofo/assign-security-paper/{id}', [RofoController::class, 'assignSecurityPaper'])->name('programmes.rofo.assign-security-paper');
        Route::get('/cofo/sua-units', [CofoController::class, 'showSuaUnits'])->name('programmes.cofo.sua-units');

        Route::get('/view_cofo/{id}', [CofoController::class, 'ViewCofO'])->name('programmes.view_cofo');
        Route::get('/print_cofo/{id}', [CofoController::class, 'PrintCofO'])->name('programmes.print_cofo');
        Route::get('/print_cofo_batch/{primaryId}', [CofoController::class, 'PrintCofOBatch'])->name('programmes.print_cofo_batch');
    });

    // Primary Application Final Bill Routes
    Route::prefix('final-bill')->group(function () {
        Route::get('/generate/{id}', [FinalBillController::class, 'generateBill'])->name('final-bill.generate');
        Route::post('/save', [FinalBillController::class, 'saveBill'])->name('final-bill.save');
        Route::get('/print/{id}', [FinalBillController::class, 'printBill'])->name('final-bill.print');
    });   
    
    
    // Sub Application Final Bill Routes
    Route::prefix('sub-final-bill')->group(function () {
        Route::get('/generate/{id}', [SubFinalBillController::class, 'generateBill'])->name('sub-final-bill.generate');
        Route::post('/save', [SubFinalBillController::class, 'saveBill'])->name('sub-final-bill.save');
        Route::get('/print/{id}', [SubFinalBillController::class, 'printBill'])->name('sub-final-bill.print');
    });

    Route::prefix('customer_care')->group(function () {
        Route::get('/', [CustomerCareController::class, 'index'])->name('customer-care.index');
        Route::get('/customer/{id}', [CustomerCareController::class, 'getCustomer'])->name('customer-care.get-customer');
        Route::post('/send-sms', [CustomerCareController::class, 'sendSms'])->name('customer-care.send-sms');
        Route::post('/make-call', [CustomerCareController::class, 'makeCall'])->name('customer-care.make-call');
        Route::post('/send-bulk-sms', [CustomerCareController::class, 'sendBulkSms'])->name('customer-care.send-bulk-sms');
        Route::post('/send-email', [CustomerCareController::class, 'sendEmail'])->name('customer-care.send-email');
        Route::post('/send-bulk-email', [CustomerCareController::class, 'sendBulkEmail'])->name('customer-care.send-bulk-email');
        Route::post('/send-whatsapp', [CustomerCareController::class, 'sendWhatsApp'])->name('customer-care.send-whatsapp');
        Route::post('/send-bulk-whatsapp', [CustomerCareController::class, 'sendBulkWhatsApp'])->name('customer-care.send-bulk-whatsapp');
        Route::get('/view-application/{id}', [CustomerCareController::class, 'viewApplication'])->name('customer-care.view-application');
        Route::get('/open-file/{id}', [CustomerCareController::class, 'openFile'])->name('customer-care.open-file');
    });

    Route::prefix('st_registration')->group(function () {
        Route::get('/', [SectionalCofORegistrationController::class, 'SectionalCofORegistration'])->name('st_registration.index');
        Route::get('/generate/{id}', [SectionalCofORegistrationController::class, 'generate'])->name('st_registration.generate');
        Route::post('/save', [SectionalCofORegistrationController::class, 'save'])->name('st_registration.save');
        Route::get('/view/{id}', [SectionalCofORegistrationController::class, 'view'])->name('st_registration.view');

        // New endpoints for direct CofO registration
        Route::get('/get-next-serial', [SectionalCofORegistrationController::class, 'getNextSerialNumber'])->name('st_registration.get-next-serial');
        Route::post('/register-single', [SectionalCofORegistrationController::class, 'registerSingle'])->name('st_registration.register-single');
        Route::post('/register-batch', [SectionalCofORegistrationController::class, 'registerBatch'])->name('st_registration.register-batch');
        Route::post('/decline', [SectionalCofORegistrationController::class, 'declineRegistration'])->name('st_registration.decline');
    });



    Route::prefix('st_transfer')->group(function () {
        Route::get('/', [STTransferOfTitleController::class, 'StTransfer'])->name('st_transfer.index');
        
        // Fix generate route to point to generate method, not save
        Route::get('/generate/{id}', [STTransferOfTitleController::class, 'generate'])->name('st_transfer.generate');
        
        // Save route (POST) 
        Route::post('/save', [STTransferOfTitleController::class, 'save'])->name('st_transfer.save');
        
        // View route is correct but make sure it's accessible
        Route::get('/view/{id}', [STTransferOfTitleController::class, 'view'])->name('st_transfer.view');

        // New endpoints for direct CofO registration
        Route::get('/get-next-serial', [STTransferOfTitleController::class, 'getNextSerialNumber'])->name('st_transfer.get-next-serial');
        Route::post('/register-single', [STTransferOfTitleController::class, 'registerSingle'])->name('st_transfer.register-single');
        Route::post('/register-batch', [STTransferOfTitleController::class, 'registerBatch'])->name('st_transfer.register-batch');
        Route::post('/decline', [STTransferOfTitleController::class, 'declineRegistration'])->name('st_transfer.decline');
        
        // Debug route
        Route::get('/debug', [STTransferOfTitleController::class, 'debug'])->name('st_transfer.debug');
    });




    // Betterment Bill Routes
    Route::prefix('betterment-bill')->group(function () {
        Route::post('/calculate', [BettermentBillController::class, 'calculate'])->name('betterment-bill.calculate');
        Route::post('/store', [BettermentBillController::class, 'store'])->name('betterment-bill.store');
        Route::get('/show/{id}', [BettermentBillController::class, 'show'])->name('betterment-bill.show');
        Route::get('/print/{id}', [BettermentBillController::class, 'printReceipt'])->name('betterment-bill.print');
    });

    // Planning Recommendation Table Management Routes
    Route::prefix('planning-tables')->group(function () {
        Route::get('/dimensions/{applicationId}', [PlanningRecommendationController::class, 'getSitePlanDimensions'])->name('planning-tables.get-dimensions');
        Route::get('/utilities/{applicationId}', [PlanningRecommendationController::class, 'getSharedUtilities'])->name('planning-tables.get-utilities');
        Route::post('/dimensions', [PlanningRecommendationController::class, 'saveSitePlanDimension'])->name('planning-tables.save-dimension');
        Route::post('/utilities', [PlanningRecommendationController::class, 'saveSharedUtility'])->name('planning-tables.save-utility');
        Route::delete('/dimensions', [PlanningRecommendationController::class, 'deleteSitePlanDimension'])->name('planning-tables.delete-dimension');
        Route::delete('/utilities', [PlanningRecommendationController::class, 'deleteSharedUtility'])->name('planning-tables.delete-utility');
        Route::get('/debug-shared-areas/{applicationId}', [PlanningRecommendationController::class, 'debugSharedAreas'])->name('planning-tables.debug-shared-areas');
        
        // Sub application routes
        Route::get('/sub/dimensions/{subApplicationId}', [SubPlanningRecommendationController::class, 'getSitePlanDimensions'])->name('sub-planning-tables.get-dimensions');
        Route::get('/sub/utilities/{subApplicationId}', [SubPlanningRecommendationController::class, 'getSharedUtilities'])->name('sub-planning-tables.get-utilities');
        Route::post('/sub/dimensions', [SubPlanningRecommendationController::class, 'saveSitePlanDimension'])->name('sub-planning-tables.save-dimension');
        Route::post('/sub/utilities', [SubPlanningRecommendationController::class, 'saveSharedUtility'])->name('sub-planning-tables.save-utility');
        Route::delete('/sub/dimensions', [SubPlanningRecommendationController::class, 'deleteSitePlanDimension'])->name('sub-planning-tables.delete-dimension');
        Route::delete('/sub/utilities', [SubPlanningRecommendationController::class, 'deleteSharedUtility'])->name('sub-planning-tables.delete-utility');
        Route::get('/sub/debug-shared-areas/{subApplicationId}', [SubPlanningRecommendationController::class, 'debugSharedAreas'])->name('sub-planning-tables.debug-shared-areas');
    });

    // Survey routes
    Route::prefix('survey_records')->group(function () {
        Route::get('/', [SurveyController::class, 'index'])->name('survey_records.index');
        Route::get('/st_survey', [SurveyController::class, 'stsurvey'])->name('survey.st_survey');
        Route::get('/sltr-survey', [SurveyController::class, 'Map'])->name('survey.sltr');
    });

    Route::prefix('other_departments')->group(function () {
        Route::get('/survey/{id}', [OtherDepartmentsController::class, 'Survey'])->name('other_departments.survey');
        Route::get('/secondary_survey_view/{id}', [OtherDepartmentsController::class, 'SecondarySurveyView'])->name('other_departments.secondary_survey_view');
        Route::get('/others', [OtherDepartmentsController::class, 'Others'])->name('other-departments.others');
        Route::get('/surveyCadastralRecord', [OtherDepartmentsController::class, 'SurveyCadastralRecord'])->name('other-departments.surveyCadastralRecord');
        Route::get('/survey_primary', [OtherDepartmentsController::class, 'Survey_Primary'])->name('other_departments.survey_primary');
        Route::get('/survey_secondary', [OtherDepartmentsController::class, 'Survey_Secondary'])->name('other_departments.survey_secondary');
        
       
    });

    //* Deeds Departments 
 
    Route::prefix('other_departments')->group(function () {
        Route::get('/deeds_primary', [DeedsDepartmentController::class, 'Deeds_Primary'])->name('other_departments.deeds_primary');
        Route::get('/deeds/{id}', [DeedsDepartmentController::class, 'DeedsView'])->name('other_departments.deeds');   
    });

    Route::prefix('other_departments')->group(function () {
        Route::get('/lands_primary', [LandsDepartmentController::class, 'Lands_Primary'])->name('other_departments.lands_primary');
        Route::get('/lands/{id}', [LandsDepartmentController::class, 'LandsView'])->name('other_departments.lands');   
    });

    Route::prefix('stmemo')->group(function () {
        // New routes for STMemoController
        Route::get('/stmemo', [STMemoController::class, 'STmemo'])->name('stmemo.stmemo');
        Route::get('/siteplan', [STMemoController::class, 'SitePlan'])->name('stmemo.siteplan');
        Route::get('/temotemplate', [STMemoController::class, 'MemoTemplate'])->name('stmemo.temotemplate');
        
        // New routes for ST Memo generation
        Route::get('/generate/{id}', [STMemoController::class, 'generateSTMemo'])->name('stmemo.generate');
        Route::post('/save', [STMemoController::class, 'saveSTMemo'])->name('stmemo.save');
        Route::get('/view/{id}', [STMemoController::class, 'viewSTMemo'])->name('stmemo.view');

        // Site Plan Management Routes
        Route::get('/upload-siteplan/{id}', [STMemoController::class, 'uploadSitePlan'])->name('stmemo.uploadSitePlan');
        Route::post('/save-siteplan', [STMemoController::class, 'saveSitePlan'])->name('stmemo.saveSitePlan');
        Route::get('/view-siteplan/{id}', [STMemoController::class, 'viewSitePlan'])->name('stmemo.viewSitePlan');
        Route::delete('/delete-siteplan/{id}', [STMemoController::class, 'deleteSitePlan'])->name('stmemo.deleteSitePlan');
        
        // Recommended Site Plan Sketch Routes
        Route::get('/get-recommended-siteplan/{id}', [STMemoController::class, 'getRecommendedSitePlan'])->name('stmemo.getRecommendedSitePlan');
        Route::post('/save-recommended-siteplan', [STMemoController::class, 'saveRecommendedSitePlan'])->name('stmemo.saveRecommendedSitePlan');
        Route::delete('/delete-recommended-siteplan/{id}', [STMemoController::class, 'deleteRecommendedSitePlan'])->name('stmemo.deleteRecommendedSitePlan');
    });



});

// Add route for showing sub final bill
Route::get('/sub-final-bill/show/{id}', [SubFinalBillController::class, 'showBill'])->name('sub-final-bill.show');

// Application details route for betterment bill form
Route::get('/gisedms/application-details/{fileId}/{fileType}', [App\Http\Controllers\ProgrammesController::class, 'getApplicationDetails'])->name('programmes.application-details');

// File Viewer Routes
Route::prefix('file-viewer')->middleware(['auth'])->group(function () {
    Route::get('/primary/{applicationId}', [App\Http\Controllers\FileViewerController::class, 'viewPrimaryFiles'])->name('file-viewer.primary');
    Route::get('/unit/{subApplicationId}', [App\Http\Controllers\FileViewerController::class, 'viewUnitFiles'])->name('file-viewer.unit');
    Route::get('/preview/{scanningId}', [App\Http\Controllers\FileViewerController::class, 'getFilePreview'])->name('file-viewer.preview');
    Route::get('/download/{scanningId}', [App\Http\Controllers\FileViewerController::class, 'downloadFile'])->name('file-viewer.download');
});

// SUA Routes - Standalone Unit Applications
Route::group(['middleware' => ['auth', 'XSS']], function () {
    Route::get('/sua/create', [App\Http\Controllers\SUAController::class, 'create'])->name('sua.create');
    Route::post('/sua/store', [App\Http\Controllers\SUAController::class, 'store'])->name('sua.store');
    Route::get('/sua', [App\Http\Controllers\SUAController::class, 'index'])->name('sua.index');
    // Specific routes MUST come before parameterized routes
    Route::get('/sua/next-fileno', [App\Http\Controllers\SUAController::class, 'getNextFileNo'])->name('sua.next-fileno');
    // Parameterized routes come after specific routes
    Route::get('/sua/{id}', [App\Http\Controllers\SUAController::class, 'show'])->name('sua.show');
    Route::get('/sua/{id}/edit', [App\Http\Controllers\SUAController::class, 'edit'])->name('sua.edit');
    Route::put('/sua/{id}', [App\Http\Controllers\SUAController::class, 'update'])->name('sua.update');
    Route::delete('/sua/{id}', [App\Http\Controllers\SUAController::class, 'destroy'])->name('sua.destroy');
});
