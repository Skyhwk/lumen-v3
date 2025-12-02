<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\EmailHistory;
use Illuminate\Http\Request;


use File;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class EmailHistoryController extends Controller
{
    public function index()
    {
        $data = EmailHistory::orderBy('id', 'desc');
        return Datatables::of($data)
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_to', function ($query, $keyword) {
                $query->where('email_to', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_cc', function ($query, $keyword) {
                $query->where('email_cc', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_bcc', function ($query, $keyword) {
                $query->where('email_bcc', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_subject', function ($query, $keyword) {
                $query->where('email_subject', 'like', '%' . $keyword . '%');
            })
        ->make(true);
    }

    public function getDetailData(Request $request)
    {
        $data = EmailHistory::where('id', $request->id)->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // ✅ Ambil isi file email
        $filePath = storage_path('repository/email_history/' . $data->email_body);

        if (file_exists($filePath) && is_file($filePath)) {
            $data->email_content = file_get_contents($filePath);
        } else {
            $data->email_content = 'File not found';
        }
        return response()->json([
            'status' => true,
            'data' => $data, // ✅ email_content SUDAH MASUK DI SINI
        ], 200);
    }
}