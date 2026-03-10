<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public const INVALID_CREDENTIALS_MESSAGE = 'Invalid credentials.';
    private const DEFAULT_TOKEN_NAME = 'iwosan_api';

    public function registerPatient(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = new User();
            $user->firedb_id = $data['firedb_id'];
            $user->firstname = $data['firstname'];
            $user->lastname = $data['lastname'];
            $user->email = $data['email'];
            $user->phone = $data['phone'];
            $user->password = Hash::make($data['password']);
            $user->remember_token = Str::random(10);
            $user->save();

            $patient = new Patient();
            $patient->user_id = $user->id;
            $patient->save();

            $token = $user->createToken(self::DEFAULT_TOKEN_NAME);
            $expiresAt = $this->tokenExpiresAt($user->id);

            return [
                'id' => $patient->id,
                'access_token' => $token->accessToken,
                'expires_at' => $token->token->expires_at ?? $expiresAt,
            ];
        });
    }

    public function loginPatient(array $data): array
    {
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $patient = Patient::select(['id'])->where('user_id', $user->id)->first();
        if (!$patient) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->accessToken;
        $expiresAt = $this->tokenExpiresAt($user->id);

        return [
            'id' => $patient->id,
            'phone' => $user->phone,
            'access_token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function registerCarer(array $data): array
    {
        $hospital = Hospital::where('code', $data['code'])->first();
        if (!$hospital) {
            throw new \RuntimeException('Hospital does not exist');
        }

        return DB::transaction(function () use ($data, $hospital) {
            $user = new User();
            $user->firedb_id = !empty($data['firedb_id'])
                ? $data['firedb_id']
                : $this->generateFiredbId('carer');
            $user->firstname = $data['firstname'];
            $user->lastname = $data['lastname'];
            $user->email = $data['email'];
            $user->phone = $data['phone'];
            $user->password = Hash::make($data['password']);
            $user->remember_token = Str::random(10);
            $user->save();

            $carer = new Carer();
            $carer->user_id = $user->id;
            $carer->hospital_id = $hospital->id;
            $carer->save();

            $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->accessToken;
            $expiresAt = $this->tokenExpiresAt($user->id);

            return [
                'id' => $carer->id,
                'access_token' => $token,
                'expires_at' => $expiresAt,
            ];
        });
    }

    public function loginCarer(array $data): array
    {
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $carer = Carer::select(['id', 'hospital_id'])->where('user_id', $user->id)->first();
        if (!$carer || !$carer->hospital_id) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $code = Hospital::select(['code'])->where('id', $carer->hospital_id)->first();
        if (!$code || $code['code'] !== $data['code']) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->accessToken;
        $expiresAt = $this->tokenExpiresAt($user->id);

        return [
            'id' => $carer->id,
            'phone' => $user->phone,
            'access_token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function registerHospital(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $hospitalCode = $this->generateHospitalCode();
            $user = new User();
            $user->firedb_id = $data['firedb_id'];
            $user->firstname = $data['name'];
            $user->lastname = 'admin';
            $user->email = $data['email'];
            $user->phone = $data['phone'];
            $user->password = Hash::make($data['password']);
            $user->remember_token = Str::random(10);
            $user->save();

            $hospital = new Hospital();
            $hospital->name = $data['name'];
            $hospital->email = $data['email'];
            $hospital->firedb_id = $data['firedb_id'];
            $hospital->phone = $data['phone'];
            $hospital->password = $user->password;
            $hospital->code = $hospitalCode;
            $hospital->user_id = $user->id;
            $hospital->admin_id = $user->id;
            $hospital->save();

            $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->accessToken;
            $expiresAt = $this->tokenExpiresAt($user->id);

            return [
                'id' => $hospital->id,
                'code' => $hospital->code,
                'access_token' => $token,
                'expires_at' => $expiresAt,
            ];
        });
    }

    public function loginHospital(array $data): array
    {
        $hospital = Hospital::where('email', $data['email'])->first();
        if (!$hospital || !Hash::check($data['password'], $hospital->password)) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        if ($data['code'] !== $hospital->code) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $user = null;
        if (!empty($hospital->user_id)) {
            $user = User::find($hospital->user_id);
        }
        if (!$user) {
            $user = User::where('firedb_id', $hospital->firedb_id)->first();
            if ($user) {
                $hospital->user_id = $user->id;
                if (empty($hospital->admin_id)) {
                    $hospital->admin_id = $user->id;
                }
                $hospital->save();
            }
        }
        if (!$user) {
            throw new \RuntimeException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        $token = $user->createToken(self::DEFAULT_TOKEN_NAME)->accessToken;
        $expiresAt = $this->tokenExpiresAt($user->id);

        return [
            'id' => $hospital->id,
            'phone' => $hospital->phone,
            'access_token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function logout(?User $user): void
    {
        if (!$user) {
            throw new \RuntimeException('Unauthorized');
        }

        $token = $user->token();
        if ($token) {
            $token->revoke();
        }
    }

    public function changePassword(?User $user, string $currentPassword, string $newPassword, string $role): void
    {
        if (!$user) {
            throw new \RuntimeException('Unauthorized');
        }

        if (!Hash::check($currentPassword, $user->password)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $hash = Hash::make($newPassword);
        $user->password = $hash;
        $user->save();

        if ($role === 'hospital') {
            $hospital = Hospital::where('user_id', $user->id)->first();
            if (!$hospital && !empty($user->firedb_id)) {
                $hospital = Hospital::where('firedb_id', $user->firedb_id)->first();
            }
            if ($hospital) {
                $hospital->password = $hash;
                $hospital->save();
            }
        }
    }

    private function tokenExpiresAt(int $userId)
    {
        $row = DB::table('oauth_access_tokens')
            ->where('user_id', $userId)
            ->where('name', self::DEFAULT_TOKEN_NAME)
            ->first();

        return $row->expires_at ?? null;
    }

    private function generateHospitalCode(): string
    {
        do {
            $code = 'HSP'.strtoupper(Str::random(6));
        } while (Hospital::where('code', $code)->exists());

        return $code;
    }

    private function generateFiredbId(string $prefix): string
    {
        do {
            $candidate = strtolower($prefix).'-'.Str::upper(Str::random(10));
        } while (User::where('firedb_id', $candidate)->exists());

        return $candidate;
    }
}
