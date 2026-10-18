<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\GaAssetAttachment;
use App\Models\GaAssetEvent;
use Illuminate\Http\Request;

class GaAssetAttachmentController extends Controller
{
    private const MAX_BYTES = 5242880;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function list(Request $request)
    {
        $asset = $this->findAsset((int) $request->input('asset_id'));
        if (!$asset) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        $rows = GaAssetAttachment::where('asset_id', $asset->id)
            ->whereNull('archived_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (GaAssetAttachment $row) {
                return $this->present($row);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function upload(Request $request)
    {
        $asset = $this->findAsset((int) $request->input('asset_id'));
        if (!$asset) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        if ($asset->publication_state === GaAsset::PUBLICATION_ARCHIVED) {
            return response()->json(['message' => 'Aset arsip tidak bisa ditambah dokumen.'], 422);
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['message' => 'File foto atau dokumen wajib diisi.'], 422);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return response()->json(['message' => 'Ukuran file maksimal 5 MB.'], 422);
        }

        $mime = (string) $file->getMimeType();
        $extension = self::ALLOWED[$mime] ?? null;
        if (!$extension) {
            return response()->json(['message' => 'File harus berupa JPG, PNG, WEBP, atau PDF.'], 422);
        }

        $directory = storage_path('app/ga-assets/' . $asset->id);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return response()->json(['message' => 'Folder penyimpanan tidak bisa dibuat.'], 500);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($directory, $storedName);
        $absolute = $directory . DIRECTORY_SEPARATOR . $storedName;
        $storageKey = 'ga-assets/' . $asset->id . '/' . $storedName;

        try {
            $attachment = GaAssetAttachment::create([
                'asset_id' => $asset->id,
                'storage_key' => $storageKey,
                'original_name' => $this->originalName($file->getClientOriginalName(), $extension),
                'mime' => $mime,
                'size_bytes' => filesize($absolute) ?: 0,
                'sha256' => hash_file('sha256', $absolute) ?: null,
                'uploaded_by' => $this->karyawan,
                'visibility' => GaAssetAttachment::VISIBILITY_INTERNAL,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            @unlink($absolute);
            return response()->json(['message' => 'Dokumen gagal disimpan.'], 500);
        }

        GaAssetEvent::create([
            'asset_id' => $asset->id,
            'event_type' => GaAssetEvent::ATTACHMENT_ADDED,
            'actor_id' => $this->karyawan,
            'occurred_at' => date('Y-m-d H:i:s'),
            'after_json' => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diunggah.',
            'data' => $this->present($attachment),
        ], 201);
    }

    public function download(Request $request)
    {
        $attachment = $this->findAttachment((int) $request->input('id'));
        if (!$attachment) {
            return response()->json(['message' => 'Dokumen tidak ditemukan.'], 404);
        }

        $path = $this->absolutePath($attachment->storage_key);
        if ($path === null || !is_file($path)) {
            return response()->json(['message' => 'File dokumen tidak ditemukan.'], 404);
        }

        $name = str_replace(['"', "\r", "\n"], '', $attachment->original_name);

        return response()->stream(function () use ($path) {
            $handle = fopen($path, 'rb');
            if ($handle) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(Request $request)
    {
        $attachment = $this->findAttachment((int) $request->input('id'));
        if (!$attachment) {
            return response()->json(['message' => 'Dokumen tidak ditemukan.'], 404);
        }

        $attachment->archived_at = date('Y-m-d H:i:s');
        $attachment->save();

        GaAssetEvent::create([
            'asset_id' => $attachment->asset_id,
            'event_type' => GaAssetEvent::ATTACHMENT_REMOVED,
            'actor_id' => $this->karyawan,
            'occurred_at' => date('Y-m-d H:i:s'),
            'before_json' => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dihapus.',
        ]);
    }

    private function findAsset(int $id): ?GaAsset
    {
        if ($id < 1) {
            return null;
        }

        return GaAsset::where('id', $id)
            ->where('record_kind', GaAsset::RECORD_UNIT)
            ->where('is_active', true)
            ->first();
    }

    private function findAttachment(int $id): ?GaAssetAttachment
    {
        if ($id < 1) {
            return null;
        }

        return GaAssetAttachment::where('id', $id)
            ->whereNull('archived_at')
            ->whereHas('asset', function ($query) {
                $query->where('record_kind', GaAsset::RECORD_UNIT)->where('is_active', true);
            })
            ->first();
    }

    private function present(GaAssetAttachment $row): array
    {
        return [
            'id' => $row->id,
            'asset_id' => $row->asset_id,
            'original_name' => $row->original_name,
            'mime' => $row->mime,
            'size_bytes' => (int) $row->size_bytes,
            'uploaded_by' => $row->uploaded_by,
            'created_at' => $row->created_at,
            'is_image' => strpos((string) $row->mime, 'image/') === 0,
        ];
    }

    private function originalName(string $name, string $extension): string
    {
        $base = trim(basename(str_replace('\\', '/', $name)));
        $base = preg_replace('/[^\w.\- ]+/u', '', $base) ?: ('dokumen.' . $extension);
        if (strlen($base) > 255) {
            $base = substr($base, -255);
        }

        return $base;
    }

    private function absolutePath(string $storageKey): ?string
    {
        $key = str_replace('\\', '/', $storageKey);
        if ($key === '' || strpos($key, '..') !== false || strpos($key, 'ga-assets/') !== 0) {
            return null;
        }

        return storage_path('app/' . $key);
    }
}
