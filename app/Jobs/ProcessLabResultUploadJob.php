<?php

namespace App\Jobs;

use App\Models\LabResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessLabResultUploadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $labResultId;

    public function __construct(int $labResultId)
    {
        $this->labResultId = $labResultId;
    }

    public function handle(): void
    {
        $labResult = LabResult::find($this->labResultId);
        if (!$labResult) {
            return;
        }

        $files = array_filter([
            $labResult->result_picture,
            $labResult->result_picture_front,
            $labResult->result_picture_back,
        ]);

        foreach ($files as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!$path) {
                continue;
            }
            $filename = basename($path);
            $location = 'labresult/'.$filename;
            if (!Storage::disk('iwosan_files')->exists($location)) {
                Log::warning('Lab result file missing', [
                    'lab_result_id' => $labResult->id,
                    'path' => $location,
                ]);
            }
        }
    }
}
