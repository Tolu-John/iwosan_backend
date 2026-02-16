<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCarerProfileRequest;
use App\Http\Requests\UploadCarerImageRequest;
use App\Http\Resources\CarerLiteResource;
use App\Http\Resources\CarerProfileResource;
use App\Http\Resources\CarerResource;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ProfileImageService;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CarerController extends Controller
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
        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $carer = Carer::where('hospital_id', $currentHospitalId)->get();
            return response(CarerLiteResource::collection($carer), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $carer = Carer::where('id', $currentCarerId)->get();
            return response(CarerLiteResource::collection($carer), 200);
        }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $carer = Carer::with(['user', 'hospital'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->withCount([
                    'appointments as appointments_total_count',
                    'appointments as appointments_completed_count' => function ($query) {
                        $query->where('status', 'completed');
                    },
                    'appointments as appointments_no_show_count' => function ($query) {
                        $query->where('status', 'no_show');
                    },
                    'consultations as consultations_total_count',
                    'consultations as consultations_completed_count' => function ($query) {
                        $query->where('status', 'completed');
                    },
                    'consultations as consultations_no_show_count' => function ($query) {
                        $query->where('status', 'no_show');
                    },
                ])
                ->get();

            return response(CarerProfileResource::collection($carer), 200);
        }

        $carer = collect();

        return response( CarerLiteResource::collection($carer)
        
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
        'address'=>'required',
        'lat' => 'required',
        'lon' => 'required',
        'gender' => 'required'
    ],
        'hospital_id' => 'required',
        'user_id' => 'required',
        'bio' => 'required',
        'position' => 'required',
        'onHome_leave' => 'required',
        'onVirtual_leave' => 'required',
        'code'=>'required',
        'qualifications' => 'required',
        'virtual_day_time' => 'required',
        'home_day_time' => 'required',
        'super_admin_approved'=>'required',
        'admin_approved'=>'required',
        ]);



        

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

    
        $user=User::find($data['user_id']);

        if($user){
        $user->firstname=$data['user']['firstname'];
        $user->lastname=$data['user']['lastname'];
        $user->firedb_id=$data['user']['firedb_id'];
        $user->email=$data['user']['email'];
        $user->phone=$data['user']['phone'];
        $user->gender=$data['user']['gender'];
        $user->address=$data['user']['address'];
        $user->lat=$data['user']['lat'];
        $user->lon=$data['user']['lon'];
        $user->save();
       
        }
        else{

            return response(['message'=> 'user not registered']);

        }


       

        


      //  $Carer=Carer::create($data);
       
            $carer= new Carer();

            $hospital_id=Hospital::select(['id'])->where('code','LIKE','%'.$request['code'].'%')->first();

            $carer->user_id=$data['user_id'];
            $carer->hospital_id=$hospital_id->id;
            $carer->bio=$data['bio'];
            $carer->position=$data['position'];
            $carer->onHome_leave=$data['onHome_leave'];
            $carer->onVirtual_leave=$data['onVirtual_leave'];
            $carer->admin_approved=$data['admin_approved'];
            $carer->super_admin_approved=$data['super_admin_approved'];
            $carer->virtual_day_time=$data['virtual_day_time'];
            $carer->home_day_time=$data['home_day_time'];
            $carer->qualifications=$data['qualifications'];
        
         
            $carer->save();


       

               return response(new CarerLiteResource($carer), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Carer  $carer
     * @return \Illuminate\Http\Response
     */
    public function show(Carer $carer)
    {
        if (is_null($carer)) {
            return $this->sendError('Carer not found.');
            }
        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            if ((int) $carer->id !== (int) $currentCarerId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $carer->loadMissing(['user', 'hospital', 'reviews']);
            return response(new CarerResource($carer), 200);
        } else {
            $this->authorize('view', $carer);
        }

        if ($this->access->currentPatientId()) {
            $carer = Carer::with(['user', 'hospital'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->withCount([
                    'appointments as appointments_total_count',
                    'appointments as appointments_completed_count' => function ($query) {
                        $query->where('status', 'completed');
                    },
                    'appointments as appointments_no_show_count' => function ($query) {
                        $query->where('status', 'no_show');
                    },
                    'consultations as consultations_total_count',
                    'consultations as consultations_completed_count' => function ($query) {
                        $query->where('status', 'completed');
                    },
                    'consultations as consultations_no_show_count' => function ($query) {
                        $query->where('status', 'no_show');
                    },
                ])
                ->findOrFail($carer->id);

            return response(new CarerProfileResource($carer), 200);
        }

        $carer->loadMissing(['user', 'hospital', 'reviews']);

        return response(new CarerResource($carer), 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Carer  $carer
     * @return \Illuminate\Http\Response
     */
    public function edit(Carer $carer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Carer  $carer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCarerProfileRequest $request, Carer $carer)
    {
        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId && (int) $carer->id !== (int) $currentCarerId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validated();
        $this->authorize('update', $carer);

        if ($currentCarerId && (int) $data['hospital_id'] !== (int) $carer->hospital_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $carer_ = $this->profiles->updateCarerProfile($carer, $data, (bool) $currentCarerId);
        
                return response(new CarerLiteResource($carer_), 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Carer  $carer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Carer $carer)
    {
        $this->authorize('update', $carer);

        $carer->delete();
        
         $user = User::find($carer->user_id);

        $util = new DashboardControllerA();
           
        $util ->deletefile("carer_images/",$user->user_img," ");
   
        return response(['message' => 'Deleted']);
    }


    public function UploadCarerImage(UploadCarerImageRequest $request)
    {
        $data = $request->validated();

        $carer = Carer::where('user_id', $data['user_id'])->first();
        if (is_null($carer)) {
            return response(['message' => 'Carer not found.'], 404);
        }

        $currentCarerId = $this->access->currentCarerId();
        if (!$currentCarerId || (int) $carer->id !== (int) $currentCarerId) {
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
            'carer_images',
            $user->firstname . ' ' . $user->lastname
        );

        $this->images->deleteIfReplaced('iwosan_files', 'carer_images', $user->user_img, $filename);

        $user->user_img = $this->images->buildPublicUrl('carer', $filename);
        $user->save();

        $carer = Carer::where('user_id', $user->id)->first();

        return response(new CarerLiteResource($carer), 200);
    }
    
    private function denyCarerWrite(Carer $carer)
    {
        $this->authorize('update', $carer);
    }
}
