<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentCollection;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\CarerLiteResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\CarerResource;
use App\Http\Resources\ConsultLiteResource;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\DrugResource;
use App\Http\Resources\Gen_VitalResource;
use App\Http\Resources\HospitalResource;
use App\Http\Resources\LabResultResource;
use App\Http\Resources\LabTestResource;
use App\Http\Resources\PatientLiteResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TeletestResource;
use App\Http\Resources\TestResource;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Carer;
use App\Models\Drug;
use App\Models\Gen_Vital;
use App\Models\User;
use App\Models\LabTest;
use App\Models\Hospital;
use App\Models\LabResult;
use App\Models\Payment;
use App\Models\Teletest;
use App\Models\test;
use App\Services\AccessService;
use App\Services\CarerSearchService;
use App\Services\GenVitalService;
use App\Services\LabTechAssignmentService;
use App\Services\PrescriptionService;
use App\Services\TestSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DashboardControllerP extends Controller
{
    private AccessService $access;
    private CarerSearchService $carerSearch;
    private GenVitalService $vitals;
    private LabTechAssignmentService $labTechs;
    private PrescriptionService $prescriptions;
    private TestSearchService $testSearch;

    public function __construct(
        AccessService $access,
        CarerSearchService $carerSearch,
        GenVitalService $vitals,
        LabTechAssignmentService $labTechs,
        PrescriptionService $prescriptions,
        TestSearchService $testSearch
    )
    {
        $this->access = $access;
        $this->carerSearch = $carerSearch;
        $this->vitals = $vitals;
        $this->labTechs = $labTechs;
        $this->prescriptions = $prescriptions;
        $this->testSearch = $testSearch;
    }

    private function requirePatientId($id)
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ((int) $currentPatientId !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $currentPatientId;
    }
   
    public function prescriptions($id){
        $auth = $this->requirePatientId($id);
        if ($auth instanceof \Illuminate\Http\Response) {
            return $auth;
        }
        $result = $this->prescriptions->forPatient((int) $id);

        return response($result, 200);

    }

public function searchcarers(\App\Http\Requests\Carers\SearchCarerRequest $request){
    $result = $this->carerSearch->search($request->validated());

    return response($result, 200);
}






public function showappointmentbypatient($id){
    $auth = $this->requirePatientId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $result_arr=array();

$appointments=Appointment::where('patient_id',$id)
->where('status','!=', 'rejected')
    ->where('status','!=', 'finished')
->orderBy('updated_at', 'desc')
->get();

if (is_null($appointments)) {
    return $this->sendError('Appointment not found.');
    }


    return response( AppointmentResource::collection($appointments)
    , 200);

}


    public function showpatientlabresult($id){
       $auth = $this->requirePatientId($id);
       if ($auth instanceof \Illuminate\Http\Response) {
           return $auth;
       }


       $labresult=LabResult::where('patient_id',$id)
    ->orderBy('updated_at', 'desc')
    ->get();
    
    if (is_null($labresult)) {
        return $this->sendError('Lab Result not found.');
        }
    
   
        return (response(LabResultResource::collection($labresult)
        , 200));

    }



public function myrecordvconsult($id){
 $auth = $this->requirePatientId($id);
 if ($auth instanceof \Illuminate\Http\Response) {
     return $auth;
 }


// vconsult lite resource
 $consultations = Consultation::where('patient_id',$id)
 ->where('treatment_type','Virtual visit')       
 ->orderBy('updated_at', 'desc')->get();

return response(ConsultationResource::collection($consultations)
, 200);
}

public function myrecordhconsult($id){
 $auth = $this->requirePatientId($id);
 if ($auth instanceof \Illuminate\Http\Response) {
     return $auth;
 }

// hconsult lite resource
 
 $consultations = Consultation::where('patient_id', $id)
    ->whereIn('treatment_type', ['Home visit', 'Home visit Admitted'])
    ->orderBy('updated_at', 'desc')
    ->get();


return response(ConsultationResource::collection($consultations)
, 200);
}

public function myrecordteletest($id){
     $auth = $this->requirePatientId($id);
     if ($auth instanceof \Illuminate\Http\Response) {
         return $auth;
     }

     $teletests = Teletest::where('patient_id',$id)     
     ->orderBy('updated_at', 'desc')->get();
    
    
    
    return (response(TeletestResource::collection($teletests)
    , 200));
}

public function searchfortest(\App\Http\Requests\Tests\SearchTestRequest $request){
    $result = $this->testSearch->search($request->validated());

    return response($result, 200);
}

public function carerfortest(\App\Http\Requests\Carers\FindCarerForTestRequest $request){
    $data = $request->validated();

    $results = $this->labTechs->topMatches(
        (int) $data['hospital_id'],
        3,
        $data['lat'] ?? null,
        $data['lon'] ?? null,
        $data['visit_type'] ?? 'home',
        $data['availability'] ?? 'anytime',
        $data['availability_start'] ?? null,
        $data['availability_end'] ?? null
    );
    if (empty($results)) {
        return response(['message' => 'No available lab techs found.'], 404);
    }

    return response([
        'results' => collect($results)->map(function (array $row) {
            return [
                'carer' => new CarerResource($row['carer']),
                'load' => $row['load'],
                'distance_km' => $row['distance_km'],
            ];
        }),
    ], 200);
}


public function PatientLite($id){
    $auth = $this->requirePatientId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $patient= Patient::find($id);

    return (response(new PatientLiteResource($patient),200)) ;

}


public function AllPatientGenVitals(Request $request, $id){
    $auth = $this->requirePatientId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $snapshot = $this->vitals->patientSnapshot((int) $id);
    $view = strtolower((string) $request->query('view', 'compact'));

    if ($view === 'full') {
        return response([
            'patient_id' => $id,
            'latest' => collect($snapshot['latest'])->map(fn ($vital) => new Gen_VitalResource($vital)),
            'series' => collect($snapshot['series'])->map(fn ($list) => Gen_VitalResource::collection($list)),
            'alerts' => Gen_VitalResource::collection($snapshot['alerts']),
        ], 200);
    }

    $latest = collect($snapshot['latest'])
        ->mapWithKeys(function ($vital, $type) {
            $resolvedType = (string) ($vital->type ?: $type);
            if ($resolvedType === 'blood_pressure') {
                $sys = $vital->systolic !== null ? (string) ((float) $vital->systolic) : '';
                $dia = $vital->diastolic !== null ? (string) ((float) $vital->diastolic) : '';
                return [$resolvedType => trim($sys . '/' . $dia, '/')];
            }

            $value = $vital->value_num ?? $vital->value ?? null;
            return [$resolvedType => $value !== null ? (string) $value : ''];
        });

    $series = collect($snapshot['series'])
        ->flatMap(function ($list, $type) {
            return collect($list)->map(function ($vital) use ($type) {
                return [
                    'id' => (int) $vital->id,
                    'type' => (string) ($vital->type ?: $type),
                    'value' => $vital->value_num !== null
                        ? (float) $vital->value_num
                        : ($vital->value !== null ? (string) $vital->value : null),
                    'systolic' => $vital->systolic !== null ? (float) $vital->systolic : null,
                    'diastolic' => $vital->diastolic !== null ? (float) $vital->diastolic : null,
                    'unit' => $vital->unit,
                    'taken_at' => optional($vital->taken_at)->toIso8601String(),
                    'status_flag' => $vital->status_flag,
                ];
            });
        })
        ->sortByDesc('taken_at')
        ->values();

    $alerts = collect($snapshot['alerts'])
        ->map(function ($vital) {
            return [
                'id' => (int) $vital->id,
                'type' => (string) ($vital->type ?: $vital->name),
                'status_flag' => (string) $vital->status_flag,
                'taken_at' => optional($vital->taken_at)->toIso8601String(),
                'value' => $vital->value_num !== null
                    ? (float) $vital->value_num
                    : ($vital->value !== null ? (string) $vital->value : null),
                'systolic' => $vital->systolic !== null ? (float) $vital->systolic : null,
                'diastolic' => $vital->diastolic !== null ? (float) $vital->diastolic : null,
                'unit' => $vital->unit,
            ];
        })
        ->values();

    return response([
        'patient_id' => $id,
        'latest' => $latest,
        'series' => $series,
        'alerts' => $alerts,
    ], 200);

}




public function StoreGenVital(\App\Http\Requests\GenVitals\StoreGenVitalRequest $request){

    $data = $request->validated();
    $auth = $this->requirePatientId($data['patient_id'] ?? 0);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $vital = $this->vitals->create($data, $this->access);
    
    return response(new Gen_VitalResource($vital), 200);
}
    



}
