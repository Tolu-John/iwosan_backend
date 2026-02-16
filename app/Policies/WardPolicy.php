<?php

namespace App\Policies;

use App\Models\ward;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WardPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, ward $model): bool
    {
        return $this->access()->canAccessWard($model);
    }

    public function update(User $user, ward $model): bool
    {
        return $this->access()->canAccessWard($model);
    }

    public function delete(User $user, ward $model): bool
    {
        return $this->access()->canAccessWard($model);
    }
}
