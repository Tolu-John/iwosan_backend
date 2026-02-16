<?php

namespace App\Policies;

use App\Models\Transfers;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransfersPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Transfers $model): bool
    {
        return $this->access()->canAccessTransfer($model);
    }

    public function update(User $user, Transfers $model): bool
    {
        return $this->access()->canAccessTransfer($model);
    }

    public function delete(User $user, Transfers $model): bool
    {
        return $this->access()->canAccessTransfer($model);
    }
}
