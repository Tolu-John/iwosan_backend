<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
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
               'type'=>$this->type,
               'type_id'=>$this->type_id,
               'author_id'=>$this->author_id,
               'author_role'=>$this->author_role,
               'meta'=>$this->meta ? json_decode($this->meta, true) : null,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
            
            ];
    }
}
