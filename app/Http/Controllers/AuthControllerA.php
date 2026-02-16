<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginHospitalRequest;
use App\Http\Requests\Auth\RegisterHospitalRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthControllerA extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function Register(RegisterHospitalRequest $request)
    {
        $result = $this->auth->registerHospital($request->validated());

        return response()->json([
            'message' => 'Registered Successfully',
            'id' => $result['id'],
            'expires_at' => $result['expires_at'],
            'access_token' => $result['access_token'],
        ], 200);
    }

    public function login(LoginHospitalRequest $request)
    {
        try {
            $result = $this->auth->loginHospital($request->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Login Successfully',
            'id' => $result['id'],
            'phone' => $result['phone'],
            'expires_at' => $result['expires_at'],
            'access_token' => $result['access_token'],
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            $this->auth->logout($request->user());
        } catch (\RuntimeException $e) {
            return response(['message' => $e->getMessage()], 401);
        }

        return response(['message' => 'You have been successfully logged out!'], 200);
    }
}
