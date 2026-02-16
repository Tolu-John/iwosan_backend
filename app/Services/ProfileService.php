<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;

class ProfileService
{
    public function updatePatientProfile(Patient $patient, array $data): Patient
    {
        $userData = $data['user'];

        $this->assertUserMatchesPatient($patient, $data);

        $user = User::findOrFail($userData['id']);
        $this->updateUserFromPayload($user, $userData);

        $patient->user_id = $data['user_id'];
        $patient->bloodtype = $data['bloodtype'];
        $patient->genotype = $data['genotype'];
        $patient->temperature = $data['temperature'];
        $patient->sugar_level = $data['sugar_level'];
        $patient->bp_dia = $data['bp_dia'];
        $patient->bp_sys = $data['bp_sys'];
        $patient->weight = $data['weight'];
        $patient->height = $data['height'];
        $patient->kin_name = $data['kin_name'];
        $patient->kin_phone = $data['kin_phone'];
        $patient->kin_address = $data['kin_address'];
        $patient->other_kin_name = $data['other_kin_name'];
        $patient->other_kin_phone = $data['other_kin_phone'];
        $patient->other_kin_address = $data['other_kin_address'];
        $patient->save();

        return $patient;
    }

    public function updateCarerProfile(Carer $carer, array $data, bool $isSelfUpdate): Carer
    {
        $userData = $data['user'];

        $this->assertUserMatchesCarer($carer, $data);

        $user = User::findOrFail($userData['id']);
        $this->updateUserFromPayload($user, $userData);

        $carer->user_id = $data['user_id'];
        $carer->hospital_id = $data['hospital_id'];
        $carer->bio = $data['bio'];
        $carer->position = $data['position'];
        $carer->onHome_leave = $data['onHome_leave'];
        $carer->onVirtual_leave = $data['onVirtual_leave'];
        $carer->virtual_day_time = $data['virtual_day_time'];
        $carer->home_day_time = $data['home_day_time'];
        $carer->qualifications = $data['qualifications'];
        $carer->service_radius_km = $data['service_radius_km'] ?? $carer->service_radius_km;
        $carer->response_time_minutes = $data['response_time_minutes'] ?? $carer->response_time_minutes;

        if ($isSelfUpdate) {
            // prevent self-escalation
            $data['admin_approved'] = $carer->admin_approved;
            $data['super_admin_approved'] = $carer->super_admin_approved;
        }

        $carer->admin_approved = $data['admin_approved'];
        $carer->super_admin_approved = $data['super_admin_approved'];
        $carer->save();

        return $carer;
    }

    public function updateHospitalProfile(Hospital $hospital, array $data): Hospital
    {
        $hospital->name = $data['name'];
        $hospital->about_us = $data['about_us'];
        $hospital->website = $data['website'];
        $hospital->email = $data['email'];
        $hospital->code = $data['code'];
        $hospital->address = $data['address'];
        $hospital->phone = $data['phone'];
        $hospital->lat = $data['lat'];
        $hospital->lon = $data['lon'];
        $hospital->account_number = $data['account_number'];
        $hospital->account_name = $data['account_name'];
        $hospital->bank_name = $data['bank_name'];
        $hospital->bank_code = $data['bank_code'];
        $hospital->save();

        return $hospital;
    }

    private function updateUserFromPayload(User $user, array $userData): void
    {
        $user->firedb_id = $userData['firedb_id'];
        $user->firstname = $userData['firstname'];
        $user->lastname = $userData['lastname'];
        $user->email = $userData['email'];
        $user->age = $userData['dob'] ?? $user->age;
        $user->phone = $userData['phone'];
        $user->gender = $userData['gender'];
        $user->address = $userData['address'];
        $user->lat = $userData['lat'];
        $user->lon = $userData['lon'];
        $user->save();
    }

    private function assertUserMatchesPatient(Patient $patient, array $data): void
    {
        if ((int) $data['user_id'] !== (int) $patient->user_id) {
            abort(403, 'Forbidden');
        }

        if (!isset($data['user']['id']) || (int) $data['user']['id'] !== (int) $patient->user_id) {
            abort(403, 'Forbidden');
        }
    }

    private function assertUserMatchesCarer(Carer $carer, array $data): void
    {
        if ((int) $data['user_id'] !== (int) $carer->user_id) {
            abort(403, 'Forbidden');
        }

        if (!isset($data['user']['id']) || (int) $data['user']['id'] !== (int) $carer->user_id) {
            abort(403, 'Forbidden');
        }
    }
}
