<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Greatday\LiburPerusahaan;
use App\Models\RekapLiburKalender;
use App\Services\Greatday\OfficeCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request, OfficeCalendarService $officeCalendarService)
    {
        try {
            $year = (int) $request->input('year', date('Y'));
            $payload = $officeCalendarService->forYear($year);

            return response()->json([
                'data' => $payload['data'],
                'calendar' => $payload['calendar'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function inputLibur(Request $request)
    {
        if (!$this->nama_lengkap) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $existingRecord = LiburPerusahaan::where('tanggal', $request->tanggal)->where('is_active', true)->first();

            if ($existingRecord) {
                LiburPerusahaan::where('id', $existingRecord->id)
                    ->update([
                        'rejected_by' => $this->nama_lengkap,
                        'rejected_at' => Carbon::now(),
                        'is_active' => false,
                    ]);

                $message = 'Libur perusahaan updated successfully';
            } else {
                LiburPerusahaan::insert([
                    'tipe' => $request->tipe ?? null,
                    'tanggal' => $request->tanggal ?? null,
                    'tgl_ganti' => $request->tgl_ganti ?? null,
                    'keterangan' => $request->keterangan ?? null,
                    'added_by' => $this->nama_lengkap,
                    'added_at' => Carbon::now(),
                    'is_active' => true,
                ]);

                $message = 'Libur perusahaan added successfully';
            }

            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function removeLibur(Request $request)
    {
        if (!$this->nama_lengkap) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $existingRecord = LiburPerusahaan::where('tanggal', $request->tanggal)->where('is_active', true)->first();

            if ($existingRecord) {
                LiburPerusahaan::where('id', $existingRecord->id)
                    ->update([
                        'rejected_by' => $this->nama_lengkap,
                        'rejected_at' => Carbon::now(),
                        'is_active' => false,
                    ]);

                $message = 'Libur perusahaan removed successfully';
                $status = 200;
            } else {
                $message = 'Libur perusahaan not found';
                $status = 401;
            }

            return response()->json(['message' => $message], $status);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function addRekap(Request $request)
    {
        if (!$this->nama_lengkap) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $existingRecord = RekapLiburKalender::where('tahun', $request->tahun)->where('is_active', false)->first();

            if ($existingRecord) {
                RekapLiburKalender::where('id', $existingRecord->id)
                    ->where('is_active', false)
                    ->update([
                        'rejected_by' => $this->nama_lengkap,
                        'rejected_at' => Carbon::now(),
                        'is_active' => true,
                    ]);

                $message = 'Rekap libur kalender updated successfully';
            } else {
                RekapLiburKalender::insert([
                    'tahun' => $request->tahun,
                    'tanggal' => json_encode($request->tanggal),
                    'added_by' => $this->nama_lengkap,
                    'added_at' => Carbon::now(),
                    'is_active' => true,
                ]);

                $message = 'Rekap libur kalender added successfully';
            }

            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
