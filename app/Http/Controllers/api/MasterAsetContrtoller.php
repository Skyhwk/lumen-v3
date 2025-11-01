<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;

use App\Models\MasterAset;
use App\Models\KategoriAset;
use App\Models\KategoriBarang;

class MasterAsetController extends Controller
{
    public function index()
    {
        $assets = MasterAset::with(['kategori_aset', 'kategori_barang'])->latest()->get();

        return Datatables::of($assets)->make(true);
    }

    public function getAllOptions()
    {
        $columns = ['jenis_aset', 'merk', 'tipe', 'ruang', 'lokasi'];
        $results = MasterAset::where('is_active', true)
            ->select($columns)
            ->distinct()
            ->get();

        $groupedResults["kategori_1_options"] = KategoriAset::orderBy('nama_kategori')->get()->map(fn($item) => ['id' => $item->id, 'text' => $item->nama_kategori])->values();
        $groupedResults["kategori_2_options"] = KategoriBarang::orderBy('kategori')->get()->map(fn($item) => ['id' => $item->id, 'text' => $item->kategori])->values();

        foreach ($columns as $column) {
            $groupedResults["{$column}_options"] = $results->pluck($column)
                ->unique()
                ->map(fn($item) => ['id' => $item, 'text' => $item])
                ->values();
        }

        return response()->json($groupedResults);
    }

    public function validateNoCS(Request $request)
    {
        $exists = MasterAset::where('no_cs', $request->no_cs)->exists();

        return response()->json(['exists' => $exists]);
    }

    public function save(Request $request)
    {
        $data['no_cs'] = $request->no_cs;
        $data['tanggal_pembelian'] = $request->tanggal_pembelian;
        $data['kategori_1'] = $request->kategori_1;
        $data['kategori_2'] = $request->kategori_2;
        $data['jenis_aset'] = $request->jenis_aset;
        $data['merk'] = $request->merk;
        $data['tipe'] = $request->tipe;
        $data['status'] = $request->status;
        $data['ruang'] = $request->ruang;
        $data['lokasi'] = $request->lokasi;
        $data['harga'] = str_replace(',', '', $request->harga);
        $data['umur_manfaat'] = $request->umur_manfaat;
        $data['accurate_check'] = $request->accurate_check;
        $data['kondisi'] = $request->kondisi;
        $data['is_labeled'] = $request->is_labeled ? 1 : 0;
        $data['is_n'] = $request->is_n ? 1 : 0;
        $data['is_k'] = $request->is_k ? 1 : 0;
        $data['is_r'] = $request->is_r ? 1 : 0;
        $data['is_rb'] = $request->is_rb ? 1 : 0;
        $data['is_h'] = $request->is_h ? 1 : 0;
        $data['is_t'] = $request->is_t ? 1 : 0;
        $data['tanggal_terima_barang_rusak'] = $request->tanggal_terima_barang_rusak;
        $data['lokasi_mutasi'] = $request->lokasi_mutasi;
        $data['tanggal_mutasi'] = $request->tanggal_mutasi;

        if (!$request->id) {
            $data['created_by'] = $this->karyawan;
            $data['updated_by'] = $this->karyawan;
        } else {
            $data['updated_by'] = $this->karyawan;
        };

        MasterAset::updateOrCreate(['id' => $request->id], $data);

        return response()->json(['message' => 'Saved Successfully'], 200);
    }

    public function destroy(Request $request)
    {
        $asset = MasterAset::find($request->id);
        $asset->is_active = false;
        $asset->deleted_by = $this->karyawan;
        $asset->save();

        $asset->delete(); // soft delete

        return response()->json(['message' => 'Deleted Successfully'], 200);
    }
}
