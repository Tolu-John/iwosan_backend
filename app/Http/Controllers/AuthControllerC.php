<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginCarerRequest;
use App\Http\Requests\Auth\RegisterCarerRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthControllerC extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function Register(RegisterCarerRequest $request)
    {
        try {
            $result = $this->auth->registerCarer($request->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Registered Successfully',
            'id' => $result['id'],
            'expires_at' => $result['expires_at'],
            'access_token' => $result['access_token'],
        ], 200);
    }

    public function login(LoginCarerRequest $request)
    {
        try {
            $result = $this->auth->loginCarer($request->validated());
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

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $this->auth->changePassword(
                $request->user(),
                $request->validated()['current_password'],
                $request->validated()['password'],
                'carer'
            );
        } catch (\RuntimeException $e) {
            return response(['message' => $e->getMessage()], 422);
        }

        return response(['message' => 'Password changed successfully.'], 200);
    }
}
