<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthControllerP;
use App\Http\Controllers\AuthControllerC;
use App\Http\Controllers\AuthControllerA;
use App\Http\Controllers\DashboardControllerA;
use App\Http\Controllers\DashboardControllerP;
use App\Http\Controllers\DashboardControllerC;
use App\Http\Controllers\CarerController;
use App\Http\Controllers\ComplaintsController;
use App\Http\Controllers\CommCallController;
use App\Http\Controllers\CommConsentController;
use App\Http\Controllers\CommMessageController;
use App\Http\Controllers\CommStatusController;
use App\Http\Controllers\CommThreadController;
use App\Http\Controllers\CommTemplateController;
use App\Http\Controllers\ComplianceAuditController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\CertliceController;
use App\Http\Controllers\DisclosureRequestController;
use App\Http\Controllers\SecurityIncidentController;
use App\Http\Controllers\GenVitalController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\HConsultationController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalCarerNoteController;
use App\Http\Controllers\LabResultController;
use App\Http\Controllers\LabTestController;
use App\Http\Controllers\OtherVitalsController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentProcessorController;
use App\Http\Controllers\FileAccessController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TeletestController;
use App\Http\Controllers\TransfersController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\VConsultationController;
use App\Http\Controllers\VitalAlertLimitController;
use App\Http\Controllers\ward_dashboard;
use App\Http\Controllers\WardBpController;
use App\Http\Controllers\WardBpDiaController;
use App\Http\Controllers\WardBpSysController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\WardNoteController;
use App\Http\Controllers\WardSugarController;
use App\Http\Controllers\WardTempController;
use App\Http\Controllers\WardWeightController;
use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('throttle:auth')->group(function () {
    Route::post('/forgot_password',  'App\Http\Controllers\ForgotPasswordController');
    Route::post('/forgot_password/check', 'App\Http\Controllers\CodeCheckController');
    Route::post('/forgot_password/reset', 'App\Http\Controllers\ResetPasswordController');
});

Route::post('/payment/webhook', [PaymentWebhookController::class, 'handle']);
Route::post('/v1/payment/webhook', [PaymentWebhookController::class, 'handle']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle']);
Route::post('/v1/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle']);
Route::get('/v1/files/labresult/{id}', [FileAccessController::class, 'labResultUrls'])
    ->middleware(['auth:api', 'phi.log', 'incident.detect', 'platform.consent', 'throttle:files']);
Route::get('/v1/files/certlice/{id}', [FileAccessController::class, 'certliceUrl'])
    ->middleware(['auth:api', 'phi.log', 'incident.detect', 'platform.consent', 'throttle:files']);
Route::get('/files/labresult/{filename}', [FileAccessController::class, 'serveLabResult'])->name('files.labresult')->middleware(['signed', 'throttle:files']);
Route::get('/files/certlices/hospital/{filename}', [FileAccessController::class, 'serveCertliceHospital'])->name('files.certlice.hospital')->middleware(['signed', 'throttle:files']);
Route::get('/files/certlices/carer/{filename}', [FileAccessController::class, 'serveCertliceCarer'])->name('files.certlice.carer')->middleware(['signed', 'throttle:files']);
Route::get('/v1/metrics/daily', [MetricsController::class, 'daily']);

Route::prefix('v1')->middleware('throttle:auth')->group(function () {
    Route::post('/forgot_password',  'App\Http\Controllers\ForgotPasswordController');
    Route::post('/forgot_password/check', 'App\Http\Controllers\CodeCheckController');
    Route::post('/forgot_password/reset', 'App\Http\Controllers\ResetPasswordController');
});





Route::get('storage/carer/{filename}', function ($filename)
{
    $path = 'carer_images/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});

Route::get('storage/patient/{filename}', function ($filename)
{
    $path = 'patient_images/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});

Route::get('storage/hospital/{filename}', function ($filename)
{
    $path = 'hospital_images/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});

Route::get('storage/labresult/{filename}', function ($filename)
{
    $path = 'labresult/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});

Route::get('storage/appointment/{filename}', function ($filename)
{
    $path = 'appointment/'.$filename;
    if (!Storage::disk('iwosan_files')->exists($path)) {
        abort(404);
    }
    return Storage::disk('iwosan_files')->response($path);
});

Route::get('storage/certlices/hospital/{filename}', function ($filename)
{
    $path = 'certlices/hospital/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});

Route::get('storage/certlices/carer/{filename}', function ($filename)
{
    $path = 'certlices/carer/'.$filename;
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return Storage::disk('public')->response($path);
});



    
Route::middleware('throttle:auth')->group(function () {
    Route::post('/patient/register', [AuthControllerP::class, 'register']);
    Route::post('/patient/login', [AuthControllerP::class, 'login']);

    Route::post('/carer/register', [AuthControllerC::class, 'register']);
    Route::post('/carer/login', [AuthControllerC::class, 'login']);

    Route::post('/hospital/register', [AuthControllerA::class, 'register']);
    Route::post('/hospital/login', [AuthControllerA::class, 'login']);
});

Route::prefix('v1')->middleware('throttle:auth')->group(function () {
    Route::get('/hospital/directory', [HospitalController::class, 'directory']);

    Route::post('/patient/register', [AuthControllerP::class, 'register']);
    Route::post('/patient/login', [AuthControllerP::class, 'login']);

    Route::post('/carer/register', [AuthControllerC::class, 'register']);
    Route::post('/carer/login', [AuthControllerC::class, 'login']);

    Route::post('/hospital/register', [AuthControllerA::class, 'register']);
    Route::post('/hospital/login', [AuthControllerA::class, 'login']);
});

Route::middleware('auth:api')->group(function () {
    
    Route::get('/patient/logout', [AuthControllerP::class, 'logout']);
    Route::get('/carer/logout', [AuthControllerC::class, 'logout']);
    Route::get('/hospital/logout', [AuthControllerA::class, 'logout']);

});
Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::get('/patient/logout', [AuthControllerP::class, 'logout']);
    Route::get('/carer/logout', [AuthControllerC::class, 'logout']);
    Route::get('/hospital/logout', [AuthControllerA::class, 'logout']);
    Route::post('/patient/change-password', [AuthControllerP::class, 'changePassword']);
    Route::post('/carer/change-password', [AuthControllerC::class, 'changePassword']);
    Route::post('/hospital/change-password', [AuthControllerA::class, 'changePassword']);
});
Route::middleware(['auth:api', 'phi.log', 'incident.detect', 'platform.consent'])->prefix('v1')->group(function () {

    Route::get('/consents/whatsapp', [CommConsentController::class, 'index']);
    Route::post('/consents/whatsapp', [CommConsentController::class, 'store']);
    Route::post('/consents/platform', [CommConsentController::class, 'storePlatform']);

    Route::post('/disclosures', [DisclosureRequestController::class, 'store']);
    Route::get('/disclosures', [DisclosureRequestController::class, 'index'])->middleware('role.hospital');
    Route::post('/disclosures/{id}/approve', [DisclosureRequestController::class, 'approve'])->middleware('role.hospital');

    Route::middleware(['role.hospital', 'platform.consent'])->group(function () {
        Route::get('/compliance/phi-access-logs', [ComplianceAuditController::class, 'phiAccessLogs']);
        Route::get('/compliance/security-incidents', [ComplianceAuditController::class, 'securityIncidents']);
        Route::patch('/compliance/security-incidents/{id}', [SecurityIncidentController::class, 'update']);
        Route::get('/compliance/encryption-status', [ComplianceAuditController::class, 'encryptionStatus']);
    });

    Route::middleware(['role.hospital', 'platform.consent', 'export.justify', 'disclosure.request'])->group(function () {
        Route::get('/compliance/exports/phi-access-logs', [ComplianceAuditController::class, 'exportPhiAccessLogs']);
        Route::get('/compliance/exports/security-incidents', [ComplianceAuditController::class, 'exportSecurityIncidents']);
    });
    
    // admin dashboard
    Route::middleware('role.hospital')->group(function () {
        Route::get('/hospital/tests/{id}', [DashboardControllerA::class, 'getalltests']);
    
        Route::get('/hospital/hconsultations/{id}', [DashboardControllerA::class, 'getallhconsultation']);

        Route::get('/hospital/vconsultations/{id}', [DashboardControllerA::class, 'getallvconsultation']);

        Route::get('/hospital/teletests/{id}', [DashboardControllerA::class, 'getallteletests']);

        Route::get('/hospital/carers/{id}', [DashboardControllerA::class, 'getallcarers']);
        Route::post('/hospital/carer/{carerId}/review', [DashboardControllerA::class, 'reviewCarer']);
        Route::get('/hospital/carer/{carerId}/approvals', [DashboardControllerA::class, 'carerApprovalHistory']);
        Route::get('/hospital/carer/{carerId}/notes', [HospitalCarerNoteController::class, 'index']);
        Route::post('/hospital/carer/{carerId}/notes', [HospitalCarerNoteController::class, 'store']);

        Route::get('/hospital/complaints/{id}', [DashboardControllerA::class, 'getcomplaints']);

        Route::get('/hospital/pendingappointments/{id}', [DashboardControllerA::class, 'pendingappointments']);

        Route::get('/hospital/pendingcarers/{id}', [DashboardControllerA::class, 'pendingcarers']);


        Route::get('/hospital/lite/{id}', [DashboardControllerA::class, 'HospitalLite']);

        Route::post('/hospital/updateprices/{id}', [DashboardControllerA::class, 'UpdateHospitalPrices']);
        Route::get('/hospital/price-history/{id}', [DashboardControllerA::class, 'getHospitalPriceHistory']);


        Route::get('/hospital/metrics/{id}', [DashboardControllerA::class, 'hospitalmetrics']);

    Route::get('/hospital/carer_metrics/{id}', [DashboardControllerA::class, 'carermetrics']);

        Route::get('/hospital/certlices/{id}', [DashboardControllerA::class, 'hospital_certs']);
        Route::get('/hospital/profile-lite/{id}', [HospitalController::class, 'profileLite']);
    });




    // carer dashboard
    Route::middleware('role.carer')->group(function () {
        Route::post('/carer/reapply-approval', [CarerController::class, 'reapplyApproval']);
        Route::get('/carer/{carer}/appointment-settings', [CarerController::class, 'appointmentSettings']);
        Route::put('/carer/{carer}/appointment-settings', [CarerController::class, 'updateAppointmentSettings']);
        Route::get('/carer/showappointment/{id}', [DashboardControllerC::class, 'showappointment']);
    
        Route::get('/carer/showpayments/{id}', [DashboardControllerC::class, 'showpaymentsbymypatients']);
        Route::get('/carer/patients/{id}', [DashboardControllerC::class, 'carerPatients']);

    Route::get('/comm/threads', [CommThreadController::class, 'index']);
    Route::post('/comm/threads', [CommThreadController::class, 'store']);
    Route::get('/comm/threads/{id}', [CommThreadController::class, 'show']);
    Route::get('/comm/messages', [CommMessageController::class, 'index']);
    Route::post('/comm/messages', [CommMessageController::class, 'store']);
    Route::post('/comm/messages/send', [CommMessageController::class, 'send']);
    Route::post('/comm/calls', [CommCallController::class, 'store']);
    Route::get('/comm/status-summary', [CommStatusController::class, 'summary']);

    Route::middleware('role.hospital')->group(function () {
        Route::get('/comm/templates', [CommTemplateController::class, 'index']);
        Route::post('/comm/templates', [CommTemplateController::class, 'store']);
        Route::patch('/comm/templates/{id}', [CommTemplateController::class, 'update']);
        Route::delete('/comm/templates/{id}', [CommTemplateController::class, 'destroy']);
    });

        Route::get('/carer/metrics/{id}', [DashboardControllerC::class, 'carerMetrics']);

        Route::get('/carer/allvconsults/{id}', [DashboardControllerC::class, 'myrecordvconsult']);

        Route::get('/carer/allhconsults/{id}', [DashboardControllerC::class, 'myrecordhconsult']);

        Route::get('/carer/allteletests/{id}', [DashboardControllerC::class, 'myrecordteletest']);
   
        Route::get('/carer/certlices/{id}', [DashboardControllerC::class, 'carer_certs']);

        Route::get('/carer/lite/{id}', [DashboardControllerC::class, 'CarerLite']);

        Route::get('/carer/reviews/{id}', [DashboardControllerC::class, 'carer_reviews']);
    });


    // patient dashboard
    Route::middleware('role.patient')->group(function () {
        Route::get('/patient/settings', [PatientController::class, 'mySettings']);
        Route::put('/patient/settings', [PatientController::class, 'updateMySettings']);
        Route::get('/patient/{patient}/settings', [PatientController::class, 'settings']);
        Route::put('/patient/{patient}/settings', [PatientController::class, 'updateSettings']);

        Route::get('/patient/prescriptions/{id}', [DashboardControllerP::class, 'prescriptions']);

        Route::get('/patient/search_carers', [DashboardControllerP::class, 'searchcarers']);

        Route::get('/patient/appointments/{id}', [DashboardControllerP::class, 'showappointmentbypatient']);

        Route::get('/patient/labresults/{id}', [DashboardControllerP::class, 'showpatientlabresult']);

        Route::get('/patient/vconsult/{id}', [DashboardControllerP::class, 'myrecordvconsult']);

        Route::get('/patient/hconsult/{id}', [DashboardControllerP::class, 'myrecordhconsult']);

        Route::get('/patient/teletest/{id}', [DashboardControllerP::class, 'myrecordteletest']);

        Route::get('/patient/search_test', [DashboardControllerP::class, 'searchfortest']);

        Route::get('/patient/carer_test/{id}', [DashboardControllerP::class, 'carerfortest']);
  
        Route::get('/patient/lite/{id}', [DashboardControllerP::class, 'PatientLite']);
    
        Route::get('/patient/vitals/{id}', [DashboardControllerP::class, 'AllPatientGenVitals']);

        Route::post('/patient/storevital', [DashboardControllerP::class, 'StoreGenVital']);
    });


    //ward routes
    Route::middleware('role.carer')->group(function () {
        Route::get('/carer/wards/{id}', [DashboardControllerC::class, 'getwardAdmissions']);
    });
    Route::middleware('role.hospital')->group(function () {
        Route::get('/hospital/wards/{id}', [DashboardControllerA::class, 'getwardAdmissions']);
    });
//
    Route::get('/ward/vitals/{id}', [ward_dashboard::class, 'getPatientVitals']);
    Route::get('/v1/ward/vitals/{id}', [ward_dashboard::class, 'getPatientVitals']);
    //
    Route::post('/ward/updatevitals/{id}', [ward_dashboard::class, 'UpdateWardVitals']);
   //
    Route::get('/ward/dashboard/{id}', [ward_dashboard::class, 'getWardDashboard']);
    Route::get('/v1/ward/dashboard/{id}', [ward_dashboard::class, 'getWardDashboard']);
    Route::get('/v1/ward/{id}/vitals/audit', [ward_dashboard::class, 'vitalsAudit']);
    Route::get('/v1/ward/{id}/audit', [WardController::class, 'audit']);
    Route::get('/ward/timeline/{id}', [ward_dashboard::class, 'getWardTimeline']);
    //
    Route::get('/ward/prescriptions/{id}', [ward_dashboard::class, 'getWardPrescriptions']);
    Route::get('/v1/ward/prescriptions/{id}', [ward_dashboard::class, 'getWardPrescriptions']);
//
 
//
Route::resource('/othervitals', 'App\Http\Controllers\OtherVitalsController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/wardtimeline', 'App\Http\Controllers\TimelineController', ['except' => ['create', 'edit']]);
//
//

Route::resource('/wardbpsys', 'App\Http\Controllers\WardBpSysController', ['except' => ['create', 'edit']]);
//

Route::resource('/wardbpdia', 'App\Http\Controllers\WardBpDiaController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/wardnote', 'App\Http\Controllers\WardNoteController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/wardsugar', 'App\Http\Controllers\WardSugarController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/wardtemp', 'App\Http\Controllers\WardTempController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/wardweight', 'App\Http\Controllers\WardWeightController', ['except' => ['create', 'edit']]);
//
//
Route::resource('/ward', 'App\Http\Controllers\WardController', ['except' => ['create', 'edit']]);
//




    //token
    Route::post('/agora/token', [DashboardControllerA::class, 'token']);


////

    Route::post('/patient/uploadimage/', [PatientController::class, 'UploadPatientImage']);

    Route::post('/carer/uploadimage', [CarerController::class, 'UploadCarerImage']);
   
    Route::post('/hospital/uploadimage', [HospitalController::class, 'UploadHospitalImage']);
    


    // paystack payment processing
    Route::middleware(['throttle:payments', 'idempotency'])->group(function () {
        Route::post('/paymentprocessing/initialize', [PaymentProcessorController::class, 'initialize_payment']);
        Route::post('/paymentprocessing/verify_payment', [PaymentProcessorController::class, 'verify_payment']);

        Route::post('/paymentprocessing/create_transfer_recipient', [PaymentProcessorController::class, 'create_transfer_recipient']);

        Route::post('/paymentprocessing/resolve_account_details', [PaymentProcessorController::class, 'resolve_account_details']);

        Route::post('/paymentprocessing/update_transfer_recipient', [PaymentProcessorController::class, 'update_transfer_recipient']);

        Route::post('/paymentprocessing/delete_transfer_recipient', [PaymentProcessorController::class, 'delete_transfer_recipient']);

        Route::post('/paymentprocessing/initiate_transfer', [PaymentProcessorController::class, 'initiate_transfer']);

        Route::post('/paymentprocessing/finalize_transfer', [PaymentProcessorController::class, 'finalize_transfer']);

        Route::post('/paymentprocessing/verify_transfer', [PaymentProcessorController::class, 'verify_transfer']);

        Route::post('/paymentprocessing/create_refund', [PaymentProcessorController::class, 'create_refund']);

        Route::post('/paymentprocessing/fetch_refund', [PaymentProcessorController::class, 'fetch_refund']);
    });

    


Route::post('/appointment/{id}/actions/{actionKey}', [AppointmentController::class, 'action']);
Route::post('/appointment/{id}/virtual-actions/{actionKey}', [AppointmentController::class, 'virtualAction']);
Route::resource('/appointment', 'App\Http\Controllers\AppointmentController', ['except' => ['create', 'edit']]);


Route::post('/certlice/{id}/review', [CertliceController::class, 'review']);
Route::resource('/certlice', 'App\Http\Controllers\CertliceController', ['except' => ['create', 'edit']]);


Route::resource('/genvital', 'App\Http\Controllers\GenVitalController', ['except' => ['create', 'edit']]);
Route::get('/genvital/{id}/audit', [GenVitalController::class, 'audit']);


Route::apiResource('/carer', CarerController::class);


Route::apiResource('/drug', DrugController::class);


Route::apiResource('/complaints', ComplaintsController::class);
Route::patch('/complaints/{id}/status', [ComplaintsController::class, 'updateStatus']);
Route::get('/complaints/{id}/audit', [ComplaintsController::class, 'audit']);


Route::apiResource('/consultation', ConsultationController::class);


Route::apiResource('/hconsultation', HConsultationController::class);


Route::apiResource('/vconsultation', VConsultationController::class);


Route::apiResource('/hospital', HospitalController::class);


Route::get('/labresult/{id}/files', [LabResultController::class, 'files']);
Route::post('/labresult/{id}/restore', [LabResultController::class, 'restore']);
Route::get('/v1/labresult/{id}/files', [LabResultController::class, 'files']);
Route::post('/v1/labresult/{id}/restore', [LabResultController::class, 'restore']);
Route::get('/v1/labresult/{id}/audit', [LabResultController::class, 'audit']);
Route::apiResource('/labresult', LabResultController::class);


Route::apiResource('/labtest', LabTestController::class);


Route::apiResource('/patient', PatientController::class);


Route::get('/payment', [PaymentController::class, 'index']);
Route::get('/payment/{payment}', [PaymentController::class, 'show']);
Route::post('/payment', [PaymentController::class, 'store'])->middleware('idempotency');
Route::put('/payment/{payment}', [PaymentController::class, 'update'])->middleware('idempotency');
Route::patch('/payment/{payment}', [PaymentController::class, 'update'])->middleware('idempotency');
Route::delete('/payment/{payment}', [PaymentController::class, 'destroy'])->middleware('idempotency');


Route::apiResource('/review', ReviewController::class);
Route::post('/review/{id}/respond', [ReviewController::class, 'respond']);
Route::get('/review/{id}/audit', [ReviewController::class, 'audit']);

Route::patch('/transfer/{id}/status', [TransfersController::class, 'update']);


Route::apiResource('/teletest', TeletestController::class);
Route::post('/teletest/{teletest}/actions/{actionKey}', [TeletestController::class, 'runAction']);
Route::post('/v1/teletest/{teletest}/actions/{actionKey}', [TeletestController::class, 'runAction']);
Route::get('/teletest/{teletest}/timeline', [TeletestController::class, 'timeline']);
Route::get('/v1/teletest/{teletest}/timeline', [TeletestController::class, 'timeline']);


Route::patch('/test/{id}/status', [TestController::class, 'updateStatus']);
Route::post('/test/{id}/duplicate', [TestController::class, 'duplicate']);
Route::apiResource('/test', TestController::class);


Route::apiResource('/transfer', TransfersController::class)->except(['index', 'show'])->middleware('idempotency');
Route::get('/transfer', [TransfersController::class, 'index']);
Route::get('/transfer/{transfer}', [TransfersController::class, 'show']);





});
