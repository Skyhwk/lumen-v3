<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\User;
use App\Models\UserToken;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;


class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with('user')->where('id', $this->user_id)->first();
        return response()->json([
            'message' => 'shown',
            'data' => $data,
        ]);
    }

    public function show(Request $request)
    {
        $data = MasterKaryawan::with('user')->where('id', $request->id)->first();
        return response()->json([
            'message' => 'shown',
            'data' => $data,
        ]);
    }

    public function editUser(Request $request)
    {
        $data = MasterKaryawan::with('user')->where('id', $this->user_id)->first();

        if ($data) {
            if ($request->has('username_baru') && !empty($request->username_baru)) {
                $data->user->username = $request->username_baru;
            }

            if ($request->has('password') && !empty($request->password)) {
                $data->user->password = Hash::make($request->password);
            }

            if ($request->hasFile('profilePicture')) {
                $profilePicture = $request->file('profilePicture');
                $imageName = time() . '.' . $profilePicture->getClientOriginalExtension();
                $destinationPath = public_path('/Foto_Karyawan');

                $profilePicture->move($destinationPath, $imageName);

                if ($data->image && file_exists($destinationPath . '/' . $data->image)) {
                    unlink($destinationPath . '/' . $data->image);
                }

                $data->image = $imageName;
            }

            
            $data->user->save();
            $data->save();

            return response()->json(['message' => 'Profil berhasil diperbarui'], 200);
        }

        return response()->json(['message' => 'Pengguna tidak ditemukan'], 404);
    }


    public function getLeaderboard(Request $request){
        $karyawanModel = new MasterKaryawan();
        $leaderboard = $karyawanModel->getLeaderboard($request->id);

        return response()->json([$leaderboard]);
    }


}