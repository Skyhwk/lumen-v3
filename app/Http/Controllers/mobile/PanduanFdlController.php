<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\PanduanFdl;
use Illuminate\Http\Request;

class PanduanFdlController extends Controller
{
    public function index(Request $request) 
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = PanduanFdl::where('is_active', true)->where('is_publish', true);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%");
            });
        }

        $activities = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Tambahkan kolom `reff` ke setiap item
        $activities->getCollection()->transform(function ($item) {
            $filePath = public_path('panduan_fdl/' . $item->body);
            if (file_exists($filePath) && is_file($filePath)) {
                $item->reff = file_get_contents($filePath);
            } else {
                $item->reff = 'File not found';
            }
            return $item;
        });

        return response()->json($activities);
    }

}