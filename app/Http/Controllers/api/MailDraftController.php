<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\InternalMailService;
use Illuminate\Http\Request;

class MailDraftController extends Controller
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
            $sort = $request->input('sort');

            $result = $this->mail()->fetchList('local_draft', $page, $perPage, $query, false, $sort);

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function viewDetail(Request $request)
    {
        try {
            $id = $request->input('uid') ?? $request->input('id');
            $data = $this->mail()->getDetail('local_draft', $id);

            return response()->json($data, 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $draft = $this->mail()->saveLocalDraft($request->all());

            return response()->json([
                'message' => 'Draft berhasil disimpan',
                'data'    => $draft,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $id = $request->input('uid') ?? $request->input('id');
            $this->mail()->deleteLocalDraft($id);

            return response()->json(['message' => 'Draft berhasil dihapus'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function send(Request $request)
    {
        try {
            $this->mail()->sendEmail($request->all());

            if ($request->filled('id')) {
                $this->mail()->deleteLocalDraft($request->input('id'));
            }

            return response()->json(['message' => 'Email berhasil dikirim'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
