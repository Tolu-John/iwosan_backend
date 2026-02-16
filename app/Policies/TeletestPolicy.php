<?php

namespace App\Policies;

use App\Models\Teletest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeletestPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Teletest $model): bool
    {
        return $this->access()->canAccessTeletest($model);
    }

    public function update(User $user, Teletest $model): bool
    {
        return $this->access()->canAccessTeletest($model);
    }

    public function delete(User $user, Teletest $model): bool
    {
        return $this->access()->canAccessTeletest($model);
    }
}
