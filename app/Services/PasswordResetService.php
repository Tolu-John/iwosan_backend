<?php

namespace App\Services;

use App\Mail\SendCodeResetPassword;
use App\Models\Hospital;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendPasswordResetCodeJob;

class PasswordResetService
{
    public function sendResetCode(string $email, string $type): void
    {
        $exists = $type === 'user'
            ? User::where('email', $email)->exists()
            : Hospital::where('email', $email)->exists();

        if (!$exists) {
            return;
        }

        ResetCodePassword::where('email', $email)->delete();

        $code = mt_rand(100000, 999999);
        ResetCodePassword::create([
            'email' => $email,
            'type' => $type,
            'code' => $code,
        ]);

        SendPasswordResetCodeJob::dispatch($email, $code);
    }

    public function verifyCode(string $code): void
    {
        $passwordReset = ResetCodePassword::firstWhere('code', $code);
        if (!$passwordReset) {
            throw new \RuntimeException('Invalid code');
        }

        if ($passwordReset->created_at->addHour()->isPast()) {
            $passwordReset->delete();
            throw new \RuntimeException('code is expired');
        }
    }

    public function resetPassword(string $code, string $password, string $type): void
    {
        $passwordReset = ResetCodePassword::firstWhere('code', $code);
        if (!$passwordReset) {
            throw new \RuntimeException('Invalid reset request.');
        }

        if ($passwordReset->created_at->addHour()->isPast()) {
            $passwordReset->delete();
            throw new \RuntimeException('code is expired');
        }

        $user = $type === 'user'
            ? User::firstWhere('email', $passwordReset->email)
            : Hospital::firstWhere('email', $passwordReset->email);

        if (!$user) {
            throw new \RuntimeException('Invalid reset request.');
        }

        $hash = Hash::make($password);
        $user->update(['password' => $hash]);

        if ($type === 'hospital') {
            $linkedUser = null;
            if (!empty($user->user_id)) {
                $linkedUser = User::find($user->user_id);
            }
            if (!$linkedUser) {
                $linkedUser = User::where('firedb_id', $user->firedb_id)->first();
            }
            if ($linkedUser) {
                $linkedUser->update(['password' => $hash]);
            }
        }

        $passwordReset->delete();
    }
}
