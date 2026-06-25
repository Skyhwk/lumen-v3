<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\InternalMailService;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    private function mail(): InternalMailService
    {
        return new InternalMailService((int) $this->user_id, $this->karyawan);
    }

    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(1, (int) $request->input('per_page', 30)));
            $query = $request->input('query');
            $forceRefresh = filter_var($request->input('force_refresh', false), FILTER_VALIDATE_BOOLEAN);
            $skipSync = filter_var($request->input('skip_sync', false), FILTER_VALIDATE_BOOLEAN);
            $sort = $request->input('sort');
            $filter = $request->input('filter');

            $result = $this->mail()->fetchList('inbox', $page, $perPage, $query, $forceRefresh, $sort, $filter, $skipSync);

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function checkUpdates(Request $request)
    {
        try {
            $result = $this->mail()->checkUpdates('inbox');
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function viewDetail(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            if (!$uid) {
                return response()->json(['message' => 'UID email wajib diisi'], 422);
            }

            $data = $this->mail()->getDetail('inbox', $uid);
            return response()->json($data, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function markSeen(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $seen = filter_var($request->input('seen', true), FILTER_VALIDATE_BOOLEAN);

            $this->mail()->markSeen('inbox', $uid, $seen);

            return response()->json(['message' => 'Status email berhasil diperbarui'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function trash(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $this->mail()->moveToTrash('inbox', $uid);

            return response()->json(['message' => 'Email berhasil dipindahkan ke sampah'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function move(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $target = $request->input('target', 'inbox');

            $this->mail()->moveEmail('inbox', $uid, $target);

            return response()->json(['message' => 'Email berhasil dipindahkan'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
