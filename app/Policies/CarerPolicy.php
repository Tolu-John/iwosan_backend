<?php

namespace App\Policies;

use App\Models\Carer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CarerPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Carer $model): bool
    {
        return $this->access()->canAccessCarer($model);
    }

    public function update(User $user, Carer $model): bool
    {
        return $this->access()->canAccessCarer($model);
    }

    public function delete(User $user, Carer $model): bool
    {
        return $this->access()->canAccessCarer($model);
    }
}
