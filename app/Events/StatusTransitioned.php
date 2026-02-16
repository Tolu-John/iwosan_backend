<?php

namespace App\Events;

class StatusTransitioned
{
    public string $modelType;
    public int $modelId;
    public ?string $fromStatus;
    public string $toStatus;
    public array $context;

    public function __construct(string $modelType, int $modelId, ?string $fromStatus, string $toStatus, array $context = [])
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->context = $context;
    }
}
