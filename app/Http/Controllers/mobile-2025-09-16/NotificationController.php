<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\NotificationFdl;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getNotification(Request $request) {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = NotificationFdl::where('user_id', $this->user_id);

        $notification = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($notification);
    }
}