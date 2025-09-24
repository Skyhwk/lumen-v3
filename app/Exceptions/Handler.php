<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Exception;
use App\Services\Notification;

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
        // Optimasi: Tangani error database timeout secara terpusat
        if ($this->isDatabaseTimeout($exception)) {
            return $this->handleDatabaseTimeout($exception);
        }

        // Tangani HttpException
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            if ($statusCode == 500) {
                return response()->json(['error' => 'Terjadi kesalahan internal server.', 'message' => $exception->getMessage()], 500);
            }
            return response()->json([
                'error' => $exception->getMessage() ?: 'Terjadi kesalahan pada server.',
                'message' => $exception->getMessage(),
                'status' => $statusCode
            ], $statusCode);
        }

        // Tangani Exception umum (PHP < 7)
        if ($exception instanceof Exception) {
            return response()->json([
                'error' => 'Terjadi kesalahan pada server.',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ], 500);
        }

        // Tangani Throwable (PHP 7+)
        if ($exception instanceof Throwable) {
            return response()->json([
                'error' => 'Terjadi kesalahan pada server.',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ], 500);
        }

        // Fallback ke parent
        return parent::render($request, $exception);
    }

    /**
     * Cek apakah exception adalah error timeout database.
     *
     * @param \Throwable|\Exception $exception
     * @return bool
     */
    private function isDatabaseTimeout($exception)
    {
        $msg = $exception->getMessage();
        return str_contains($msg, 'Connection timed out') || str_contains($msg, 'MySQL server has gone away');
    }

    /**
     * Handler khusus untuk error timeout database.
     *
     * @param \Throwable|\Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleDatabaseTimeout($exception)
    {
        Notification::whereIn('id_department', [7])
            ->title('Database time out Exceeded')
            ->message('Saat ini terjadi masalah koneksi database (timeout/gone away) di aplikasi pada controller: ' . $exception->getFile() . ' line: ' . $exception->getLine())->url('/monitor-database')->send();

        return response()->json([
            'message' => 'Terdapat antrian transaksi pada fitur ini, mohon untuk mencoba kembali beberapa saat lagi.!',
            'status' => 401
        ], 401);
    }
}
