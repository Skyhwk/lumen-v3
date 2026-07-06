<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Services\GenerateDokumenCocService;

use App\Models\DokumenCoc;

class DokumenCocController extends Controller
{
    public function generateDokumenCoc(Request $request)
    {
        try {
            $no_lhp = trim((string) ($request->input('no_lhp')
                ?? $request->input('cfr')
                ?? $request->input('noLhp')
                ?? $request->input('lhp_number')
                ?? ''));

            if ($no_lhp === '') {
                return response()->json([
                    'message' => 'No LHP wajib diisi',
                ], 422);
            }

            $dokumenCoc = DokumenCoc::where('no_lhp', $no_lhp)->first();
            if ($dokumenCoc) {
                return response()->json([
                    'message'  => 'Berhasil mendapatkan data COC',
                    'filename' => $dokumenCoc->filename,
                ], 200);
            }

            $service = new GenerateDokumenCocService($no_lhp);
            // Jika kode di bawah ini crash, dia akan lompat ke blok catch
            $filename = $service->generate();

            if (!$filename) return response()->json(['message' => 'Dokumen tidak tersedia'], 404);

            return response()->json([
                'message'  => 'Berhasil generate data COC',
                'filename' => $filename,
            ], 200);

        } catch (\Throwable $th) {
            // Tangkap error aslinya agar kita tahu apa yang rusak di dalam proses generate
            \Log::error('Crash saat generate COC V3: ' . $th->getMessage() . ' Line: ' . $th->getLine(), [
                'no_lhp' => $no_lhp ?? null,
            ]);
            
            return response()->json([
                'message' => 'Terjadi kerusakan saat proses pembuatan dokumen di server V3',
                'error'   => $th->getMessage()
            ], 500); 
            // Jika response ini yang muncul, fix masalahnya ada di dalam GenerateDokumenCocService!
        }
    }
}
