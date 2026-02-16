<?php

namespace App\Policies;

use App\Models\Complaints;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComplaintsPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Complaints $model): bool
    {
        return $this->access()->canAccessComplaint($model);
    }

    public function update(User $user, Complaints $model): bool
    {
        return $this->access()->canAccessComplaint($model);
    }

    public function delete(User $user, Complaints $model): bool
    {
        return $this->access()->canAccessComplaint($model);
    }
}
