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
        $data = Notification::where('user_id', $this->user_id)->where('is_active', true)->get();

        return response()->json(['data'=>$data]);
    }

    public function getNotification(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)
        ->where('is_active', true)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json(['data'=>$data]);
    }

    public function readNotificationAll(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->update(['is_read'=>true]);
        return response()->json(['data'=>$data]);
    }

    public function deleteNotificationAll(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->delete();
        return response()->json(['data'=>$data]);
    }

    public function deleteNotification(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->where('id', $request->id)->delete();
        return response()->json(['data'=>$data]);
    }

    public function readNotification(Request $request)
    {
        $data = Notification::where('user_id', $this->user_id)->where('id', $request->id)->update(['is_read'=>true]);
        return response()->json(['data'=>$data]);
    }

}