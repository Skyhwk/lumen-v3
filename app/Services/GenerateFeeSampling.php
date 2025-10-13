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

class GenerateFeeSampling
{
    // public function rekapFeeSampling($userId, $level, $tanggal)
    // {
    //     try {
    //         // 1. Ambil fee berdasarkan level
    //         $fee = MasterFeeSampling::where('kategori', $level)->first();
    //         if (!$fee) {
    //             throw new \Exception("Fee untuk level '$level' tidak ditemukan.");
    //         }

    //         // 2. Ambil semua tanggal libur
    //         $liburKantor = LiburPerusahaan::whereIn('tipe', ['cuti_bersama', 'Penggantian'])
    //             ->where('is_active', 1)
    //             ->pluck('tanggal')
    //             ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
    //             ->toArray();

    //         // 3. Ambil semua jadwal sampling
    //         $jadwal = Jadwal::with('persiapanHeader')
    //             ->where('is_active', 1)
    //             ->where('userid', $userId)
    //             ->whereIn('tanggal', $tanggal)
    //             ->whereHas('persiapanHeader')
    //             ->get()
    //             ->groupBy('tanggal');
             
    //         // 4. Ambil semua data wilayah dalam kota (cache di awal)
    //         $dalamKotaMap = MasterWilayahSampling::where('is_active', 1)
    //             ->where('status_wilayah', 'Dalam Kota')
    //             ->get()
    //             ->groupBy('id_cabang')
    //             ->map(function ($group) {
    //                 return $group->pluck('wilayah')->map(fn($w) => strtoupper($w))->toArray();
    //             });

    //         // 5. Siapkan hasil
    //         $rekap = [];
    //         $total = 0;

    //         $durasi_map = [
    //             0 => 'Sesaat',
    //             1 => '8 Jam',
    //             2 => '1x24 Jam',
    //             3 => '2x24 Jam',
    //             4 => '3x24 Jam',
    //             5 => '4x24 Jam',
    //             6 => '6x24 Jam',
    //             7 => '7x24 Jam',
    //         ];

    //         // Cek luar kota (hanya 1x per hari)
    //         $adaLuarKota = false;
    //         $durasiTertinggiLuarKota = 0;

    //         foreach ($jadwal as $tgl => $items) {
    //             // dd($tgl);
    //             $semuaAir = true;
    //             $titik = 0;
    //             $feeTambahan = 0;
    //             $durasi_tertinggi = 0;
    //             // $quotationTotal = 0;
    //             $perusahaanUnik = collect($items)->pluck('nama_perusahaan')->unique();
    //             $noQuote = collect($items)->pluck('no_quotation')->unique();
    //             // foreach($noQuote as $quote){
    //             //     $quotation = QuotationKontrakH::where('no_document', $quote)->first();
    //             //     if(!$quotation) $quotation = QuotationNonKontrak::where('no_document', $quote)->first();
    //             //         if(isset($quotation->harga_perdiem_personil_total)) {
    //             //         $quotationTotal += 1;
    //             //     }
    //             // }
    //             $feeTambahanRincian = [
    //                 'sampling_24jam' => 0,
    //                 'isokinetik' => 0,
    //                 'hari_libur' => 0,
    //                 'luar_kota' => 0,
    //                 'luar_kota_24jam' => 0,
    //                 'driver' => 0, // <- tambah ini
    //                 'durasi_sampling' => '',
    //             ];

    //             $titikAirGabungan = 0;
    //             $ptCampurAtauNonAir = [];  // PT yang mengandung non-air (campur atau non-air only)
    //             $ptAirOnly = []; 
    //                  // PT yang hanya air
    //                 //  dd($items);
    //             foreach ($items as $item) {
    //                 $noOrder = $item->persiapanHeader->no_order;
    //                 $durasiValue = (int) $item->durasi;
    //                 $durasi_tertinggi = max($durasi_tertinggi, $durasiValue);
    //                 $nama = trim(strtolower($item->nama_perusahaan)); // bisa ganti pakai titik sampling
    //                 // $perusahaanGabungan[] = $nama;
    //                 // Kategori validasi
    //                 $kategori = json_decode($item->kategori, true);
    //                 if (!is_array($kategori)) $kategori = [];
    //                 $punyaAir = false;
    //                 $punyaNonAir = false;
    //                 $titikAirPTIni = 0;
    //                 foreach ($kategori as $k) {
    //                     if (stripos($k, 'air') !== false) {
    //                         $punyaAir = true;
    //                         $noSampelIni = $noOrder ."/".explode(' - ', $k)[1];
    //                         $datalapangan = DataLapanganAir::where('no_sampel', $noSampelIni)->first();
    //                         // if($datalapangan) $titikAirPTIni++;
    //                        $titikAirPTIni++;
                        
    //                     } else {
    //                         $punyaNonAir = true;
    //                     }
    //                 }
    //                 if ($punyaAir && !$punyaNonAir) {
    //                     // PT ini hanya air, simpan titiknya
    //                     if (!isset($ptAirOnly[$nama])) {
    //                         $ptAirOnly[$nama] = 0;
    //                     }
    //                     $ptAirOnly[$nama] += $titikAirPTIni;
    //                 } else {
    //                     // PT ini campur atau non-air
    //                     $ptCampurAtauNonAir[$nama] = true;
    //                 }

    //                 // Isokinetik
    //                 if ($item->note && stripos($item->note, 'isokinetik') !== false) {
    //                     $feeTambahan += $fee->isokinetik;
    //                     $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
    //                 }

    //                 // Luar kota
    //                 // $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
    //                 // if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
    //                 //     // dd($dalamKotaWilayah, $item->wilayah, $dalamKotaMap);
    //                 //     if ($durasiValue >= 3) {
    //                 //         $feeTambahan += $fee->sampling_luar_kota_24jam;
    //                 //         $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
    //                 //     } else {
    //                 //         $feeTambahan += $fee->sampling_luar_kota;
    //                 //         $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
    //                 //     }
    //                 // }
    //                 $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
    //                 if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
    //                     $adaLuarKota = true;
    //                     $durasiTertinggiLuarKota = max($durasiTertinggiLuarKota, (int) $item->durasi);
    //                 }
            
    //             }
    //             // Setelah selesai foreach($items)
    //             if ($adaLuarKota) {
    //                 if ($durasiTertinggiLuarKota >= 3) {
    //                     $feeTambahan += $fee->sampling_luar_kota_24jam;
    //                     $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
    //                 } else {
    //                     $feeTambahan += $fee->sampling_luar_kota;
    //                     $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
    //                 }
    //             }
    //             // ⬇️ DISINI tempatnya cek apakah dia driver
    //             $isDriver = collect($items)->contains(function ($item) {
    //                 return isset($item->driver, $item->sampler) &&
    //                     trim(strtolower($item->driver)) === trim(strtolower($item->sampler));
    //             });
    //             if ($isDriver) {
    //                 $feeTambahan += 20000;
    //                 $feeTambahanRincian['driver'] = 20000;
    //             } else {
    //                 $feeTambahanRincian['driver'] = 0;
    //             }
    //             // Ambil durasi tertinggi dari semua item hari ini
    //             $durasiEfektif = max($durasi_tertinggi - 1, 0);
    //             $tanggalCarbon = Carbon::parse($tgl);

    //             $isLibur = in_array($tgl, $liburKantor) || $tanggalCarbon->isSaturday() || $tanggalCarbon->isSunday();

    //             if ($isLibur) {
    //                 $feeTambahan += $fee->hari_libur;
    //                 $feeTambahanRincian['hari_libur'] += $fee->hari_libur;
    //             }

    //             if ($durasiEfektif > 0) {
    //                 $feeTambahan += $fee->sampling_24jam * $durasiEfektif;
    //                 $feeTambahanRincian['sampling_24jam'] += $fee->sampling_24jam * $durasiEfektif;
    //             }

    //             // Hitung total titik air dari PT yang hanya air
    //             foreach ($ptAirOnly as $jumlahTitik) {
    //                 $titikAirGabungan += $jumlahTitik;
    //             }
       
    //             // Hitung tempat dari titik air gabungan
    //             $airTempat = 0;

    //             if ($titikAirGabungan > 0) {
    //                 if ($titikAirGabungan <= 10) {
    //                     $airTempat = 1;
    //                 } elseif ($titikAirGabungan <= 20) {
    //                     $airTempat = 2;
    //                 } else {
    //                     $airTempat = 3;
    //                 }
    //             }

    //             // if(count($noQuote) > 1){
    //             //     $airTempat = $airTempat + $quotationTotal;
    //             // } else {
    //             //     $airTempat = $quotationTotal;
    //             // }

    //             // Hitung tempat dari PT campur/non-air
    //             // $nonAirTempat = count($ptCampurAtauNonAir) > 0 ? 1 : 0;
    //             $nonAirTempat = count($ptCampurAtauNonAir);

    //             // Total tempat
    //             if ($airTempat > 0 && $nonAirTempat > 0) {
    //                 $tempat = $airTempat + $nonAirTempat;
    //             } else if ($airTempat > 0) {
    //                 $tempat = $airTempat;
    //             } else {
    //                 $tempat = $nonAirTempat;
    //             }

    //             $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

    //             // Fee Pokok
    //             $feePokokDasar = $fee->titik_1;
    //             $feePokokTambahan = 0;
                
    //             if ($tempat >= 2) {
    //                 $feePokokTambahan += 10000; // tempat ke-2
    //             }

    //             if ($tempat >= 3) {
    //                 $feePokokTambahan += ($tempat - 2) * 15000; // tempat ke-3 ke atas
    //             }

    //             // ✅ Hitung hari aktif: durasi tertinggi - 1 (minimal 1 hari)
    //             $hariAktif = max($durasi_tertinggi - 1, 1);

    //             // ✅ Hitung fee pokok berdasarkan hari aktif
    //             $feePokok = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

    //             // ✅ Total harian = fee pokok + fee tambahan
    //             $totalHarian = $feePokok + $feeTambahan;
    //             // dd($tempat);
    //             // ✅ Push ke rekap
    //             $rekap[] = [
    //                 'tanggal' => $tgl,
    //                 'jumlah_tempat' => $tempat,
    //                 'fee_pokok' => $feePokok,
    //                 'rincian_fee_pokok' => [
    //                     'base' => $feePokokDasar * $hariAktif,
    //                     'tambah_tempat_kedua' => $tempat >= 2 ? 10000 * $hariAktif : 0,
    //                     'tambah_tempat_selanjutnya' => $tempat >= 3 ? ($tempat - 2) * 15000 * $hariAktif : 0,
    //                 ],
    //                 'fee_tambahan' => $feeTambahan,
    //                 'fee_tambahan_rincian' => $feeTambahanRincian,
    //                 'total_fee' => $totalHarian,
    //             ];

    //             // ✅ Tambahkan ke total mingguan
    //             $total += $totalHarian;
    //         }

    //         return [
    //             'sampler_id' => $userId,
    //             'level' => $level,
    //             'harian' => collect($rekap)->sortBy('tanggal')->values()->all(),
    //             'total_mingguan' => $total,
    //             'overtime' => MasterFeeSampling::where('kategori', $level)->first()->sampling_24jam,
    //         ];
    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }
    // public function rekapFeeSampling($userId, $level, $tanggal)
    // {
    //     try {
    //         // 1. Ambil fee berdasarkan level
    //         $fee = MasterFeeSampling::where('kategori', $level)->first();
    //         if (!$fee) {
    //             throw new \Exception("Fee untuk level '$level' tidak ditemukan.");
    //         }

    //         // 2. Ambil semua tanggal libur
    //         $liburKantor = LiburPerusahaan::whereIn('tipe', ['cuti_bersama', 'Penggantian'])
    //             ->where('is_active', 1)
    //             ->pluck('tanggal')
    //             ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
    //             ->toArray();

    //         // 3. Ambil semua jadwal sampling
    //         $jadwal = Jadwal::with('persiapanHeader')
    //             ->where('is_active', 1)
    //             ->where('userid', $userId)
    //             ->whereIn('tanggal', $tanggal)
    //             ->whereHas('persiapanHeader')
    //             ->get()
    //             ->groupBy('tanggal');

    //         // 4. Ambil semua data wilayah dalam kota (cache di awal)
    //         $dalamKotaMap = MasterWilayahSampling::where('is_active', 1)
    //             ->where('status_wilayah', 'Dalam Kota')
    //             ->get()
    //             ->groupBy('id_cabang')
    //             ->map(function ($group) {
    //                 return $group->pluck('wilayah')->map(fn($w) => strtoupper($w))->toArray();
    //             });

    //         // 5. Siapkan hasil
    //         $rekap = [];
    //         $total = 0;

    //         $durasi_map = [
    //             0 => 'Sesaat',
    //             1 => '8 Jam',
    //             2 => '1x24 Jam',
    //             3 => '2x24 Jam',
    //             4 => '3x24 Jam',
    //             5 => '4x24 Jam',
    //             6 => '6x24 Jam',
    //             7 => '7x24 Jam',
    //         ];

    //         foreach ($jadwal as $tgl => $items) {
    //             $semuaAir = true;
    //             $titik = 0;
    //             $feeTambahan = 0;
    //             $durasi_tertinggi = 0;
    //             $feeTambahanRincian = [
    //                 'sampling_24jam' => 0,
    //                 'isokinetik' => 0,
    //                 'hari_libur' => 0,
    //                 'luar_kota' => 0,
    //                 'luar_kota_24jam' => 0,
    //                 'driver' => 0,
    //                 'durasi_sampling' => '',
    //             ];

    //             $titikAirGabungan = 0;
    //             $ptCampurAtauNonAir = [];
    //             $ptAirOnly = [];

    //             // Flag luar kota
    //             $adaLuarKota = false;
    //             $durasiTertinggiLuarKota = 0;

    //             foreach ($items as $item) {
    //                 $noOrder = $item->persiapanHeader->no_order;
    //                 $durasiValue = (int) $item->durasi;
    //                 $durasi_tertinggi = max($durasi_tertinggi, $durasiValue);
    //                 $nama = trim(strtolower($item->nama_perusahaan));

    //                 // Kategori validasi
    //                 $kategori = json_decode($item->kategori, true);
    //                 if (!is_array($kategori)) $kategori = [];
    //                 $punyaAir = false;
    //                 $punyaNonAir = false;
    //                 $titikAirPTIni = 0;

    //                 foreach ($kategori as $k) {
    //                     if (stripos($k, 'air') !== false) {
    //                         $punyaAir = true;
    //                         $noSampelIni = $noOrder . "/" . explode(' - ', $k)[1];
    //                         $datalapangan = DataLapanganAir::where('no_sampel', $noSampelIni)->first();
    //                         $titikAirPTIni++;
    //                     } else {
    //                         $punyaNonAir = true;
    //                     }
    //                 }

    //                 if ($punyaAir && !$punyaNonAir) {
    //                     // PT ini hanya air
    //                     if (!isset($ptAirOnly[$nama])) {
    //                         $ptAirOnly[$nama] = 0;
    //                     }
    //                     $ptAirOnly[$nama] += $titikAirPTIni;
    //                 } else {
    //                     // PT ini campur atau non-air
    //                     $ptCampurAtauNonAir[$nama] = true;
    //                 }

    //                 // Isokinetik
    //                 if ($item->note && stripos($item->note, 'isokinetik') !== false) {
    //                     $feeTambahan += $fee->isokinetik;
    //                     $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
    //                 }

    //                 // Tandai luar kota (hanya 1x per hari)
    //                 $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
    //                 if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
    //                     $adaLuarKota = true;
    //                     $durasiTertinggiLuarKota = max($durasiTertinggiLuarKota, (int) $item->durasi);
    //                 }
    //             }

    //             // 🔁 Gabungkan Air Only ke PT Campur jika keduanya ada
    //             if (count($ptCampurAtauNonAir) > 0 && count($ptAirOnly) > 0) {
    //                 $totalTitikAirOnly = array_sum($ptAirOnly);
    //                 $gabunganAirDenganCampur = true;
    //                 $totalTitikGabunganAir = $totalTitikAirOnly;
    //             } else {
    //                 $gabunganAirDenganCampur = false;
    //                 $totalTitikGabunganAir = 0;
    //             }

    //             // ✅ Fee luar kota (hanya 1x per hari)
    //             if ($adaLuarKota) {
    //                 if ($durasiTertinggiLuarKota >= 3) {
    //                     $feeTambahan += $fee->sampling_luar_kota_24jam;
    //                     $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
    //                 } else {
    //                     $feeTambahan += $fee->sampling_luar_kota;
    //                     $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
    //                 }
    //             }

    //             // ✅ Cek apakah dia driver
    //             $isDriver = collect($items)->contains(function ($item) {
    //                 return isset($item->driver, $item->sampler) &&
    //                     trim(strtolower($item->driver)) === trim(strtolower($item->sampler));
    //             });
    //             if ($isDriver) {
    //                 $feeTambahan += 20000;
    //                 $feeTambahanRincian['driver'] = 20000;
    //             }

    //             // ✅ Hari libur
    //             $tanggalCarbon = Carbon::parse($tgl);
    //             $isLibur = in_array($tgl, $liburKantor) || $tanggalCarbon->isSaturday() || $tanggalCarbon->isSunday();
    //             if ($isLibur) {
    //                 $feeTambahan += $fee->hari_libur;
    //                 $feeTambahanRincian['hari_libur'] += $fee->hari_libur;
    //             }

    //             // ✅ Fee 24 jam
    //             $durasiEfektif = max($durasi_tertinggi - 1, 0);
    //             if ($durasiEfektif > 0) {
    //                 $feeTambahan += $fee->sampling_24jam * $durasiEfektif;
    //                 $feeTambahanRincian['sampling_24jam'] += $fee->sampling_24jam * $durasiEfektif;
    //             }

    //             // ✅ Hitung titik & tempat
    //             foreach ($ptAirOnly as $jumlahTitik) {
    //                 $titikAirGabungan += $jumlahTitik;
    //             }

    //             $airTempat = 0;
    //             if ($gabunganAirDenganCampur) {
    //                 $titikGabunganAkhir = $titikAirGabungan + $totalTitikGabunganAir;
    //                 if ($titikGabunganAkhir <= 10) {
    //                     $airTempat = 1;
    //                 } elseif ($titikGabunganAkhir <= 20) {
    //                     $airTempat = 2;
    //                 } else {
    //                     $airTempat = 3;
    //                 }
    //                 $nonAirTempat = 1;
    //             } else {
    //                 if ($titikAirGabungan > 0) {
    //                     if ($titikAirGabungan <= 10) {
    //                         $airTempat = 1;
    //                     } elseif ($titikAirGabungan <= 20) {
    //                         $airTempat = 2;
    //                     } else {
    //                         $airTempat = 3;
    //                     }
    //                 }
    //                 $nonAirTempat = count($ptCampurAtauNonAir);
    //             }

    //             // Total tempat gabungan
    //             $tempat = $airTempat + $nonAirTempat;

    //             $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

    //             // ✅ Fee pokok
    //             $feePokokDasar = $fee->titik_1;
    //             $feePokokTambahan = 0;

    //             if ($tempat >= 2) $feePokokTambahan += 10000;
    //             if ($tempat >= 3) $feePokokTambahan += ($tempat - 2) * 15000;

    //             $hariAktif = max($durasi_tertinggi - 1, 1);
    //             $feePokok = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

    //             // ✅ Total harian
    //             $totalHarian = $feePokok + $feeTambahan;

    //             // Simpan hasil
    //             $rekap[] = [
    //                 'tanggal' => $tgl,
    //                 'jumlah_tempat' => $tempat,
    //                 'fee_pokok' => $feePokok,
    //                 'rincian_fee_pokok' => [
    //                     'base' => $feePokokDasar * $hariAktif,
    //                     'tambah_tempat_kedua' => $tempat >= 2 ? 10000 * $hariAktif : 0,
    //                     'tambah_tempat_selanjutnya' => $tempat >= 3 ? ($tempat - 2) * 15000 * $hariAktif : 0,
    //                 ],
    //                 'fee_tambahan' => $feeTambahan,
    //                 'fee_tambahan_rincian' => $feeTambahanRincian,
    //                 'total_fee' => $totalHarian,
    //             ];

    //             $total += $totalHarian;
    //         }

    //         return [
    //             'sampler_id' => $userId,
    //             'level' => $level,
    //             'harian' => collect($rekap)->sortBy('tanggal')->values()->all(),
    //             'total_mingguan' => $total,
    //             'overtime' => $fee->sampling_24jam,
    //         ];
    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }
    // public function rekapFeeSampling($userId, $level, $tanggal)
    // {
    //     try {
    //         // 1. Ambil fee berdasarkan level
    //         $fee = MasterFeeSampling::where('kategori', $level)->first();
    //         if (!$fee) {
    //             throw new \Exception("Fee untuk level '$level' tidak ditemukan.");
    //         }

    //         // 2. Ambil semua tanggal libur
    //         $liburKantor = LiburPerusahaan::whereIn('tipe', ['cuti_bersama', 'Penggantian'])
    //             ->where('is_active', 1)
    //             ->pluck('tanggal')
    //             ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
    //             ->toArray();

    //         // 3. Ambil semua jadwal sampling
    //         $jadwal = Jadwal::with('persiapanHeader')
    //             ->where('is_active', 1)
    //             ->where('userid', $userId)
    //             ->whereIn('tanggal', $tanggal)
    //             ->whereHas('persiapanHeader')
    //             ->get()
    //             ->groupBy('tanggal');
            
    //         // 4. Ambil wilayah dalam kota
    //         $dalamKotaMap = MasterWilayahSampling::where('is_active', 1)
    //             ->where('status_wilayah', 'Dalam Kota')
    //             ->get()
    //             ->groupBy('id_cabang')
    //             ->map(function ($group) {
    //                 return $group->pluck('wilayah')->map(fn($w) => strtoupper($w))->toArray();
    //             });

    //         $rekap = [];
    //         $total = 0;

    //         $durasi_map = [
    //             0 => 'Sesaat',
    //             1 => '8 Jam',
    //             2 => '1x24 Jam',
    //             3 => '2x24 Jam',
    //             4 => '3x24 Jam',
    //             5 => '4x24 Jam',
    //             6 => '6x24 Jam',
    //             7 => '7x24 Jam',
    //         ];

    //         foreach ($jadwal as $tgl => $items) {

    //             $feeTambahan = 0;
    //             $durasi_tertinggi = 0;

    //             $feeTambahanRincian = [
    //                 'sampling_24jam' => 0,
    //                 'isokinetik' => 0,
    //                 'hari_libur' => 0,
    //                 'luar_kota' => 0,
    //                 'luar_kota_24jam' => 0,
    //                 'driver' => 0,
    //                 'durasi_sampling' => '',
    //             ];

    //             // =========================
    //             // 🔹 BAGIAN HITUNG TITIK
    //             // =========================
    //             $ptAirOnly = [];
    //             $ptCampuran = [];
    //             $ptNonAir = [];

    //             foreach ($items as $item) {
    //                 $durasiValue = (int) $item->durasi;
    //                 $durasi_tertinggi = max($durasi_tertinggi, $durasiValue);

    //                 $namaPT = strtolower(trim($item->nama_perusahaan));
    //                 $kategoriList = json_decode($item->kategori, true);
    //                 if (!is_array($kategoriList)) $kategoriList = [];

    //                 $hasAir = false;
    //                 $hasNonAir = false;
    //                 $titikAirPT = 0;

    //                 foreach ($kategoriList as $kategori) {
    //                     if (stripos($kategori, 'air') !== false) {
    //                         $hasAir = true;
    //                         $titikAirPT++;
    //                     } else {
    //                         $hasNonAir = true;
    //                     }
    //                 }

    //                 if ($hasAir && $hasNonAir) {
    //                     // Campuran
    //                     if (!isset($ptCampuran[$namaPT])) {
    //                         $ptCampuran[$namaPT] = ['air' => 0, 'nonAir' => 0];
    //                     }
    //                     $ptCampuran[$namaPT]['air'] += $titikAirPT;
    //                     $ptCampuran[$namaPT]['nonAir'] = 1;
    //                 } elseif ($hasAir) {
    //                     // Air only
    //                     $ptAirOnly[$namaPT] = ($ptAirOnly[$namaPT] ?? 0) + $titikAirPT;
    //                 } else {
    //                     // Non-air
    //                     $ptNonAir[$namaPT] = 1;
    //                 }

    //                 // Isokinetik
    //                 if ($item->note && stripos($item->note, 'isokinetik') !== false) {
    //                     $feeTambahan += $fee->isokinetik;
    //                     $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
    //                 }

    //                 // Deteksi luar kota (hitung 1x per hari)
    //                 $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
    //                 if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
    //                     $adaLuarKota = true;
    //                     $durasiTertinggiLuarKota = max($durasiTertinggiLuarKota ?? 0, $durasiValue);
    //                 }
    //             }

    //             // === Cek luar kota hanya 1x per hari
    //             if (!empty($adaLuarKota ?? false)) {
    //                 if (($durasiTertinggiLuarKota ?? 0) >= 3) {
    //                     $feeTambahan += $fee->sampling_luar_kota_24jam;
    //                     $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
    //                 } else {
    //                     $feeTambahan += $fee->sampling_luar_kota;
    //                     $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
    //                 }
    //             }

    //             // === Hitung total titik air gabungan
    //             $totalAirGabungan = array_sum($ptAirOnly);
    //             foreach ($ptCampuran as $data) {
    //                 $totalAirGabungan += $data['air'];
    //             }

    //             // Konversi jumlah titik air ke skor
    //             if ($totalAirGabungan > 20) {
    //                 $titikAir = 3;
    //             } elseif ($totalAirGabungan > 10) {
    //                 $titikAir = 2;
    //             } elseif ($totalAirGabungan > 0) {
    //                 $titikAir = 1;
    //             } else {
    //                 $titikAir = 0;
    //             }

    //             // Hitung titik non-air
    //             $titikNonAir = count($ptNonAir) + count($ptCampuran);

    //             // Total titik (maksimal 3)
    //             $tempat = min(3, $titikAir + $titikNonAir);
    //             // =========================

    //             // Tambahan driver
    //             $isDriver = collect($items)->contains(function ($item) {
    //                 return isset($item->driver, $item->sampler) &&
    //                     trim(strtolower($item->driver)) === trim(strtolower($item->sampler));
    //             });
    //             if ($isDriver) {
    //                 $feeTambahan += 20000;
    //                 $feeTambahanRincian['driver'] = 20000;
    //             }

    //             // Hari libur
    //             $tanggalCarbon = Carbon::parse($tgl);
    //             $isLibur = in_array($tgl, $liburKantor) || $tanggalCarbon->isSaturday() || $tanggalCarbon->isSunday();
    //             if ($isLibur) {
    //                 $feeTambahan += $fee->hari_libur;
    //                 $feeTambahanRincian['hari_libur'] += $fee->hari_libur;
    //             }

    //             // Durasi tambahan
    //             $durasiEfektif = max($durasi_tertinggi - 1, 0);
    //             if ($durasiEfektif > 0) {
    //                 $feeTambahan += $fee->sampling_24jam * $durasiEfektif;
    //                 $feeTambahanRincian['sampling_24jam'] += $fee->sampling_24jam * $durasiEfektif;
    //             }

    //             // Durasi label
    //             $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

    //             // Hitung fee pokok
    //             $feePokokDasar = $fee->titik_1;
    //             $feePokokTambahan = 0;

    //             if ($tempat >= 2) $feePokokTambahan += 10000;
    //             if ($tempat >= 3) $feePokokTambahan += ($tempat - 2) * 15000;

    //             $hariAktif = max($durasi_tertinggi - 1, 1);
    //             $feePokok = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

    //             $totalHarian = $feePokok + $feeTambahan;

    //             $rekap[] = [
    //                 'tanggal' => $tgl,
    //                 'jumlah_tempat' => $tempat,
    //                 'fee_pokok' => $feePokok,
    //                 'rincian_fee_pokok' => [
    //                     'base' => $feePokokDasar * $hariAktif,
    //                     'tambah_tempat_kedua' => $tempat >= 2 ? 10000 * $hariAktif : 0,
    //                     'tambah_tempat_selanjutnya' => $tempat >= 3 ? ($tempat - 2) * 15000 * $hariAktif : 0,
    //                 ],
    //                 'fee_tambahan' => $feeTambahan,
    //                 'fee_tambahan_rincian' => $feeTambahanRincian,
    //                 'total_fee' => $totalHarian,
    //             ];

    //             $total += $totalHarian;
    //         }

    //         return [
    //             'sampler_id' => $userId,
    //             'level' => $level,
    //             'harian' => collect($rekap)->sortBy('tanggal')->values()->all(),
    //             'total_mingguan' => $total,
    //             'overtime' => $fee->sampling_24jam,
    //         ];

    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }
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
                    'sampling_24jam' => 0,
                    'isokinetik' => 0,
                    'hari_libur' => 0,
                    'luar_kota' => 0,
                    'luar_kota_24jam' => 0,
                    'driver' => 0,
                    'durasi_sampling' => '',
                ];

                // ==== Klasifikasi PT dan Hitung Titik ====
                $ptAirOnly = [];
                $ptCampuran = [];
                $ptNonAir = [];

                $adaLuarKota = false;
                $durasiTertinggiLuarKota = 0;

                foreach ($items as $item) {
                    $namaPT = strtolower(trim($item->nama_perusahaan));
                    $kategoriList = json_decode($item->kategori, true);
                    if (!is_array($kategoriList)) $kategoriList = [];

                    $hasAir = false;
                    $hasNonAir = false;
                    $titikAirPT = 0;

                    foreach ($kategoriList as $kategori) {
                        if (stripos($kategori, 'air') !== false) {
                            $hasAir = true;
                            $titikAirPT++;
                        } else {
                            $hasNonAir = true;
                        }
                    }

                    if ($hasAir && $hasNonAir) {
                        if (!isset($ptCampuran[$namaPT])) {
                            $ptCampuran[$namaPT] = ['air' => 0, 'nonAir' => 0];
                        }
                        $ptCampuran[$namaPT]['air'] += $titikAirPT;
                        $ptCampuran[$namaPT]['nonAir'] = 1;
                    } elseif ($hasAir) {
                        $ptAirOnly[$namaPT] = ($ptAirOnly[$namaPT] ?? 0) + $titikAirPT;
                    } else {
                        $ptNonAir[$namaPT] = 1;
                    }

                    // Isokinetik
                    if ($item->note && stripos($item->note, 'isokinetik') !== false) {
                        $feeTambahan += $fee->isokinetik;
                        $feeTambahanRincian['isokinetik'] += $fee->isokinetik;
                    }

                    // Cek luar kota (hanya sekali per hari)
                    $dalamKotaWilayah = $dalamKotaMap[$item->id_cabang] ?? [];
                    if (!in_array(strtoupper($item->wilayah), $dalamKotaWilayah)) {
                        $adaLuarKota = true;
                        $durasiTertinggiLuarKota = max($durasiTertinggiLuarKota, (int) $item->durasi);
                    }

                    $durasi_tertinggi = max($durasi_tertinggi, (int) $item->durasi);
                }

                // === Hitung total titik air gabungan ===
                $totalAirGabungan = array_sum($ptAirOnly);
                foreach ($ptCampuran as $data) {
                    $totalAirGabungan += $data['air'];
                }
                // === Hitung titik air ===
                if (count($ptAirOnly) > 0 && count($ptCampuran) > 0) {
                    if ($totalAirGabungan > 20) {
                        $titikAir = 3;
                    } elseif ($totalAirGabungan > 10) {
                        $titikAir = 2;
                    } elseif ($totalAirGabungan > 0) {
                        $titikAir = 1;
                    } else {
                        $titikAir = 0;
                    }
                } else if(count($ptAirOnly) > 0) {
                    if ($totalAirGabungan > 20) {
                        $titikAir = 3;
                    } elseif ($totalAirGabungan > 10) {
                        $titikAir = 2;
                    } elseif ($totalAirGabungan > 0) {
                        $titikAir = 1;
                    } else {
                        $titikAir = 0;
                    }
                } else {
                    $titikAir = 0;
                }

                // Jika tidak ada air tapi ada campuran → tetap 1 titik
                // if ($titikAir === 0 && count($ptCampuran) > 0) {
                //     $titikAir = 1;
                // }
                // Non-Air & Campuran dihitung 1 titik per PT
                $titikNonAir = count($ptNonAir) + count($ptCampuran);

                // Total titik (maks 3)
                $tempat = min(3, $titikAir + $titikNonAir);

                // === Fee luar kota (1x per hari) ===
                if ($adaLuarKota) {
                    if ($durasiTertinggiLuarKota >= 3) {
                        $feeTambahan += $fee->sampling_luar_kota_24jam;
                        $feeTambahanRincian['luar_kota_24jam'] += $fee->sampling_luar_kota_24jam;
                    } else {
                        $feeTambahan += $fee->sampling_luar_kota;
                        $feeTambahanRincian['luar_kota'] += $fee->sampling_luar_kota;
                    }
                }

                // === Cek driver ===
                $isDriver = collect($items)->contains(function ($item) {
                    return isset($item->driver, $item->sampler) &&
                        trim(strtolower($item->driver)) === trim(strtolower($item->sampler));
                });
                if ($isDriver) {
                    $feeTambahan += 20000;
                    $feeTambahanRincian['driver'] = 20000;
                }

                // === Hari Libur ===
                $tanggalCarbon = Carbon::parse($tgl);
                $isLibur = in_array($tgl, $liburKantor) || $tanggalCarbon->isSaturday() || $tanggalCarbon->isSunday();
                if ($isLibur) {
                    $feeTambahan += $fee->hari_libur;
                    $feeTambahanRincian['hari_libur'] += $fee->hari_libur;
                }

                // === Sampling 24 jam ===
                $durasiEfektif = max($durasi_tertinggi - 1, 0);
                if ($durasiEfektif > 0) {
                    $feeTambahan += $fee->sampling_24jam * $durasiEfektif;
                    $feeTambahanRincian['sampling_24jam'] += $fee->sampling_24jam * $durasiEfektif;
                }

                $feeTambahanRincian['durasi_sampling'] = $durasi_map[$durasi_tertinggi] ?? 'Tidak Diketahui';

                // === Fee Pokok ===
                $feePokokDasar = $fee->titik_1;
                $feePokokTambahan = 0;

                if ($tempat >= 2) {
                    $feePokokTambahan += 10000;
                }
                if ($tempat >= 3) {
                    $feePokokTambahan += ($tempat - 2) * 15000;
                }

                $hariAktif = max($durasi_tertinggi - 1, 1);
                $feePokok = ($feePokokDasar + $feePokokTambahan) * $hariAktif;

                $totalHarian = $feePokok + $feeTambahan;

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
