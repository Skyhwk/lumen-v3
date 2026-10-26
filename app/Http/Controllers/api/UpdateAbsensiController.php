<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use \App\Models\{Absensi, RekapMasukKerja, RekapLiburKalender, ShiftKaryawan, MasterKaryawan};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Hr\MonthlyAbsensiDataBuilder;
use App\Services\Hr\MonthlyAbsensiGenerateOrchestrator;


class UpdateAbsensiController extends Controller
{
    public function hari($tanggal)
    {
        return MonthlyAbsensiDataBuilder::hariIndonesia($tanggal);
    }

    // Need test from front-end
    public function updateJadwal(Request $request)
    {
        $orchestrator = new MonthlyAbsensiGenerateOrchestrator();
        DB::beginTransaction();
        try {
            $masukJam = $orchestrator->normalizeJamForDb($request->masuk);
            $keluarJam = $orchestrator->normalizeJamForDb($request->keluar);

            if ($masukJam !== '') {
                if ($request->id_masuk != '') {
                    Absensi::where('id', '!=', $request->id_masuk)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_masuk)
                        ->where('status', 'Masuk')->delete();

                    Absensi::where('id', $request->id_masuk)->update([
                        'kode_kartu' => NULL,
                        'jam' => $masukJam,
                    ]);
                } else {
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $request->tgl,
                        'hari' => self::hari($request->tgl),
                        'jam' => $masukJam,
                        'status' => 'Masuk',
                    ]);
                }
            }
            if ($keluarJam !== '') {
                if ($request->id_keluar != '') {
                    Absensi::where('id', '!=', $request->id_keluar)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_keluar)
                        ->where('status', 'Keluar')->delete();

                    Absensi::where('id', $request->id_keluar)->update([
                        'kode_kartu' => NULL,
                        'jam' => $keluarJam,
                    ]);
                } else {
                    $tanggal = $request->tgl;
                    if ($request->shift == 'SHSECURITY2' || $request->shift == '24jam') {
                        $tanggal = DATE('Y-m-d', strtotime($request->tgl . '+1day'));
                    }
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $tanggal,
                        'hari' => self::hari($tanggal),
                        'jam' => $keluarJam,
                        'status' => 'Keluar',
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Update Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Tested - Clear
    public function generateJadwal(Request $request)
    {
        $orchestrator = new MonthlyAbsensiGenerateOrchestrator();
        DB::beginTransaction();
        try {
            $absensiPayload = $request->absensi ? (array) $request->absensi : null;
            $orchestrator->persistGenerate(
                (int) $request->id_karyawan,
                (string) $request->bulan,
                $absensiPayload,
                (array) $request->data,
                $this->karyawan ? (int) $this->karyawan : null
            );
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function SelectUserbyDivisi(Request $request)
    {
        $reqBulan = $request->bulan;
        $data = MasterKaryawan::with([
            'jabatan',
            'divisi',
            'rekap' => function ($query) use ($reqBulan) {
                $query->where('bulan', $reqBulan)
                    ->where('is_active', true)
                    ->select('id', 'karyawan_id', 'bulan');
            },
        ])
            ->whereIn('id_cabang', $this->privilageCabang)
            ->whereRaw('CASE WHEN is_active = 0 THEN CAST(NOW() as DATE) <= DATE_ADD(effective_date, INTERVAL 1 month) ELSE is_active = 1 END');

        if ($request->id_jabatan) {
            $data->where('id_jabatan', $request->id_jabatan);
        } elseif ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', $request->departement);
        }

        return datatables()->of($data)->make(true);
    }

    public function generateJadwalBackup(Request $request)
    {
        DB::beginTransaction();
        try {
            $bulan = explode("-", $request->bulan);
            $cek = RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                ->where('tahun', $bulan[0])
                ->where('bulan', $request->bulan)
                ->where('is_active', true)
                ->first();
            if ($cek) {
                RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                    ->where('tahun', $bulan[0])
                    ->where('bulan', $request->bulan)
                    ->where('is_active', true)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
            }

            $karyawan_id = $request->id_karyawan;
            $bulan = explode("-", $request->bulan);
            $hari_kerja = RekapLiburKalender::where('tahun', $bulan[0])
                ->where('is_active', true)
                ->first();
            $tgl_kerja = '';
            $masuk_kerja = [];

            foreach (json_decode($hari_kerja->tanggal) as $key => $value) {
                if ($key == $bulan[0] . '-' . $bulan[1]) {
                    $tgl_kerja = $value;
                }
            }

            foreach ($request->tanggal as $key => $value) {
                if ($request->masuk[$key] != '' && $request->keluar[$key] != '') {
                    if (in_array($request->shift[$key], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
                        array_push($masuk_kerja, $request->tanggal[$key]);
                    } else if ($request->shift[$key] != 'off') {
                        if (in_array($request->tanggal[$key], $tgl_kerja)) {
                            array_push($masuk_kerja, $request->tanggal[$key]);
                        }
                    }
                }
            }

            RekapMasukKerja::insert([
                'karyawan_id' => $request->id_karyawan,
                'tahun' => $bulan[0],
                'bulan' => $request->bulan,
                'tanggal' => json_encode($masuk_kerja),
                'added_by' => $this->karyawan,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function indexAbsen(Request $request)
    {
        try {
            date_default_timezone_set('Asia/Jakarta');

            $karyawanId = (int) $request->id_karyawan;
            $karyawan = MasterKaryawan::select('id', 'nik_karyawan', 'nama_lengkap')->find($karyawanId);
            if (!$karyawan) {
                return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
            }

            $builder = new MonthlyAbsensiDataBuilder();
            $data = $builder->buildForKaryawan($karyawanId, (string) $request->tgl);

            return response()->json(['data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
