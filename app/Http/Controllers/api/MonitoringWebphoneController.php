<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Webphone;
use App\Models\LogWebphone;
use App\Models\MasterKaryawan;

class MonitoringWebphoneController extends Controller
{
    public function getSales(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan;
        if ($karyawan) {
            if ($karyawan->id_jabatan == 24) { // Sales Staff
                $webphones = Webphone::where('karyawan_id', $this->user_id)->limit(1);
            } else if ($karyawan->id_jabatan == 21) { // Sales SPV
                $bawahan = MasterKaryawan::where('is_active', true)
                    ->whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $webphones = Webphone::whereIn('karyawan_id', $bawahan);
            } else if ($karyawan->id_jabatan == 15) { // Sales Manager
                $webphones = Webphone::query();
            } else { // Yang mulia
                $webphones = Webphone::query();
            }
            $data = $webphones->get()->map(function ($item) {

                $item->karyawan = MasterKaryawan::where('id_department', 9)->where('is_active', true)->find($item->karyawan_id);
                $item->logWebphone = LogWebphone::where('karyawan_id', $item->karyawan_id)->latest()->first();

                return $item->karyawan ? $item : null;
            })->filter()->values();
        } else {
            // buat monitoring pake token public
            $webphones = Webphone::query();
            $data = $webphones->get()->map(function ($item) {

                $item->karyawan = MasterKaryawan::where('id_department', 9)->whereIn('id_jabatan', [24, 148])->where('is_active', true)->find($item->karyawan_id);
                $item->logWebphone = LogWebphone::where('karyawan_id', $item->karyawan_id)->latest()->first();

                return $item->karyawan ? $item : null;
            })->filter()->values();

        }


        $data = $data->map(function ($item) {
            if (is_null($item->karyawan->image))
                $item->karyawan->image = 'no_image_2.jpg';
            return $item;
        })->values();

        return response()->json(['data' => $data, 'success' => true], 200);
    }
    public function getDetail(Request $request)
    {
        $data = LogWebphone::where('karyawan_id', $request->karyawan_id)->latest()->get();

        return response()->json(['data' => $data, 'success' => true], 200);
    }
}
