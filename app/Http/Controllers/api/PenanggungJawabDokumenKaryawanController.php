<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\PenanggungJawabDokumenKaryawan;
use App\Services\DateColumnFilterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PenanggungJawabDokumenKaryawanController extends Controller
{
    public function list()
    {
        PenanggungJawabDokumenKaryawan::syncActive();

        $data = PenanggungJawabDokumenKaryawan::query()
            ->orderByDesc('mulai_berlaku')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    public function index()
    {
        PenanggungJawabDokumenKaryawan::syncActive();

        $data = PenanggungJawabDokumenKaryawan::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id');

        return Datatables::of($data)
            ->filterColumn('mulai_berlaku', DateColumnFilterService::datatablesFilter('mulai_berlaku'))
            ->addColumn('periode', function ($row) {
                return $this->formatPeriode($row);
            })
            ->filterColumn('periode', function () {
            })
            ->make(true);
    }

    public function options()
    {
        PenanggungJawabDokumenKaryawan::syncActive();

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
            'latestDate' => PenanggungJawabDokumenKaryawan::query()->max('mulai_berlaku'),
        ]);
    }

    public function store(Request $request)
    {
        $nama = trim((string) $request->nama_karyawan);
        $jabatan = trim((string) $request->jabatan);
        $mulaiBerlaku = trim((string) $request->mulai_berlaku);

        if ($nama === '' || $jabatan === '' || $mulaiBerlaku === '') {
            return response()->json(['message' => 'Nama karyawan, jabatan, dan mulai berlaku wajib diisi.'], 422);
        }
        if (mb_strlen($nama) > 150 || mb_strlen($jabatan) > 150) {
            return response()->json(['message' => 'Nama karyawan dan jabatan maksimal 150 karakter.'], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulaiBerlaku)) {
            return response()->json(['message' => 'Mulai berlaku harus berupa tanggal.'], 422);
        }

        $now = Carbon::now();

        try {
            DB::transaction(function () use ($nama, $jabatan, $mulaiBerlaku, $now) {
                $data = new PenanggungJawabDokumenKaryawan();
                $data->nama_karyawan = $nama;
                $data->jabatan = $jabatan;
                $data->mulai_berlaku = $mulaiBerlaku;
                $data->is_active = 0;
                $data->created_by = $this->karyawan;
                $data->created_at = $now;
                $data->save();

                PenanggungJawabDokumenKaryawan::syncActive();
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan penanggung jawab dokumen.'], 500);
        }

        return response()->json(['message' => 'Penanggung jawab dokumen berhasil dijadwalkan.'], 200);
    }

    private function formatPeriode($row)
    {
        if (empty($row->mulai_berlaku)) {
            return '-';
        }

        static $latestId = null;
        if ($latestId === null) {
            $latestId = (int) PenanggungJawabDokumenKaryawan::query()->max('id');
        }

        $mulai = $this->tanggalIndonesia($row->mulai_berlaku);
        $adaPengganti = (int) $row->id < $latestId && !empty($row->updated_at);
        if (!$adaPengganti) {
            return $mulai . ' - sekarang';
        }

        return $mulai . ' - ' . $this->tanggalIndonesia($row->updated_at);
    }

    private function tanggalIndonesia($tanggal)
    {
        $date = Carbon::parse($tanggal);
        $bulan = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        return $date->day . ' ' . $bulan[(int) $date->month] . ' ' . $date->year;
    }

    private function deactivateActive($now, $exceptId = null)
    {
        $query = PenanggungJawabDokumenKaryawan::where('is_active', 1);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update([
            'is_active' => 0,
            'updated_by' => $this->karyawan,
            'updated_at' => $now,
        ]);
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
