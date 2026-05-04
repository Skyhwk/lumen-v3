<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\JadwalMobil;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
class JadwalMobilController extends Controller
{
    public function index(Request $request){
        $data = JadwalMobil::where('is_active', true)
            ->where('tanggal_berangkat', $request->tanggal);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request){
        JadwalMobil::create([
            'tanggal_berangkat' => $request->tanggal_berangkat ?? null,
            'jam_berangkat' => $request->jam_berangkat ?? null,
            'plat_mobil' => $request->plat_mobil ?? null,
            'keterangan' => $request->keterangan ?? null,
            'created_by' => $this->karyawan,
            'created_at' => Carbon::now()
        ]);

        return response()->json(['message' => 'Jadwal mobil berhasil dibuat'], 201);
    }


    public function update(Request $request, $id){
        $jadwal = JadwalMobil::find($id);
        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal mobil tidak ditemukan'], 404);
        }

        $jadwal->update([
            'tanggal_berangkat' => $request->tanggal_berangkat ?? $jadwal->tanggal_berangkat,
            'jam_berangkat' => $request->jam_berangkat ?? $jadwal->jam_berangkat,
            'plat_mobil' => $request->plat_mobil ?? $jadwal->plat_mobil,
            'keterangan' => $request->keterangan ?? $jadwal->keterangan,
            'updated_by' => $this->karyawan,
            'updated_at' => Carbon::now()
        ]);

        return response()->json(['message' => 'Jadwal mobil berhasil diperbarui'], 200);
    }

    public function delete($id){
        $jadwal = JadwalMobil::find($id);
        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal mobil tidak ditemukan'], 404);
        }

        $jadwal->update([
            'is_active' => false,
            'deleted_by' => $this->karyawan,
            'deleted_at' => Carbon::now()
        ]);

        return response()->json(['message' => 'Jadwal mobil berhasil dihapus'], 200);
    }

}