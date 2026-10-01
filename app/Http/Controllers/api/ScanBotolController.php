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
        $query = ScanBotol::query()
            ->orderByRaw('COALESCE(updated_at, created_at) DESC');

        return Datatables::of($query)
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'no_sampel', $keyword);
            })
            ->filterColumn('status', function ($query, $keyword) {
                $this->applyLikeFilter($query, 'status', $keyword);
            })
            ->filterColumn('updated_by', function ($query, $keyword) {
                $this->applyLikeFilterOnColumns($query, ['updated_by', 'created_by'], $keyword);
            })
            ->filterColumn('updated_at', function ($query, $keyword) {
                $this->applyLikeFilterOnColumns($query, ['updated_at', 'created_at'], $keyword);
            })
            ->orderColumn('updated_by', function ($query, $direction) {
                $query->orderByRaw('COALESCE(updated_by, created_by) ' . $direction);
            })
            ->orderColumn('updated_at', function ($query, $direction) {
                $query->orderByRaw('COALESCE(updated_at, created_at) ' . $direction);
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

    private function applyLikeFilterOnColumns($query, array $columns, $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $keyword = addcslashes($keyword, '%_\\');
        $pattern = '%' . $keyword . '%';

        $query->where(function ($nested) use ($columns, $pattern) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $nested->where($column, 'like', $pattern);
                } else {
                    $nested->orWhere($column, 'like', $pattern);
                }
            }
        });
    }
}
