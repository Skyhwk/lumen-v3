<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\PendidikanKaryawan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsHrisEmployeeIndexDatatable;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DataPendidikanKaryawanController extends Controller
{
    use BuildsHrisEmployeeIndexDatatable;

    public function index(Request $request)
    {
        $data = $this->hrisEmployeeBaseQuery();

        return $this->applyHrisEmployeeContextColumns(Datatables::of($data))
            ->addColumn('jumlah_pendidikan', function ($row) {
                return $this->educationItems($row)->count();
            })
            ->addColumn('pendidikan_terakhir', function ($row) {
                return $this->latestEducationSummary($row);
            })
            ->addColumn('periode_pendidikan', function ($row) {
                return $this->latestEducationPeriod($row);
            })
            ->filterColumn('pendidikan_terakhir', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('pendidikan_karyawan', function ($sub) use ($keyword) {
                        $this->applyActiveScope($sub)->where(function ($w) use ($keyword) {
                            $w->where('institusi', 'like', '%' . $keyword . '%')
                                ->orWhere('jenjang', 'like', '%' . $keyword . '%')
                                ->orWhere('jurusan', 'like', '%' . $keyword . '%')
                                ->orWhere('kota', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('pendidikan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('periode_pendidikan', function ($query, $keyword) {
                $query->whereHas('pendidikan_karyawan', function ($sub) use ($keyword) {
                    $this->applyActiveScope($sub)->where(function ($w) use ($keyword) {
                        $w->where('tahun_masuk', 'like', '%' . $keyword . '%')
                            ->orWhere('tahun_lulus', 'like', '%' . $keyword . '%');
                    });
                });
            })
            ->filterColumn('jumlah_pendidikan', function ($query, $keyword) {
                if (!is_numeric($keyword)) {
                    return;
                }
                $query->has('pendidikan_karyawan', '=', (int) $keyword);
            })
            ->make(true);
    }

    public function showById(Request $request)
    {
        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['data' => []], 200);
        }

        $rows = PendidikanKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('tahun_lulus')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $karyawan = MasterKaryawan::find($karyawanId);
            $rows = $karyawan ? $this->legacyEducationFromJson($karyawan) : collect([]);
        }

        return response()->json(['data' => $rows->values()->all()], 200);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            PendidikanKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'institusi' => $request->institusi,
                'jenjang' => $request->jenjang,
                'jurusan' => $request->jurusan,
                'tahun_masuk' => $request->tahun_masuk,
                'tahun_lulus' => $request->tahun_lulus,
                'kota' => $request->kota,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
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

            $data = PendidikanKaryawan::find($request->id);
            $data->institusi = $request->institusi;
            $data->jenjang = $request->jenjang;
            $data->jurusan = $request->jurusan;
            $data->tahun_masuk = $request->tahun_masuk;
            $data->tahun_lulus = $request->tahun_lulus;
            $data->kota = $request->kota;
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

            $data = PendidikanKaryawan::find($request->id);
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

    private function educationItems($row): Collection
    {
        static $cache = [];

        $karyawanId = (int) ($row->id ?? 0);
        if ($karyawanId <= 0) {
            return collect([]);
        }

        if (isset($cache[$karyawanId])) {
            return $cache[$karyawanId];
        }

        $rows = PendidikanKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('tahun_lulus')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $rows = $this->legacyEducationFromJson($row);
        }

        $cache[$karyawanId] = $rows;

        return $rows;
    }

    private function legacyEducationFromJson($row): Collection
    {
        $raw = $row->getAttributes()['pendidikan'] ?? null;
        if ($raw === null || $raw === '') {
            return collect([]);
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return collect([]);
        }

        return collect($decoded)
            ->filter(fn ($item) => is_array($item))
            ->map(function ($item) {
                return (object) [
                    'id' => $item['id'] ?? null,
                    'institusi' => $item['institusi'] ?? $item['nama_institusi'] ?? null,
                    'jenjang' => $item['jenjang'] ?? $item['jenjang_pendidikan'] ?? null,
                    'jurusan' => $item['jurusan'] ?? null,
                    'tahun_masuk' => $item['tahun_masuk'] ?? null,
                    'tahun_lulus' => $item['tahun_lulus'] ?? null,
                    'kota' => $item['kota'] ?? null,
                ];
            })
            ->filter(function ($item) {
                return trim((string) ($item->institusi ?? '')) !== '' || trim((string) ($item->jenjang ?? '')) !== '';
            })
            ->values();
    }

    private function latestEducation($row)
    {
        return $this->educationItems($row)->first();
    }

    private function latestEducationSummary($row): string
    {
        $item = $this->latestEducation($row);
        if (!$item) {
            return '-';
        }

        $parts = array_filter([
            trim((string) ($item->jenjang ?? '')),
            trim((string) ($item->institusi ?? '')),
            trim((string) ($item->jurusan ?? '')),
        ]);

        return $parts ? implode(' · ', $parts) : '-';
    }

    private function latestEducationPeriod($row): string
    {
        $item = $this->latestEducation($row);
        if (!$item) {
            return '-';
        }

        $masuk = trim((string) ($item->tahun_masuk ?? ''));
        $lulus = trim((string) ($item->tahun_lulus ?? ''));

        if ($masuk === '' && $lulus === '') {
            return '-';
        }
        if ($masuk === '') {
            return $lulus;
        }
        if ($lulus === '') {
            return $masuk;
        }

        return $masuk . ' – ' . $lulus;
    }
}
