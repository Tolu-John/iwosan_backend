<?php

namespace App\Policies;

use App\Models\Gen_Vital;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GenVitalPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Gen_Vital $model): bool
    {
        return $this->access()->canAccessGenVital($model);
    }

    public function update(User $user, Gen_Vital $model): bool
    {
        return $this->access()->canAccessGenVital($model);
    }

    public function delete(User $user, Gen_Vital $model): bool
    {
        return $this->access()->canAccessGenVital($model);
    }
}
