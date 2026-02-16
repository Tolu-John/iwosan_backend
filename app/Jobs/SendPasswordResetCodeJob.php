<?php

namespace App\Jobs;

use App\Mail\SendCodeResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetCodeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $email;
    public string $code;

    public function __construct(string $email, string $code)
    {
        $this->email = $email;
        $this->code = $code;
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new SendCodeResetPassword($this->code));
    }
}
