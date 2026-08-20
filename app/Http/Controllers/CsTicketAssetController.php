<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CsTicketAssetController extends Controller
{
    public function show(Request $request, string $file = '')
    {
        try {
            $fileName = basename((string) ($request->query('file') ?: $file));
            if ($fileName === '') {
                return response()->json(['error' => 'File not found'], 404);
            }

            $localPath = public_path('cs_tickets/conversation/' . $fileName);
            if (is_file($localPath)) {
                return $this->serveLocalFile($localPath, $fileName);
            }

            $remoteContent = $this->fetchFromPpi($fileName);
            if ($remoteContent === null) {
                return response()->json(['error' => 'File not found'], 404);
            }

            return response($remoteContent['body'], 200)
                ->header('Content-Type', $remoteContent['mime'])
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Throwable $th) {
            \Log::error('CsTicketAssetController error', [
                'file' => $file,
                'error' => $th->getMessage(),
            ]);

            return response()->json(['error' => 'Server error: ' . $th->getMessage()], 500);
        }
    }

    private function fetchFromPpi(string $fileName): ?array
    {
        $encoded = rawurlencode($fileName);
        $bases = $this->resolvePpiPublicBases();
        if (empty($bases)) {
            return null;
        }

        $candidates = [];
        foreach ($bases as $base) {
            $appBase = preg_replace('#/public/?$#', '', $base);
            // Static PPI dulu (file customer biasanya ada di sini)
            $candidates[] = $base . '/cs_tickets/conversation/' . $encoded;
            $candidates[] = $appBase . '/cs_tickets/conversation/' . $encoded;
            // Route proxy PPI (fallback)
            $candidates[] = $appBase . '/cs-tickets/' . $encoded;
        }
        $candidates = array_unique($candidates);

        return $this->fetchRemoteAttachment($candidates, $fileName);
    }

    private function resolvePpiPublicBases(): array
    {
        $bases = [];

        $configured = rtrim((string) env('PPI_PUBLIC_URL', ''), '/');
        if ($configured !== '') {
            $bases[] = $configured;
        }

        if (env('APP_DEBUG', false)) {
            foreach (['http://localhost:8000', 'http://127.0.0.1:8000'] as $localBase) {
                if (!in_array($localBase, $bases, true)) {
                    $bases[] = $localBase;
                }
            }
        }

        if (empty($bases)) {
            foreach (['http://localhost:8000', 'http://127.0.0.1:8000'] as $localBase) {
                $bases[] = $localBase;
            }
        }

        return $bases;
    }

    private function fetchRemoteAttachment(array $candidates, string $fileName): ?array
    {
        $client = new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 8,
            'connect_timeout' => 3,
            'http_errors' => false,
        ]);

        foreach ($candidates as $url) {
            $res = $client->get($url);
            if ($res->getStatusCode() !== 200) {
                continue;
            }

            $contentType = strtolower(trim(explode(';', $res->getHeaderLine('Content-Type'))[0]));
            if ($contentType === '' || strpos($contentType, 'text/html') === 0 || strpos($contentType, 'application/json') === 0) {
                continue;
            }

            return [
                'body' => $res->getBody()->getContents(),
                'mime' => $contentType ?: $this->resolveAttachmentMime($fileName),
            ];
        }

        return null;
    }

    private function serveLocalFile(string $path, string $fileName)
    {
        $mime = $this->resolveAttachmentMime($fileName);

        return response(file_get_contents($path), 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function resolveAttachmentMime(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];

        return $mimeMap[$extension] ?? 'application/octet-stream';
    }
}
