<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\KontakDaruratKaryawan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsHrisEmployeeIndexDatatable;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class DataContactDaruratController extends Controller
{
    use BuildsHrisEmployeeIndexDatatable;

    public function index(Request $request)
    {
        $data = $this->hrisEmployeeBaseQuery();

        return $this->applyHrisEmployeeContextColumns(Datatables::of($data))
            ->addColumn('jumlah_kontak', function ($row) {
                return $this->contactItems($row)->count();
            })
            ->addColumn('kontak_utama', function ($row) {
                return $this->primaryContactSummary($row);
            })
            ->filterColumn('kontak_utama', function ($query, $keyword) {
                $query->whereHas('kontak_darurat', function ($sub) use ($keyword) {
                    $this->applyActiveScope($sub)->where(function ($w) use ($keyword) {
                        $w->where('nama_kontak', 'like', '%' . $keyword . '%')
                            ->orWhere('hubungan', 'like', '%' . $keyword . '%')
                            ->orWhere('nomor_kontak', 'like', '%' . $keyword . '%');
                    });
                });
            })
            ->filterColumn('jumlah_kontak', function ($query, $keyword) {
                if (!is_numeric($keyword)) {
                    return;
                }
                $query->has('kontak_darurat', '=', (int) $keyword);
            })
            ->make(true);
    }

    public function showById(Request $request)
    {
        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['data' => []], 200);
        }

        $rows = KontakDaruratKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $rows->values()->all()], 200);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            KontakDaruratKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_kontak' => $request->nama_kontak,
                'hubungan' => $request->hubungan,
                'nomor_kontak' => $request->nomor_kontak,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = KontakDaruratKaryawan::find($request->id);
            $data->nama_kontak = $request->nama_kontak;
            $data->hubungan = $request->hubungan;
            $data->nomor_kontak = $request->nomor_kontak;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Data berhasil diupdate', 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = KontakDaruratKaryawan::find($request->id);
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function contactItems($row): Collection
    {
        static $cache = [];

        $karyawanId = (int) ($row->id ?? 0);
        if ($karyawanId <= 0) {
            return collect([]);
        }

        if (isset($cache[$karyawanId])) {
            return $cache[$karyawanId];
        }

        $rows = KontakDaruratKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderBy('id')
            ->get();

        $cache[$karyawanId] = $rows;

        return $rows;
    }

    private function primaryContactSummary($row): string
    {
        $first = $this->contactItems($row)->first();
        if (!$first) {
            return '-';
        }

        $nama = trim((string) ($first->nama_kontak ?? ''));
        $hubungan = trim((string) ($first->hubungan ?? ''));
        $nomor = trim((string) ($first->nomor_kontak ?? ''));

        $parts = [];
        if ($nama !== '') {
            $parts[] = $hubungan !== '' ? $nama . ' (' . $hubungan . ')' : $nama;
        }
        if ($nomor !== '') {
            $parts[] = $nomor;
        }

        return $parts ? implode(' · ', $parts) : '-';
    }
}
