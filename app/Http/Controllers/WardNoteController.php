<?php

namespace App\Http\Controllers;

use App\Http\Resources\Ward_NoteResource;
use App\Models\timeline;
use App\Models\ward;
use App\Models\ward_note;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WardNoteController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
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
            $note = ward_note::whereIn('ward_id', $wardIds)->get();
            return response(Ward_NoteResource::collection($note), 200);
        }

        $note = collect();

        return response( Ward_NoteResource::collection($note)
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
            'note_type' => 'nullable|string|max:50',
            ]);
    
    
    
        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $deny = $this->denyWardAccessById($data['ward_id']);
        if ($deny) {
            return $deny;
        }
    
        
            $note=new ward_note();
    
            $note->ward_id=$data['ward_id'];
            $note->text=$data['text'];
            $note->note_type = $data['note_type'] ?? 'observation';
            $note->author_id = Auth::id();
            $note->author_role = $this->currentActorRole();
            $note->recorded_at = now();
            $note->save();

            // create and save timeline here
           
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => $data['text'],
                'type' => 'note',
                'type_id' => $note->id,
                'author_id' => $note->author_id,
                'author_role' => $note->author_role,
                'meta' => [
                    'note_type' => $note->note_type,
                ],
            ]);


        
            
            return response(new Ward_NoteResource($note)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward_note  $ward_note
     * @return \Illuminate\Http\Response
     */
    public function show(ward_note $ward_note)
    {
        if (is_null($ward_note)) {
            return $this->sendError('Note not found.');
            }

        $deny = $this->denyWardAccessById($ward_note->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Ward_NoteResource($ward_note)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward_note  $ward_note
     * @return \Illuminate\Http\Response
     */
    public function edit(ward_note $ward_note)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward_note  $ward_note
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
        
            'ward_id' => 'required',
            'text' => 'required',
            'note_type' => 'nullable|string|max:50',
            ]);
    
    
    
            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }

            $deny = $this->denyWardAccessById($data['ward_id']);
            if ($deny) {
                return $deny;
            }
    
        
            $note=ward_note::find($id);
            if (is_null($note)) {
                return $this->sendError('Note not found.');
            }

            $note->ward_id=$data['ward_id'];
            $note->text=$data['text'];
            $note->note_type = $data['note_type'] ?? $note->note_type ?? 'observation';
            $note->author_id = Auth::id();
            $note->author_role = $this->currentActorRole();
            $note->recorded_at = now();
            $note->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => $data['text'],
                'type' => 'note',
                'type_id' => $note->id,
                'author_id' => $note->author_id,
                'author_role' => $note->author_role,
                'meta' => [
                    'note_type' => $note->note_type,
                ],
            ]);

        
            
            return response(new Ward_NoteResource($note)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward_note  $ward_note
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward_note $ward_note)
    {
        $deny = $this->denyWardAccessById($ward_note->ward_id);
        if ($deny) {
            return $deny;
        }

        $ward_note->delete();
   
        return response(['message' => 'Deleted']);
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
