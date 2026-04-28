<?php

namespace App\Services;

use App\Models\DataLapanganAir;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use App\Models\MasterFeeSampling;
use App\Models\Jadwal;
use App\Models\LiburPerusahaan;
use App\Models\MasterWilayahSampling;
use App\Models\MasterKaryawan;
use App\Models\MasterFeeDriver;


class GenerateFeeSampling
{
    public function rekapFeeSampling($userId, $level, $tanggal)
    {
        try {
            $userK3 = [166, 216];

            // 1. Ambil fee berdasarkan level
            $fee = MasterFeeSampling::where('kategori', $level)->first();
            if (!$fee) {
                throw new \Exception("Fee untuk level '$level' tidak ditemukan.");
            }

            // 2. Ambil semua tanggal libur
            $liburKantor = LiburPerusahaan::whereIn('tipe', ['cuti_bersama', 'Penggantian'])
                ->where('is_active', 1)
                ->pluck('tanggal')
                ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
                ->toArray();

            // 3. Ambil semua jadwal sampling
            $jadwal = Jadwal::with('persiapanHeader', 'orderDetail')
                ->where('is_active', 1)
                ->where('userid', $userId)
                ->whereIn('tanggal', $tanggal)
                ->whereHas('persiapanHeader')
                ->get()
                ->groupBy('tanggal');

            // 4. Ambil semua wilayah dalam kota (cache di awal)
            $dalamKotaMap = MasterWilayahSampling::where('is_active', 1)
                ->where('status_wilayah', 'Dalam Kota')
                ->get()
                ->groupBy('id_cabang')
                ->map(fn($g) => $g->pluck('wilayah')->map(fn($w) => strtoupper($w))->toArray());

            // 5. Siapkan hasil
            $rekap = [];
            $total = 0;

            $durasi_map = [
                0 => 'Sesaat',
                1 => '8 Jam',
                2 => '1x24 Jam',
                3 => '2x24 Jam',
                4 => '3x24 Jam',
                5 => '4x24 Jam',
                6 => '6x24 Jam',
                7 => '7x24 Jam',
            ];

            foreach ($jadwal as $tgl => $items) {
                $feeTambahan = 0;
                $durasi_tertinggi = 0;
                $feeTambahanRincian = [
                    'sampling_24jam'          => 0,
                    'isokinetik'              => 0,
                    'hari_libur'              => 0,
                    'luar_kota'               => 0,
                    'luar_kota_24jam'         => 0,
                    'driver'                  => 0,
                    'durasi_sampling'         => '',
                    'biaya_pendampingan_k3'   => 0,
                ];

                // ==== Klasifikasi PT dan Hitung Titik ====
                $ptAirOnly        = [];
                $ptCampuran       = [];
                $ptNonAir         = [];
                $AllAlamatSampling = [];

                $adaLuarKota             = false;
                $durasiTertinggiLuarKota  = 0;
                $durasiTertinggiDalamKota = 0; // FIX #3: track durasi dalam kota sendiri
                $itemDriver               = null; // FIX #1: simpan item yang jadi driver

                foreach ($items as $item) {
                    $alamatSampling  = strtolower(trim($item->nama_perusahaan));
                    $AllAlamatSampling[] = $alamatSampling;
                    $kategoriList    = json_decode($item->kategori, true);
                    if (!is_array($kategoriList)) $kategoriList = [];

                    $hasAir              = false;
                    $hasNonAir           = false;
                    $titikAirPT          = 0;
                    $hasEmisiIsokinetik  = false;

                    foreach ($kategoriList as $kategori) {
                        $kategoriLower = strtolower($kategori);
                        $no_sampel     = $item->persiapanHeader->no_order . "/" . explode(' - ', $kategori)[1];

                        if (stripos($kategoriLower, 'emisi isokinetik') !== false) {
                            $orderDetails = $item->orderDetail;
                            $isMatch = $orderDetails->contains(function ($detail) use ($no_sampel) {
                                return $detail->no_sampel === $no_sampel
                                    && $detail->tanggal_terima !== null;
                            });
                            if ($isMatch) {
                                $hasEmisiIsokinetik = true;
                            }
                        }

                        if (stripos($kategoriLower, 'air') !== false && DataLapanganAir::where('no_sampel', $no_sampel)->exists()) {
                            $hasAir = true;
                            $titikAirPT++;
                        } else if (
                            // FIX #2: gunakan === false bukan !== true
                            // stripos() return int|false, bukan bool — !== true selalu true
                            stripos($kategoriLower, 'air') === false
                            && stripos($kategoriLower, 'emisi isokinetik') === false
                        ) {
                            $orderDetails = $item->orderDetail;
                            $isMatch = $orderDetails->contains(function ($detail) use ($no_sampel) {
                                return $detail->no_sampel === $no_sampel
                                    && $detail->tanggal_terima !== null;
                            });
                            if ($isMatch) {
                                $hasNonAir = true;
                            }
                        }
                    }

                    if ($hasAir && $hasNonAir) {
                        if (!isset($ptCampuran[$alamatSampling])) {
                            $ptCampuran[$alamatSampling] = ['air' => 0, 'nonAir' => 0];
                        }
                        $ptCampuran[$alamatSampling]['air']    += $titikAirPT;
                        $ptCampuran[$alamatSampling]['nonAir']  = 1;
                    } elseif ($hasAir) {
                        $ptAirOnly[$alamatSampling] = ($ptAirOnly[$alamatSampling] ?? 0) + $titikAirPT;
                    } else {
                        $ptNonAir[$alamatSampling] = 1;
                    }

                    // --- Isokinetik dan Pendampingan K3 ---
                    if ($item->note) {
                        $note = strtolower($item->note);

                        if (strpos($note, 'pendampingan') !== false || $item->pendampingan_k3 == true) {
                            if (in_array($userId, $userK3)) {
                                $feeTambahan += 45000;
                                $feeTambahanRincian['biaya_pendampingan_k3'] += 45000;
                            }
                        } elseif (strpos($note, 'isokinetik') !== false || $hasEmisiIsokinetik || stripos($note, 'iso') !== false || $item->isokinetic == true) {
                            $feeTambahan += $fee->isokinetik;
                            $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
                        }
                    } else {
                        if ($hasEmisiIsokinetik || $item->isokinetic == true) {
                            $feeTambahan += $fee->isokinetik;
                            $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
                        }
                        if ($item->pendampingan_k3 == true) {
                            if (in_array($userId, $userK3)) {
                                $feeTambahan += 45000;
                                $feeTambahanRincian['biaya_pendampingan_k3'] += 45000;
                            }
                        }
                    }

                    // Cek luar kota (hanya sekali per hari)
                    $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
                    if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
                        $adaLuarKota             = true;
                        $durasiTertinggiLuarKota = max($durasiTertinggiLuarKota, (int) $item->durasi);
                    } else {
                        // FIX #3: track durasi tertinggi khusus dalam kota
                        // agar kasus campuran (ada luar+dalam kota di hari sama) tetap benar
                        $durasiTertinggiDalamKota = max($durasiTertinggiDalamKota, (int) $item->durasi);
                    }

                    $durasi_tertinggi = max($durasi_tertinggi, (int) $item->durasi);

                    // FIX #1: simpan item yang bertindak sebagai driver (jangan pakai $item sisa loop)
                    if (
                        isset($item->driver, $item->sampler) &&
                        trim(strtolower($item->driver)) === trim(strtolower($item->sampler))
                    ) {
                        $itemDriver = $item;
                    }
                }

                // === Hitung total titik air gabungan ===
                $totalAirGabungan = array_sum($ptAirOnly);
                foreach ($ptCampuran as $data) {
                    $totalAirGabungan += $data['air'];
                }

                $allAlamatSampling = array_unique($AllAlamatSampling);
                $tempat            = count($allAlamatSampling);
                $lokasiSampling    = 1;

                if (count($ptAirOnly) > 0 || count($ptCampuran) > 0) {
                    if ($totalAirGabungan > 20) {
                        $lokasiSampling = $tempat + 2;
                    } elseif ($totalAirGabungan > 10) {
                        $lokasiSampling = $tempat + 1;
                    } else {
                        $lokasiSampling = $tempat;
                    }
                } else {
                    $lokasiSampling = $tempat;
                }

                $tempat = min(3, $lokasiSampling);

                // =========================================================
                // FIX UTAMA: Fee Luar Kota & Sampling 24 Jam — Saling Eksklusif
                // =========================================================
                //
                // ATURAN BISNIS:
                //   - Luar Kota durasi >= 2  → pakai `sampling_luar_kota_24jam` SAJA
                //                              (sudah mencakup komponen menginap/24jam)
                //                              → sampling_24jam TIDAK dihitung
                //
                //   - Luar Kota durasi == 1  → pakai `sampling_luar_kota` SAJA
                //                              → sampling_24jam TIDAK dihitung
                //                              (pulang hari, tidak ada komponen menginap)
                //
                //   - Dalam Kota durasi >= 2 → pakai `sampling_24jam` per hari efektif
                //                              (dihitung via hitungFeeHariLiburDanSampling24Jam)
                //
                //   - Hari libur             → selalu dihitung di semua kondisi
                // =========================================================

                if ($adaLuarKota) {

                    // --- Hitung fee hari libur saja (tanpa sampling_24jam) ---
                    $hasilLibur = $this->hitungFeeHariLibur(
                        $tgl,
                        $durasi_tertinggi,
                        $fee,
                        $liburKantor
                    );
                    $feeTambahan += $hasilLibur['total_fee'];
                    $feeTambahanRincian['hari_libur'] += $hasilLibur['fee_hari_libur'];

                    // --- Fee luar kota (pilih salah satu, tidak + sampling_24jam) ---
                    if ($durasiTertinggiLuarKota >= 2) {
                        // Luar kota dengan menginap: gunakan tarif luar_kota_24jam
                        $feeTambahan += $fee->sampling_luar_kota_24jam;
                        $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
                    } else {
                        // Luar kota pulang hari: gunakan tarif luar_kota biasa
                        $feeTambahan += $fee->sampling_luar_kota;
                        $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
                    }

                } else {

                    // --- Dalam kota: hitung hari libur + sampling_24jam normal ---
                    // FIX #3: pakai durasiTertinggiDalamKota (bukan durasi_tertinggi global)
                    // agar kasus 1 hari ada item dalam+luar kota, durasi dalam kota tetap benar
                    $hasilLibur24Jam = $this->hitungFeeHariLiburDanSampling24Jam(
                        $tgl,
                        $durasiTertinggiDalamKota,
                        $fee,
                        $liburKantor
                    );
                    $feeTambahan += $hasilLibur24Jam['total_fee'];
                    $feeTambahanRincian['hari_libur']     += $hasilLibur24Jam['rincian']['hari_libur'];
                    $feeTambahanRincian['sampling_24jam'] += $hasilLibur24Jam['rincian']['sampling_24jam'];
                }

                $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

                // === Cek driver ===
                // FIX #1: gunakan $itemDriver yang disimpan saat loop, bukan $item sisa foreach
                if ($itemDriver) {
                    $driver = MasterKaryawan::where('nama_lengkap', $itemDriver->driver)->where('is_active', 1)->first();
                    if ($driver) {
                        $feeDriver = MasterFeeDriver::where('driver_id', $driver->id)->where('is_active', 1)->first();
                        if ($feeDriver) {
                            $feeTambahan += $feeDriver->fee;
                            $feeTambahanRincian['driver'] = $feeDriver->fee;
                        } else {
                            return [
                                'error'  => "Driver {$itemDriver->driver} belum ditetapkan fee drivernya silahkan hubungi HRD.",
                                'status' => 401,
                            ];
                        }
                    } else {
                        return [
                            'error'  => "Driver '{$itemDriver->driver}' tidak ditemukan di MasterKaryawan.",
                            'status' => 401,
                        ];
                    }
                }

                // === Fee Pokok ===
                $feePokokDasar    = $fee->titik_1;
                $feePokokTambahan = 0;

                if ($tempat >= 2) {
                    $feePokokTambahan += 10000;
                }
                if ($tempat >= 3) {
                    $feePokokTambahan += ($tempat - 2) * 15000;
                }

                $hariAktif = max($durasi_tertinggi - 1, 1);
                $feePokok  = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

                $totalHarian = $feePokok + $feeTambahan;

                $rekap[] = [
                    'tanggal'       => $tgl,
                    'jumlah_tempat' => $tempat,
                    'fee_pokok'     => $feePokok,
                    'rincian_fee_pokok' => [
                        'base'                     => $feePokokDasar * $hariAktif,
                        'tambah_tempat_kedua'       => $tempat >= 2 ? 10000 * $hariAktif : 0,
                        'tambah_tempat_selanjutnya' => $tempat >= 3 ? ($tempat - 2) * 15000 * $hariAktif : 0,
                    ],
                    'fee_tambahan'         => $feeTambahan,
                    'fee_tambahan_rincian' => $feeTambahanRincian,
                    'total_fee'            => $totalHarian,
                ];

                $total += $totalHarian;
            }

            return [
                'sampler_id'     => $userId,
                'level'          => $level,
                'harian'         => collect($rekap)->sortBy('tanggal')->values()->all(),
                'total_mingguan' => $total,
                'overtime'       => MasterFeeSampling::where('kategori', $level)->first()->sampling_24jam,
            ];

        } catch (\Throwable $th) {
            dd($th);
        }
    }

    /**
     * Hitung fee hari libur DAN sampling 24 jam.
     * Digunakan untuk kasus DALAM KOTA dengan durasi > 1.
     */
    private function hitungFeeHariLiburDanSampling24Jam($tanggalAwal, $durasi, $fee, $liburKantor)
    {
        $totalFee   = 0;
        $feeRincian = [
            'hari_libur'   => 0,
            'sampling_24jam' => 0,
        ];

        $tanggalMulai = Carbon::parse($tanggalAwal);
        $durasiEfektif = max((int) $durasi - 1, 0);

        $feeSudahDihitungPadaTanggal = [];

        for ($i = 0; $i < $durasiEfektif; $i++) {
            $tgl       = $tanggalMulai->copy()->addDays($i)->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor)
                    || $carbonTgl->isSaturday()
                    || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee += $fee->hari_libur;
                    $feeRincian['hari_libur'] += $fee->hari_libur;
                }

                // Dalam kota: hitung sampling_24jam per hari efektif
                $totalFee += $fee->sampling_24jam;
                $feeRincian['sampling_24jam'] += $fee->sampling_24jam;

                $feeSudahDihitungPadaTanggal[] = $tgl;
            }
        }

        // Jika hanya 1 hari (durasi == 0), cek hari libur saja
        if ((int) $durasi === 0) {
            $tgl       = $tanggalMulai->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor)
                    || $carbonTgl->isSaturday()
                    || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee += $fee->hari_libur;
                    $feeRincian['hari_libur'] += $fee->hari_libur;
                }
            }
        }

        return [
            'total_fee' => $totalFee,
            'rincian'   => $feeRincian,
        ];
    }

    /**
     * Hitung fee hari libur SAJA (tanpa sampling_24jam).
     * Digunakan untuk kasus LUAR KOTA, karena fee 24jam sudah
     * tercakup di dalam sampling_luar_kota_24jam.
     */
    private function hitungFeeHariLibur($tanggalAwal, $durasi, $fee, $liburKantor)
    {
        $totalFee    = 0;
        $feeHariLibur = 0;

        $tanggalMulai  = Carbon::parse($tanggalAwal);
        $durasiEfektif = max((int) $durasi - 1, 0);

        $feeSudahDihitungPadaTanggal = [];

        for ($i = 0; $i < $durasiEfektif; $i++) {
            $tgl       = $tanggalMulai->copy()->addDays($i)->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor)
                    || $carbonTgl->isSaturday()
                    || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee     += $fee->hari_libur;
                    $feeHariLibur += $fee->hari_libur;
                }

                $feeSudahDihitungPadaTanggal[] = $tgl;
            }
        }

        // Jika hanya 1 hari (durasi == 0), tetap cek hari libur
        if ((int) $durasi === 0) {
            $tgl       = $tanggalMulai->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor)
                    || $carbonTgl->isSaturday()
                    || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee     += $fee->hari_libur;
                    $feeHariLibur += $fee->hari_libur;
                }
            }
        }

        return [
            'total_fee'    => $totalFee,
            'fee_hari_libur' => $feeHariLibur,
        ];
    }
}