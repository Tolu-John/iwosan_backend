<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $type;
    public array $filters;
    public string $requestedBy;

    public function __construct(string $type, array $filters, string $requestedBy)
    {
        $this->type = $type;
        $this->filters = $filters;
        $this->requestedBy = $requestedBy;
    }

    public function handle(): void
    {
        $payload = [
            'type' => $this->type,
            'filters' => $this->filters,
            'requested_by' => $this->requestedBy,
            'generated_at' => now()->toDateTimeString(),
        ];

        $filename = 'reports/'.Str::uuid().'.json';
        Storage::disk('iwosan_files')->put($filename, json_encode($payload));
    }
}
