<?php

namespace App\Policies;

use App\Models\LabResult;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LabResultPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, LabResult $model): bool
    {
        return $this->access()->canAccessLabResult($model);
    }

    public function update(User $user, LabResult $model): bool
    {
        return $this->access()->canAccessLabResult($model);
    }

    public function delete(User $user, LabResult $model): bool
    {
        return $this->access()->canAccessLabResult($model);
    }
}
