<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\PasswordResetService;

class ForgotPasswordController extends Controller
{
    private PasswordResetService $passwords;

    public function __construct(PasswordResetService $passwords)
    {
        $this->passwords = $passwords;
    }

    public function __invoke(ForgotPasswordRequest $request)
    {
        $data = $request->validated();
        $this->passwords->sendResetCode($data['email'], $data['type']);

        return response(['message' => 'If the email exists, a reset code has been sent.'], 200);
    }
}
