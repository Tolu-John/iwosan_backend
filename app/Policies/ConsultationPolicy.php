<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsultationPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Consultation $model): bool
    {
        return $this->access()->canAccessConsultation($model);
    }

    public function update(User $user, Consultation $model): bool
    {
        return $this->access()->canAccessConsultation($model);
    }

    public function delete(User $user, Consultation $model): bool
    {
        return $this->access()->canAccessConsultation($model);
    }
}
