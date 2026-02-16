<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        \App\Models\Appointment::class => \App\Policies\AppointmentPolicy::class,
        \App\Models\Consultation::class => \App\Policies\ConsultationPolicy::class,
        \App\Models\Teletest::class => \App\Policies\TeletestPolicy::class,
        \App\Models\ward::class => \App\Policies\WardPolicy::class,
        \App\Models\LabResult::class => \App\Policies\LabResultPolicy::class,
        \App\Models\LabTest::class => \App\Policies\LabTestPolicy::class,
        \App\Models\Drug::class => \App\Policies\DrugPolicy::class,
        \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Review::class => \App\Policies\ReviewPolicy::class,
        \App\Models\Complaints::class => \App\Policies\ComplaintsPolicy::class,
        \App\Models\Transfers::class => \App\Policies\TransfersPolicy::class,
        \App\Models\Gen_Vital::class => \App\Policies\GenVitalPolicy::class,
        \App\Models\test::class => \App\Policies\TestPolicy::class,
        \App\Models\Patient::class => \App\Policies\PatientPolicy::class,
        \App\Models\Carer::class => \App\Policies\CarerPolicy::class,
        \App\Models\Hospital::class => \App\Policies\HospitalPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
