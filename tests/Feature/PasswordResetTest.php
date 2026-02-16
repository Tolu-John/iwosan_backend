<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_flow_for_user(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);

        $this->postJson('/api/v1/forgot_password', [
            'email' => $user->email,
            'type' => 'user',
        ])->assertStatus(200);

        $code = ResetCodePassword::first()->code;

        $this->postJson('/api/v1/forgot_password/check', [
            'code' => $code,
        ])->assertStatus(200);

        $this->postJson('/api/v1/forgot_password/reset', [
            'code' => $code,
            'password' => 'newpassword123',
            'type' => 'user',
        ])->assertStatus(200);
    }

    public function test_password_reset_flow_for_hospital(): void
    {
        $hospital = Hospital::factory()->create(['email' => 'hospital@example.test', 'firedb_id' => 'hosp-001']);
        User::factory()->create(['email' => 'hospital-admin@example.test', 'firedb_id' => $hospital->firedb_id]);

        $this->postJson('/api/v1/forgot_password', [
            'email' => $hospital->email,
            'type' => 'hospital',
        ])->assertStatus(200);

        $code = ResetCodePassword::first()->code;

        $this->postJson('/api/v1/forgot_password/check', [
            'code' => $code,
        ])->assertStatus(200);

        $this->postJson('/api/v1/forgot_password/reset', [
            'code' => $code,
            'password' => 'newpassword123',
            'type' => 'hospital',
        ])->assertStatus(200);
    }
}
