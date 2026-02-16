<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Patient $model): bool
    {
        return $this->access()->canAccessPatient((int) $model->id);
    }

    public function update(User $user, Patient $model): bool
    {
        return $this->access()->canAccessPatient((int) $model->id);
    }

    public function delete(User $user, Patient $model): bool
    {
        return $this->access()->canAccessPatient((int) $model->id);
    }
}
