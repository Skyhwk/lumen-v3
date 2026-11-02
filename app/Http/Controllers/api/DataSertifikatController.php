<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\DataSertifikatKaryawan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsHrisEmployeeIndexDatatable;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DataSertifikatController extends Controller
{
    use BuildsHrisEmployeeIndexDatatable;

    public function index(Request $request)
    {
        $data = $this->hrisEmployeeBaseQuery();

        return $this->applyHrisEmployeeContextColumns(Datatables::of($data))
            ->addColumn('jumlah_sertifikat', function ($row) {
                return $this->certificateItems($row)->count();
            })
            ->addColumn('sertifikat_terakhir', function ($row) {
                return $this->latestCertificateSummary($row);
            })
            ->addColumn('masa_berlaku', function ($row) {
                return $this->latestCertificateExpiry($row);
            })
            ->filterColumn('sertifikat_terakhir', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('sertifikat_karyawan', function ($sub) use ($keyword) {
                        $this->applyActiveScope($sub)->where(function ($w) use ($keyword) {
                            $w->where('nama_sertifikat', 'like', '%' . $keyword . '%')
                                ->orWhere('tipe_sertifikat', 'like', '%' . $keyword . '%')
                                ->orWhere('nomor_sertifikat', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('sertifikat', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('masa_berlaku', function ($query, $keyword) {
                $query->whereHas('sertifikat_karyawan', function ($sub) use ($keyword) {
                    $this->applyActiveScope($sub)->where('tgl_exp_sertifikat', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('jumlah_sertifikat', function ($query, $keyword) {
                if (!is_numeric($keyword)) {
                    return;
                }
                $query->has('sertifikat_karyawan', '=', (int) $keyword);
            })
            ->make(true);
    }

    public function showById(Request $request)
    {
        $karyawanId = (int) $request->karyawan_id;
        if ($karyawanId <= 0) {
            return response()->json(['data' => []], 200);
        }

        $rows = DataSertifikatKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('tgl_sertifikat')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $karyawan = MasterKaryawan::find($karyawanId);
            $rows = $karyawan ? $this->legacyCertificateFromJson($karyawan) : collect([]);
        }

        return response()->json(['data' => $rows->values()->all()], 200);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            DataSertifikatKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_sertifikat' => $request->nama_sertifikat,
                'tipe_sertifikat' => $request->tipe_sertifikat,
                'nomor_sertifikat' => $request->nomor_sertifikat,
                'deskripsi_sertifikat' => $request->deskripsi_sertifikat,
                'tgl_sertifikat' => $request->tgl_sertifikat,
                'tgl_exp_sertifikat' => $request->tgl_exp_sertifikat,
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

            $data = DataSertifikatKaryawan::find($request->id);
            $data->nama_sertifikat = $request->nama_sertifikat;
            $data->tipe_sertifikat = $request->tipe_sertifikat;
            $data->nomor_sertifikat = $request->nomor_sertifikat;
            $data->deskripsi_sertifikat = $request->deskripsi_sertifikat;
            $data->tgl_sertifikat = $request->tgl_sertifikat;
            $data->tgl_exp_sertifikat = $request->tgl_exp_sertifikat;
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

            $data = DataSertifikatKaryawan::find($request->id);
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

    private function certificateItems($row): Collection
    {
        static $cache = [];

        $karyawanId = (int) ($row->id ?? 0);
        if ($karyawanId <= 0) {
            return collect([]);
        }

        if (isset($cache[$karyawanId])) {
            return $cache[$karyawanId];
        }

        $rows = DataSertifikatKaryawan::where('karyawan_id', $karyawanId)
            ->where(function ($q) {
                $this->applyActiveScope($q);
            })
            ->orderByDesc('tgl_sertifikat')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            $rows = $this->legacyCertificateFromJson($row);
        }

        $cache[$karyawanId] = $rows;

        return $rows;
    }

    private function legacyCertificateFromJson($row): Collection
    {
        $raw = $row->getAttributes()['sertifikat'] ?? null;
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
                    'nama_sertifikat' => $item['nama_sertifikat'] ?? $item['nama'] ?? null,
                    'tipe_sertifikat' => $item['tipe_sertifikat'] ?? $item['tipe'] ?? null,
                    'nomor_sertifikat' => $item['nomor_sertifikat'] ?? $item['nomor'] ?? null,
                    'tgl_sertifikat' => $item['tgl_sertifikat'] ?? $item['tanggal_sertifikasi'] ?? null,
                    'tgl_exp_sertifikat' => $item['tgl_exp_sertifikat'] ?? $item['tanggal_expired'] ?? null,
                ];
            })
            ->filter(fn ($item) => trim((string) ($item->nama_sertifikat ?? '')) !== '')
            ->values();
    }

    private function latestCertificate($row)
    {
        return $this->certificateItems($row)->first();
    }

    private function latestCertificateSummary($row): string
    {
        $item = $this->latestCertificate($row);
        if (!$item) {
            return '-';
        }

        $nama = trim((string) ($item->nama_sertifikat ?? ''));
        $tipe = trim((string) ($item->tipe_sertifikat ?? ''));

        if ($nama === '') {
            return '-';
        }

        return $tipe !== '' ? $nama . ' (' . $tipe . ')' : $nama;
    }

    private function latestCertificateExpiry($row): string
    {
        $item = $this->latestCertificate($row);
        if (!$item) {
            return '-';
        }

        $exp = $item->tgl_exp_sertifikat ?? null;
        if ($exp === null || $exp === '' || strpos((string) $exp, '0000-00-00') === 0) {
            return 'Tidak ada expired';
        }

        try {
            return 'Exp. ' . Carbon::parse($exp)->locale('id')->translatedFormat('j F Y');
        } catch (\Exception $e) {
            return (string) $exp;
        }
    }
}
