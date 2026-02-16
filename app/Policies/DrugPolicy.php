<?php

namespace App\Policies;

use App\Models\Drug;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DrugPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Drug $model): bool
    {
        return $this->access()->canAccessDrug($model);
    }

    public function update(User $user, Drug $model): bool
    {
        return $this->access()->canAccessDrug($model);
    }

    public function delete(User $user, Drug $model): bool
    {
        return $this->access()->canAccessDrug($model);
    }
}
