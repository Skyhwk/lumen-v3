<?php

namespace App\Http\Controllers\mobile;

use App\Models\RekeningKaryawan;
use App\Models\MasterKaryawan;
use App\Models\LiburPerusahaan;
use App\Models\Jadwal;
use App\Models\MasterFeeSampling;
use App\Models\PengajuanFeeSampling;
use App\Models\{
    DataLapanganAir,
    DataLapanganEmisiCerobong,
    DataLapanganLingkunganHidup,
    DataLapanganLingkunganKerja,
    DataLapanganSenyawaVolatile,
    DataLapanganPartikulatMeter,
    DataLapanganMicrobiologi,
    DataLapanganKebisingan,
    DataLapanganKebisinganPersonal,
    DataLapanganCahaya,
    DataLapanganGetaran,
    DataLapanganGetaranPersonal,
    DataLapanganIklimPanas,
    DataLapanganIklimDingin,
    DataLapanganSwab,
    DataLapanganErgonomi,
    DataLapanganEmisiKendaraan
};
use App\Services\GenerateFeeSampling;
use App\Services\InsertActivityFdl;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class FeeSamplingController extends Controller
{

    public function index()
    {
        $data = PengajuanFeeSampling::where('user_id', $this->user_id)
            ->get();
        foreach ($data as $key => $value) {
            $value->periode = json_decode($value->periode);
        }
        return response()->json(['data' => $data], 200);
    }

    public function getRekening()
    {
        $data = MasterKaryawan::where('id', $this->user_id)->first();
        $rekening = RekeningKaryawan::where('nik_karyawan', $data->nik_karyawan)
            ->select('nik_karyawan', 'no_rekening', 'nama_bank')
            ->where('is_active', true)
            ->first();

        $rekening->no_telpon = $data->no_telpon;

        return response()->json(['data' => $rekening], 200);
    }

    public function getLibur(Request $request)
    {
        $data = LiburPerusahaan::where('is_active', true)
            ->where('tipe', 'Penggantian')
            ->get();

        $detail = [];
        foreach ($data as $key => $value) {
            $detail[$key]['title'] = $value->keterangan;
            $detail[$key]['start'] = $value->tgl_ganti;
            $detail[$key]['end'] = $value->tgl_ganti;
            $detail[$key]['extendextendedProps']['data'] = $value;
        }
        $data = $detail;
        $currentDate = Carbon::now();
        $startOfWeek = $currentDate->copy()->startOfWeek(Carbon::SATURDAY);
        $endOfWeek = $currentDate->copy()->endOfWeek(Carbon::FRIDAY);

        $dateRange = [];
        while ($startOfWeek->lte($endOfWeek)) {
            $dateRange[] = $startOfWeek->toDateString();
            $startOfWeek->addDay();
        }


        return response()->json(['data' => $data], 200);
    }

    public function getTanggalSampling(Request $request)
    {
        $feeSampling = PengajuanFeeSampling::where('user_id', $this->user_id)
            ->where('is_approve_finance', 0)
            ->where('is_active', true)
            ->pluck('detail_fee');

        $tanggalList = $feeSampling
            ->flatMap(function ($json) {
                return collect(json_decode($json, true))->pluck('tanggal');
            })
            ->unique()
            ->values();

        $jadwal = Jadwal::where('userid', $this->user_id)
            ->where('is_active', true)
            ->whereDate('tanggal', '<', Carbon::today())
            ->whereNotIn('tanggal', $tanggalList)
            ->distinct()
            ->pluck('tanggal');

        return response()->json(['data' => $jadwal], 200);
    }




    public function rekapFeeSampling(Request $request)
    {
        // Validasi input
        if (!is_array($request->tanggal) || empty($request->tanggal)) {
            return response()->json(['message' => 'Tanggal Tidak Boleh Kosong'], 422);
        }

        // Ambil jadwal dengan relasi yang dibutuhkan
        $jadwals = Jadwal::with('orderDetail')
            ->where('is_active', true)
            ->where('userid', $this->user_id)
            ->whereIn('tanggal', $request->tanggal)
            ->get()
            ->filter(function ($jadwal) {
                return $jadwal->orderDetail->isNotEmpty();
            });

        if ($jadwals->isEmpty()) {
            return response()->json(['message' => 'Jadwal Tidak Ditemukan Untuk Tanggal Tersebut'], 404);
        }

        $categoryMappings = [
            'Air' => [[DataLapanganAir::class]],
            'Emisi Sumber Tidak Bergerak' => [[DataLapanganEmisiCerobong::class]],
            'Emisi Kendaraan' => [[DataLapanganEmisiKendaraan::class]],
            'Udara Ambient' => [
                [DataLapanganPartikulatMeter::class],
                [DataLapanganLingkunganHidup::class, DataLapanganSenyawaVolatile::class]
            ],
            'Udara Lingkungan Kerja' => [
                [DataLapanganPartikulatMeter::class],
                [DataLapanganLingkunganKerja::class, DataLapanganSenyawaVolatile::class]
            ],
            'Udara Angka Kuman' => [
                [DataLapanganPartikulatMeter::class],
                [DataLapanganMicrobiologi::class]
            ],
            'Kebisingan' => [[DataLapanganKebisingan::class, DataLapanganKebisinganPersonal::class]],
            'Kebisingan (24 Jam)' => [[DataLapanganKebisingan::class]],
            'Pencahayaan' => [[DataLapanganCahaya::class]],
            'Getaran (Mesin)' => [[DataLapanganGetaran::class]],
            'Getaran (Kejut Bangunan)' => [[DataLapanganGetaran::class]],
            'Getaran' => [[DataLapanganGetaran::class]],
            'Getaran (Lengan & Tangan)' => [[DataLapanganGetaranPersonal::class]],
            'Getaran (Seluruh Tubuh)' => [[DataLapanganGetaranPersonal::class]],
            'Iklim Kerja' => [[DataLapanganIklimPanas::class, DataLapanganIklimDingin::class]],
            'Udara Swab Test' => [[DataLapanganSwab::class]],
            'Ergonomi' => [[DataLapanganErgonomi::class]],
        ];

        $tanggal_revisi = $jadwals
            ->filter(fn($jadwal) => empty($jadwal->orderDetail[0]->no_order))
            ->pluck('tanggal')
            ->unique()
            ->values()
            ->toArray();


        if (!empty($tanggal_revisi)) {
            return response()->json([
                'message' => 'Terdapat Nomor Penawaran yang Sedang Dalam Tahap Revisi Pada Tanggal ' . implode(', ', $tanggal_revisi),
            ], 401);
        }

        $allSamples = [];
        foreach ($jadwals as $jadwal) {
            $noOrder = $jadwal->orderDetail->first()->no_order;
            $kategoriList = json_decode($jadwal->kategori);

            foreach ($kategoriList as $kategori) {
                if (preg_match('/^(.*?)\s*-\s*(\d{3})$/', $kategori, $matches)) {
                    $namaKategori = trim($matches[1]);
                    $kode = $matches[2];
                    $noSampel = $noOrder . '/' . $kode;

                    $allSamples[] = [
                        'category' => $namaKategori,
                        'no_sampel' => $noSampel,
                        'tanggal' => $jadwal->tanggal
                    ];
                }
            }
        }

        // Group samples by category
        $samplesByCategory = [];
        foreach ($allSamples as $sample) {
            $category = $sample['category'];
            if (!isset($samplesByCategory[$category])) {
                $samplesByCategory[$category] = [];
            }
            $samplesByCategory[$category][] = $sample;
        }

        $tanggalApprove = [];

        $tanggalApprove = array_unique($tanggalApprove);

        if (!empty($tanggalApprove)) {
            return response()->json([
                'message' => 'Terdapat Data Lapangan Yang Belum Diapprove Pada Tanggal ' . implode(', ', $tanggalApprove),
            ], 401);
        }

        // Validasi level sampler
        $master_karyawan = MasterKaryawan::where('id', $this->user_id)->first();
        if (!$master_karyawan || $master_karyawan->warna == null) {
            return response()->json(['message' => 'Level Sampler Belum Ditentukan'], 401);
        }

        $level = MasterFeeSampling::where('warna', $master_karyawan->warna)
            ->where('is_active', true)
            ->first();

        if (!$level) {
            return response()->json(['message' => 'Level Sampler Tidak Ditemukan'], 404);
        }

        // Generate rekap
        $generate = new GenerateFeeSampling();
        $rekap = $generate->rekapFeeSampling($this->user_id, $level->kategori, $request->tanggal);

        return response()->json(['data' => $rekap], 200);
    }


    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = new PengajuanFeeSampling();
            $data->user_id = $this->user_id;
            $data->total_fee = $request->total_fee;
            $data->periode = json_encode($request->periode);
            $data->metode_transfer = $request->metode_transfer;
            $data->nama_bank = $request->nama_bank;
            $data->no_rekening = $request->no_rekening;
            $data->tgl_pengajuan = Carbon::now()->format('Y-m-d');
            $data->no_telp = $request->no_telpon;
            $data->detail_fee = json_encode($request->detail_fee);
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            InsertActivityFdl::by($this->user_id)->action('input')->target("Fee Sampling dengan total fee $request->total_fee")->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
