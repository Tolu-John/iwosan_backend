<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHospitalProfileRequest;
use App\Http\Requests\UploadHospitalImageRequest;
use App\Http\Resources\HospitalProfileResource;
use App\Http\Resources\HospitalResource;
use App\Models\Hospital;
use App\Services\AccessService;
use App\Services\ProfileImageService;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HospitalController extends Controller
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
            $hospital = Hospital::where('id', $currentHospitalId)->get();
            return response(HospitalResource::collection($hospital), 200);
        }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $hospital = Hospital::with(['carer:id,hospital_id'])->get();
            return response(HospitalProfileResource::collection($hospital), 200);
        }

        $hospital = collect();

        return response(HospitalResource::collection($hospital)
        , 200);
    }

    public function directory(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 25);
        if ($limit < 1) {
            $limit = 25;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $query = Hospital::query()
            ->select(['id', 'name', 'code', 'email'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ], 200);
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
        if (!$this->access->currentHospitalId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required',
                'code'=>'required',
                'address' => 'required',
                'phone' => 'required',
                "firedb_id"=>"required"
            ]);
    
            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }
    

            $data['password']=Hash::make($request->password);
            $data['code']=$request->code;

            $hospital= new Hospital();
            $hospital->name=$data['name'];
            $hospital->firedb_id=$data['firedb_id'];
            $hospital->email=$data['email'];
            $hospital->code=$data['code'];
            $hospital->phone=$data['phone'];
         
         
            $hospital->save();
    
            return response( new HospitalResource($hospital)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Hospital  $hospital
     * @return \Illuminate\Http\Response
     */
    public function show(Hospital $hospital)
    {
        if (is_null($hospital)) {
            return $this->sendError('Hospital not found.');
            }

        $this->authorize('view', $hospital);

        if ($this->access->currentPatientId()) {
            $hospital->loadMissing(['carer:id,hospital_id']);
            return response(new HospitalProfileResource($hospital), 200);
        }

        if (request()->boolean('full')) {
            return response(new HospitalResource($hospital), 200);
        }

        return response()->json([
            'data' => $this->toLitePayload($hospital),
        ], 200);
    }

    public function profileLite($id)
    {
        $hospital = Hospital::find($id);
        if (is_null($hospital)) {
            return response()->json(['message' => 'Hospital not found.'], 404);
        }

        $this->authorize('view', $hospital);

        return response()->json([
            'data' => $this->toLitePayload($hospital),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Hospital  $hospital
     * @return \Illuminate\Http\Response
     */
    public function edit(Hospital $hospital)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hospital  $hospital
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateHospitalProfileRequest $request, $id)
    {
        $data = $request->validated();

        $hospital=Hospital::find($id);
        if (is_null($hospital)) {
            return $this->sendError('Hospital not found.');
        }

        $this->authorize('update', $hospital);

        $hospital = $this->profiles->updateHospitalProfile($hospital, $data);

        return response()->json([
            'data' => $this->toLitePayload($hospital),
        ], 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Hospital  $hospital
     * @return \Illuminate\Http\Response
     */
    public function destroy(Hospital $hospital)
    {
        $deny = $this->denyHospitalWrite($hospital);
        if ($deny) {
            return $deny;
        }

        $hospital->delete();
        return response(['message' => 'Deleted']);
    }


    public function UploadHospitalImage(UploadHospitalImageRequest $request)
    {
        $data = $request->validated();

        $hospital = Hospital::find($data['id']);
        if (is_null($hospital)) {
            return $this->sendError('Hospital not found.');
        }

        $deny = $this->denyHospitalWrite($hospital);
        if ($deny) {
            return $deny;
        }

        if (!$request->file('file')->isValid()) {
            return response('Invalid File', 422);
        }

        $filename = $this->images->storeUserImage(
            $request->file('file'),
            'iwosan_files',
            'hospital_images',
            $hospital->name
        );

        $this->images->deleteIfReplaced('iwosan_files', 'hospital_images', $hospital->hospital_img, $filename);

        $hospital->hospital_img = $this->images->buildPublicUrl('hospital', $filename);
        $hospital->save();

        return response()->json([
            'data' => $this->toLitePayload($hospital),
        ], 200);
    }

    private function toLitePayload(Hospital $hospital): array
    {
        return [
            'id' => (string) $hospital->id,
            'name' => $hospital->name,
            'phone' => $hospital->phone,
            'code' => $hospital->code,
            'about_us' => $hospital->about_us,
            'website' => $hospital->website,
            'hospital_img' => $hospital->hospital_img,
            'email' => $hospital->email,
            'account_number' => $hospital->account_number,
            'account_name' => $hospital->account_name,
            'bank_name' => $hospital->bank_name,
            'bank_code' => $hospital->bank_code,
            'home_visit_price' => $hospital->home_visit_price,
            'virtual_visit_price' => $hospital->virtual_visit_price,
            'virtual_ward_price' => $hospital->virtual_ward_price,
            'lat' => $hospital->lat,
            'lon' => $hospital->lon,
            'address' => $hospital->address,
            'super_admin_approved' => (int) $hospital->super_admin_approved,
            'updated_at' => optional($hospital->updated_at)->toISOString(),
        ];
    }

    private function denyHospitalWrite(Hospital $hospital)
    {
        $this->authorize('update', $hospital);
    }
}
