<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterTargetPenjadwalan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class MasterTargetPenjadwalanController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterTargetPenjadwalan::where('is_active', true)->get();
        
        $logs = DB::table('log_target_penjadwalan')
            ->whereIn('id_target', $data->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function($item) {
                return $item->id_target . '_' . $item->bulan;
            });

        foreach($data as $row) {
            $rowLogs = [];
            $months = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
            foreach($months as $m) {
                $key = $row->id . '_' . $m;
                if(isset($logs[$key])) {
                    $rowLogs[$m] = $logs[$key]->values()->all();
                }
            }
            $row->logs = $rowLogs;
        }

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            if ($request->id) {
                $data = MasterTargetPenjadwalan::find($request->id);
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
            } else {
                $cekTahun = MasterTargetPenjadwalan::where('tahun', $request->tahun)->where('is_active', true)->first();
                if ($cekTahun) return response()->json(['message' => 'Data Sudah Ada untuk Tahun tersebut'], 400);
                $data = new MasterTargetPenjadwalan();
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
            }

            $data->tahun = $request->tahun;
            $data->januari = $request->januari;
            $data->februari = $request->februari;
            $data->maret = $request->maret;
            $data->april = $request->april;
            $data->mei = $request->mei;
            $data->juni = $request->juni;
            $data->juli = $request->juli;
            $data->agustus = $request->agustus;
            $data->september = $request->september;
            $data->oktober = $request->oktober;
            $data->november = $request->november;
            $data->desember = $request->desember;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data Berhasil Disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Data Gagal Disimpan: ' . $e->getMessage()], 500);
        }
    }

    public function updateMonth(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterTargetPenjadwalan::find($request->id);
            if (!$data) return response()->json(['message' => 'Data Tidak Ditemukan'], 404);

            $bulanField = strtolower($request->bulan);
            
            // Validasi nama kolom bulan agar terhindar dari mass assignment vulnerability / injection
            $validMonths = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
            if (!in_array($bulanField, $validMonths)) {
                return response()->json(['message' => 'Kolom Bulan Tidak Valid'], 400);
            }

            // Simpan log perubahan (jika tabel log sudah disiapkan)
            DB::table('log_target_penjadwalan')->insert([
                'id_target' => $data->id,
                'tahun' => $data->tahun,
                'bulan' => $bulanField,
                'nilai_lama' => $data->{$bulanField},
                'nilai_baru' => $request->nilai_baru,
                'alasan' => $request->alasan,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()
            ]);

            $data->{$bulanField} = $request->nilai_baru;
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now();
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data bulan berhasil diperbarui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Data Gagal Disimpan: ' . $e->getMessage()], 500);
        }
    }

    public function delete($id)
    {
        $data = MasterTargetPenjadwalan::find($id);
        if (!$data) return response()->json(['message' => 'Data Tidak Ditemukan'], 404);

        $data->is_active = false;
        $data->deleted_by = $this->karyawan;
        $data->deleted_at = Carbon::now();
        $data->save();

        return response()->json(['message' => 'Data Berhasil Dihapus']);
    }
}