<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ScanBotol;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class ScanBotolController extends Controller
{
    public function index(Request $request)
    {
        $query = ScanBotol::query()->orderByDesc('created_at');

        return Datatables::of($query)
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'no_sampel', $keyword);
            })
            ->filterColumn('status', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'status', $keyword);
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'created_by', $keyword);
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'created_at', $keyword);
            })
            ->make(true);
    }

    private function applyLikeFilter($query, string $column, $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $keyword = addcslashes($keyword, '%_\\');
        $query->where($column, 'like', '%' . $keyword . '%');
    }
}
