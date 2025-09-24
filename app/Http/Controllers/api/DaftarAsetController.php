<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;

use App\Models\MasterAset;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use App\Models\KategoriAset;
use App\Models\KategoriBarang;

class DaftarAsetController extends Controller
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

    public function save(Request $request)
    {
        $data['no_cs'] = $request->no_cs;
        $data['tanggal_pembelian'] = $request->tanggal_pembelian;
        $data['kategori'] = $request->kategori_1;
        $data['sub_kategori'] = $request->kategori_2;
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

    public function createMutation(Request $request)
    {
        dd($request->all());
        $asset = MasterAset::find($request->id);
        $asset->is_active = false;
        $asset->deleted_by = $this->karyawan;
        $asset->save();

        return response()->json(['message' => 'Deleted Successfully'], 200);
    }
}
