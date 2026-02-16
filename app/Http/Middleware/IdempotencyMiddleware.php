<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '') {
            return $next($request);
        }

        $userId = optional($request->user())->id;
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $record = IdempotencyKey::where('key', $key)->where('user_id', $userId)->first();
        if ($record) {
            if ($record->request_hash !== $hash) {
                return response()->json(['message' => 'Idempotency key reuse with different payload.'], 409);
            }
            if ($record->completed_at) {
                return response($record->response_body, $record->response_status ?? 200, [
                    'Content-Type' => 'application/json',
                ]);
            }

            return response()->json(['message' => 'Request in progress.'], 409);
        }

        $record = IdempotencyKey::create([
            'key' => $key,
            'user_id' => $userId,
            'method' => $request->method(),
            'path' => $request->path(),
            'request_hash' => $hash,
        ]);

        /** @var Response $response */
        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status >= 500) {
            $record->delete();
            return $response;
        }

        $record->response_status = $status;
        $record->response_body = $response->getContent();
        $record->completed_at = now();
        $record->save();

        return $response;
    }
}
