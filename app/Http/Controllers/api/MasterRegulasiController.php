<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterRegulasi;
use App\Models\MasterBakumutu;
use App\Models\MasterKategori;
use App\Models\Parameter;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterRegulasiController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $timestamp = DATE('Y-m-d H:i:s');

            if ($request->id != '') {
                // Update existing regulasi
                $dataregulasi = MasterRegulasi::find($request->id);

                if (!$dataregulasi) {
                    return response()->json(['message' => 'Regulasi tidak ditemukan'], 404);
                }

                $dataregulasi->peraturan = $request->peraturan;
                $dataregulasi->deskripsi = $request->deskripsi;
                $dataregulasi->updated_by = $this->karyawan;
                $dataregulasi->updated_at = $timestamp;
                $dataregulasi->save();

                $existingBakumutuIds = MasterBakumutu::where('id_regulasi', $dataregulasi->id)->pluck('id')->toArray();
                $updatedBakumutuIds = $request->bakumutu['id'] ?? [];

                foreach ($request->bakumutu['id_parameter'] as $index => $id_parameter) {

                    $bakumutuData = [
                        'id_regulasi' => $dataregulasi->id,
                        'id_parameter' => $id_parameter,
                        'satuan' => $request->bakumutu['satuan'][$index],
                        'method' => $request->bakumutu['method'][$index],
                        'baku_mutu' => $request->bakumutu['baku_mutu'][$index],
                        'nama_header' => $request->bakumutu['nama_header'][$index] ?? null,
                        'durasi_pengukuran' => $request->bakumutu['durasi_pengukuran'][$index] ?? null,
                        'akreditasi' => $request->bakumutu['akreditasi'][$index] ?? null,
                    ];

                    if (isset($request->bakumutu['id'][$index]) && in_array($request->bakumutu['id'][$index], $existingBakumutuIds)) {
                        $bakumutu = MasterBakumutu::find($request->bakumutu['id'][$index]);
                        $bakumutu->update($bakumutuData);
                    } else {
                        MasterBakumutu::create($bakumutuData);
                    }
                }

                $deletedBakumutuIds = array_diff($existingBakumutuIds, $updatedBakumutuIds);
                if (!empty($deletedBakumutuIds)) {
                    MasterBakumutu::whereIn('id', $deletedBakumutuIds)->update([
                        'is_active' => false
                    ]);
                }

            } else {
                // Create new regulasi
                $dataregulasi = $request->only([
                    'peraturan',
                    'deskripsi',
                    'id_kategori',
                ]);

                $existingRegulasi = MasterRegulasi::where('peraturan', $request->peraturan)->where('is_active', true)->first();
                if ($existingRegulasi) {
                    return response()->json(['message' => 'Regulasi dengan data yang sama sudah ada'], 401);
                }

                $cek_kategori = MasterKategori::where('id', $request->id_kategori)->first();
                $dataregulasi['nama_kategori'] = $cek_kategori->nama_kategori;
                $dataregulasi['created_by'] = $this->karyawan;
                $dataregulasi['created_at'] = DATE('Y-m-d H:i:s');

                $regulasi = MasterRegulasi::create($dataregulasi);

                if ($request->has('bakumutu')) {
                    foreach ($request->bakumutu['id_parameter'] as $index => $id_parameter) {
                        if (!empty($id_parameter)) {

                            $bakumutu = [
                                'id_regulasi' => $regulasi->id,
                                'id_parameter' => $id_parameter,
                                'satuan' => $request->bakumutu['satuan'][$index],
                                'method' => $request->bakumutu['method'][$index],
                                'baku_mutu' => $request->bakumutu['baku_mutu'][$index],
                                'nama_header' => $request->bakumutu['nama_header'][$index],
                                'durasi_pengukuran' => $request->bakumutu['durasi_pengukuran'][$index] ?? null,
                                'akreditasi' => $request->bakumutu['akreditasi'][$index] ?? null,
                                'created_by' => $this->karyawan,
                                'created_at' => $timestamp,
                            ];

                            MasterBakumutu::create($bakumutu);
                        }
                    }
                }
            }
        });

        return response()->json(['message' => 'Data berhasil disimpan']);
    }

    public function delete(Request $request)
    {
        $data = MasterRegulasi::find($request->id);
        if ($data) {
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = Date('Y-m-d H:i:s');
            $data->is_active = false;
            $data->save();
            return response()->json(['message' => 'Data Regulasi berhasil dinonaktifkan']);
        }

        return response()->json(['message' => 'Data Regulasi tidak ditemukan'], 404);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json([
            'message' => 'Data Kategori berhasil ditampilkan',
            'data' => $data
        ], 200);
    }

    public function getParameters(Request $request)
    {
        /* Perbaikan oleh 565 2025-04-30
        $data = Parameter::where('is_active', true)
            ->where('id_kategori', $request->id_kategori)
            ->select('id', 'nama_lab', 'nama_regulasi', 'nama_lhp', 'method', 'satuan')
            ->get();
        */

        $data = Parameter::with('hargaParameter')
            ->whereHas('hargaParameter')
            ->where('is_active', true)
            ->where('id_kategori', $request->id_kategori)
            ->select('id', 'nama_lab', 'nama_regulasi', 'nama_lhp', 'method', 'satuan')
            ->get();

        return response()->json([
            'message' => 'Data Parameter berhasil ditampilkan',
            'data' => $data
        ], 200);
    }
}
