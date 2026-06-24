<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\InternalMailService;
use Illuminate\Http\Request;

class MailSpamController extends Controller
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

            $result = $this->mail()->fetchList('spam', $page, $perPage, $query, $forceRefresh, $sort, $filter, $skipSync);

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function viewDetail(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $data = $this->mail()->getDetail('spam', $uid);

            return response()->json($data, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function trash(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $this->mail()->moveToTrash('spam', $uid);

            return response()->json(['message' => 'Email berhasil dihapus'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function emptySpam(Request $request)
    {
        try {
            $result = $this->mail()->emptySpam();

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
