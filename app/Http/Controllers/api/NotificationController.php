<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserToken;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Ambil total jumlah notifikasi yang belum dibaca
        $unreadCount = Notification::where('user_id', $this->user_id)
            ->where('is_active', true)
            ->where('is_read', false)
            ->count();

        // Ambil 5 notifikasi terbaru yang belum dibaca
        $unreadNotifications = Notification::where('user_id', $this->user_id)
            ->where('is_active', true)
            ->where('is_read', false)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => $unreadNotifications,
            'unread_count' => $unreadCount,
        ]);
    }


    public function getNotification(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $data = Notification::where('user_id', $this->user_id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    public function readNotificationAll(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->update(['is_read' => true]);
        return response()->json(['data' => $data]);
    }

    public function deleteNotificationAll(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->delete();
        return response()->json(['data' => $data]);
    }

    public function deleteNotification(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->where('id', $request->id)->delete();
        return response()->json(['data' => $data]);
    }

    public function readNotification(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->where('id', $request->id)->update(['is_read' => true]);
        return response()->json(['data' => $data]);
    }

    public function sendNotificationToV3(Request $request)
    {
        Notification::whereIn('id', $request->users)
            ->title($request->title)
            ->message($request->message)
            ->url($request->url)
            ->send();
    }
}
