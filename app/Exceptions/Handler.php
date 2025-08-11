<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Exception;

class Handler extends ExceptionHandler
{
    /**
     * Daftar tipe exception yang tidak perlu dilaporkan.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Melaporkan atau mencatat exception.
     *
     * Tempat yang bagus untuk mengirim exception ke Sentry, Bugsnag, dll.
     *
     * @param  \Throwable|\Exception  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report($exception)
    {
        parent::report($exception);
    }

    /**
     * Render exception ke dalam response HTTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable|\Exception  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, $exception)
    {
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            if ($statusCode == 500) {
                return response()->json(['error' => 'Internal Server Error'], 500);
            }
        }

        // Handle Exception selain Throwable (untuk kompatibilitas PHP < 7)
        if ($exception instanceof Exception) {
            return response()->json([
                'error' => 'Terjadi kesalahan pada server.',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ], 500);
        }

        // Handle Throwable (PHP 7+)
        if ($exception instanceof Throwable) {
            return response()->json([
                'error' => 'Terjadi kesalahan pada server.',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
