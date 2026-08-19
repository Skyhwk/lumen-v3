<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class CsTicketAssetController extends Controller
{
    public function show(string $file)
    {
        try {
            $fileName = basename($file);
            if ($fileName === '' || $fileName !== $file) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $localPath = public_path('cs_tickets/conversation/' . $fileName);
            if (is_file($localPath)) {
                return $this->serveLocalFile($localPath, $fileName);
            }

            $ppiPublic = rtrim((string) env('PPI_PUBLIC_URL', ''), '/');
            if ($ppiPublic === '') {
                return response()->json(['error' => 'File not found'], 404);
            }

            $url = $ppiPublic . '/cs-tickets/' . rawurlencode($fileName);
            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);
            $res = $client->get($url);

            if ($res->getStatusCode() !== 200) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $contentType = $res->getHeaderLine('Content-Type') ?: $this->resolveAttachmentMime($fileName);
            $fileContent = $res->getBody()->getContents();

            return response($fileContent, 200)
                ->header('Content-Type', $contentType)
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
