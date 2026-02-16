<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimelineResource;
use App\Models\timeline;
use App\Models\ward;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TimelineController extends Controller
{
    private AccessService $access;

    public function __construct(?AccessService $access = null)
    {
        $this->access = $access ?: app(AccessService::class);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $wardIds = $this->access->accessibleWardIds();
        if (!empty($wardIds)) {
            $timeline = timeline::whereIn('ward_id', $wardIds)->get();
            return response(TimelineResource::collection($timeline), 200);
        }

        $timeline = collect();

        return response( TimelineResource::collection($timeline)
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
            'ward_id' => 'required',
            'text' => 'required',
            'type' => 'required',
            'type_id' => 'required',
            'meta' => 'nullable|array',
            ]);
    
    
    
        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $deny = $this->denyWardAccessById($data['ward_id']);
        if ($deny) {
            return $deny;
        }
    
        
            $timeline=new timeline();
    
            
            $timeline->ward_id=$data['ward_id'];
            $timeline->text=$data['text'];
            $timeline->type=$data['type'];
            $timeline->type_id=$data['type_id'];
            $timeline->author_id = Auth::id();
            $timeline->author_role = $this->currentActorRole();
            $timeline->meta = isset($data['meta']) ? json_encode($data['meta']) : null;
            $timeline->save();
           
        
            
            return response(new TimelineResource($timeline)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\timeline  $timeline
     * @return \Illuminate\Http\Response
     */
    public function show(timeline $timeline)
    {
        if (is_null($timeline)) {
            return $this->sendError('timelineerature not found.');
            }

        $deny = $this->denyWardAccessById($timeline->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new TimelineResource($timeline)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\timeline  $timeline
     * @return \Illuminate\Http\Response
     */
    public function edit(timeline $timeline)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\timeline  $timeline
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
            'ward_id' => 'required',
            'text' => 'required',
            'type' => 'required',
            'type_id' => 'required',
            'meta' => 'nullable|array',
            ]);
    
    
    
        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $deny = $this->denyWardAccessById($data['ward_id']);
        if ($deny) {
            return $deny;
        }
    
        
            $timeline= timeline::find($id);
            if (is_null($timeline)) {
                return $this->sendError('timelineerature not found.');
            }
    
            
            $timeline->ward_id=$data['ward_id'];
            $timeline->text=$data['text'];
            $timeline->type=$data['type'];
            $timeline->type_id=$data['type_id'];
            $timeline->author_id = Auth::id();
            $timeline->author_role = $this->currentActorRole();
            $timeline->meta = isset($data['meta']) ? json_encode($data['meta']) : null;
            $timeline->save();

            
           
        
            
            return response(new TimelineResource($timeline)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\timeline  $timeline
     * @return \Illuminate\Http\Response
     */
    public function destroy(timeline $timeline)
    {
        $deny = $this->denyWardAccessById($timeline->ward_id);
        if ($deny) {
            return $deny;
        }

        $timeline->delete();
   
        return response(['message' => 'Deleted']);
    }

    public function localTimelineStorage($data)
    {
        $timeline=new timeline();
        $timeline->ward_id=$data['ward_id'];
        $timeline->text=$data['text'];
        $timeline->type=$data['type'];
        $timeline->type_id=$data['type_id'];
        $timeline->author_id = $data['author_id'] ?? Auth::id();
        $timeline->author_role = $data['author_role'] ?? $this->currentActorRole();
        $timeline->meta = isset($data['meta']) ? json_encode($data['meta']) : null;
        $timeline->save();
    }

    private function denyWardAccessById($wardId)
    {
        $ward = ward::find($wardId);
        if (is_null($ward)) {
            return response()->json(['message' => 'Ward not found.'], 404);
        }

        return $this->access->denyIfFalse($this->access->canAccessWard($ward));
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }
        if ($this->access->currentPatientId()) {
            return 'patient';
        }

        return 'system';
    }
}
