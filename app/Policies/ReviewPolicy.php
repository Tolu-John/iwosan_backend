<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;
    use UsesAccessService;

    public function view(User $user, Review $model): bool
    {
        return $this->access()->canAccessReview($model);
    }

    public function update(User $user, Review $model): bool
    {
        return $this->access()->canAccessReview($model);
    }

    public function delete(User $user, Review $model): bool
    {
        return $this->access()->canAccessReview($model);
    }
}
