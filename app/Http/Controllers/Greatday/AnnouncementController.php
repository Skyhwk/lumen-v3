<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Greatday\Announcement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $userId = $this->user_id;

        $data = Announcement::select(
            'id',
            DB::raw("COALESCE(title, description) as title"),
            'filename',
            'attachments',
            'created_at as timestamp',
            DB::raw("CASE WHEN JSON_CONTAINS(is_read, '\"{$userId}\"') THEN true ELSE false END as is_read"),
            DB::raw("CASE WHEN JSON_CONTAINS(is_deleted, '\"{$userId}\"') THEN true ELSE false END as is_deleted")
        )->visibleTo($this->karyawan ?: $userId);

        if ($request->has('bulan')) {
            try {
                $periode = Carbon::createFromFormat('Y-m', $request->bulan);
                $data->whereBetween('created_at', [
                    $periode->copy()->startOfMonth(),
                    $periode->copy()->endOfMonth(),
                ]);
            } catch (\Throwable $th) {
                return response()->json(['message' => 'Invalid bulan format'], 422);
            }
        }

        $webPublic = rtrim(config('greatday.web_public', ''), '/');
        $data = $data->orderBy('id', 'desc')->get()->map(function ($row) use ($webPublic) {
            $row->reff = $this->announcementBodyFromFilename($row->filename ?? null, $webPublic);
            $row->attachments = $this->announcementAttachments($row->attachments ?? null, $webPublic);

            return $row;
        });

        return response()->json(['data' => $data], 200);
    }

    public function getSingle()
    {
        $data = Announcement::select(
            'id',
            DB::raw("'Announcement' as title"),
            'description as message',
            'created_at as timestamp'
        )
            ->visibleTo($this->karyawan ?: $this->user_id)
            ->orderBy('id', 'desc')
            ->first();

        return response()->json(['data' => $data], 200);
    }

    public function read(Request $request)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($request->id);
            $readUsers = json_decode($announcement->is_read) ?: [];
            $userId = (string) $this->user_id;

            if (!in_array($userId, $readUsers, true)) {
                $readUsers[] = $userId;
                $announcement->is_read = json_encode($readUsers);
                $announcement->save();
            }

            DB::commit();

            return response()->json(['message' => 'Successfully read announcement'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function readAll(Request $request)
    {
        DB::beginTransaction();
        try {
            $userId = (string) $this->user_id;
            $announcements = Announcement::visibleTo($this->karyawan ?: $userId)->get();

            foreach ($announcements as $announcement) {
                $readUsers = json_decode($announcement->is_read) ?: [];
                if (!in_array($userId, $readUsers, true)) {
                    $readUsers[] = $userId;
                    $announcement->is_read = json_encode($readUsers);
                    $announcement->save();
                }
            }

            DB::commit();

            return response()->json(['message' => 'Successfully read all announcements'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function deletePartial(Request $request)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($request->id);
            $deleteUsers = json_decode($announcement->is_deleted) ?: [];
            $userId = (string) $this->user_id;

            if (!in_array($userId, $deleteUsers, true)) {
                $deleteUsers[] = $userId;
                $announcement->is_deleted = json_encode($deleteUsers);
                $announcement->save();
            }

            DB::commit();

            return response()->json(['message' => 'Successfully removed announcement'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function deleteAll(Request $request)
    {
        DB::beginTransaction();
        try {
            $userId = (string) $this->user_id;
            $announcements = Announcement::visibleTo($this->karyawan ?: $userId)->get();

            foreach ($announcements as $announcement) {
                $deleteUsers = json_decode($announcement->is_deleted) ?: [];
                if (!in_array($userId, $deleteUsers, true)) {
                    $deleteUsers[] = $userId;
                    $announcement->is_deleted = json_encode($deleteUsers);
                    $announcement->save();
                }
            }

            DB::commit();

            return response()->json(['message' => 'Successfully removed all announcements'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Lampiran dari kolom attachments → public/announcement/attachments/{file}.
     *
     * @return list<array{name: string, filename: string, url: string}>
     */
    private function announcementAttachments($raw, string $webPublic): array
    {
        $entries = [];
        if (is_array($raw)) {
            $entries = $raw;
        } elseif (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || $trimmed === 'null' || $trimmed === '[]') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $entries = isset($decoded[0]) || $decoded === [] ? $decoded : [$decoded];
                } else {
                    $entries = [$trimmed];
                }
            } else {
                $entries = preg_split('/\s*,\s*/', $trimmed) ?: [];
            }
        }

        $items = [];
        foreach ($entries as $entry) {
            $file = '';
            $label = '';
            if (is_array($entry)) {
                $file = (string) ($entry['filename'] ?? $entry['file'] ?? $entry['name'] ?? $entry['path'] ?? '');
                $label = (string) ($entry['original_name'] ?? $entry['label'] ?? $entry['name'] ?? '');
            } else {
                $file = (string) $entry;
            }

            $file = basename(str_replace('\\', '/', trim($file)));
            if ($file === '' || $file === '.' || $file === '..') {
                continue;
            }

            $items[] = [
                'name' => $label !== '' ? basename(str_replace('\\', '/', $label)) : $file,
                'filename' => $file,
                'url' => ($webPublic !== '' ? $webPublic : '') . '/announcement/attachments/' . rawurlencode($file),
            ];
        }

        return $items;
    }

    /**
     * Body pengumuman = isi file TinyMCE/SunEditor di public/announcement/{filename}.
     * Filename kosong atau file tidak ada → string kosong, bukan "File not found".
     */
    private function announcementBodyFromFilename(?string $filename, string $webPublic): string
    {
        $filename = basename(str_replace('\\', '/', trim((string) $filename)));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return '';
        }

        $localPath = public_path('announcement' . DIRECTORY_SEPARATOR . $filename);
        if (is_file($localPath)) {
            $content = @file_get_contents($localPath);
            if ($content !== false && trim($content) !== '') {
                return $this->absolutizeAnnouncementHtmlAssets($content, $webPublic);
            }
        }

        if ($webPublic !== '') {
            $fileUrl = $webPublic . '/announcement/' . rawurlencode($filename);
            $content = @file_get_contents($fileUrl);
            if ($content !== false && trim($content) !== '' && strcasecmp(trim($content), 'File not found') !== 0) {
                return $this->absolutizeAnnouncementHtmlAssets($content, $webPublic);
            }
        }

        return '';
    }

    private function absolutizeAnnouncementHtmlAssets(string $html, string $webPublic): string
    {
        if ($webPublic === '' || $html === '') {
            return $html;
        }

        $rewritten = preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i',
            function ($match) use ($webPublic) {
                $src = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
                if (preg_match('#^(?:https?:|data:|blob:|mailto:)#i', $src)) {
                    return $match[0];
                }

                return $match[1] . $webPublic . '/' . ltrim($src, '/') . $match[3];
            },
            $html
        );

        return is_string($rewritten) ? $rewritten : $html;
    }
}
