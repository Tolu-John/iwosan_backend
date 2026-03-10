<?php

namespace Tests\Feature;

use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_review_for_completed_consultation(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'text' => 'Great communication and clear guidance.',
            'rating' => 5,
            'recomm' => 'yes',
            'tags' => ['Professional', 'Empathetic'],
        ];

        $this->postJson('/api/v1/review', $payload)
            ->assertStatus(200)
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('carer_id', $carer->id)
            ->assertJsonPath('consultation_id', $consultation->id)
            ->assertJsonPath('recomm', 'yes')
            ->assertJsonPath('recommend', true);

        $this->assertDatabaseHas('reviews', [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'rating' => 5,
            'recomm' => 1,
            'status' => 'published',
        ]);
    }

    public function test_patient_cannot_create_review_for_non_completed_consultation(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('in_progress');
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'text' => 'Attempted too early.',
            'rating' => 4,
            'recomm' => 'yes',
        ];

        $this->postJson('/api/v1/review', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Reviews are only allowed for completed consultations.']);
    }

    public function test_patient_cannot_create_duplicate_review_for_same_consultation(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
        ]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'text' => 'Second review should fail.',
            'rating' => 4,
            'recomm' => 'yes',
        ];

        $this->postJson('/api/v1/review', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Review already exists for this consultation.']);
    }

    public function test_patient_cannot_create_review_for_other_patient(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        $otherUser = User::factory()->create();
        $otherPatient = Patient::factory()->create(['user_id' => $otherUser->id]);
        Passport::actingAs($user);

        $payload = [
            'patient_id' => $otherPatient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'text' => 'Forbidden write.',
            'rating' => 4,
            'recomm' => 'no',
        ];

        $this->postJson('/api/v1/review', $payload)
            ->assertStatus(403);
    }

    public function test_patient_can_update_own_review_and_recommendation_is_normalized(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        $review = Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'recomm' => true,
            'status' => 'published',
        ]);
        Passport::actingAs($user);

        $payload = [
            'text' => 'Service was fine, but waiting time can improve.',
            'rating' => 3,
            'recomm' => 'no',
            'tags' => ['Helpful advice'],
        ];

        $this->putJson("/api/v1/review/{$review->id}", $payload)
            ->assertStatus(200)
            ->assertJsonPath('recomm', 'no')
            ->assertJsonPath('recommend', false);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'recomm' => 0,
            'rating' => 3,
        ]);
    }

    public function test_carer_cannot_update_patient_review(): void
    {
        [$patientUser, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        $review = Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
        ]);
        $carerUser = $carer->user;
        Passport::actingAs($carerUser);

        $payload = [
            'text' => 'Carer should not update patient review.',
            'rating' => 2,
            'recomm' => 'no',
        ];

        $this->putJson("/api/v1/review/{$review->id}", $payload)
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Only the patient can update this review.']);
    }

    public function test_patient_review_list_hides_rejected_items(): void
    {
        [$user, $patient, $carer, $consultation] = $this->buildConsultationContext('completed');
        $consultationTwo = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $consultation->hospital_id,
            'status' => 'completed',
        ]);
        Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultation->id,
            'status' => 'published',
        ]);
        Review::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'consultation_id' => $consultationTwo->id,
            'status' => 'rejected',
        ]);
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/review')->assertStatus(200);
        $items = $response->json('data');
        $statuses = collect($items)->pluck('status')->all();

        $this->assertContains('published', $statuses);
        $this->assertNotContains('rejected', $statuses);
    }

    /**
     * @return array{0:User,1:Patient,2:Carer,3:Consultation}
     */
    private function buildConsultationContext(string $consultationStatus = 'completed'): array
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $hospital = Hospital::factory()->create();
        $carerUser = User::factory()->create();
        $carer = Carer::factory()->create([
            'user_id' => $carerUser->id,
            'hospital_id' => $hospital->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_id' => $patient->id,
            'carer_id' => $carer->id,
            'hospital_id' => $hospital->id,
            'status' => $consultationStatus,
        ]);

        return [$user, $patient, $carer, $consultation];
    }
}
