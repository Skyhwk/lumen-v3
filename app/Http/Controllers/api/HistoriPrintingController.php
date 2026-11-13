<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HistoriPrinting;
use DataTables;

class HistoriPrintingController extends Controller
{
    public function index(Request $request)
    {
        $data = HistoriPrinting::query();

        return DataTables::of($data)
            ->order(function ($query) {
                // created_at banyak NULL; fallback ke timestamp
                $query->reorder()
                    ->orderByRaw('COALESCE(created_at, timestamp) DESC')
                    ->orderBy('id', 'desc');
            })
            ->make(true);
    }
}
