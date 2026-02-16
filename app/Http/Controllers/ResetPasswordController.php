<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;

class ResetPasswordController extends Controller
{
    private PasswordResetService $passwords;

    public function __construct(PasswordResetService $passwords)
    {
        $this->passwords = $passwords;
    }

    public function __invoke(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        try {
            $this->passwords->resetPassword($data['code'], $data['password'], $data['type']);
        } catch (\RuntimeException $e) {
            return response(['message' => $e->getMessage()], 422);
        }

        return response(['message' => 'password has been successfully reset'], 200);
    }
}
