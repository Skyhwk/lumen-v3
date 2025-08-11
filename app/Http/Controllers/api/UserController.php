<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\UserToken;

class UserController extends Controller
{
    public function index(Request $request)
    {
    
        $data = User::with(['karyawan'])
            ->select('users.id', 'users.username', 'users.email')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'nama_lengkap' => $user->karyawan ? $user->karyawan->nama_lengkap : null
                ];
            });
        return response()->json([
            'data' => $data,
        ]);
    }

    public function impersonate(Request $request)
    {
        $user = UserToken::where('token', $request->token)->first();
        if ($user) {
            $user->user_id = $request->user_id;
            $user->is_impersonate = true;
            $user->save();
        } else {
            return response()->json(['error' => 'Token tidak ditemukan'], 404);
        }
        return response()->json([
            'message' => 'Berhasil impersonate user',
            'data' => $user
        ]);
    }

}




