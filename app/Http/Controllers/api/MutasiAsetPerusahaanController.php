<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;
use Illuminate\Support\Facades\DB;

use App\Models\MasterAset;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use App\Models\KategoriAset;
use App\Models\KategoriBarang;
use App\Models\MutasiAsetPerusahaan;

use Carbon\Carbon;

class MutasiAsetPerusahaanController extends Controller
{
    public function index()
    {
        $assets = MasterAset::with(['kategori_aset', 'sub_kategori_aset', 'mutasi'])->latest()->get();

        return Datatables::of($assets)->make(true);
    }

    public function getAllOptions()
    {
        // dd("test");
        $columns = ['jenis_aset', 'merk', 'tipe', 'ruang', 'lokasi'];
        $results = MasterAset::where('is_active', true)
            ->select($columns)
            ->distinct()
            ->get();

        $groupedResults["kategori_1_options"] = MasterKategoriAset::orderBy('nama')->where('is_active', true)->get()->map(fn($item) => ['id' => $item->id, 'text' => $item->nama])->values();
        // $groupedResults["kategori_2_options"] = MasterSubKategoriAset::orderBy('nama')->where('is_active', true)->get()->map(fn($item) => ['id' => $item->id, 'text' => $item->nama])->values();

        foreach ($columns as $column) {
            $groupedResults["{$column}_options"] = $results->pluck($column)
                ->unique()
                ->map(fn($item) => ['id' => $item, 'text' => $item])
                ->values();
        }

        return response()->json($groupedResults);
    }

    public function getSubKategori(Request $request)
    {
        $subKategori = MasterSubKategoriAset::where('kategori_aset_id', $request->kategori_id)->orderBy('nama')->where('is_active', true)->get()->map(fn($item) => ['id' => $item->id, 'text' => $item->nama])->values();
        return response()->json($subKategori);
    }

    public function validateNoCS(Request $request)
    {
        $exists = MasterAset::where('no_cs', $request->no_cs)->exists();

        return response()->json(['exists' => $exists]);
    }

    public function destroy(Request $request)
    {
        $aset = MasterAset::find($request->id);

        $aset->is_active = false;
        $aset->deleted_by = $this->karyawan;
        $aset->save();

        return response()->json(['message' => 'Deleted Successfully'], 200);
    }

    public function createMutation(Request $request)
    {
        try {
            DB::beginTransaction();
            $asset = MasterAset::find($request->id);
            if(isset($request->mutasi) && $request->mutasi == true){
                MutasiAsetPerusahaan::create([
                    'id_aset_perusahaan' => $asset->id,
                    'lokasi_mutasi' => $request->lokasi_mutasi,
                    'ruang_mutasi' => $request->ruang_mutasi,
                    'keterangan_mutasi' => $request->keterangan_mutasi ?? null,
                    'mutasi_by' => $this->karyawan,
                    'mutasi_at' => Carbon::now()
                ]);
            }

            if(isset($request->barang_rusak) && $request->barang_rusak == true){
                $asset->tanggal_terima_barang_rusak = Carbon::now();
                $asset->keterangan_barang_rusak = $request->keterangan_barang_rusak;
                $asset->updated_by = $this->karyawan;
                $asset->updated_at = Carbon::now();
                $asset->save();
            }

            DB::commit();
            return response()->json(['message' => 'Deleted Successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error occurred while deleting asset', 'error' => $e->getMessage()], 500);
        }
    }
}
