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
        $hospital = Hospital::find($data['hospital_id']);

        $this->assertUserMatchesCarer($carer, $data);

        // If a carer does not provide profile location fields, inherit hospital location.
        if ($hospital) {
            if ($this->isBlank($userData['address'] ?? null)) {
                $userData['address'] = $hospital->address;
            }
            if ($this->isBlank($userData['lat'] ?? null)) {
                $userData['lat'] = $hospital->lat;
            }
            if ($this->isBlank($userData['lon'] ?? null)) {
                $userData['lon'] = $hospital->lon;
            }
        }

        $user = User::findOrFail($userData['id']);
        $this->updateUserFromPayload($user, $userData);

        $carer->user_id = $data['user_id'];
        $carer->hospital_id = $data['hospital_id'];
        $carer->bio = $data['bio'];
        $carer->position = $data['position'];
        if (array_key_exists('onHome_leave', $data) && !$this->isBlank($data['onHome_leave'])) {
            $carer->onHome_leave = $data['onHome_leave'];
        }
        if (array_key_exists('onVirtual_leave', $data) && !$this->isBlank($data['onVirtual_leave'])) {
            $carer->onVirtual_leave = $data['onVirtual_leave'];
        }
        if (array_key_exists('virtual_day_time', $data) && !$this->isBlank($data['virtual_day_time'])) {
            $carer->virtual_day_time = $data['virtual_day_time'];
        }
        if (array_key_exists('home_day_time', $data) && !$this->isBlank($data['home_day_time'])) {
            $carer->home_day_time = $data['home_day_time'];
        }
        $carer->primary_qualification = $data['primary_qualification'] ?? $carer->primary_qualification;
        $carer->specialties = $this->normalizeSpecialties($data['specialties'] ?? $carer->specialties);
        $carer->license_number = $data['license_number'] ?? $carer->license_number;
        $carer->issuing_body = $data['issuing_body'] ?? $carer->issuing_body;
        $carer->years_experience = $data['years_experience'] ?? $carer->years_experience;
        $carer->qualifications = $this->resolveLegacyQualifications($carer, $data);
        $carer->service_radius_km = $data['service_radius_km'] ?? $carer->service_radius_km;
        $carer->response_time_minutes = $data['response_time_minutes'] ?? $carer->response_time_minutes;

        if ($isSelfUpdate) {
            // prevent self-escalation
            $data['admin_approved'] = $carer->admin_approved;
            $data['super_admin_approved'] = $carer->super_admin_approved;
        }

        if (array_key_exists('admin_approved', $data) && !$this->isBlank($data['admin_approved'])) {
            $carer->admin_approved = $data['admin_approved'];
        }
        if (array_key_exists('super_admin_approved', $data) && !$this->isBlank($data['super_admin_approved'])) {
            $carer->super_admin_approved = $data['super_admin_approved'];
        }
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
        $hospital->bank_code = $data['bank_code'] ?? $hospital->bank_code;
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

    private function isBlank($value): bool
    {
        return is_null($value) || trim((string) $value) === '';
    }

    private function normalizeSpecialties($specialties): array
    {
        if (!is_array($specialties)) {
            return [];
        }

        $values = array_map(static function ($value) {
            return trim((string) $value);
        }, $specialties);

        return array_values(array_filter($values, static function ($value) {
            return $value !== '';
        }));
    }

    private function resolveLegacyQualifications(Carer $carer, array $data): ?string
    {
        if (array_key_exists('qualifications', $data) && !$this->isBlank($data['qualifications'])) {
            return $data['qualifications'];
        }

        $primary = $data['primary_qualification'] ?? $carer->primary_qualification;
        $specialties = $this->normalizeSpecialties($data['specialties'] ?? $carer->specialties);
        $license = $data['license_number'] ?? $carer->license_number;
        $issuer = $data['issuing_body'] ?? $carer->issuing_body;
        $years = $data['years_experience'] ?? $carer->years_experience;

        $parts = [];
        if (!$this->isBlank($primary)) {
            $parts[] = $primary;
        }
        if (!empty($specialties)) {
            $parts[] = 'Specialties: '.implode(', ', $specialties);
        }
        if (!$this->isBlank($license)) {
            $parts[] = 'License: '.$license;
        }
        if (!$this->isBlank($issuer)) {
            $parts[] = 'Issuer: '.$issuer;
        }
        if (!is_null($years) && (string) $years !== '') {
            $parts[] = 'Experience: '.$years.' years';
        }

        if (!empty($parts)) {
            return implode(' | ', $parts);
        }

        return $carer->qualifications;
    }
}
