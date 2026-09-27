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
            DB::raw("'Announcement' as title"),
            'description as title',
            'filename',
            'created_at as timestamp',
            DB::raw("CASE WHEN JSON_CONTAINS(is_read, '\"{$userId}\"') THEN true ELSE false END as is_read"),
            DB::raw("CASE WHEN JSON_CONTAINS(is_deleted, '\"{$userId}\"') THEN true ELSE false END as is_deleted")
        )->whereJsonContains('delivery', (string) $userId);

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
            $fileUrl = $webPublic . '/announcement/' . $row->filename;

            try {
                $content = @file_get_contents($fileUrl);
                $row->reff = $content !== false ? $content : 'File not found';
            } catch (\Throwable $th) {
                $row->reff = 'Error fetching file';
            }

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
            ->whereJsonContains('delivery', (string) $this->user_id)
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
            $announcements = Announcement::whereJsonContains('delivery', $userId)->get();

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
            $announcements = Announcement::whereJsonContains('delivery', $userId)->get();

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
}
