<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use Datatables;

use App\Models\Webphone;
use App\Models\MasterKaryawan;
use App\Services\Crypto;

class WebphoneAccessController extends Controller
{
    public function index()
    {
        $webphones = Webphone::with('karyawan.jabatan')->latest('id')->get();

        return Datatables::of($webphones)->make(true);
    }

    public function getAllKaryawan()
    {
        $employees = MasterKaryawan::where('is_active', true)->orderBy('nama_lengkap')->get();

        return response()->json($employees, 200);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sip_username' => 'unique:webphone,sip_username',
        ]);

        if ($validator->fails()) return response()->json(['message' => 'Username already used'], 400);

        $webphone = $request->id ? Webphone::find($request->id) : new Webphone();

        $webphone->karyawan_id = $request->id_karyawan;
        $webphone->sip_username = $request->sip_username;
        $webphone->sip_password = (new Crypto)->encrypt($request->sip_password);

        $webphone->save();

        return response()->json(['message' => 'Webphone Access saved successfully'], 200);
    }

    public function updateInline(Request $request)
    {
        $webphone = Webphone::find($request->id);

        if ($request->sip_username) {
            $validator = Validator::make($request->all(), [
                'sip_username' => 'unique:webphone,sip_username,' . $request->id,
            ]);

            if ($validator->fails()) return response()->json(['message' => 'Username already used'], 400);

            $webphone->sip_username = $request->sip_username;
        }

        if ($request->karyawan_id) {
            $validator = Validator::make($request->all(), [
                'karyawan_id' => 'unique:webphone,karyawan_id,' . $request->id,
            ]);

            if ($validator->fails()) return response()->json(['message' => 'Employee already exist'], 400);

            $webphone->karyawan_id = $request->karyawan_id;
        }

        if ($request->sip_password) $webphone->sip_password = (new Crypto)->encrypt($request->sip_password);

        $webphone->save();

        return response()->json(['message' => 'Webphone Access updated successfully'], 200);
    }

    public function destroy(Request $request)
    {
        $webphone = Webphone::find($request->id);
        $webphone->delete();

        return response()->json(['message' => 'Webphone Access deleted successfully'], 200);
    }
}
