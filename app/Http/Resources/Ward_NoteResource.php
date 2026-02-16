<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Ward_NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
        
            'id'=>(string)$this->id,
                'ward_id'=>$this->ward_id,
               'text'=>$this->text,
               'note_type'=>$this->note_type,
               'author_id'=>$this->author_id,
               'author_role'=>$this->author_role,
               'recorded_at'=>$this->recorded_at,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
            
            ];
    }
}
