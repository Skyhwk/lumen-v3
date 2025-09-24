<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\UserToken;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class GenerateTokenController extends Controller
{
    public function index(Request $request)
    {
        $divisi = UserToken::with('user')->where('type', 'public')->get();
        return Datatables::of($divisi)->make(true);
    }

    public function store(Request $request){
        if($request->id!=''){
            DB::beginTransaction();
            try {
                $data = UserToken::where('id', $request->id)->first();
                if ($data) {
                    $existingEmail = User::where('email', $request->email)->where('id', '!=', $data->user_id)->first();
                    if ($existingEmail) {
                        return response()->json(['message' => 'Email already exists'], 401);
                    }

                    $data->expired = $request->expired;
                    $data->save();

                    $user = User::where('id', $data->user_id)->first();
                    $user->email = $request->email;
                    $user->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Token hasben configured'
                    ], 200);
                }
                return response()->json(['message' => 'Something Wrong'], 401);
            } catch (\Throwable $th) {
                DB::rollback();
                return response()->json([
                    'message' => $th->getMessage()
                ], 401);
            }
        } else {
            DB::beginTransaction();
            try {
                $cek = User::where('email', $request->email)->where('is_active', true)->first();
                if($cek){
                    return response()->json([
                        'message' => 'Email already Exist.'
                    ], 401);
                }

                $user = new User;
                $user->username = $this->generateUsername();
                $user->email = $request->email;
                $user->password = Hash::make($request->email);
                $user->created_by = $this->karyawan;
                $user->created_at = Date('Y-m-d H:i:s');
                $user->save();

                if($user){
                    $data = new UserToken;
                    $data->user_id = $user->id;
                    $data->type = 'public';
                    $data->create_date = DATE('Y-m-d H:i:s');
                    $data->expired = $request->expired;
                    $data->token = $request->token;
                    $data->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Token hasben Generate.'
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Something wrong.!'
                    ], 401);
                }
            } catch (\Throwable $th) {
                DB::rollback();
                return response()->json([
                    'message' => $th->getMessage()
                ], 401);
            }
        }
    }

    public function delete(Request $request){
        if($request->email !=''){
            $data = User::where('email', $request->email)->delete();
            if($data){
                return response()->json(['message' => 'Token Delete successfully'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }

    private function generateUsername($length = 8) {
        $microtime = microtime(true);
        $hash = preg_replace('/[^a-zA-Z0-9]/', '', base_convert($microtime, 10, 36));
        return substr($hash, 0, $length);
    }
    

}
