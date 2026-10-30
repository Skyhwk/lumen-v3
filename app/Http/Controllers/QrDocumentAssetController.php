<?php

namespace App\Http\Controllers;

use App\Services\GenerateQrDocumentDisipliner;
use App\Support\QrDocumentsStorage;
use Illuminate\Http\Request;

class QrDocumentAssetController extends Controller
{
    public function show(Request $request, string $filename = '')
    {
        $raw = (string) ($request->query('file') ?: $filename);
        $base = preg_replace('/\.svg$/i', '', basename($raw));
        if ($base === '' || preg_match('/[^A-Za-z0-9._-]/', $base)) {
            return response()->json(['message' => '404 not found'], 404);
        }

        GenerateQrDocumentDisipliner::ensurePublicSvgForFile($base);

        try {
            $path = QrDocumentsStorage::svgPath($base);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => '404 not found'], 404);
        }

        if (!is_file($path)) {
            return response()->json(['message' => '404 not found'], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
