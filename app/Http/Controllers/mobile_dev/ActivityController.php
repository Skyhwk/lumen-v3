<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\FdlActivity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function getActivity(Request $request) {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = FdlActivity::where('user_id', $this->user_id);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('activity', 'like', "%$search%");
            });
        }

        $activities = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($activities);
    }
}