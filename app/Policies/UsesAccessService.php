<?php

namespace App\Policies;

use App\Services\AccessService;

trait UsesAccessService
{
    protected function access(): AccessService
    {
        return app(AccessService::class);
    }
}
