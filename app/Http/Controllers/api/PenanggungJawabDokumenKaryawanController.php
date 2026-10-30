<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\PenanggungJawabDokumenKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class PenanggungJawabDokumenKaryawanController extends Controller
{
    public function index()
    {
        $data = PenanggungJawabDokumenKaryawan::query()
            ->where('is_active', 1)
            ->orderBy('nama_karyawan');

        return Datatables::of($data)->make(true);
    }

    public function options()
    {
        $rows = PenanggungJawabDokumenKaryawan::query()
            ->where('is_active', 1)
            ->orderBy('nama_karyawan')
            ->get(['id', 'nama_karyawan', 'jabatan']);

        return response()->json([
            'message' => 'Penanggung jawab dokumen berhasil dimuat.',
            'data' => $rows,
        ], 200);
    }

    public function getListKaryawan()
    {
        $rows = MasterKaryawan::with('jabatan')
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json([
            'data' => $rows->map(function ($row) {
                $jabatan = $row->getAttributes()['jabatan'] ?? null;
                if ($jabatan === null || $jabatan === '') {
                    $jabatan = optional($row->getRelation('jabatan'))->nama_jabatan;
                }

                return [
                    'id' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                    'jabatan' => $jabatan ?: '',
                ];
            })->filter(function ($row) {
                return trim((string) $row['nama_lengkap']) !== '';
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $nama = trim((string) $request->nama_karyawan);
        $jabatan = trim((string) $request->jabatan);

        if ($nama === '' || $jabatan === '') {
            return response()->json(['message' => 'Nama karyawan dan jabatan wajib diisi.'], 422);
        }
        if (mb_strlen($nama) > 150 || mb_strlen($jabatan) > 150) {
            return response()->json(['message' => 'Nama karyawan dan jabatan maksimal 150 karakter.'], 422);
        }

        $now = Carbon::now();
        if ($request->id) {
            $data = PenanggungJawabDokumenKaryawan::where('id', $request->id)
                ->where('is_active', 1)
                ->first();
            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }
            $data->updated_by = $this->karyawan;
            $data->updated_at = $now;
        } else {
            $data = new PenanggungJawabDokumenKaryawan();
            $data->is_active = 1;
            $data->created_by = $this->karyawan;
            $data->created_at = $now;
        }

        $data->nama_karyawan = $nama;
        $data->jabatan = $jabatan;
        $data->save();

        return response()->json(['message' => 'Penanggung jawab dokumen berhasil disimpan.'], 200);
    }

    public function delete(Request $request)
    {
        $data = PenanggungJawabDokumenKaryawan::where('id', $request->id)
            ->where('is_active', 1)
            ->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $data->is_active = 0;
        $data->updated_by = $this->karyawan;
        $data->updated_at = Carbon::now();
        $data->save();

        return response()->json(['message' => 'Penanggung jawab dokumen berhasil dihapus.'], 200);
    }
}
