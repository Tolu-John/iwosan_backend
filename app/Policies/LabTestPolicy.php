<?php

namespace App\Policies;

use App\Models\LabTest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LabTestPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, LabTest $model): bool
    {
        return $this->access()->canAccessLabTest($model);
    }

    public function update(User $user, LabTest $model): bool
    {
        return $this->access()->canAccessLabTest($model);
    }

    public function delete(User $user, LabTest $model): bool
    {
        return $this->access()->canAccessLabTest($model);
    }
}
