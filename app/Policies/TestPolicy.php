<?php

namespace App\Policies;

use App\Models\test;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TestPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, test $model): bool
    {
        return $this->access()->canAccessTest($model);
    }

    public function update(User $user, test $model): bool
    {
        return $this->access()->canAccessTest($model);
    }

    public function delete(User $user, test $model): bool
    {
        return $this->access()->canAccessTest($model);
    }
}
