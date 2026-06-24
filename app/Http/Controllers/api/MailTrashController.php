<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\InternalMailService;
use Illuminate\Http\Request;

class MailTrashController extends Controller
{
    private function mail(): InternalMailService
    {
        return new InternalMailService($this->karyawan);
    }

    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(1, (int) $request->input('per_page', 30)));
            $query = $request->input('query');
            $forceRefresh = filter_var($request->input('force_refresh', false), FILTER_VALIDATE_BOOLEAN);
            $sort = $request->input('sort');
            $filter = $request->input('filter');

            $result = $this->mail()->fetchList('trash', $page, $perPage, $query, $forceRefresh, $sort, $filter);

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function viewDetail(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $data = $this->mail()->getDetail('trash', $uid);

            return response()->json($data, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function restore(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $this->mail()->moveEmail('trash', $uid, 'inbox');

            return response()->json(['message' => 'Email berhasil dipulihkan'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $uid = $request->input('uid') ?? $request->input('id');
            $this->mail()->deletePermanent('trash', $uid);

            return response()->json(['message' => 'Email berhasil dihapus permanen'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function emptyTrash(Request $request)
    {
        try {
            $result = $this->mail()->emptyTrash();

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
