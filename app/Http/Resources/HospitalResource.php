<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Complaints;
use App\Models\Review;
use App\Models\Teletest;
use App\Models\test;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class HospitalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $carers=Carer::where('hospital_id',$this->id)->get();
        $count=sizeof($carers);
        $sum=0;
        
        foreach ($carers as $carer) {
            $sum+=Review::where('carer_id',$carer->id)->avg('rating');
        }
        
        if($count > 0){

            $rating_result= number_format((float)($sum/$count), 1, '.', '');

        }else{

            $rating_result=0.0;

        }

        $pendingAppointments = [];
        $pendingCarers = [];
        $wards = [];
        $metrics = [];

        $user = $request->user();
        if ($user) {
            $hospitalUser = \App\Models\Hospital::where('user_id', $user->id)
                ->orWhere('firedb_id', $user->firedb_id)
                ->first();
            if ($hospitalUser && (int) $hospitalUser->id === (int) $this->id) {
                $controller = app(\App\Http\Controllers\DashboardControllerA::class);
                $pendingAppointments = $this->unwrapResponse($controller->pendingappointments(request(), $this->id));
                $pendingCarers = $this->unwrapResponse($controller->pendingcarers(request(), $this->id));
                $wards = $this->unwrapResponse($controller->getwardAdmissions(request(), $this->id));
                $metrics = $this->unwrapResponse($controller->hospitalmetrics($this->id));
            }
        }

        
        return [
            'id'=>(string)$this->id,
                'name'=>$this->name,
                'firedb_id'=>$this->firedb_id,
                'phone'=>$this->phone,
                'code'=>$this->code,
               'carers'=>CarerResource::collection($carers),
                 "complaints"=>ComplaintResource::collection(Complaints::where('hospital_id',$this->id)->get()),
                  'teletests'=>TeletestResource::collection(Teletest::where('hospital_id',$this->id)->get()), 
                  'tests'=>TestResource::collection(test::where('hospital_id',$this->id)->get()),
                  'pending_appointments'=> $pendingAppointments,
              'pending_carers'=>$pendingCarers,
              'wards'=>$wards,
              'metrics'=> $metrics,
                'rating'=>$rating_result,
                'about_us'=>$this->about_us,
                'website'=>$this->website,
                'hospital_img'=>$this->hospital_img,
                'email'=>$this->email,
                'account_number'=>$this->account_number,
                'account_name'=>$this->account_name,
                'bank_name'=>$this->bank_name,
                'bank_code'=>$this->bank_code,
                'recipient'=>$this->recipient,
                'home_visit_price'=>$this->home_visit_price,
                'virtual_visit_price'=>$this->virtual_visit_price,
                'virtual_ward_price'=>$this->virtual_ward_price,
                'lat'=>$this->lat,
                'lon'=>$this->lon,
                'address'=>$this->address,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at,
                'super_admin_approved'=>$this->super_admin_approved,
        ];
    }

    private function unwrapResponse($response): array
    {
        if ($response instanceof JsonResponse) {
            return (array) $response->getData(true);
        }

        if ($response instanceof Response) {
            $content = $response->getContent();
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : [];
        }

        return (array) $response;
    }
}
