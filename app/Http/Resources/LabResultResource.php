<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Patient;
use App\Models\Teletest;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $result = null;
        if ($this->teletest_id) {
            $result = new TeletestResource(Teletest::find($this->teletest_id));
        }
        $carer = null;
        if ($this->carer_id) {
            $carer = new CarerLiteResource(Carer::find($this->carer_id));
        }
        return [
         
            'id'=>(string)$this->id,
                'patient_id'=>$this->patient_id,
                  'patient'=>new PatientLiteResource(Patient::find($this->patient_id)),
                'carer_id'=>$this->carer_id,
                'carer'=>$carer,
                'teletest_id'=>$this->teletest_id,
                'teletest'=>$result,
                'name'=>$this->name,
                'lab_name'=>$this->lab_name,
                'result_picture'=>$this->result_picture,
                'result_pictures'=>array_values(array_filter([
                    $this->result_picture_front,
                    $this->result_picture_back,
                ])),
                'signed_files' => $this->signedFiles(),
                'extra_notes'=>$this->extra_notes,
                'uploaded_at' => $this->uploaded_at,
                'uploaded_by' => $this->uploaded_by,
                'uploaded_role' => $this->uploaded_role,
                'source' => $this->source,
                'updated_at'=>$this->updated_at,
            ];
    }

    private function signedFiles(): array
    {
        $files = array_values(array_filter([
            $this->filenameFromUrl($this->result_picture_front),
            $this->filenameFromUrl($this->result_picture_back),
            $this->filenameFromUrl($this->result_picture),
        ]));

        return array_map(function ($filename) {
            return URL::temporarySignedRoute(
                'files.labresult',
                now()->addMinutes(10),
                ['filename' => $filename]
            );
        }, $files);
    }

    private function filenameFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }
        return basename($path);
    }
}
