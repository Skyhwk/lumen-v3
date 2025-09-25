<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\EmailHistory;
use Illuminate\Http\Request;

class EmailHistoryController extends Controller
{
    public function index()
    {
        $data = EmailHistory::orderBy('id', 'desc');
        return Datatables::of($data)->make(true);
    }
}