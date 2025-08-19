<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\MasterFeeSampling;
use App\Models\Jadwal;
use App\Models\LiburPerusahaan;
use App\Models\MasterWilayahSampling;

class GenerateFeeSampling
{
    public function rekapFeeSampling($userId, $level, $tanggal)
    {
        try {
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
            $jadwal = Jadwal::with('persiapanHeader')
                ->where('is_active', 1)
                ->where('userid', $userId)
                ->whereIn('tanggal', $tanggal)
                ->whereHas('persiapanHeader')
                ->get()
                ->groupBy('tanggal');

            // 4. Ambil semua data wilayah dalam kota (cache di awal)
            $dalamKotaMap = MasterWilayahSampling::where('is_active', 1)
                ->where('status_wilayah', 'Dalam Kota')
                ->get()
                ->groupBy('id_cabang')
                ->map(function ($group) {
                    return $group->pluck('wilayah')->map(fn($w) => strtoupper($w))->toArray();
                });

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
                // dd($tgl);
                $semuaAir = true;
                $titik = 0;
                $feeTambahan = 0;
                $durasi_tertinggi = 0;
                $perusahaanUnik = collect($items)->pluck('nama_perusahaan')->unique();

                $feeTambahanRincian = [
                    'sampling_24jam' => 0,
                    'isokinetik' => 0,
                    'hari_libur' => 0,
                    'luar_kota' => 0,
                    'luar_kota_24jam' => 0,
                    'driver' => 0, // <- tambah ini
                    'durasi_sampling' => '',
                ];

                $titikAirGabungan = 0;
                $ptCampurAtauNonAir = [];  // PT yang mengandung non-air (campur atau non-air only)
                $ptAirOnly = [];           // PT yang hanya air
                foreach ($items as $item) {
                    $durasiValue = (int) $item->durasi;
                    $durasi_tertinggi = max($durasi_tertinggi, $durasiValue);
                    $nama = trim(strtolower($item->nama_perusahaan)); // bisa ganti pakai titik sampling
                    // $perusahaanGabungan[] = $nama;
                    // Kategori validasi
                    $kategori = json_decode($item->kategori, true);
                    if (!is_array($kategori)) $kategori = [];
                    $punyaAir = false;
                    $punyaNonAir = false;
                    $titikAirPTIni = 0;
                    foreach ($kategori as $k) {
                        if (stripos($k, 'air') !== false) {
                            $punyaAir = true;
                            $titikAirPTIni++;
                        } else {
                            $punyaNonAir = true;
                        }
                    }

                    if ($punyaAir && !$punyaNonAir) {
                        // PT ini hanya air, simpan titiknya
                        if (!isset($ptAirOnly[$nama])) {
                            $ptAirOnly[$nama] = 0;
                        }
                        $ptAirOnly[$nama] += $titikAirPTIni;
                    } else {
                        // PT ini campur atau non-air
                        $ptCampurAtauNonAir[$nama] = true;
                    }

                    // Isokinetik
                    if ($item->note && stripos($item->note, 'isokinetik') !== false) {
                        $feeTambahan += $fee->isokinetik;
                        $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
                    }

                    // Luar kota
                    $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
                    if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
                        // dd($dalamKotaWilayah, $item->wilayah, $dalamKotaMap);
                        if ($durasiValue >= 3) {
                            $feeTambahan += $fee->sampling_luar_kota_24jam;
                            $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
                        } else {
                            $feeTambahan += $fee->sampling_luar_kota;
                            $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
                        }
                    }
                    // dd($ptAirOnly, $ptCampurAtauNonAir);

                    // Hari libur & sampling 24 jam
                    // $hasilLibur24 = $this->hitungFeeHariLiburDanSampling24Jam($item->tanggal, $durasiValue, $fee, $liburKantor);
                    // $feeTambahan += $hasilLibur24['total_fee'];
                    // $feeTambahanRincian['hari_libur'] += $hasilLibur24['rincian']['hari_libur'];
                    // $feeTambahanRincian['sampling_24jam'] += $hasilLibur24['rincian']['sampling_24jam'];
                }
                // ⬇️ DISINI tempatnya cek apakah dia driver
                $isDriver = collect($items)->contains(function ($item) {
                    return isset($item->driver, $item->sampler) &&
                        trim(strtolower($item->driver)) === trim(strtolower($item->sampler));
                });

                if ($isDriver) {
                    $feeTambahan += 20000;
                    $feeTambahanRincian['driver'] = 20000;
                } else {
                    $feeTambahanRincian['driver'] = 0;
                }
                // Ambil durasi tertinggi dari semua item hari ini
                $durasiEfektif = max($durasi_tertinggi - 1, 0);
                $tanggalCarbon = Carbon::parse($tgl);

                $isLibur = in_array($tgl, $liburKantor) || $tanggalCarbon->isSaturday() || $tanggalCarbon->isSunday();

                if ($isLibur) {
                    $feeTambahan += $fee->hari_libur;
                    $feeTambahanRincian['hari_libur'] += $fee->hari_libur;
                }

                if ($durasiEfektif > 0) {
                    $feeTambahan += $fee->sampling_24jam * $durasiEfektif;
                    $feeTambahanRincian['sampling_24jam'] += $fee->sampling_24jam * $durasiEfektif;
                }

                // Hitung total titik air dari PT yang hanya air
                foreach ($ptAirOnly as $jumlahTitik) {
                    $titikAirGabungan += $jumlahTitik;
                }

                // Hitung tempat dari titik air gabungan
                $airTempat = 0;
                if ($titikAirGabungan > 0) {
                    if ($titikAirGabungan <= 10) {
                        $airTempat = 1;
                    } elseif ($titikAirGabungan <= 20) {
                        $airTempat = 2;
                    } else {
                        $airTempat = 3;
                    }
                }

                // Hitung tempat dari PT campur/non-air
                $nonAirTempat = count($ptCampurAtauNonAir) >= 1 ? 1 : 0;


                // Total tempat
                if (isset($airTempat) && isset($nonAirTempat)) {
                    $tempat = $airTempat;
                } else if (isset($airTempat)) {
                    $tempat = $airTempat;
                } else {
                    $tempat = $nonAirTempat;
                }
                // $tempat = $airTempat + $nonAirTempat;
                // dd($titikAirGabungan, $nonAirTempat, $tempat);

                $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

                // Fee Pokok
                $feePokokDasar = $fee->titik_1;
                $feePokokTambahan = 0;

                if ($tempat >= 2) {
                    $feePokokTambahan += 10000; // tempat ke-2
                }

                if ($tempat >= 3) {
                    $feePokokTambahan += ($tempat - 2) * 15000; // tempat ke-3 ke atas
                }

                // ✅ Hitung hari aktif: durasi tertinggi - 1 (minimal 1 hari)
                $hariAktif = max($durasi_tertinggi - 1, 1);

                // ✅ Hitung fee pokok berdasarkan hari aktif
                $feePokok = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

                // ✅ Total harian = fee pokok + fee tambahan
                $totalHarian = $feePokok + $feeTambahan;

                // ✅ Push ke rekap
                $rekap[] = [
                    'tanggal' => $tgl,
                    'jumlah_tempat' => $tempat,
                    'fee_pokok' => $feePokok,
                    'rincian_fee_pokok' => [
                        'base' => $feePokokDasar * $hariAktif,
                        'tambah_tempat_kedua' => $tempat >= 2 ? 10000 * $hariAktif : 0,
                        'tambah_tempat_selanjutnya' => $tempat >= 3 ? ($tempat - 2) * 15000 * $hariAktif : 0,
                    ],
                    'fee_tambahan' => $feeTambahan,
                    'fee_tambahan_rincian' => $feeTambahanRincian,
                    'total_fee' => $totalHarian,
                ];

                // ✅ Tambahkan ke total mingguan
                $total += $totalHarian;
            }

            return [
                'sampler_id' => $userId,
                'level' => $level,
                'harian' => collect($rekap)->sortBy('tanggal')->values()->all(),
                'total_mingguan' => $total,
                'overtime' => MasterFeeSampling::where('kategori', $level)->first()->sampling_24jam,
            ];
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    private function hitungFeeHariLiburDanSampling24Jam($tanggalAwal, $durasi, $fee, $liburKantor)
    {
        $totalFee = 0;
        $feeRincian = [
            'hari_libur' => 0,
            'sampling_24jam' => 0,
        ];

        $tanggalMulai = Carbon::parse($tanggalAwal);
        $durasiEfektif = max((int) $durasi - 1, 0); // abaikan hari terakhir jika durasi >= 2

        $feeSudahDihitungPadaTanggal = []; // agar 1x per tanggal

        for ($i = 0; $i < $durasiEfektif; $i++) {
            $tgl = $tanggalMulai->copy()->addDays($i)->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            // Jika tanggal ini belum dikenakan fee
            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor) || $carbonTgl->isSaturday() || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee += $fee->hari_libur;
                    $feeRincian['hari_libur'] += $fee->hari_libur;
                }

                $totalFee += $fee->sampling_24jam;
                $feeRincian['sampling_24jam'] += $fee->sampling_24jam;

                $feeSudahDihitungPadaTanggal[] = $tgl; // tandai tanggal sudah diproses
            }
        }

        // ✅ Jika hanya 1 hari (durasi == 0 atau 1), cek hari itu
        if ((int) $durasi === 0) {
            $tgl = $tanggalMulai->toDateString();
            $carbonTgl = Carbon::parse($tgl);

            if (!in_array($tgl, $feeSudahDihitungPadaTanggal)) {
                $isLibur = in_array($tgl, $liburKantor) || $carbonTgl->isSaturday() || $carbonTgl->isSunday();

                if ($isLibur) {
                    $totalFee += $fee->hari_libur;
                    $feeRincian['hari_libur'] += $fee->hari_libur;

                    $feeSudahDihitungPadaTanggal[] = $tgl; // tandai tanggal sudah diproses
                }
            }
        }

        return [
            'total_fee' => $totalFee,
            'rincian' => $feeRincian
        ];
    }
}
