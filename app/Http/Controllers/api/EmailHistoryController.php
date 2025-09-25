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
            ->addColumn('email', function ($row) {
                $filePath = storage_path('repository/email_history/' . $row->email_body);
                if (file_exists($filePath) && is_file($filePath)) {
                    return file_get_contents($filePath);
                } else {
                    return 'File not found';
                }
                })
        ->make(true);
    }
}