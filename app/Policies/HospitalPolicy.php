<?php

namespace App\Policies;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HospitalPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Hospital $model): bool
    {
        return $this->access()->canAccessHospital($model);
    }

    public function update(User $user, Hospital $model): bool
    {
        return $this->access()->canAccessHospital($model);
    }

    public function delete(User $user, Hospital $model): bool
    {
        return $this->access()->canAccessHospital($model);
    }
}
