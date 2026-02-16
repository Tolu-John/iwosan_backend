<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Payment $model): bool
    {
        return $this->access()->canAccessPayment($model);
    }

    public function update(User $user, Payment $model): bool
    {
        return $this->access()->canAccessPayment($model);
    }

    public function delete(User $user, Payment $model): bool
    {
        return $this->access()->canAccessPayment($model);
    }
}
