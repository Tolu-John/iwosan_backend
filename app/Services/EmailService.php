<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(string $email, string $subject, string $body): void
    {
        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }
}
