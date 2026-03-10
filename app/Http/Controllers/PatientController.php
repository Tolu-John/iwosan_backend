<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePatientProfileRequest;
use App\Http\Requests\UpdatePatientSettingsRequest;
use App\Http\Requests\UploadPatientImageRequest;
use App\Http\Resources\PatientLiteResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ProfileImageService;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    private AccessService $access;
    private ProfileService $profiles;
    private ProfileImageService $images;

    public function __construct(AccessService $access, ProfileService $profiles, ProfileImageService $images)
    {
        $this->access = $access;
        $this->profiles = $profiles;
        $this->images = $images;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $patientIds = $this->access->accessiblePatientIds();
        if (!empty($patientIds)) {
            $Patient = Patient::whereIn('id', $patientIds)->get();
            return response(PatientResource::collection($Patient), 200);
        }

        $Patient = collect();

        return response(PatientResource::collection($Patient)
        , 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
        $data=$request->all();
        
    $validator = Validator::make($request->all(), [
        'user'=>
        [ 
            'firstname' => 'required',
        'lastname' => 'required',
        'firedb_id'=>'required',
        'email' => 'required',
        'phone' => 'required',
        'gender' => 'required',
        'dob'=>'required',
        'address'=>'required',
        'lat' => 'required',
        'lon' => 'required',
    ],
        'user_id' =>'required',
        'weight'=>'required',
        'bloodtype'=>'required',
        'genotype'=>'required',
        'sugar_level'=>'required',
        'bp_dia'=>'required',
        'bp_sys'=>'required',
        'height'=>'required',



        ]);
        
    


        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        
    }
    
    $user=User::find($data['user']['id']);
        $user->firstname=$data['user']['firstname'];
        $user->lastname=$data['user']['lastname'];
        $user->firedb_id=$data['user']['firedb_id'];
        $user->email=$data['user']['email'];
        $user->age=$data['user']['dob'];
        $user->phone=$data['user']['phone'];
        $user->gender=$data['user']['gender'];
        $user->address=$data['user']['address'];
        $user->lat=$data['user']['lat'];
        $user->lon=$data['user']['lon'];

       
        
        $user->save();

        $patient= new Patient();

        $patient->user_id=$user->id;
        $patient->bloodtype=$data['bloodtype'];
        $patient->genotype=$data['genotype'];
        $patient->temperature=$data['temperature'];
         $patient->sugar_level=$data['sugar_level'];
        $patient->bp_sys=$data['bp_sys'];
        $patient->bp_dia=$data['bp_dia'];
        $patient->weight=$data['weight'];
        $patient->height=$data['height'];

        
     
        $patient->save();
     


        return response( new PatientResource($patient)
        , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function show(Patient $patient)
    {
        if (is_null($patient)) {
            return $this->sendError('Patient not found.');
            }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            if ((int) $patient->id !== (int) $currentPatientId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            $this->authorize('view', $patient);
        }
        
            return response( new PatientResource($patient)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function edit(Patient $patient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePatientProfileRequest $request, Patient $patient)
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $patient->id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validated();
        $patient = $this->profiles->updatePatientProfile($patient, $data);
        
        
        return response( new PatientResource($patient)
        , 200);

    }

    public function settings(Patient $patient)
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $patient->id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response([
            'push_notifications_enabled' => (bool) $patient->push_notifications_enabled,
            'sms_alerts_enabled' => (bool) $patient->sms_alerts_enabled,
            'share_vitals_with_carers' => (bool) $patient->share_vitals_with_carers,
        ], 200);
    }

    public function mySettings()
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $patient = Patient::find($currentPatientId);
        if (!$patient) {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        return response([
            'push_notifications_enabled' => (bool) $patient->push_notifications_enabled,
            'sms_alerts_enabled' => (bool) $patient->sms_alerts_enabled,
            'share_vitals_with_carers' => (bool) $patient->share_vitals_with_carers,
        ], 200);
    }

    public function updateSettings(UpdatePatientSettingsRequest $request, Patient $patient)
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $patient->id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validated();
        if (array_key_exists('push_notifications_enabled', $data)) {
            $patient->push_notifications_enabled = (bool) $data['push_notifications_enabled'];
        }
        if (array_key_exists('sms_alerts_enabled', $data)) {
            $patient->sms_alerts_enabled = (bool) $data['sms_alerts_enabled'];
        }
        if (array_key_exists('share_vitals_with_carers', $data)) {
            $patient->share_vitals_with_carers = (bool) $data['share_vitals_with_carers'];
        }
        $patient->save();

        return response([
            'message' => 'Patient settings updated.',
            'data' => [
                'push_notifications_enabled' => (bool) $patient->push_notifications_enabled,
                'sms_alerts_enabled' => (bool) $patient->sms_alerts_enabled,
                'share_vitals_with_carers' => (bool) $patient->share_vitals_with_carers,
            ],
        ], 200);
    }

    public function updateMySettings(UpdatePatientSettingsRequest $request)
    {
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $patient = Patient::find($currentPatientId);
        if (!$patient) {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        $data = $request->validated();
        if (array_key_exists('push_notifications_enabled', $data)) {
            $patient->push_notifications_enabled = (bool) $data['push_notifications_enabled'];
        }
        if (array_key_exists('sms_alerts_enabled', $data)) {
            $patient->sms_alerts_enabled = (bool) $data['sms_alerts_enabled'];
        }
        if (array_key_exists('share_vitals_with_carers', $data)) {
            $patient->share_vitals_with_carers = (bool) $data['share_vitals_with_carers'];
        }
        $patient->save();

        return response([
            'message' => 'Patient settings updated.',
            'data' => [
                'push_notifications_enabled' => (bool) $patient->push_notifications_enabled,
                'sms_alerts_enabled' => (bool) $patient->sms_alerts_enabled,
                'share_vitals_with_carers' => (bool) $patient->share_vitals_with_carers,
            ],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\Response
     */
    public function destroy(Patient $patient)
    {  
        $this->authorize('update', $patient);
        $patient->delete();
        
        $user = User::find($patient->user_id);

        $util = new DashboardControllerA();
           
        $util ->deletefile("patient_images/",$user->user_img," ");
   
        return response(['message' => 'Deleted']);
    
    }



    public function UploadPatientImage(UploadPatientImageRequest $request)
    {
        $data = $request->validated();

        $patient = Patient::where('user_id', $data['user_id'])->first();
        if (is_null($patient)) {
            return response(['message' => 'Patient not found.'], 404);
        }

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $patient->id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($data['user_id']);
        if (!$user) {
            return response(['message' => 'User not found.'], 404);
        }

        if (!$request->file('file')->isValid()) {
            return response('Invalid File', 422);
        }

        $filename = $this->images->storeUserImage(
            $request->file('file'),
            'iwosan_files',
            'patient_images',
            $user->firstname . ' ' . $user->lastname
        );

        $this->images->deleteIfReplaced('iwosan_files', 'patient_images', $user->user_img, $filename);

        $user->user_img = $this->images->buildPublicUrl('patient', $filename);
        $user->save();

        $patient = Patient::where('user_id', $user->id)->first();

        return response(new PatientLiteResource($patient), 200);
    }

       
    }
