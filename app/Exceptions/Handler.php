<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            Log::error('exception.reported', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            if (config('services.sentry.dsn') && class_exists('\\Sentry\\State\\Hub')) {
                try {
                    \Sentry\captureException($e);
                } catch (Throwable $ignored) {
                    Log::warning('sentry.capture_failed', ['message' => $ignored->getMessage()]);
                }
            }
        });
    }

    public function render($request, Throwable $e)
    {
        $isApiRequest = $request->expectsJson() || $request->is('api/*');

        if ($isApiRequest && $e instanceof AuthenticationException) {
            $requestId = $request->attributes->get('request_id');
            return response()->json([
                'message' => 'Unauthenticated.',
                'request_id' => $requestId,
            ], 401);
        }

        if ($isApiRequest && $e instanceof AuthorizationException) {
            $requestId = $request->attributes->get('request_id');
            $message = $e->getMessage() ?: 'Forbidden';
            return response()->json([
                'message' => $message,
                'request_id' => $requestId,
            ], 403);
        }

        if ($isApiRequest && ($e instanceof ValidationException)) {
            $requestId = $request->attributes->get('request_id');
            Log::warning('validation.failed', [
                'request_id' => $requestId,
                'path' => $request->path(),
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'request_id' => $requestId,
            ], 422);
        }

        if ($isApiRequest && !($e instanceof ValidationException)) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: 'Server Error';
            $requestId = $request->attributes->get('request_id');

            return response()->json([
                'message' => $message,
                'request_id' => $requestId,
            ], $status);
        }

        return parent::render($request, $e);
    }
}
