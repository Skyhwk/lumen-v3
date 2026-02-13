<?php

namespace App\Http\Controllers\api;

use App\Models\{LiburPerusahaan, RekapLiburKalender};
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
class KalenderPerusahaanController extends Controller
{
    public function indexKalender(Request $request)
    {
        try {
            $data = LiburPerusahaan::where('is_active', true)
                ->where('tanggal', 'like', '%' . $request->tahun . '%')
                ->get();
            return response()->json([
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function inputLibur(Request $request)
    {
        DB::beginTransaction();
        try {
            $message = '';
            $existingRecord = LiburPerusahaan::where('tanggal', $request->tanggal)
                ->where('is_active', true)
                ->first();

            if ($existingRecord) {
                LiburPerusahaan::where('id', $existingRecord->id)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
                $message = 'Berhasil Mengubah Libur Perusahaan.!';
            } else {
                // dd($request->all());
                if ($request->tipe === 'libur_pengganti') {
                    LiburPerusahaan::insert([
                        'tipe' => $request->tipe ?? null,
                        'tanggal' => $request->tanggal ?? null,
                        'tgl_ganti' => $request->tgl_ganti ?? null,
                        'keterangan' => $request->keterangan ?? null,
                        'added_by' => $this->karyawan,
                        'added_at' => date('Y-m-d H:i:s'),
                        'is_active' => true
                    ]);
                } else {
                    LiburPerusahaan::insert([
                        'tipe' => $request->tipe ?? null,
                        'tanggal' => $request->tanggal ?? null,
                        'keterangan' => $request->keterangan ?? null,
                        'added_by' => $this->karyawan,
                        'added_at' => date('Y-m-d H:i:s'),
                        'is_active' => true
                    ]);
                }

                $message = 'Berhasil Menambahkan Libur Perusahaan.!';
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function removeLibur(Request $request)
    {
        DB::beginTransaction();
        try {
            $message = '';
            $status = 200;

            $existingRecord = LiburPerusahaan::where('tanggal', $request->tanggal)
                ->where('is_active', true)
                ->first();

            if ($existingRecord) {
                LiburPerusahaan::where('id', $existingRecord->id)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);

                $message = 'Berhasil Menghapus Libur Perusahaan.!';
                $status = 200;
            } else {
                $message = 'Tidak Dapat Hapus Tanggal Libur Nasional Atau Tanggal Libur Pengganti..!';
                $status = 401;
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message], $status);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    // public function addRekap(Request $request)
    // {
    //     // dd($request->all());
    //     DB::beginTransaction();
    //     try {
    //         $message = '';

    //         $existingRecord = RekapLiburKalender::where('tahun', $request->tahun)
    //             ->where('is_active', false)
    //             ->first();

    //         if ($existingRecord) {
    //             RekapLiburKalender::where('id', $existingRecord->id)
    //                 ->where('is_active', false)
    //                 ->update([
    //                     'rejected_by' => $this->user_id,
    //                     'rejected_at' => date('Y-m-d H:i:s'),
    //                     'is_active' => true
    //                 ]);
    //             $message = 'Berhasil Mengupdate Rekap Libur Kalender.!';
    //         } else {
    //             RekapLiburKalender::insert([
    //                 'tahun' => $request->tahun,
    //                 'tanggal' => json_encode($request->tanggal),
    //                 'added_by' => $this->user_id,
    //                 'added_at' => date('Y-m-d H:i:s'),
    //                 'is_active' => true
    //             ]);
    //             $message = 'Berhasil Menambahkan Rekap Libur Kalender.!';
    //         }

    //         DB::commit();
    //         return response()->json(['success' => true, 'message' => $message], 200);
    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    //     }
    // }
    public function addRekap(Request $request)
    {
        $dataevent = $request->event;
        $dataTanggal = $request->tanggal;
        $tanggalEventArray = array_column($dataevent, 'tanggal');

        $normalizedEventDates = array_map(function($date) {
            return Carbon::parse($date)->format('Y-m-d');
        }, $tanggalEventArray);

        foreach ($dataTanggal as $month => &$dates) {
            $dataTanggal[$month] = array_filter($dates, function($date) use ($normalizedEventDates) {
                $dateCarbon = Carbon::parse($date);
                return !in_array($dateCarbon->format('Y-m-d'), $normalizedEventDates)
                    && !$dateCarbon->isWeekend();
            });
            
            $dataTanggal[$month] = array_values($dataTanggal[$month]);
        }
        $tanggal_tambahan = LiburPerusahaan::select('tanggal')
            ->whereYear('tanggal', $request->tahun)
            ->where('is_active', true)
            ->where('tipe', 'cuti_bersama_tapi_masuk')
            ->get()
            ->pluck('tanggal') 
            ->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d'); 
            })
            ->toArray();
        foreach ($tanggal_tambahan as $tanggal) {
            $month = Carbon::parse($tanggal)->format('Y-m'); 
            $dateFormatted = Carbon::parse($tanggal)->format('Y-m-d'); 
            if (!isset($dataTanggal[$month])) {
                $dataTanggal[$month] = [];
            }
            
            if (!in_array($dateFormatted, $dataTanggal[$month])) {
                $dataTanggal[$month][] = $dateFormatted;
                usort($dataTanggal[$month], function ($a, $b) {
                    return strtotime($a) - strtotime($b);
                });
            }
        }

        DB::beginTransaction();
        try {
            $message = '';

            $existingRecord = RekapLiburKalender::where('tahun', $request->tahun)
                ->where('is_active', true)
                ->first();

            if ($existingRecord) {
                RekapLiburKalender::where('id', $existingRecord->id)
                    ->where('is_active', true)
                    ->update([
                        'tahun' => $request->tahun,
                        'tanggal' => json_encode($dataTanggal),
                        'updated_by' => $this->karyawan,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'is_active' => true
                    ]);
                $message = 'Berhasil Mengupdate Rekap Libur Kalender.!';
            } else {
                RekapLiburKalender::insert([
                    'tahun' => $request->tahun,
                    'tanggal' => json_encode($dataTanggal),
                    'added_by' => $this->karyawan,
                    'added_at' => date('Y-m-d H:i:s'),
                    'is_active' => true
                ]);
                $message = 'Berhasil Menambahkan Rekap Libur Kalender.!';
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function hariLibur(Request $request)
    {
        try {
            $response = Http::get('https://hari-libur-api.vercel.app/api?year=' . $request->tahun);
            return response()->json([
                'data' => $response->json(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}