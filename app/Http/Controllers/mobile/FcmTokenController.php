<?php

namespace App\Http\Controllers\mobile;
use App\Http\Controllers\Controller;
use App\Models\FcmTokenFdl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FcmTokenController extends Controller
{
    public function store(Request $request) {
        $fcm_token = $request->tokenFcm;
        DB::beginTransaction();
        try {
            if(isset($fcm_token) && $fcm_token !== ''){
                $checkFcmToken = FcmTokenFdl::where('user_id', $this->user_id)->first();
                if($checkFcmToken){
                    $checkFcmToken->fcm_token = $fcm_token;
                    $checkFcmToken->updated_at = Carbon::now();
                    $checkFcmToken->save();
                } else {
                    FcmTokenFdl::create([
                        'fcm_token' => $fcm_token,
                        'user_id' => $this->user_id,
                        'created_at' => Carbon::now(),
                    ]);
                }
                DB::commit();
                return response()->json([
                    'message' => 'Success Update FCM Token'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Tidak ada FCM Token'
                ], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
}