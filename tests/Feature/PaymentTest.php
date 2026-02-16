<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessPaymentWebhookJob;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_pending_payment(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $carer = Carer::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'code' => 'CODE123',
            'method' => 'card',
            'status' => 'pending',
            'price' => 5000,
            'type' => 'virtual_visit',
            'type_id' => 1,
            'reuse' => false,
            'gateway' => 'paystack',
        ];

        $this->postJson('/api/v1/payment', $payload)->assertStatus(200);
    }

    public function test_cannot_mark_paid_without_verification(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $carer = Carer::factory()->create();

        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'status' => 'processing',
        ]);

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'code' => $payment->code,
            'method' => $payment->method ?? 'card',
            'status' => 'paid',
            'price' => $payment->price,
            'type' => $payment->type ?? 'virtual_visit',
            'type_id' => $payment->type_id ?? 1,
            'reuse' => false,
            'gateway' => 'paystack',
        ];

        $this->putJson("/api/v1/payment/{$payment->id}", $payload)->assertStatus(422);
    }

    public function test_payment_idempotency_key_returns_same_response(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $carer = Carer::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'code' => 'CODE456',
            'method' => 'card',
            'status' => 'pending',
            'price' => 7000,
            'type' => 'virtual_visit',
            'type_id' => 1,
            'reuse' => false,
            'gateway' => 'paystack',
        ];

        $headers = ['Idempotency-Key' => 'payment-key-123'];

        $response1 = $this->postJson('/api/v1/payment', $payload, $headers)->assertStatus(200);
        $paymentId = $response1->json('id');

        $response2 = $this->postJson('/api/v1/payment', $payload, $headers)->assertStatus(200);
        $response2->assertJsonFragment(['id' => $paymentId]);

        $this->assertEquals(1, Payment::count());
    }

    public function test_payment_response_contains_gateway_fields(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $carer = Carer::factory()->create();

        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'code' => 'CODE789',
            'method' => 'card',
            'status' => 'pending',
            'price' => 8000,
            'type' => 'virtual_visit',
            'type_id' => 1,
            'reuse' => false,
            'gateway' => 'paystack',
        ];

        $this->postJson('/api/v1/payment', $payload)
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'reference',
                'gateway',
                'gateway_transaction_id',
                'channel',
                'currency',
                'fees',
                'verified_at',
            ]);
    }

    public function test_paystack_webhook_queues_job_and_returns_accepted(): void
    {
        Queue::fake();
        config(['paystack.secret' => 'testsecret']);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'REF123',
                'id' => 123,
            ],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha512', $body, 'testsecret');

        $response = $this->postJson('/api/v1/payment/webhook', $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(202)->assertJson(['message' => 'Accepted']);

        Queue::assertPushed(ProcessPaymentWebhookJob::class);
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway' => 'paystack',
            'reference' => 'REF123',
            'event' => 'charge.success',
        ]);
    }
}
