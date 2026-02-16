<?php

namespace App\Services;

use App\Events\StatusTransitioned;

class NotificationService
{
    public function notifyStatusChange(string $modelType, int $modelId, ?string $from, string $to, array $context = []): void
    {
        event(new StatusTransitioned($modelType, $modelId, $from, $to, $context));
    }
}
