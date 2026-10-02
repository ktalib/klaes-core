<?php
// use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\AuthPageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\NoticeBoardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FAQController;
use App\Http\Controllers\Api\FileTrackerApiController;
use App\Http\Controllers\Api\FileTrackerDashboardApiController;
use App\Http\Controllers\FileTrackingController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SubApplicationController;
use App\Http\Controllers\ProgrammesController;
use App\Http\Controllers\ApplicationMotherController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\LegalSearchController;
use App\Http\Controllers\ResidentialController;
use App\Http\Controllers\eRegistryController;
use App\Http\Controllers\DeedsController;
use App\Http\Controllers\ConveyanceController;
use App\Http\Controllers\SaveMainAppController;
use App\Http\Controllers\FileIndexingController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\FileScanningController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\UnindexedFileBackendController;
use App\Http\Controllers\PropIdMasterController;
use App\Http\Controllers\CsvImporterController;
use App\Http\Controllers\CofoController;

use App\Http\Controllers\GisController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\InstrumentRegistrationController;
use App\Http\Controllers\StInstrumentRegistrationController;
use App\Http\Controllers\PrimaryApplicationController;
use App\Http\Controllers\FileTrackerDashboardController;
use Illuminate\Support\Facades\DB;

// Test routes for new controller
Route::get('/fix-db-schema', function () {
    try {
        if (!Illuminate\Support\Facades\Schema::hasTable('print_logs')) {
            Illuminate\Support\Facades\Schema::create('print_logs', function (Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('reference_number')->index();
                $table->string('document_type');
                $table->string('print_type');
                $table->string('status');
                $table->foreignId('user_id')->nullable();
                $table->timestamps();
            });
            return "Table 'print_logs' created successfully.";
        }
        return "Table 'print_logs' already exists.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});
Route::get('/test-db-connection', function () {
    try {
        $connection = DB::connection('sqlsrv');
        $tables = $connection->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME = 'mother_applications'");
        $columns = $connection->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mother_applications' ORDER BY ORDINAL_POSITION");

        $output = "Database Connection: SUCCESS\n\n";
        $output .= "Table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n\n";
        $output .= "Columns in mother_applications:\n";
        foreach ($columns as $column) {
            $output .= "- {$column->COLUMN_NAME} ({$column->DATA_TYPE})\n";
        }

        return response($output)->header('Content-Type', 'text/plain');
    } catch (\Exception $e) {
        return response("Database Connection: FAILED\nError: " . $e->getMessage())->header('Content-Type', 'text/plain');
    }
});

Route::get('/verify-application-data', function () {
    try {
        $connection = DB::connection('sqlsrv');
        $recent = $connection->select("SELECT TOP 3 id, np_fileno, fileno, fname, lname, email, land_use, applicant_type, created_at FROM mother_applications ORDER BY created_at DESC");

        $output = "Recent Applications:\n\n";
        foreach ($recent as $app) {
            $output .= "ID: {$app->id}\n";
            $output .= "NP File No: {$app->np_fileno}\n";
            $output .= "File No: {$app->fileno}\n";
            $output .= "Name: {$app->fname} {$app->lname}\n";
            $output .= "Email: {$app->email}\n";
            $output .= "Land Use: {$app->land_use}\n";
            $output .= "Applicant Type: {$app->applicant_type}\n";
            $output .= "Created: {$app->created_at}\n";
            $output .= "------------------------\n";
        }

        return response($output)->header('Content-Type', 'text/plain');
    } catch (\Exception $e) {
        return response("Verification FAILED\nError: " . $e->getMessage())->header('Content-Type', 'text/plain');
    }
});

// Simple hello world page for File Search
Route::get('/file-search', [FileSearchController::class, 'index'])->name('file-search.index');

Route::middleware(['auth', 'XSS'])->get('/file-search/scans', function () {
    return view('search.index');
})->name('file-search.scans');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
require __DIR__ . '/auth.php';
require __DIR__ . '/session_lock.php';
require __DIR__ . '/recertification_routes.php';
require __DIR__ . '/file_numbers.php';
require __DIR__ . '/file_decommissioning.php';
require __DIR__ . '/mls_fileno.php';
require __DIR__ . '/caveat.php';
require __DIR__ . '/buyer_list.php';
require __DIR__ . '/allocation_list.php';
Route::get('/', [HomeController::class, 'index'])->middleware(
    [
        'XSS',
    ]
);
Route::get('home', [HomeController::class, 'index'])->name('home')->middleware(
    [
        'XSS',
    ]
);
Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware(
    [
        'XSS',
    ]
);
//-------------------------------User-------------------------------------------
// User import routes need to resolve before the resource wildcard.
Route::group(['prefix' => 'users', 'middleware' => ['auth', 'XSS']], function () {
    Route::get('/import-form', [\App\Http\Controllers\UserImportController::class, 'showImportForm'])->name('users.import.form');
    Route::get('/import-template', [\App\Http\Controllers\UserImportController::class, 'downloadTemplate'])->name('users.import.template');
    Route::get('/department-lookup', [\App\Http\Controllers\UserImportController::class, 'departmentLookupPdf'])->name('users.department.lookup-pdf');
    Route::post('/import', [\App\Http\Controllers\UserImportController::class, 'importUsers'])->name('users.import.process');
    Route::post('/clear-test-data', [\App\Http\Controllers\UserImportController::class, 'clearTestData'])->name('users.clear.test');
    Route::get('/import-stats', [\App\Http\Controllers\UserImportController::class, 'getImportStats'])->name('users.import.stats');
    Route::patch('/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::patch('/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
});

Route::resource('users', UserController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

// User levels API endpoint
Route::get('users/get-levels/{userTypeId}', [UserController::class, 'getUserLevels'])->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Subscription-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::resource('subscriptions', SubscriptionController::class);
        Route::get('coupons/history', [CouponController::class, 'history'])->name('coupons.history');
        Route::delete('coupons/history/{id}/destroy', [CouponController::class, 'historyDestroy'])->name('coupons.history.destroy');
        Route::get('coupons/apply', [CouponController::class, 'apply'])->name('coupons.apply');
        Route::resource('coupons', CouponController::class);
        Route::get('subscription/transaction', [SubscriptionController::class, 'transaction'])->name('subscription.transaction');
        Route::post('subscription/{id}/{user_id}/manual-assign-package', [PaymentController::class, 'subscriptionManualAssignPackage'])->name('subscription.manual_assign_package');
    }
);
//-------------------------------Subscription Payment-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::post('subscription/{id}/stripe/payment', [SubscriptionController::class, 'stripePayment'])->name('subscription.stripe.payment');
    }
);
//-------------------------------Settings-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('settings', [SettingController::class, 'index'])->name('setting.index');
        Route::post('settings/account', [SettingController::class, 'accountData'])->name('setting.account');
        Route::delete('settings/account/delete', [SettingController::class, 'accountDelete'])->name('setting.account.delete');
        Route::post('settings/password', [SettingController::class, 'passwordData'])->name('setting.password');
        Route::post('settings/general', [SettingController::class, 'generalData'])->name('setting.general');
        Route::post('settings/multipurpose', [SettingController::class, 'multipurposeData'])->name('setting.multipurpose');
        Route::post('settings/smtp', [SettingController::class, 'smtpData'])->name('setting.smtp');
        Route::get('settings/smtp-test', [SettingController::class, 'smtpTest'])->name('setting.smtp.test');
        Route::post('settings/smtp-test', [SettingController::class, 'smtpTestMailSend'])->name('setting.smtp.testing');
        Route::post('settings/payment', [SettingController::class, 'paymentData'])->name('setting.payment');
        Route::post('settings/site-seo', [SettingController::class, 'siteSEOData'])->name('setting.site.seo');
        Route::post('settings/google-recaptcha', [SettingController::class, 'googleRecaptchaData'])->name('setting.google.recaptcha');
        Route::post('settings/company', [SettingController::class, 'companyData'])->name('setting.company');
        Route::post('settings/2fa', [SettingController::class, 'twofaEnable'])->name('setting.twofa.enable');
        Route::get('footer-setting', [SettingController::class, 'footerSetting'])->name('footerSetting');
        Route::post('settings/footer', [SettingController::class, 'footerData'])->name('setting.footer');
        Route::get('language/{lang}', [SettingController::class, 'lanquageChange'])->name('language.change');
        Route::post('theme/settings', [SettingController::class, 'themeSettings'])->name('theme.settings');
    }
);
Route::group(
    [
        'middleware' => [
            'auth',
        ],
    ],
    function () {
        Route::post('settings/payment', [SettingController::class, 'paymentData'])->name('setting.payment');
    }
);
//-------------------------------Role & Permissions-------------------------------------------
Route::resource('permission', PermissionController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::resource('role', RoleController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Note-------------------------------------------
Route::resource('note', NoticeBoardController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Contact-------------------------------------------
Route::resource('contact', ContactController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------logged History-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('logged/history', [UserController::class, 'loggedHistory'])->name('logged.history');
        Route::get('logged/{id}/history/show', [UserController::class, 'loggedHistoryShow'])->name('logged.history.show');
        Route::delete('logged/{id}/history', [UserController::class, 'loggedHistoryDestroy'])->name('logged.history.destroy');
    }
);

//-------------------------------User Activity Logs-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('user-activity-logs', [App\Http\Controllers\UserActivityLogController::class, 'index'])->name('user-activity-logs.index');
        Route::get('user-activity-logs/{id}', [App\Http\Controllers\UserActivityLogController::class, 'show'])->name('user-activity-logs.show');
        Route::delete('user-activity-logs/{id}', [App\Http\Controllers\UserActivityLogController::class, 'destroy'])->name('user-activity-logs.destroy');
        Route::post('user-activity-logs/logout-user/{userId}', [App\Http\Controllers\UserActivityLogController::class, 'logoutUser'])->name('user-activity-logs.logout-user');
        Route::get('user-activity-logs/stats/data', [App\Http\Controllers\UserActivityLogController::class, 'getStats'])->name('user-activity-logs.stats');
        Route::get('user-activity-logs/online/users', [App\Http\Controllers\UserActivityLogController::class, 'getOnlineUsers'])->name('user-activity-logs.online-users');
        Route::get('user-activity-logs/chart/data', [App\Http\Controllers\UserActivityLogController::class, 'getChartData'])->name('user-activity-logs.chart-data');
        Route::get('user-activity-logs/export/csv', [App\Http\Controllers\UserActivityLogController::class, 'export'])->name('user-activity-logs.export');
        Route::post('user-activity-logs/bulk-delete', [App\Http\Controllers\UserActivityLogController::class, 'bulkDelete'])->name('user-activity-logs.bulk-delete');
        Route::post('user-activity-logs/clean-old', [App\Http\Controllers\UserActivityLogController::class, 'cleanOldLogs'])->name('user-activity-logs.clean-old');
        Route::get('user-activity-logs/settings/get', [App\Http\Controllers\UserActivityLogController::class, 'getSettings'])->name('user-activity-logs.settings.get');
        Route::post('user-activity-logs/settings/save', [App\Http\Controllers\UserActivityLogController::class, 'saveSettings'])->name('user-activity-logs.settings.save');
        Route::get('user-activity-logs/settings/global', [App\Http\Controllers\UserActivityLogController::class, 'getGlobalSettings'])->name('user-activity-logs.settings.global');
        Route::post('user-activity-logs/settings/global', [App\Http\Controllers\UserActivityLogController::class, 'saveGlobalSettings'])->name('user-activity-logs.settings.global.save');
        Route::get('user-activity-logs/cleanup/status', [App\Http\Controllers\UserActivityLogController::class, 'getCleanupStatus'])->name('user-activity-logs.cleanup.status');
        Route::post('user-activity-logs/cleanup/auto', [App\Http\Controllers\UserActivityLogController::class, 'runAutomaticCleanup'])->name('user-activity-logs.cleanup.auto');
    }
);
//-------------------------------Document-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('document/history', [DocumentController::class, 'history'])->name('document.history');
        Route::resource('document', DocumentController::class);
        Route::get('my-document', [DocumentController::class, 'myDocument'])->name('document.my-document');
        Route::get('document/{id}/comment', [DocumentController::class, 'comment'])->name('document.comment');
        Route::post('document/{id}/comment', [DocumentController::class, 'commentData'])->name('document.comment.store');
        Route::get('document/{id}/reminder', [DocumentController::class, 'reminder'])->name('document.reminder');
        Route::get('document/{id}/add-reminder', [DocumentController::class, 'addReminder'])->name('document.add.reminder');
        Route::get('document/{id}/version-history', [DocumentController::class, 'versionHistory'])->name('document.version.history');
        Route::post('document/{id}/version-history', [DocumentController::class, 'newVersion'])->name('document.new.version');
        Route::get('document/{id}/share', [DocumentController::class, 'shareDocument'])->name('document.share');
        Route::post('document/{id}/share', [DocumentController::class, 'shareDocumentData'])->name('document.share.store');
        Route::get('document/{id}/add-share', [DocumentController::class, 'addshareDocumentData'])->name('document.add.share');
        Route::delete('document/{id}/share/destroy', [DocumentController::class, 'shareDocumentDelete'])->name('document.share.destroy');
        Route::get('document/{id}/send-email', [DocumentController::class, 'sendEmail'])->name('document.send.email');
        Route::post('document/{id}/send-email', [DocumentController::class, 'sendEmailData'])->name('document.send.email.store');
        Route::get('logged/history', [DocumentController::class, 'loggedHistory'])->name('logged.history');
        Route::get('logged/{id}/history/show', [DocumentController::class, 'loggedHistoryShow'])->name('logged.history.show');
        Route::delete('logged/{id}/history', [DocumentController::class, 'loggedHistoryDestroy'])->name('logged.history.destroy');
    }
);
//-------------------------------Reminder-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::resource('reminder', ReminderController::class);
        Route::get('my-reminder', [ReminderController::class, 'myReminder'])->name('my-reminder');
    }
);
//-------------------------------Category, Sub Category & Tag-------------------------------------------
Route::get('category/{id}/sub-category', [CategoryController::class, 'getSubcategory'])->name('category.sub-category');
Route::resource('category', CategoryController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::resource('sub-category', SubCategoryController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::resource('tag', TagController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Plan Payment-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::post('subscription/{id}/bank-transfer', [PaymentController::class, 'subscriptionBankTransfer'])->name('subscription.bank.transfer');
        Route::get('subscription/{id}/bank-transfer/action/{status}', [PaymentController::class, 'subscriptionBankTransferAction'])->name('subscription.bank.transfer.action');
        Route::post('subscription/{id}/paypal', [PaymentController::class, 'subscriptionPaypal'])->name('subscription.paypal');
        Route::get('subscription/{id}/paypal/{status}', [PaymentController::class, 'subscriptionPaypalStatus'])->name('subscription.paypal.status');
        Route::get('subscription/flutterwave/{sid}/{tx_ref}', [PaymentController::class, 'subscriptionFlutterwave'])->name('subscription.flutterwave');
    }
);
//-------------------------------Notification-------------------------------------------
Route::resource('notification', NotificationController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get('email-verification/{token}', [VerifyEmailController::class, 'verifyEmail'])->name('email-verification')->middleware(
    [
        'XSS',
    ]
);
//-------------------------------FAQ-------------------------------------------
Route::resource('FAQ', FAQController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Home Page-------------------------------------------
Route::resource('homepage', HomePageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------FAQ-------------------------------------------
Route::resource('pages', PageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------FAQ-------------------------------------------
Route::resource('authPage', AuthPageController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
Route::get('page/{slug}', [PageController::class, 'page'])->name('page');
//-------------------------------FAQ-------------------------------------------

// Application Mother routes
Route::get('/sectionaltitling', [ApplicationMotherController::class, 'index'])->name('sectionaltitling.index');
Route::get('/sectionaltitling/landuse', [ApplicationMotherController::class, 'landuse'])->name('sectionaltitling.landuse');
Route::get('/sectionaltitling/create', [ApplicationMotherController::class, 'create'])->name('sectionaltitling.create');
Route::get('/sectionaltitling/sub_application', [ApplicationMotherController::class, 'subApplication'])->name('sectionaltitling.sub_application');
Route::get('/sectionaltitling/generate_bill/{id?}', [ApplicationMotherController::class, 'GenerateBill'])->name('sectionaltitling.generate_bill');

Route::get('/sectionaltitling/AcceptLetter', [ApplicationMotherController::class, 'AcceptLetter'])
    ->name('sectionaltitling.AcceptLetter');
Route::get('/sectionaltitling/sub_applications', [ApplicationMotherController::class, 'Subapplications'])->name('sectionaltitling.sub_applications');
// Add this new route for storing sub-applications
Route::post('/sectionaltitling/storesub', [ApplicationMotherController::class, 'storeSub'])->name('sectionaltitling.storesub');
Route::post('/sectionaltitling', [ApplicationMotherController::class, 'store'])->name('sectionaltitling.store');
Route::get('/sectionaltitling/{id}/edit', [ApplicationMotherController::class, 'edit']);
Route::post('sectionaltitling/approve-sub', [ApplicationMotherController::class, 'approveSubApplication'])
    ->name('sectionaltitling.approveSubApplication');
Route::post('sectionaltitling/decline-sub', [ApplicationMotherController::class, 'declineSubApplication'])
    ->name('sectionaltitling.declineSubApplication');
Route::post('sectionaltitling/decision-sub', [ApplicationMotherController::class, 'decisionSubApplication'])
    ->name('sectionaltitling.decisionSubApplication');
Route::post('sectionaltitling/decision-mother', [ApplicationMotherController::class, 'decisionMotherApplication'])
    ->name('sectionaltitling.decisionMotherApplication');
// Sectional Titling routes
Route::post('/sectional-titling/planning-recommendation', [App\Http\Controllers\ApplicationMotherController::class, 'planningRecommendation'])->name('sectionaltitling.planningRecommendation');
Route::post('/sectional-titling/department-approval', [App\Http\Controllers\ApplicationMotherController::class, 'departmentApproval'])->name('sectionaltitling.departmentApproval');
Route::get('sectionaltitling/getFinancialData', [App\Http\Controllers\ApplicationMotherController::class, 'getFinancialData'])->name('sectionaltitling.getFinancialData');
// Add these routes in the appropriate section of your web.php file
Route::get('sectionaltitling/get-billing-data/{id}', [App\Http\Controllers\ApplicationMotherController::class, 'getBillingData'])->name('sectionaltitling.getBillingData');
Route::get('sectionaltitling/get-billing-data2/{id}', [App\Http\Controllers\ApplicationMotherController::class, 'getBillingData2'])->name('sectionaltitling.getBillingData2');
Route::post('sectionaltitling/save-billing-data', [App\Http\Controllers\ApplicationMotherController::class, 'saveBillingData'])->name('sectionaltitling.saveBillingData');
Route::post('sectionaltitling/save-application-data', [App\Http\Controllers\ApplicationMotherController::class, 'saveApplicationData'])->name('sectionaltitling.saveApplicationData');

// Planning Recommendation - Complete Survey Data modal endpoints (AJAX)
Route::get('/programmes/planning/primary/{id}/survey-data', [ProgrammesController::class, 'getPrimarySurveyData'])->name('programmes.planning.primary.survey');
Route::get('/programmes/planning/unit/{id}/survey-data', [ProgrammesController::class, 'getUnitSurveyData'])->name('programmes.planning.unit.survey');
Route::post('/programmes/planning/unit/save-survey', [SubApplicationController::class, 'saveUnitSurveyData'])->name('programmes.planning.unit.save');
Route::get('/programmes/planning/primary/{id}/complete-survey', [ProgrammesController::class, 'showPrimarySurveyForm'])->name('programmes.planning.primary.complete-survey');
Route::get('/programmes/planning/unit/{id}/complete-survey', [ProgrammesController::class, 'showUnitSurveyForm'])->name('programmes.planning.unit.complete-survey');
Route::get('sectionaltitling/viewrecorddetail', [App\Http\Controllers\ApplicationMotherController::class, 'Veiwrecords'])->name('sectionaltitling.viewrecorddetail');
Route::get('sectionaltitling/edit/{id}', [App\Http\Controllers\ApplicationMotherController::class, 'edit'])->name('sectionaltitling.edit');
Route::put('sectionaltitling/update/{id}', [App\Http\Controllers\ApplicationMotherController::class, 'update'])->name('sectionaltitling.update');
Route::delete('sectionaltitling/delete/{id}', [App\Http\Controllers\ApplicationMotherController::class, 'delete'])->name('sectionaltitling.delete');
// Add this route in the appropriate section
Route::post('/sectionaltitling/save-eregistry', [eRegistryController::class, 'saveERegistry'])->name('sectionaltitling.saveERegistry');

// Add this fallback route for propertycard data
Route::get('/propertycard/data-fallback', function () {
    $sampleData = [
        [
            'id' => 1,
            'prop_id' => 90001,
            'mlsFNo' => 'MLSF-001',
            'kangisFileNo' => 'KF-001',
            'NewKANGISFileno' => null,
            'fileno' => null,
            'temp_fileno' => null,
            'property_description' => 'Model Residential Allocation',
            'file_number_display' => 'KF-001',
            'location' => 'Sunrise Layout, Kano',
            'regNo' => '101/12/55',
            'registration_particulars' => '101/12/55',
            'land_use' => 'Residential',
            'land_use_type' => 'Residential',
            'transaction_type' => 'Assignment',
            'instrument_type' => 'Assignment',
            'transaction_date' => '2025-12-01 10:00:00',
            'created_at' => '2025-12-02 09:15:00',
            'timeline_count' => 2,
            'grantor' => 'Model Development Authority',
            'grantee' => 'Amina Bello'
        ],
        [
            'id' => 2,
            'prop_id' => 90002,
            'mlsFNo' => 'MLSF-002',
            'kangisFileNo' => 'KF-002',
            'NewKANGISFileno' => null,
            'fileno' => null,
            'temp_fileno' => null,
            'property_description' => 'Commercial Warehouse Plot',
            'file_number_display' => 'KF-002',
            'location' => 'Industrial Estate, Kano',
            'regNo' => '212/09/77',
            'registration_particulars' => '212/09/77',
            'land_use' => 'Commercial',
            'land_use_type' => 'Commercial',
            'transaction_type' => 'Mortgage',
            'instrument_type' => 'Mortgage',
            'transaction_date' => '2025-11-28 14:30:00',
            'created_at' => '2025-11-29 08:00:00',
            'timeline_count' => 1,
            'grantor' => 'Alpha Industries Ltd',
            'grantee' => 'Unity Bank Plc'
        ]
    ];

    return response()->json([
        'draw' => 1,
        'recordsTotal' => count($sampleData),
        'recordsFiltered' => count($sampleData),
        'data' => $sampleData
    ]);
})->name('propertycard.data.fallback');
Route::group(['middleware' => 'web'], function () {
    Route::get('/legal_search', [LegalSearchController::class, 'index'])->name('legal_search.index');
    Route::post('/legal_search/search', [LegalSearchController::class, 'search'])->name('legal_search.search');
    Route::get('/legal_search/report', [LegalSearchController::class, 'report'])->name('legal_search.report');
    //Route::post('/legal_search', [LegalSearchController::class, 'store'])->name('legal_search.store');
    Route::get('/legal_search/legal_search_report', [LegalSearchController::class, 'legal_search_report'])->name('legal_search.legal_search_report');
    // Add alias for JS compatibility
    Route::post('/legal_search/search', [LegalSearchController::class, 'search'])->name('legalsearch.search');
});

Route::post('/deeds/insert', [DeedsController::class, 'insert'])->name('deeds.insert');
Route::get('/deeds/getdeedsdublicate', [DeedsController::class, 'getDeedsDublicate'])->name('deeds.getDeedsDublicate');
Route::post('/conveyance/update', [ConveyanceController::class, 'updateConveyance'])->name('conveyance.update');
Route::get('/sectionaltitling/generate-bill/{id?}', [SubApplicationController::class, 'GenerateBill'])->name('sectionaltitling.sub.generate_bill');
Route::get('/sectionaltitling/generate-bill', [SubApplicationController::class, 'GenerateBill'])->name('sectionaltitling.generate_bill_no_id');
Route::get('/subapplications/{id}', [SubApplicationController::class, 'getSubApplication']);
Route::get('sectionaltitling/viewrecorddetail_sub/{id?}', [SubApplicationController::class, 'viewrecorddetail_sub'])->name('sectionaltitling.viewrecorddetail_sub');
// Fix the route definition - we have a duplicate route
Route::post('/sectionaltitling/store-mother-app', [SaveMainAppController::class, 'storeMotherApp'])
    ->name('sectionaltitling.storeMotherApp');
// Remove or comment out the duplicate route
// Route::post('/sectionaltitling', [SaveMainAppController::class, 'storeMotherApp'])->name('sectionaltitling.storeMotherApp');
// Instrument Registration routes
Route::group(['middleware' => ['auth'], 'prefix' => 'instrument_registration'], function () {
    Route::get('/', [InstrumentRegistrationController::class, 'InstrumentRegistration'])->name('instrument_registration.index');
    Route::get('get-batch-data', [InstrumentRegistrationController::class, 'getBatchData']);
    Route::get('get-next-serial', [InstrumentRegistrationController::class, 'getNextSerialNumber']);
    Route::post('register-batch', [InstrumentRegistrationController::class, 'registerBatch']);
    Route::post('register-single', [InstrumentRegistrationController::class, 'registerSingle']);
    Route::get('export', [InstrumentRegistrationController::class, 'exportRegistry']);
    Route::post('decline', [InstrumentRegistrationController::class, 'declineRegistration']);
    Route::get('check-registration-status', [InstrumentRegistrationController::class, 'checkRegistrationStatus']);
    Route::get('file-completion-status', [InstrumentRegistrationController::class, 'getFileCompletionStatus']);
    Route::get('overall-completion-status', [InstrumentRegistrationController::class, 'getOverallCompletionStatus']);
    Route::get('view/{id}', [InstrumentRegistrationController::class, 'view'])->name('instrument_registration.view');
    Route::get('edit/{id}', [InstrumentRegistrationController::class, 'edit'])->name('instrument_registration.edit');
    Route::put('update/{id}', [InstrumentRegistrationController::class, 'update'])->name('instrument_registration.update');
    Route::delete('delete/{id}', [InstrumentRegistrationController::class, 'destroy'])->name('instrument_registration.destroy');
});

// Instrument Types Management
Route::resource('instrument-types', \App\Http\Controllers\InstrumentTypeController::class)->middleware(['auth', 'XSS']);

Route::group(['middleware' => ['auth'], 'prefix' => 'st_deeds'], function () {
    Route::get('/', [StInstrumentRegistrationController::class, 'StInstrumentRegistration'])->name('st_deeds.index');

});
// Instrument routes
Route::group(['middleware' => ['auth'], 'prefix' => 'instruments'], function () {
    Route::get('/', [App\Http\Controllers\InstrumentController::class, 'index'])->name('instruments.index');
    Route::get('/export', [App\Http\Controllers\InstrumentController::class, 'exportCapture'])->name('instruments.export');
    Route::post('/store', [App\Http\Controllers\InstrumentController::class, 'store'])->name('instruments.store');
    Route::get('/create', [App\Http\Controllers\InstrumentController::class, 'create'])->name('instruments.create');
    Route::get('/generate-particulars', [App\Http\Controllers\InstrumentController::class, 'generateParticulars'])->name('instruments.generateParticulars');
    Route::get('/get-lgas/{stateId}', [App\Http\Controllers\InstrumentController::class, 'getLgas'])->name('instruments.getLgas');
    Route::get('/preview-registration-number', [App\Http\Controllers\InstrumentController::class, 'previewRegistrationNumber'])->name('instruments.previewRegistrationNumber');
    Route::get('/get-next-temp-fileno', [App\Http\Controllers\InstrumentController::class, 'getNextTempFileNo'])->name('instruments.getNextTempFileNo');
    Route::get('/{id}', [App\Http\Controllers\InstrumentController::class, 'show'])->name('instruments.show');
    Route::get('/{id}/edit', [App\Http\Controllers\InstrumentController::class, 'edit'])->name('instruments.edit');
    Route::put('/{id}', [App\Http\Controllers\InstrumentController::class, 'update'])->name('instruments.update');
    Route::delete('/{id}', [App\Http\Controllers\InstrumentController::class, 'destroy'])->name('instruments.destroy');
});
// Add a fallback route for debugging
Route::fallback(function () {
    return response('Route not found. Please check the URL.', 404);
});
Route::get('/sectionaltitling/generate_bill_sub/{id?}', [ApplicationMotherController::class, 'GenerateBill2'])->name('sectionaltitling.generate_bill_sub');


// FileIndexing routes
Route::impersonate();

// Check if a fileno has already been indexed
Route::get('/fileindex/check-indexed', [FileIndexingController::class, 'checkIndexed'])->name('fileindex.check-indexed');

Route::post('fileindex/save-cofo', 'App\Http\Controllers\FileIndexingController@saveCofO')->name('fileindex.save-cofo');
Route::post('fileindex/save-transaction', [FileIndexingController::class, 'savePropertyTransaction'])->name('fileindex.save-transaction');

Route::resource('fileindex', 'App\Http\Controllers\FileIndexingController');

// Get shelf for batch
Route::get('/fileindexing/get-shelf-for-batch/{batch}', [FileIndexingController::class, 'getShelfForBatch'])->name('fileindexing.get-shelf-for-batch');

// Get available batches
Route::get('/fileindexing/get-available-batches', [FileIndexingController::class, 'getAvailableBatches'])->name('fileindexing.get-available-batches');

// Get current batch status from actual file_indexings data
Route::get('/fileindexing/get-current-batch-status', [App\Http\Controllers\FileIndexController::class, 'getCurrentBatchStatus'])->name('fileindexing.get-current-batch-status');

// Get all batches for export (including full batches)
Route::get('/fileindexing/get-all-batches-for-export', [App\Http\Controllers\FileIndexController::class, 'getAllBatchesForExport'])->name('fileindexing.get-all-batches-for-export');

// New shelf label selection system routes
Route::get('/fileindexing/available-shelves', [FileIndexingController::class, 'getAvailableShelfLabels'])->name('fileindexing.available-shelves');
Route::get('/fileindexing/get-shelf/{shelfId}', [FileIndexingController::class, 'getShelfById'])->name('fileindexing.get-shelf');
Route::get('/fileindexing/{fileIndexing}/transactions', [FileIndexingController::class, 'transactions'])->name('fileindexing.transactions');

// Distinct batch numbers from file_indexings (for Sign In & Out)
Route::get('/fileindexing/distinct-batches', [FileIndexingController::class, 'distinctBatches'])->name('fileindexing.distinct-batches');

// Debug batches in file_indexings table
Route::get('/fileindexing/debug-batches', [FileIndexingController::class, 'debugBatches'])->name('fileindexing.debug-batches');

// Batch Management System routes
Route::get('/fileindexing/batch-management', [FileIndexingController::class, 'batchManagement'])->name('fileindexing.batch-management');
Route::get('/fileindexing/batch-management-data', [FileIndexingController::class, 'getBatchManagementData'])->name('fileindexing.batch-management-data');
Route::post('/fileindexing/generate-batches', [FileIndexingController::class, 'generateBatches'])->name('fileindexing.generate-batches');
Route::post('/fileindexing/auto-assign-shelves', [FileIndexingController::class, 'autoAssignShelves'])->name('fileindexing.auto-assign-shelves');
Route::post('/fileindexing/run-shelf-cleanup', [FileIndexingController::class, 'runShelfCleanup'])->name('fileindexing.run-shelf-cleanup');

// Sign In & Out report JSON
Route::get('/fileindexing/signin-report', [FileIndexingController::class, 'signinReport'])->name('fileindexing.signin-report');

// Export Sign In & Out report
Route::get('/fileindexing/signin-report/export/{format}', [FileIndexingController::class, 'exportSigninReport'])->name('fileindexing.signin-report.export');

// Standalone Sign In & Out page
Route::get('/fileindexing/signin', [FileIndexingController::class, 'signin'])->name('fileindexing.signin');

// Store file indexing
Route::post('/fileindexing/store', [FileIndexingController::class, 'store'])->name('fileindexing.store');

// File Scanning
Route::get('/filescanning/index', [FileScanningController::class, 'index'])->name('filescanning.index');
Route::get('/filescanning/create', [FileScanningController::class, 'create'])->name('filescanning.create');
Route::get('/scanners', [ScannerController::class, 'getScanners'])->name('scanners.list');
Route::post('/scan', [ScannerController::class, 'scan'])->name('scanners.scan');
Route::post('/webcam-capture', [ScannerController::class, 'captureFromWebcam'])->name('scanners.webcam');

Route::get('/sectionaltitling', [\App\Http\Controllers\SectionalTitlingController::class, 'index'])->name('sectionaltitling.index');
Route::get('/sectionaltitling/primary', [\App\Http\Controllers\SectionalTitlingController::class, 'Primary'])->name('sectionaltitling.primary');
Route::get(
    '/sectionaltitling/primary/{primary}/rofo-units',
    [\App\Http\Controllers\SectionalTitlingController::class, 'getRofoUnits']
)->name('sectionaltitling.primary.rofo-units');
Route::get(
    '/sectionaltitling/primary/{primary}/cofo-units',
    [\App\Http\Controllers\SectionalTitlingController::class, 'getCofoUnits']
)->name('sectionaltitling.primary.cofo-units');
Route::post(
    '/sectionaltitling/primary/{primary}/cofo/batch',
    [CofoController::class, 'batchGenerateCofO']
)->name('sectionaltitling.primary.cofo.batch');
Route::get('/sectionaltitling/mother', [\App\Http\Controllers\SectionalTitlingController::class, 'mother'])->name('sectionaltitling.mother');
Route::get('/sectionaltitling/secondary', [\App\Http\Controllers\SectionalTitlingController::class, 'Secondary'])->name('sectionaltitling.secondary');
Route::get('/sectionaltitling/units', [\App\Http\Controllers\SectionalTitlingController::class, 'Units'])->name('sectionaltitling.units');
Route::get('/sectionaltitling/conveyance', [\App\Http\Controllers\SectionalTitlingController::class, 'conveyance'])->name('sectionaltitling.conveyance');
Route::get('/sectionaltitling/buyer-list/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'getBuyerList'])->name('sectionaltitling.buyerList');
Route::get('/sectionaltitling/cofo-details', [\App\Http\Controllers\SectionalTitlingController::class, 'getCofoDetails'])->name('sectionaltitling.cofo-details');
Route::post('/sectionaltitling/save-cofo-details', [\App\Http\Controllers\SectionalTitlingController::class, 'saveCofoDetails'])->name('sectionaltitling.save-cofo-details');
// Print acknowledgement slip for a primary (mother) application
// Generate and view/print acknowledgement sheet
Route::post('/sectionaltitling/primary/acknowledgement/generate/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'generateAcknowledgement'])
    ->name('sectionaltitling.primary.acknowledgement.generate');
Route::get('/sectionaltitling/primary/acknowledgement/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'printAcknowledgement'])
    ->name('sectionaltitling.primary.acknowledgement');
Route::post('/sectionaltitling/primary/acknowledgement/{id}/mark-printed', [\App\Http\Controllers\SectionalTitlingController::class, 'markAcknowledgementPrinted'])
    ->name('sectionaltitling.primary.acknowledgement.markPrinted');

// Sub-application (Units & SUA) acknowledgement routes
Route::post('/sectionaltitling/sub/acknowledgement/generate/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'generateSubAcknowledgement'])
    ->name('sectionaltitling.sub.acknowledgement.generate');
Route::get('/sectionaltitling/sub/acknowledgement/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'printSubAcknowledgement'])
    ->name('sectionaltitling.sub.acknowledgement');
Route::post('/sectionaltitling/sub/acknowledgement/{id}/mark-printed', [\App\Http\Controllers\SectionalTitlingController::class, 'markSubAcknowledgementPrinted'])
    ->name('sectionaltitling.sub.acknowledgement.markPrinted');
// Soft delete sub-application
Route::delete('/sectionaltitling/sub/{id}', [\App\Http\Controllers\SectionalTitlingController::class, 'deleteSubapplication'])->name('sectionaltitling.sub.delete');
Route::get('/map', [\App\Http\Controllers\SectionalTitlingController::class, 'Map'])->name('map.index');

// Payment filtering route
Route::get('/programmes/payments/filter', [App\Http\Controllers\ProgrammesController::class, 'filterPayments'])->name('programmes.payments.filter');

// Payment action menu routes
Route::get('/programmes/payments/initial-bill-receipt/{fileNo}', [App\Http\Controllers\ProgrammesController::class, 'getInitialBillReceipt'])->name('programmes.payments.initial-bill-receipt');
Route::get('/programmes/payments/betterment-bill-reference/{fileNo}', [App\Http\Controllers\ProgrammesController::class, 'getBettermentBillReference'])->name('programmes.payments.betterment-bill-reference');
Route::post('/programmes/payments/save-betterment-bill-receipt', [App\Http\Controllers\ProgrammesController::class, 'saveBettermentBillReceipt'])->name('programmes.payments.save-betterment-bill-receipt');

// Unit application action menu routes
Route::get('/programmes/payments/bill-balance-reference/{fileNo}', [App\Http\Controllers\ProgrammesController::class, 'getBillBalanceReference'])->name('programmes.payments.bill-balance-reference');
Route::post('/programmes/payments/save-bill-balance-receipt', [App\Http\Controllers\ProgrammesController::class, 'saveBillBalanceReceipt'])->name('programmes.payments.save-bill-balance-receipt');

Route::get('/programmes/memo/{id}', 'App\Http\Controllers\ProgrammeController@viewMemo')->name('programmes.view_memo_detail');
//landing page
Route::get('/landing', [LandingController::class, 'index'])->name('landing.index');
Route::get('planning-recommendation/print/{id}', function ($id) {
    $application = DB::connection('sqlsrv')->table('mother_applications')->where('id', $id)->first();
    if (!$application) {
        abort(404);
    }
    $surveyRecord = DB::connection('sqlsrv')->table('surveyCadastralRecord')
        ->where('application_id', $application->id)
        ->first();

    $dimensionRecords = DB::connection('sqlsrv')
        ->table('site_plan_dimensions')
        ->where('application_id', $application->id)
        ->orderBy('order')
        ->orderBy('id')
        ->get();

    if ($dimensionRecords->isEmpty()) {
        $dimensionRecords = DB::connection('sqlsrv')
            ->table('st_unit_measurements')
            ->where('application_id', $application->id)
            ->orderBy('unit_no')
            ->get();

        if ($dimensionRecords->isNotEmpty()) {
            $dimensionRecords = $dimensionRecords->map(function ($record, $index) {
                return (object) [
                    'sn' => $index + 1,
                    'description' => $record->unit_no ?? null,
                    'dimension' => $record->measurement ?? null,
                    'count' => $record->count ?? 1,
                ];
            });
        }
    }

    if ($dimensionRecords->isEmpty()) {
        $dimensionRecords = DB::connection('sqlsrv')
            ->table('st_unit_measurements')
            ->select('unit_no as description', 'measurement as dimension', 'id', DB::raw('1 as count'))
            ->where('application_id', $application->id)
            ->orderBy('unit_no')
            ->get();
    }

    $sectionRecords = DB::connection('sqlsrv')
        ->table('buyer_list')
        ->where('application_id', $application->id)
        ->whereNotNull('section_number')
        ->select('unit_no', 'section_number')
        ->get();

    $generateUnitVariants = function ($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $variants = [
            $value,
            strtolower($value),
            strtoupper($value),
            preg_replace('/\s+/', '', strtolower($value)),
        ];

        $withoutUnit = trim(preg_replace('/\bunit\b/i', '', $value));
        if ($withoutUnit !== '' && $withoutUnit !== $value) {
            $variants[] = $withoutUnit;
            $variants[] = strtolower($withoutUnit);
            $variants[] = preg_replace('/\s+/', '', strtolower($withoutUnit));
        }

        $numericOnly = preg_replace('/\D+/', '', $value);
        if ($numericOnly !== '') {
            $trimmedNumeric = ltrim($numericOnly, '0');
            $variants[] = $trimmedNumeric !== '' ? $trimmedNumeric : '0';
            $variants[] = $numericOnly;
        }

        return array_values(array_unique(array_filter($variants, function ($variant) {
            return $variant !== '' && $variant !== null;
        })));
    };

    $sectionMap = [];

    foreach ($sectionRecords as $record) {
        $variants = $generateUnitVariants($record->unit_no);
        foreach ($variants as $variant) {
            $sectionMap[$variant] = $record->section_number;
        }
    }

    $dimensionsData = $dimensionRecords->map(function ($record, $index) use ($sectionMap, $generateUnitVariants) {
        $serial = isset($record->sn) && is_numeric($record->sn)
            ? (int) $record->sn
            : ($index + 1);

        $description = $record->description ?? ($record->unit_no ?? null);
        $dimension = $record->dimension ?? ($record->measurement ?? null);

        $sectionValue = $record->section ?? $record->section_no ?? $record->section_number ?? null;

        if (!$sectionValue) {
            $candidates = array_filter([
                $description,
                $record->unit_no ?? null,
            ]);

            foreach ($candidates as $candidate) {
                foreach ($generateUnitVariants($candidate) as $variant) {
                    if (isset($sectionMap[$variant])) {
                        $sectionValue = $sectionMap[$variant];
                        break 2;
                    }
                }
            }
        }

        return [
            'sn' => $serial,
            'description' => $description,
            'dimension' => $dimension,
            'count' => $record->count ?? 1,
            'section' => $sectionValue ?? null,
            'section_number' => $sectionValue ?? null,
        ];
    })->filter(function ($item) {
        return !empty($item['description']) || !empty($item['dimension']);
    })->values()->all();

    $utilitiesData = [];

    $jsiReport = DB::connection('sqlsrv')
        ->table('joint_site_inspection_reports')
        ->where('application_id', $application->id)
        ->first();

    if ($jsiReport && !empty($jsiReport->shared_utilities)) {
        $sharedUtilitiesRaw = $jsiReport->shared_utilities;

        if (is_string($sharedUtilitiesRaw)) {
            $sharedUtilities = json_decode($sharedUtilitiesRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $sharedUtilities = [];
            }
        } elseif (is_array($sharedUtilitiesRaw)) {
            $sharedUtilities = $sharedUtilitiesRaw;
        } else {
            $sharedUtilities = [];
        }

        $utilitiesData = collect($sharedUtilities)->filter()->values()->map(function ($utility, $index) {
            $label = is_array($utility) ? ($utility['utility_type'] ?? reset($utility)) : $utility;

            return [
                'sn' => $index + 1,
                'utility_type' => $label,
                'dimension' => 0,
                'count' => 1,
                'block' => '1',
                'section' => '1',
            ];
        })->toArray();
    }

    if (empty($utilitiesData)) {
        $utilityRecords = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $application->id)
            ->whereNull('sub_application_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $utilitiesData = $utilityRecords->map(function ($record, $index) {
            $utilityType = $record->utility_type ?? $record->name ?? $record->label ?? null;

            if (!$utilityType) {
                return null;
            }

            return [
                'sn' => $index + 1,
                'utility_type' => $utilityType,
                'dimension' => $record->dimension ?? $record->size ?? null,
                'count' => $record->count ?? 1,
                'block' => $record->block ?? '1',
                'section' => $record->section ?? '1',
            ];
        })->filter()->values()->all();
    }

    // Return view with print-specific data
    return view('actions.planning_recomm', [
        'application' => $application,
        'surveyRecord' => $surveyRecord,
        'printMode' => true,
        'dimensionsData' => $dimensionsData,
        'utilitiesData' => $utilitiesData,
    ]);
})->name('planning-recommendation.print');
// Route to mark welcome popup as shown
Route::post('/mark-welcome-popup-shown', function () {
    session(['show_welcome_popup' => false]);
    return response()->json(['success' => true]);
})->middleware('auth')->name('markWelcomePopupShown');
// Add this route wherever your other GIS routes are defined
Route::get('/gis/get-all-units', [GisController::class, 'getAllUnits'])->name('gis.get-all-units');
// COROI routes
Route::get('/coroi', [App\Http\Controllers\CoroiController::class, 'index'])->name('coroi.index');
Route::get('/coroi/search-by-fileno', [App\Http\Controllers\CoroiController::class, 'searchByFileno'])->name('coroi.search.fileno');
Route::get('/coroi/search', function () {
    return view('coroi.search');
})->name('coroi.search');
Route::get('/coroi/demo', function () {
    return view('coroi.demo');
})->name('coroi.demo');
Route::get('/coroi/debug', [App\Http\Controllers\CoroiController::class, 'debug'])->name('coroi.debug');
Route::get('/coroi/test', function () {
    return view('coroi.test');
})->name('coroi.test');
Route::get('/coroi/test-database', [App\Http\Controllers\CoroiController::class, 'testDatabase'])->name('coroi.test.database');
// User role routes for department-based filtering
Route::get('/user-roles/by-department', 'App\Http\Controllers\UserRoleController@getByDepartment')
    ->name('user-roles.by-department');
// Direct route for user roles by department - Fix for AJAX issue
Route::get('/get-roles-by-department/{departmentId}', function ($departmentId) {
    try {
        // Get roles for the specific department
        $departmentRoles = \App\Models\UserRole::where('department_id', $departmentId)
            ->where('is_active', 1)
            ->get(['id', 'name', 'description']);

        // Also include general roles that don't have a specific department
        $generalRoles = \App\Models\UserRole::whereNull('department_id')
            ->where('is_active', 1)
            ->get(['id', 'name', 'description']);

        // Merge and return all roles
        $allRoles = $departmentRoles->merge($generalRoles);

        return response()->json($allRoles);
    } catch (\Exception $e) {
        \Log::error('Error fetching roles for department', [
            'department_id' => $departmentId,
            'error' => $e->getMessage()
        ]);

        return response()->json(['error' => 'Failed to load roles: ' . $e->getMessage()], 500);
    }
})->name('get.roles.by.department');
// Debug routes - only for development
if (app()->environment('local', 'development', 'staging')) {
    Route::get('/debug/roles-departments', [App\Http\Controllers\DebugController::class, 'rolesDepartments']);
}
// Debug route that directly returns roles for a department (bypass controller completely)
Route::get('/debug-roles/{departmentId}', function ($departmentId) {
    try {
        // Log the request for debugging
        \Log::info('Debug route hit', ['department_id' => $departmentId]);

        // Get all user roles (with or without department_id)
        $roles = \App\Models\UserRole::where(function ($query) use ($departmentId) {
            $query->where('department_id', $departmentId)
                ->orWhereNull('department_id');
        })->where('is_active', 1)->get(['id', 'name']);

        // Log what we found
        \Log::info('Roles found', ['count' => $roles->count(), 'roles' => $roles->toArray()]);

        return response()->json($roles);
    } catch (\Exception $e) {
        \Log::error('Error in debug route', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
// Department and User Role Management Routes
Route::group(['middleware' => ['auth', 'XSS']], function () {
    // Department Routes
    Route::resource('departments', 'App\Http\Controllers\DepartmentController');

    // User Role Routes
    Route::resource('user-roles', 'App\Http\Controllers\UserRoleController');
});
// Debug routes for fixing user roles issue
Route::prefix('debug')->group(function () {
    Route::get('/check-roles', 'App\Http\Controllers\DebugController@checkUserRoles');
    Route::get('/add-sample-roles', 'App\Http\Controllers\DebugController@addSampleRoles');

    // Test user types and levels connection
    Route::get('/test-user-types', function () {
        try {
            // Test connection
            $userTypes = \App\Models\UserType::on('sqlsrv')->get();
            $userLevels = \App\Models\UserLevel::on('sqlsrv')->get();

            return response()->json([
                'status' => 'success',
                'user_types_count' => $userTypes->count(),
                'user_levels_count' => $userLevels->count(),
                'user_types' => $userTypes->toArray(),
                'user_levels' => $userLevels->toArray(),
                'operations_levels' => \App\Models\UserLevel::on('sqlsrv')
                    ->whereHas('userType', function ($query) {
                        $query->where('name', 'Operations');
                    })->get()->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
});
Route::get('/print_buyer_list', [ProgrammeController::class, 'printBuyerList']);
Route::get('/print_buyer_list/{applicationId}', [ProgrammeController::class, 'printBuyerList']);
// Final Conveyance Agreement
Route::get('actions/final-conveyance-agreement/{id}/{buyer_id?}', [\App\Http\Controllers\PrimaryActionsController::class, 'finalConveyanceAgreement'])->name('actions.final-conveyance-agreement');
Route::get('actions/final-conveyance/{id}', [\App\Http\Controllers\PrimaryActionsController::class, 'finalConveyance'])->name('actions.final-conveyance');
Route::get('actions/final-conveyance/download-word/{id}', [\App\Http\Controllers\PrimaryActionsController::class, 'downloadFinalConveyanceWord'])->name('actions.final-conveyance.download-word');
Route::post('actions/final-conveyance/upload', [\App\Http\Controllers\PrimaryActionsController::class, 'uploadFinalConveyanceDocument'])->name('actions.final-conveyance.upload');
Route::get('actions/final-conveyance/uploads/{uploadId}', [\App\Http\Controllers\PrimaryActionsController::class, 'downloadFinalConveyanceUpload'])->name('actions.final-conveyance.uploads.download');
Route::post('actions/generate-final-conveyance', [\App\Http\Controllers\PrimaryActionsController::class, 'generateFinalConveyanceDocument'])->name('actions.generate-final-conveyance');
Route::get('actions/get-final-conveyance/{applicationId}', [\App\Http\Controllers\PrimaryActionsController::class, 'getFinalConveyance'])->name('actions.get-final-conveyance');
Route::get('actions/generate-final-conveyance-document/{id}', [\App\Http\Controllers\PrimaryActionsController::class, 'generateFinalConveyanceDocument'])->name('actions.generate-final-conveyance-document');
// Conveyance operations
Route::get('conveyance/{id}', [\App\Http\Controllers\PrimaryActionsController::class, 'getConveyance'])->name('conveyance.get');
Route::post('conveyance/update-buyer', [\App\Http\Controllers\PrimaryActionsController::class, 'updateSingleBuyer'])->name('conveyance.update.buyer');
Route::post('conveyance/delete-buyer', [\App\Http\Controllers\PrimaryActionsController::class, 'deleteBuyer'])->name('conveyance.delete.buyer');
// EDMS Workflow Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'edms'], function () {
    // Main EDMS workflow
    Route::get('/{applicationId}', [\App\Http\Controllers\EdmsController::class, 'index'])->name('edms.index');
    // Sub-application EDMS workflow
    Route::get('/sub/{applicationId}', [\App\Http\Controllers\EdmsController::class, 'index'])->defaults('type', 'sub')->name('edms.sub.index');
    // File Indexing
    Route::get('/create-file-indexing/{applicationId}/{type?}', [\App\Http\Controllers\EdmsController::class, 'createFileIndexing'])->name('edms.create-file-indexing');
    Route::get('/fileindexing/{fileIndexingId}', [\App\Http\Controllers\EdmsController::class, 'fileIndexing'])->where('fileIndexingId', '[0-9]+')->name('edms.fileindexing');
    Route::put('/fileindexing/{fileIndexingId}', [\App\Http\Controllers\EdmsController::class, 'updateFileIndexing'])->where('fileIndexingId', '[0-9]+')->name('edms.update-file-indexing');
    // Scanning
    Route::get('/scanning/{fileIndexingId}', [\App\Http\Controllers\EdmsController::class, 'scanning'])->name('edms.scanning');
    Route::post('/scanning/{fileIndexingId}/upload', [\App\Http\Controllers\EdmsController::class, 'uploadScannedDocuments'])->name('edms.upload-documents');

    // Page Typing Data API Routes (MUST come before parameterized routes)
    Route::get('/pagetyping/get-data', [\App\Http\Controllers\EdmsController::class, 'getPageTypingData'])->name('pagetyping.get-data');
    Route::post('/pagetyping/save-single', [\App\Http\Controllers\EdmsController::class, 'saveSinglePageTyping'])->name('pagetyping.save-single');
    Route::post('/pdf-thumbnail', [\App\Http\Controllers\EdmsController::class, 'getPdfPageThumbnail'])->name('edms.pdf-thumbnail');

    // Page Typing (parameterized routes come after specific routes)
    Route::get('/pagetyping/{fileIndexingId}', [\App\Http\Controllers\EdmsController::class, 'pageTyping'])->name('edms.pagetyping');
    Route::post('/pagetyping/{fileIndexingId}', [\App\Http\Controllers\EdmsController::class, 'savePageTyping'])->name('edms.save-page-typing');
    Route::post('/pagetyping/{fileIndexingId}/save-single', [\App\Http\Controllers\EdmsController::class, 'saveSinglePageTyping'])->name('edms.save-single-page-typing');
    Route::post('/pagetyping/{fileIndexingId}/finish', [\App\Http\Controllers\EdmsController::class, 'finishPageTyping'])->name('edms.finish-page-typing');
    Route::post('/pagetyping/{fileIndexingId}/batch-save', [\App\Http\Controllers\EdmsController::class, 'batchSavePageTyping'])->name('edms.batch-save-page-typing');
    Route::put('/edms/scanning/{scanningId}/update-details', [\App\Http\Controllers\EdmsController::class, 'updateDocumentDetails'])->name('edms.update-document-details');

    // Status API
    Route::get('/status/{applicationId}', [\App\Http\Controllers\EdmsController::class, 'getEdmsStatus'])->name('edms.status');
});
// Public CSV Template Download (no authentication required)
Route::get('/primaryform/template/buyers-csv', [\App\Http\Controllers\PrimaryApplicationController::class, 'downloadBuyersTemplate'])->name('primaryform.template.download');

// NEW LIVEWIRE PRIMARY FORM ROUTES (ACTIVE)
Route::group(['middleware' => ['auth'], 'prefix' => 'primaryform'], function () {
    Route::get('/', [\App\Http\Controllers\PrimaryFormDraftController::class, 'index'])->name('primaryform.index');
    Route::post('/', [\App\Http\Controllers\PrimaryApplicationController::class, 'store'])->name('primaryform.store');

});

// Debug routes for primary form
Route::get('/debug-primary-form', function (Request $request) {
    $landUse = $request->query('landuse', 'COMMERCIAL');

    // Determine the land use code
    $landUseCode = match (strtoupper($landUse)) {
        'COMMERCIAL' => 'COM',
        'INDUSTRIAL' => 'IND',
        'RESIDENTIAL' => 'RES',
        'MIXED' => 'MIXED',
        default => 'COM'
    };

    $currentYear = date('Y');

    // Get current serial from database
    $currentSerial = \DB::connection('sqlsrv')
        ->table('land_use_serials')
        ->where('land_use_type', $landUse)
        ->where('year', $currentYear)
        ->value('current_serial') ?? 0;

    $nextSerial = $currentSerial + 1;
    $npFileNo = "ST-{$landUseCode}-{$currentYear}-{$nextSerial}";

    return response()->json([
        'success' => true,
        'debug_info' => [
            'landUse' => $landUse,
            'landUseCode' => $landUseCode,
            'currentYear' => $currentYear,
            'currentSerial' => $currentSerial,
            'nextSerial' => $nextSerial,
            'npFileNo' => $npFileNo,
            'query_params' => $request->query(),
            'url_used' => $request->fullUrl()
        ]
    ]);
});

// Debug route
Route::get('/htmx-debug', function () {
    return view('htmx-debug');
})->middleware('auth')->name('htmx.debug');
// Betterment Bill Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'gisedms'], function () {
    Route::get('/betterment-bill/show/{id}', [App\Http\Controllers\BettermentBillController::class, 'show'])->name('gisedms.betterment-bill.show');
    Route::post('/betterment-bill/store', [App\Http\Controllers\BettermentBillController::class, 'store'])->name('gisedms.betterment-bill.store');
    Route::get('/betterment-bill/print/{id}', [App\Http\Controllers\BettermentBillController::class, 'printReceipt'])->name('gisedms.betterment-bill.print');
    Route::get('/sub-final-bill/show/{id}', [App\Http\Controllers\SubFinalBillController::class, 'showBill'])->name('gisedms.sub-final-bill.show');
    Route::post('/sub-final-bill/save', [App\Http\Controllers\SubFinalBillController::class, 'saveBill'])->name('gisedms.sub-final-bill.save');
    Route::get('/application-details/{fileId}/{fileType}', [App\Http\Controllers\ProgrammesController::class, 'getApplicationDetails'])->name('gisedms.application-details');
});
// File Number Generation Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'file-numbers'], function () {
    Route::get('/', [App\Http\Controllers\FileNumberController::class, 'index'])->name('file-numbers.index');
    Route::get('/data', [App\Http\Controllers\FileNumberController::class, 'getData'])->name('file-numbers.data');
    Route::get('/test-db', [App\Http\Controllers\FileNumberController::class, 'testDatabase'])->name('file-numbers.test-db');
    Route::get('/debug-data', function () {
        try {
            $data = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select(['id', 'kangisFileNo', 'NewKANGISFileNo', 'FileName', 'mlsfNo', 'created_by', 'created_at'])
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'raw_data' => $data->toArray(),
                'formatted_data' => $data->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: '-',
                        'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: '-',
                        'FileName' => trim($row->FileName ?? '') ?: '-',
                        'mlsfNo' => trim($row->mlsfNo ?? '') ?: '-',
                        'created_by' => trim($row->created_by ?? '') ?: 'System',
                        'created_at' => $row->created_at ?: '-'
                    ];
                })->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    })->name('file-numbers.debug-data');
    Route::get('/next-serial', [App\Http\Controllers\FileNumberController::class, 'getNextSerial'])->name('file-numbers.next-serial');
    Route::get('/existing', [App\Http\Controllers\FileNumberController::class, 'getExistingFileNumbers'])->name('file-numbers.existing');
    Route::post('/store', [App\Http\Controllers\FileNumberController::class, 'store'])->name('file-numbers.store');
    Route::post('/migrate', [App\Http\Controllers\FileNumberController::class, 'migrate'])->name('file-numbers.migrate');
    Route::get('/{id}', [App\Http\Controllers\FileNumberController::class, 'show'])->name('file-numbers.show');
    Route::put('/{id}', [App\Http\Controllers\FileNumberController::class, 'update'])->name('file-numbers.update');
    Route::delete('/{id}', [App\Http\Controllers\FileNumberController::class, 'destroy'])->name('file-numbers.destroy');
    Route::get('/count/total', [App\Http\Controllers\FileNumberController::class, 'getCount'])->name('file-numbers.count');

    // Global File Number Search API Routes
    Route::get('/api/search', [App\Http\Controllers\FileNumberController::class, 'searchFileNumbers'])->name('file-numbers.api.search');
    Route::get('/api/top', [App\Http\Controllers\FileNumberController::class, 'getTopFileNumbers'])->name('file-numbers.api.top');
    Route::get('/api/details/{id}', [App\Http\Controllers\FileNumberController::class, 'getFileNumberDetails'])->name('file-numbers.api.details');
});
 
// File Commissioning Sheet Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'commissioning-sheet'], function () {
    Route::get('/', [App\Http\Controllers\CommissioningSheetController::class, 'index'])->name('commissioning-sheet.index');
    Route::post('/store', [App\Http\Controllers\CommissioningSheetController::class, 'store'])->name('commissioning-sheet.store');
    Route::post('/generate-print', [App\Http\Controllers\CommissioningSheetController::class, 'generateAndPrint'])->name('commissioning-sheet.generate-print');
    Route::get('/print/{id}', [App\Http\Controllers\CommissioningSheetController::class, 'print'])->name('commissioning-sheet.print');
    Route::get('/{id}', [App\Http\Controllers\CommissioningSheetController::class, 'show'])->name('commissioning-sheet.show');
    Route::put('/{id}', [App\Http\Controllers\CommissioningSheetController::class, 'update'])->name('commissioning-sheet.update');
    Route::delete('/{id}', [App\Http\Controllers\CommissioningSheetController::class, 'destroy'])->name('commissioning-sheet.destroy');
});
// Page Typing Debug Routes (main routes are in apps2.php)
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'pagetyping'], function () {
    Route::get('/test-routes', function () {
        return view('pagetyping.test_routes');
    })->name('pagetyping.test');
    Route::get('/debug-database', function () {
        return view('pagetyping.debug_database');
    })->name('pagetyping.debug');
    Route::get('/test-file-urls', function () {
        return view('pagetyping.test_file_urls');
    })->name('pagetyping.test-urls');
    Route::get('/test-pdf-access', function () {
        return view('pagetyping.test_pdf_access');
    })->name('pagetyping.test-pdf');
    Route::get('/pdf-diagnostic', function () {
        return view('pagetyping.pdf_diagnostic');
    })->name('pagetyping.pdf-diagnostic');
    Route::post('/check-pdf-file', function (Request $request) {
        try {
            $filePath = $request->input('file_path');
            $fullPath = file_storage_path('app/public/' . $filePath);

            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File does not exist on server',
                    'details' => ['path' => $fullPath]
                ]);
            }

            $fileSize = filesize($fullPath);
            $mimeType = mime_content_type($fullPath);

            // Read first 100 bytes to check PDF header
            $handle = fopen($fullPath, 'rb');
            $header = fread($handle, 100);
            fclose($handle);

            $isPdf = strpos($header, '%PDF') === 0;
            $pdfVersion = null;
            if (preg_match('/%PDF-(\d\.\d)/', $header, $matches)) {
                $pdfVersion = $matches[1];
            }

            return response()->json([
                'success' => true,
                'message' => 'File exists and analyzed',
                'details' => [
                    'path' => $fullPath,
                    'size' => $fileSize,
                    'mimeType' => $mimeType,
                    'isPdf' => $isPdf,
                    'pdfVersion' => $pdfVersion,
                    'header' => bin2hex(substr($header, 0, 20)),
                    'headerAscii' => substr($header, 0, 20),
                    'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4)
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server-side check failed: ' . $e->getMessage()
            ]);
        }
    })->name('pagetyping.check-pdf');
});
// PropertyCard routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'propertycard'], function () {
    Route::get('/', [App\Http\Controllers\PropertyCardController::class, 'index'])->name('propertycard.index');
    Route::get('/data', [App\Http\Controllers\PropertyCardController::class, 'getData'])->name('propertycard.getData');
    Route::get('/cofo-data', [App\Http\Controllers\PropertyCardController::class, 'getCofOData'])->name('propertycard.getCofOData');
    Route::get('/cofo/{cofo}', [App\Http\Controllers\PropertyCardController::class, 'showCofO'])->name('propertycard.cofo.show');
    Route::get('/create', [App\Http\Controllers\PropertyCardController::class, 'create'])->name('propertycard.create');
    Route::post('/store', [App\Http\Controllers\PropertyCardController::class, 'store'])->name('propertycard.store');
    Route::post('/search', [App\Http\Controllers\PropertyCardController::class, 'search'])->name('propertycard.search');
    Route::post('/save-record', [App\Http\Controllers\PropertyCardController::class, 'savePropertyRecord'])->name('propertycard.saveRecord');
    Route::post('/navigate', [App\Http\Controllers\PropertyCardController::class, 'navigateRecord'])->name('propertycard.navigate');
    Route::get('/record-details', [App\Http\Controllers\PropertyCardController::class, 'getRecordDetails'])->name('propertycard.getRecordDetails');
    Route::get('/capture', [App\Http\Controllers\PropertyCardController::class, 'capture'])->name('propertycard.capture');

    // CofO Records standalone page
    Route::get('/cofo', [App\Http\Controllers\PropertyCardController::class, 'cofoIndex'])->name('propertycard.cofo');

    // AI Assistant routes
    Route::get('/ai', [App\Http\Controllers\PropertyCardController::class, 'aiIndex'])->name('propertycard.ai');
    Route::post('/ai/save', [App\Http\Controllers\PropertyCardController::class, 'saveAiPropertyRecord'])->name('propertycard.ai.save');
});

Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'property-index-card'], function () {
    Route::get('/', [App\Http\Controllers\PropertyIndexCardController::class, 'index'])->name('property_index_card.index');
    Route::get('/data', [App\Http\Controllers\PropertyIndexCardController::class, 'getData'])->name('property_index_card.getData');
    Route::get('/cofo', function () {
        return redirect()->route('propertycard.cofo');
    })->name('property_index_card.cofo');
});

Route::middleware(['auth', 'XSS'])->group(function () {
    Route::get('/file-index-view', [\App\Http\Controllers\FileIndexViewController::class, 'index'])->name('file-index-view.index');
    Route::get('/file-index-view/data', [\App\Http\Controllers\FileIndexViewController::class, 'data'])->name('file-index-view.data');
    Route::get('/file-index-view/{record}/timeline', [\App\Http\Controllers\FileIndexViewController::class, 'timeline'])->name('file-index-view.timeline');
});

Route::middleware(['auth', 'XSS'])->group(function () {
    Route::get('/propid-master', [PropIdMasterController::class, 'index'])->name('propid-master.index');
});

// PropertyRecord routes (for new property record management)
Route::group(['middleware' => ['auth', 'XSS']], function () {
    Route::get('property-records', [App\Http\Controllers\PropertyRecordController::class, 'index'])->name('property-records.index');
    Route::get('property-records/create', [App\Http\Controllers\PropertyRecordController::class, 'create'])->name('property-records.create');
    Route::get('property-records/temp-file-number', [App\Http\Controllers\PropertyRecordController::class, 'fetchTempFileNumber'])->name('property-records.temp-file-number');
    Route::post('property-records/store-from-indexing', [App\Http\Controllers\PropertyRecordController::class, 'storeFromIndexing'])->name('property-records.storeFromIndexing');
    Route::post('property-records', [App\Http\Controllers\PropertyRecordController::class, 'store'])->name('property-records.store');
    Route::get('property-records/{id}', [App\Http\Controllers\PropertyRecordController::class, 'show'])->name('property-records.show');
    Route::get('property-records/{id}/edit', [App\Http\Controllers\PropertyRecordController::class, 'edit'])->name('property-records.edit');
    Route::put('property-records/{id}', [App\Http\Controllers\PropertyRecordController::class, 'update'])->name('property-records.update');
    Route::delete('property-records/{id}', [App\Http\Controllers\PropertyRecordController::class, 'destroy'])->name('property-records.destroy');
});

Route::group(['middleware' => ['auth', 'XSS']], function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');

    Route::prefix('notifications/api')->name('notifications.api.')->group(function () {
        Route::get('/', [NotificationCenterController::class, 'list'])->name('list');
        Route::patch('/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('mark-all-read');
        Route::get('/settings', [NotificationCenterController::class, 'settings'])->name('settings.show');
        Route::put('/settings', [NotificationCenterController::class, 'updateSettings'])->name('settings.update');

        Route::patch('/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('mark-read');
        Route::patch('/{notification}/unread', [NotificationCenterController::class, 'markUnread'])->name('mark-unread');
        Route::delete('/{notification}', [NotificationCenterController::class, 'destroy'])->name('delete');
    });
});

// Test route to debug PropertyRecordController
Route::get('/test-property-controller', function () {
    try {
        $controller = new App\Http\Controllers\PropertyRecordController();
        return response()->json([
            'success' => true,
            'message' => 'PropertyRecordController loaded successfully',
            'methods' => get_class_methods($controller)
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Test form for property record creation
Route::get('/test-property-form', function () {
    return '
    <form action="/test-property-records" method="POST">
        <input type="hidden" name="_token" value="' . csrf_token() . '">
        <input type="text" name="mlsFNo" placeholder="MLS File No" value="TEST123"><br>
        <input type="text" name="property_description" placeholder="Property Description" value="Test Property"><br>
        <button type="submit">Submit</button>
    </form>';
});

// Test route for property record creation (no auth)
Route::post('/test-property-records', [App\Http\Controllers\PropertyRecordController::class, 'store']);

// File Indexing routes - Dynamic API endpoints
// File Indexing API routes (require authentication for AJAX calls)
Route::group(['middleware' => ['web', 'auth', 'XSS'], 'prefix' => 'fileindexing'], function () {
    // API endpoints for dynamic data
    Route::get('/api/pending-files', [UnindexedFileBackendController::class, 'getPendingFiles'])->name('fileindexing.api.pending-files');
    Route::get('/api/indexed-files', [UnindexedFileBackendController::class, 'getIndexedFiles'])->name('fileindexing.api.indexed-files');
    Route::get('/api/indexed-files/quick', [UnindexedFileBackendController::class, 'getIndexedFilesQuick'])->name('fileindexing.api.indexed-files.quick');
    Route::get('/api/statistics', [UnindexedFileBackendController::class, 'getStatistics'])->name('fileindexing.api.statistics');
    Route::get('/api/selected-files-for-ai-insights', [UnindexedFileBackendController::class, 'getSelectedFilesForAiInsights'])->name('fileindexing.api.selected-files-for-ai-insights');
    Route::post('/api/begin-indexing', [UnindexedFileBackendController::class, 'beginIndexing'])->name('fileindexing.api.begin-indexing');
    Route::post('/api/ai-insights', [UnindexedFileBackendController::class, 'getAiInsights'])->name('fileindexing.api.ai-insights');
});
Route::get('/debug-api', function () {
    return view('debug-api');
})->middleware(['auth']);

Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'fileindexing'], function () {
    Route::get('/', [App\Http\Controllers\FileIndexController::class, 'index'])->name('fileindexing.index');
    Route::get('/create', App\Http\Controllers\FileIndexCreatePageController::class)->name('fileindexing.create');

    // Other authenticated routes
    Route::get('/search-applications', [App\Http\Controllers\FileIndexController::class, 'searchApplications'])->name('fileindexing.search-applications');
    Route::get('/check-file-status', [App\Http\Controllers\FileIndexController::class, 'checkFileStatus'])->name('fileindexing.check-file-status');
    Route::get('/list', [App\Http\Controllers\FileIndexController::class, 'getFileIndexingList'])->name('fileindexing.list');

    // Tracking sheet generation routes (specific routes before parameterized routes)
    Route::get('/batch-tracking-sheet', [App\Http\Controllers\FileIndexController::class, 'generateBatchTrackingSheet'])->name('fileindexing.batch-tracking-sheet');
    Route::get('/tracking-sheet/{id}', [App\Http\Controllers\FileIndexController::class, 'generateTrackingSheet'])->name('fileindexing.tracking-sheet');
    Route::get('/print-tracking-sheet/{id}', [App\Http\Controllers\FileIndexController::class, 'printTrackingSheet'])->name('fileindexing.print-tracking-sheet');

    // Smart Batch Tracking Interface
    Route::get('/batch-tracking-interface', [App\Http\Controllers\FileIndexController::class, 'batchTrackingInterface'])->name('fileindexing.batch-tracking-interface');
    Route::post('/bulk-movement-update', [App\Http\Controllers\FileIndexController::class, 'bulkMovementUpdate'])->name('fileindexing.bulk-movement-update');
    Route::get('/movement-history', [App\Http\Controllers\FileIndexController::class, 'getMovementHistory'])->name('fileindexing.movement-history');
    Route::get('/export-movement-history', [App\Http\Controllers\FileIndexController::class, 'exportMovementHistory'])->name('fileindexing.export-movement-history');
    Route::post('/{id}/update-tracking', [App\Http\Controllers\FileIndexController::class, 'updateTrackingLocation'])->name('fileindexing.update-tracking');

    // Batch History API route
    Route::get('/api/batch-history', [App\Http\Controllers\FileIndexController::class, 'getBatchHistory'])->name('fileindexing.api.batch-history');

    // Batch Selection API routes
    Route::get('/api/available-batches', [App\Http\Controllers\FileIndexController::class, 'getAvailableBatches'])->name('fileindexing.api.available-batches');
    Route::get('/api/batch-files/{batchNo}', [App\Http\Controllers\FileIndexController::class, 'getBatchFiles'])->name('fileindexing.api.batch-files');

    // Export routes
    Route::get('/export/batch/{batchNo}', [App\Http\Controllers\FileIndexController::class, 'exportByBatch'])->name('fileindexing.export.batch');
    Route::get('/export/date', [App\Http\Controllers\FileIndexController::class, 'exportByDate'])->name('fileindexing.export.date');

    // Delete file route
    Route::post('/api/delete-file/{id}', [App\Http\Controllers\FileIndexController::class, 'deleteIndexedFile'])->name('fileindexing.api.delete-file');

    // CSV Import routes - MUST come before wildcard routes
    Route::get('/import', [App\Http\Controllers\FileIndexController::class, 'showImportForm'])->name('fileindexing.import.form');
    Route::post('/import/preview', [App\Http\Controllers\FileIndexController::class, 'previewCsv'])->name('fileindexing.import.preview');
    Route::post('/import', [App\Http\Controllers\FileIndexController::class, 'importCsv'])->name('fileindexing.import');
    Route::get('/duplicates/database', [App\Http\Controllers\FileIndexController::class, 'getDatabaseDuplicates'])->name('fileindexing.duplicates.database');
    Route::get('/duplicates/database/details', [App\Http\Controllers\FileIndexController::class, 'getDatabaseDuplicateDetails'])->name('fileindexing.duplicates.database.details');
    Route::get('/duplicates/database/export', [App\Http\Controllers\FileIndexController::class, 'exportDatabaseDuplicates'])->name('fileindexing.duplicates.database.export');
    Route::get('/duplicates/csv/download/{token}', [App\Http\Controllers\FileIndexController::class, 'downloadCleanCsv'])->name('fileindexing.duplicates.csv.download');

    // Parameterized routes (must come last)
    Route::get('/{id}', [App\Http\Controllers\FileIndexingController::class, 'show'])->name('fileindexing.show');
    Route::get('/{id}/edit', [App\Http\Controllers\FileIndexingController::class, 'edit'])->name('fileindexing.edit');
    Route::put('/{id}', [App\Http\Controllers\FileIndexingController::class, 'update'])->name('fileindexing.update');
    Route::delete('/{id}', [App\Http\Controllers\FileIndexController::class, 'destroy'])->name('fileindexing.destroy');
});

Route::middleware(['auth', 'XSS'])->group(function () {
    Route::view(
        '/system-admin/folder-watcher',
        'system-admin.folder-watcher'
    )->name('system-admin.folder-watcher');
});

Route::get(
    '/system-admin/csv-import/file-number',
    [CsvImporterController::class, 'fileNumber']
)->name('system-admin.csv-import.file-number');

Route::get(
    '/system-admin/csv-import/file-indexing',
    [CsvImporterController::class, 'fileIndexing']
)->name('system-admin.csv-import.file-indexing');

// File number search API endpoint for Select2
Route::get('/api/search-file-numbers', [App\Http\Controllers\FileIndexController::class, 'searchFileNumbers'])->name('api.search-file-numbers');

// File Tracker Integration
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'filetracker'], function () {
    Route::get('/', [App\Http\Controllers\FileTrackerController::class, 'index'])->name('filetracker.index');
    Route::get('/create', [App\Http\Controllers\FileTrackerController::class, 'trackingForm'])->name('filetracker.create');
    Route::post('/store', [App\Http\Controllers\FileTrackerController::class, 'store'])->name('filetracker.store');
    Route::post('/store-batch', [App\Http\Controllers\FileTrackerController::class, 'storeBatch'])->name('filetracker.store-batch');
    Route::get('/get-indexed-files', [App\Http\Controllers\FileTrackerController::class, 'getIndexedFiles'])->name('filetracker.get-indexed-files');
});

Route::get('/file-tracker-dashboard', [FileTrackerDashboardController::class, 'index'])
    ->name('file-tracker.dashboard')
    ->middleware(['auth', 'XSS']);

Route::get('/file-tracker-dashboard/notifications', [FileTrackerDashboardApiController::class, 'notifications'])
    ->name('file-tracker-dashboard.notifications')
    ->middleware(['auth', 'XSS']);
Route::post('/file-tracker-dashboard/notifications/{notification}/read', [FileTrackerDashboardApiController::class, 'markNotificationAsRead'])
    ->name('file-tracker-dashboard.notifications.read')
    ->middleware(['auth', 'XSS']);

Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'api/file-tracker-dashboard'], function () {
    Route::get('/overview', [FileTrackerDashboardApiController::class, 'overview'])
        ->name('web.api.file-tracker-dashboard.overview');

    Route::get('/notifications', [FileTrackerDashboardApiController::class, 'notifications'])
        ->name('web.api.file-tracker-dashboard.notifications');
});

// Create File Tracker Page Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'create-file-tracker'], function () {
    Route::get('/', [App\Http\Controllers\CreateFileTrackerController::class, 'index'])->name('create-file-tracker.index');
    Route::post('/store', [App\Http\Controllers\CreateFileTrackerController::class, 'store'])->name('create-file-tracker.store');
    Route::post('/receiving-officers', [App\Http\Controllers\CreateFileTrackerController::class, 'storeReceivingOfficer'])->name('create-file-tracker.receiving-officers.store');
    Route::post('/other-receiving-officers', [App\Http\Controllers\CreateFileTrackerController::class, 'storeOtherReceivingOfficer'])->name('create-file-tracker.other-receiving-officers.store');
    Route::get('/shelf-location', [App\Http\Controllers\CreateFileTrackerController::class, 'shelfLocation'])->name('create-file-tracker.shelf-location');
    Route::get('/list', [App\Http\Controllers\CreateFileTrackerController::class, 'list'])->name('create-file-tracker.list');
    Route::get('/search', [App\Http\Controllers\CreateFileTrackerController::class, 'search'])->name('create-file-tracker.search');
});

// File Tracker API Routes for AJAX calls (Web-based authentication)
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'api/file-trackers'], function () {
    Route::get('/', [App\Http\Controllers\Api\FileTrackerApiController::class, 'index']);
    Route::post('/', [App\Http\Controllers\Api\FileTrackerApiController::class, 'store']);
    Route::get('/dashboard', [App\Http\Controllers\Api\FileTrackerApiController::class, 'dashboard']);
    Route::get('/search', [App\Http\Controllers\Api\FileTrackerApiController::class, 'search']); // Quick Actions - Search
    Route::get('/track/{identifier}', [App\Http\Controllers\Api\FileTrackerApiController::class, 'track']); // Quick Actions - Track by ID/Number
    Route::post('/bulk', [App\Http\Controllers\Api\FileTrackerApiController::class, 'bulk']); // Quick Actions - Bulk Operations
    Route::get('/export', [App\Http\Controllers\Api\FileTrackerApiController::class, 'export']); // Quick Actions - Export Data
    Route::get('/{id}', [App\Http\Controllers\Api\FileTrackerApiController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\Api\FileTrackerApiController::class, 'update']);
    Route::delete('/{id}', [App\Http\Controllers\Api\FileTrackerApiController::class, 'destroy']);
    Route::post('/{id}/movements', [App\Http\Controllers\Api\FileTrackerApiController::class, 'addMovement']);
    Route::post('/{id}/complete-movement', [App\Http\Controllers\Api\FileTrackerApiController::class, 'completeMovement']);
    Route::put('/{id}/logs/{logId}', [App\Http\Controllers\Api\FileTrackerApiController::class, 'updateLogEntry']);
    Route::post('/{id}/accept', [App\Http\Controllers\CreateFileTrackerController::class, 'acceptAssignment']);
    Route::post('/{id}/reject', [App\Http\Controllers\CreateFileTrackerController::class, 'rejectAssignment']);
});

Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'scanning'], function () {
    Route::get('/', [App\Http\Controllers\ScanningController::class, 'index'])->name('scanning.index');
    Route::post('/upload', [App\Http\Controllers\ScanningController::class, 'upload'])->name('scanning.upload');
    Route::post('/upload-unindexed', [App\Http\Controllers\ScanningController::class, 'uploadUnindexed'])->name('scanning.upload-unindexed');
    Route::get('/unindexed', [App\Http\Controllers\ScanningController::class, 'unindexed'])->name('scanning.unindexed');
    Route::get('/unindexed-files', [App\Http\Controllers\ScanningController::class, 'getUnindexedFiles'])->name('scanning.unindexed-files');
    Route::get('/list', [App\Http\Controllers\ScanningController::class, 'list'])->name('scanning.list');
    Route::get('/{id}', [App\Http\Controllers\ScanningController::class, 'view'])->name('scanning.view');
    Route::get('/{id}/details', [App\Http\Controllers\ScanningController::class, 'details'])->name('scanning.details');
    Route::put('/{id}/update', [App\Http\Controllers\ScanningController::class, 'updateDetails'])->name('scanning.update');
    Route::delete('/{id}', [App\Http\Controllers\ScanningController::class, 'delete'])->name('scanning.delete');
    Route::post('/upload-more/{fileIndexingId}', [App\Http\Controllers\ScanningController::class, 'uploadMore'])->name('scanning.upload-more');
});

// Test route for backend connectivity
Route::get('/test-backend', [App\Http\Controllers\TestController::class, 'test'])->name('test.backend');

// Page Typing Integration
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'pagetyping'], function () {
    Route::get('/', [App\Http\Controllers\PageTypingController::class, 'index'])->name('pagetyping.index');
    Route::post('/save', [App\Http\Controllers\PageTypingController::class, 'save'])->name('pagetyping.save');
});

require __DIR__ . '/instrument_batch_fix.php';

Route::get('/api/application-details/{fileno}', [App\Http\Controllers\PrimaryApplicationController::class, 'getApplicationDetails'])->name('api.application-details');

// Blind Scanning Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'blind-scanning'], function () {
    Route::get('/', [App\Http\Controllers\BlindScanningController::class, 'index'])->name('blind-scanning.index');
    Route::post('/', [App\Http\Controllers\BlindScanningController::class, 'migrate'])->name('blind-scanning.migrate');
    Route::post('/create-folder', [App\Http\Controllers\BlindScanningController::class, 'createFolder'])->name('blind-scanning.create-folder');
    Route::post('/store', [App\Http\Controllers\BlindScanningController::class, 'store'])->name('blind-scanning.store');
    Route::get('/list', [App\Http\Controllers\BlindScanningController::class, 'apiList'])->name('blind-scanning.list');
    Route::get('/logs', [App\Http\Controllers\BlindScanningController::class, 'apiLogs'])->name('blind-scanning.logs');
    Route::post('/sync-migrations', [App\Http\Controllers\BlindScanningController::class, 'syncMigrationLog'])->name('blind-scanning.sync-migrations');
    Route::post('/save-image', [App\Http\Controllers\BlindScanningController::class, 'apiSaveImage'])->name('blind-scanning.save-image');
    Route::post('/delete-file', [App\Http\Controllers\BlindScanningController::class, 'apiDeleteFile'])->name('blind-scanning.delete-file');
    Route::post('/convert-to-upload', [App\Http\Controllers\BlindScanningController::class, 'convertToUpload'])->name('blind-scanning.convert-to-upload');
    Route::get('/{id}', [App\Http\Controllers\BlindScanningController::class, 'show'])->where('id', '[0-9]+')->name('blind-scanning.show');
    Route::delete('/{id}', [App\Http\Controllers\BlindScanningController::class, 'destroy'])->where('id', '[0-9]+')->name('blind-scanning.destroy');

    // New migration functionality routes
    Route::post('/migrate', [App\Http\Controllers\BlindScanningController::class, 'migrate'])->name('blind_scan.migrate');
    Route::get('/api/list', [App\Http\Controllers\BlindScanningController::class, 'apiList'])->name('blind_scan.api.list');
    Route::get('/api/logs', [App\Http\Controllers\BlindScanningController::class, 'apiLogs'])->name('blind_scan.api.logs');
    Route::post('/api/save-image', [App\Http\Controllers\BlindScanningController::class, 'apiSaveImage'])->name('blind_scan.api.save-image');
    Route::get('/aggregates', [App\Http\Controllers\BlindScanningController::class, 'aggregates'])->name('blind_scanning.aggregates');
    Route::post('/api/delete-file', [App\Http\Controllers\BlindScanningController::class, 'apiDeleteFile'])->name('blind_scan.api.delete-file');
});

// PTQ Control (Quality Control) Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'ptq-control'], function () {
    Route::get('/', [App\Http\Controllers\PTQController::class, 'index'])->name('ptq-control.index');
    Route::get('/list-pending', [App\Http\Controllers\PTQController::class, 'listPending'])->name('ptq-control.list-pending');
    Route::get('/list-in-progress', [App\Http\Controllers\PTQController::class, 'listInProgress'])->name('ptq-control.list-in-progress');
    Route::get('/list-completed', [App\Http\Controllers\PTQController::class, 'listCompleted'])->name('ptq-control.list-completed');
    Route::get('/qc-details/{fileIndexingId}', [App\Http\Controllers\PTQController::class, 'getQCDetails'])->name('ptq-control.qc-details');
    Route::post('/mark-qc-status', [App\Http\Controllers\PTQController::class, 'markQCStatus'])->name('ptq-control.mark-qc-status');
    Route::post('/override-qc', [App\Http\Controllers\PTQController::class, 'overrideQC'])->name('ptq-control.override-qc');
    Route::post('/batch-qc-operation', [App\Http\Controllers\PTQController::class, 'batchQCOperation'])->name('ptq-control.batch-qc-operation');
    Route::post('/approve-for-archiving', [App\Http\Controllers\PTQController::class, 'approveForArchiving'])->name('ptq-control.approve-for-archiving');
    Route::post('/archive-file', [App\Http\Controllers\PTQController::class, 'archiveFile'])->name('ptq-control.archive-file');
    Route::get('/qc-audit-trail/{fileIndexingId}', [App\Http\Controllers\PTQController::class, 'getQCAuditTrail'])->name('ptq-control.qc-audit-trail');
    Route::get('/qc-stats', [App\Http\Controllers\PTQController::class, 'getQCStats'])->name('ptq-control.qc-stats');
});

// Unindexed Scanning Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'unindexed-scanning'], function () {
    Route::get('/', [App\Http\Controllers\UnindexedScanningController::class, 'index'])->name('unindexed-scanning.index');
    Route::post('/upload', [App\Http\Controllers\UnindexedScanningController::class, 'upload'])->name('unindexed-scanning.upload');
    Route::post('/process-ocr', [App\Http\Controllers\UnindexedScanningController::class, 'processOcr'])->name('unindexed-scanning.process-ocr');
    Route::post('/create-indexing-entry', [App\Http\Controllers\UnindexedScanningController::class, 'createIndexingEntry'])->name('unindexed-scanning.create-indexing-entry');
    Route::get('/files', [App\Http\Controllers\UnindexedScanningController::class, 'getUnindexedFiles'])->name('unindexed-scanning.files');

    // Document preview and metadata routes
    Route::get('/preview/{id}', [App\Http\Controllers\UnindexedScanningController::class, 'getFilePreview'])->name('unindexed-scanning.preview');
    Route::get('/metadata/{id}', [App\Http\Controllers\UnindexedScanningController::class, 'getFileMetadata'])->name('unindexed-scanning.metadata');
    Route::put('/metadata/{id}', [App\Http\Controllers\UnindexedScanningController::class, 'updateFileMetadata'])->name('unindexed-scanning.update-metadata');

    Route::delete('/files/{id}', [App\Http\Controllers\UnindexedScanningController::class, 'deleteUnindexedFile'])->name('unindexed-scanning.delete-file');
});

//-------------------------------Page Typing-------------------------------------------
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'pagetyping'], function () {
    // Main dashboard
    Route::get('/', [App\Http\Controllers\PageTypingController::class, 'index'])->name('pagetyping.index');

    // API routes
    Route::group(['prefix' => 'api'], function () {
        Route::get('/stats', [App\Http\Controllers\PageTypingController::class, 'getStats'])->name('pagetyping.api.stats');
        Route::get('/files', [App\Http\Controllers\PageTypingController::class, 'getFilesByStatus'])->name('pagetyping.api.files');
        Route::get('/pagetype-more-files', [App\Http\Controllers\PageTypingController::class, 'getPageTypeMoreFiles'])->name('pagetyping.api.pagetype-more-files');
        Route::get('/typing-data', [App\Http\Controllers\PageTypingController::class, 'getTypingData'])->name('pagetyping.api.typing-data');
        Route::get('/file-details', [App\Http\Controllers\PageTypingController::class, 'getFileDetails'])->name('pagetyping.api.file-details');
        Route::post('/replace-page', [App\Http\Controllers\PageTypingController::class, 'replacePage'])->name('pagetyping.api.replace-page');
    });

    // Save routes
    Route::post('/save-single', [App\Http\Controllers\PageTypingController::class, 'saveSingle'])->name('pagetyping.save-single');
});

Route::get('/test-serial-api', function () {
    return view('pagetyping.test_serial_api');
})->name('pagetyping.test-serial-api');

// Print Label Routes
Route::prefix('printlabel')->middleware('auth')->group(function () {
    // Main page
    Route::get('/', [App\Http\Controllers\PrintLabelController::class, 'index'])->name('printlabel.index');

    // API routes
    Route::prefix('api')->group(function () {
        Route::get('/files', [App\Http\Controllers\PrintLabelController::class, 'getAvailableFiles'])->name('printlabel.api.files');
        Route::post('/batch', [App\Http\Controllers\PrintLabelController::class, 'createBatch'])->name('printlabel.api.create-batch');
        Route::get('/batches', [App\Http\Controllers\PrintLabelController::class, 'getBatches'])->name('printlabel.api.batches');
        Route::get('/batch/{id}', [App\Http\Controllers\PrintLabelController::class, 'getBatchDetails'])->name('printlabel.api.batch-details');
        Route::get('/batch/{id}/print', [App\Http\Controllers\PrintLabelController::class, 'getBatchForPrinting'])->name('printlabel.api.batch-for-printing');
        Route::patch('/batch/{id}/print', [App\Http\Controllers\PrintLabelController::class, 'markBatchAsPrinted'])->name('printlabel.api.mark-printed');
        Route::delete('/batch/{id}', [App\Http\Controllers\PrintLabelController::class, 'deleteBatch'])->name('printlabel.api.delete-batch');
        Route::get('/statistics', [App\Http\Controllers\PrintLabelController::class, 'getStatistics'])->name('printlabel.api.statistics');
        Route::get('/grouping/preview', [App\Http\Controllers\PrintLabelController::class, 'previewGroupingBatch'])->name('printlabel.api.grouping-preview');
        Route::get('/registry-batches', [App\Http\Controllers\PrintLabelController::class, 'searchRegistryBatches'])->name('printlabel.api.registry-batches');
        Route::get('/rack-label/status', [App\Http\Controllers\PrintLabelController::class, 'getRackLabelStatus'])->name('printlabel.api.rack-label-status');
        Route::get('/registries', [App\Http\Controllers\PrintLabelController::class, 'getPrintableRegistries'])->name('printlabel.api.registries');
        Route::post('/override/lookup', [App\Http\Controllers\PrintLabelController::class, 'lookupFileNumbers'])->name('printlabel.api.override-lookup');
    });

    // Print template route
    Route::get('/print-template', function () {
        return view('printlabel.print-file-lab');
    })->name('printlabel.print-template');
});

// Simple test route for file number API
Route::get('/test-file-api', function () {
    return response()->json([
        'success' => true,
        'message' => 'File number API test route working',
        'timestamp' => now()
    ]);
});

// Debug route for print label API (no auth for testing)
Route::get('/debug-print-label-noauth', function () {
    try {
        $count = \App\Models\FileIndexing::on('sqlsrv')->count();
        $hasModel = class_exists(\App\Models\FileIndexing::class);
        $connectionWorks = \DB::connection('sqlsrv')->select('SELECT 1 as test');

        return response()->json([
            'success' => true,
            'fileIndexing_count' => $count,
            'model_exists' => $hasModel,
            'connection_test' => $connectionWorks
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Test the actual print label API logic (no auth for testing)
Route::get('/test-print-api-fix', function () {
    try {
        // Simulate the same logic as PrintLabelController getAvailableFiles
        $query = \App\Models\FileIndexing::on('sqlsrv')
            ->where(function ($query) {
                $query->whereNull('is_deleted')
                    ->orWhere('is_deleted', false);
            })
            ->whereDoesntHave('printLabelBatchItems')
            ->whereNotNull('batch_no');

        // Check if shelf_rack column exists in grouping table
        $hasShelfRack = \Schema::connection('sqlsrv')->hasColumn('grouping', 'shelf_rack');

        $total = $query->count();
        $files = $query->take(5)->get(); // Just get 5 for testing

        return response()->json([
            'success' => true,
            'message' => 'Print label API test completed successfully',
            'data' => [
                'total_available_files' => $total,
                'has_shelf_rack_column_in_grouping' => $hasShelfRack,
                'sample_files' => $files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_number' => $file->file_number,
                        'file_title' => $file->file_title,
                        'batch_no' => $file->batch_no,
                        'shelf_location' => $file->shelf_location,
                        'tracking_id' => $file->tracking_id,
                    ];
                }),
                'query_logic' => 'Uses whereDoesntHave(printLabelBatchItems) and batch_no not null',
                'test_passed' => true
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Debug route for print label API (with auth)
Route::get('/debug-print-label', function () {
    try {
        $count = \App\Models\FileIndexing::on('sqlsrv')->count();
        $hasModel = class_exists(\App\Models\FileIndexing::class);
        $connectionWorks = \DB::connection('sqlsrv')->select('SELECT 1 as test');

        return response()->json([
            'success' => true,
            'fileIndexing_count' => $count,
            'model_exists' => $hasModel,
            'connection_test' => $connectionWorks,
            'auth_user' => auth()->check() ? auth()->id() : null
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
})->middleware('auth');

// Test file number API without auth
Route::get('/test-file-numbers', function () {
    try {
        $count = \App\Models\FileNumber::active()->count();
        $sample = \App\Models\FileNumber::active()->limit(3)->get(['id', 'kangisFileNo', 'mlsfNo', 'FileName']);

        return response()->json([
            'success' => true,
            'count' => $count,
            'sample' => $sample->map(function ($file) {
                return [
                    'id' => $file->id,
                    'kangis_file_no' => $file->kangisFileNo,
                    'mlsf_no' => $file->mlsfNo,
                    'file_name' => $file->FileName,
                    'status' => 'Active'
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Page Typing Test Routes
Route::get('/test-db-connection', [\App\Http\Controllers\PageTypingTestController::class, 'testDbConnection']);
Route::get('/test-raw-tables', [\App\Http\Controllers\PageTypingTestController::class, 'testRawTables']);
Route::get('/create-pagetyping-tables', [\App\Http\Controllers\PageTypingTestController::class, 'createTables']);

// Debug page typing AJAX (requires auth)
Route::get('/debug-pagetyping', function () {
    return view('debug_pagetyping');
})->middleware(['auth']);

// Test duplicate validation page
Route::get('/test-duplicate-validation', function () {
    return response()->file(base_path('test_duplicate_validation.html'));
});

// Test batch data persistence
Route::get('/test-batch-form', function () {
    return response()->file(base_path('test_batch_form.html'));
});

// Test controller fields debugging
Route::get('/test-controller-fields', function () {
    return response()->file(base_path('test_controller_fields.html'));
});

// Test suite for primary form
Route::get('/test-primary-form-suite', function () {
    return response()->file(base_path('test_primary_form_suite.html'));
});

Route::get('/test-db-structure', function () {
    try {
        $columns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('mother_applications');
        return response()->json([
            'success' => true,
            'table' => 'mother_applications',
            'columns' => $columns,
            'count' => count($columns)
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Test shared areas processing
Route::get('/test-shared-areas', function () {
    return view('test_shared_areas');
});

// API route for getting recent file numbers for duplicate testing
Route::get('/api/recent-file-numbers', function (Illuminate\Http\Request $request) {
    try {
        $limit = min((int) $request->input('limit', 10), 50);

        $recentFiles = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->select('file_number', 'file_title', 'created_at', 'id')
            ->whereNotNull('file_number')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'files' => $recentFiles
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

Route::post('/test-shared-areas', function (Illuminate\Http\Request $request) {
    // Test the same logic as in SecondaryFormController
    $sharedAreas = null;
    if ($request->has('shared_areas') && is_array($request->input('shared_areas'))) {
        $sharedAreasArray = $request->input('shared_areas');

        $debugInfo = [
            'shared_areas_array' => $sharedAreasArray,
            'other_areas_detail' => $request->input('other_areas_detail'),
            'has_other' => in_array('other', $sharedAreasArray),
            'other_detail_filled' => $request->filled('other_areas_detail')
        ];

        // If "other" is selected and other_areas_detail is provided, process the custom areas
        if (in_array('other', $sharedAreasArray) && $request->filled('other_areas_detail')) {
            // Remove "other" from the array
            $sharedAreasArray = array_filter($sharedAreasArray, function ($area) {
                return $area !== 'other';
            });

            // Parse the other_areas_detail and add each area to the array
            $otherAreas = $request->input('other_areas_detail');
            $customAreas = array_map('trim', explode(',', $otherAreas));
            $customAreas = array_filter($customAreas); // Remove empty values

            // Add custom areas to the shared areas array
            $sharedAreasArray = array_merge($sharedAreasArray, $customAreas);

            $debugInfo['custom_areas_parsed'] = $customAreas;
            $debugInfo['final_shared_areas_array'] = $sharedAreasArray;
        }

        $sharedAreas = json_encode(array_values($sharedAreasArray));
        $debugInfo['shared_areas_json'] = $sharedAreas;

        return response()->json([
            'success' => true,
            'debug' => $debugInfo,
            'final_json' => $sharedAreas
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'No shared areas data received'
    ]);

    //$recentEntries = \App\Models\SecondaryForm::orderBy('created_at', 'desc')->take(5)->get();
});
