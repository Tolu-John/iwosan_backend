<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CodeCheckRequest;
use App\Services\PasswordResetService;

class CodeCheckController extends Controller
{
    private PasswordResetService $passwords;

    public function __construct(PasswordResetService $passwords)
    {
        $this->passwords = $passwords;
    }

    public function __invoke(CodeCheckRequest $request)
    {
        try {
            $this->passwords->verifyCode($request->validated()['code']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'code is valid',
            'code' => $request->validated()['code'],
        ], 200);
    }
}
