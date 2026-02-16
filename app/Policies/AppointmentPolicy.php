<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Appointment $model): bool
    {
        return $this->access()->canAccessAppointment($model);
    }

    public function update(User $user, Appointment $model): bool
    {
        return $this->access()->canAccessAppointment($model);
    }

    public function delete(User $user, Appointment $model): bool
    {
        return $this->access()->canAccessAppointment($model);
    }
}
