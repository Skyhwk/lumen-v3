<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SelfRegistration\StatementPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SelfRegistrationDocumentController extends Controller
{
    public function store(Request $request)
    {
        $id = (int) $request->input('registration_id');
        $signature = (string) $request->input('signature_base64');
        $payload = $request->input('payload');

        if ($id < 1) {
            return response()->json(['message' => 'registration_id tidak valid'], 422);
        }
        if (!is_array($payload) || empty($payload['email'])) {
            return response()->json(['message' => 'payload tidak valid'], 422);
        }

        $binary = $this->decodeBinary($signature);
        if ($binary === null) {
            return response()->json(['message' => 'Tanda tangan tidak valid'], 422);
        }

        try {
            $stored = (new StatementPdfService())->storeFromPayload($id, $binary, $payload);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal membuat dokumen: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'signature_path' => $stored['signature_path'],
            'pdf_path' => $stored['pdf_path'],
            'dokumen_hash' => $stored['dokumen_hash'],
        ]);
    }

    public function fetch(Request $request)
    {
        $id = (int) $request->input('registration_id');
        if ($id < 1) {
            return response()->json(['message' => 'registration_id tidak valid'], 422);
        }

        $pdfRel = 'self-registration/' . $id . '/surat-pernyataan.pdf';
        try {
            $row = DB::table($this->produksiDb() . '.self_registration_customer')->where('id', $id)->first();
            if ($row && !empty($row->pdf_path)) {
                $pdfRel = $row->pdf_path;
            }
        } catch (\Throwable $e) {
            Log::warning('self_registration_fetch_db', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
        }

        $binary = (new StatementPdfService())->readPdf($pdfRel);
        if ($binary === null || $binary === '') {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'filename' => 'surat-pernyataan-portal-intilab.pdf',
            'mime' => 'application/pdf',
            'content_base64' => base64_encode($binary),
        ]);
    }

    private function produksiDb()
    {
        return env('DB_2024', env('DB_DATABASE', 'intilab_produksi'));
    }

    private function decodeBinary($value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('#^data:image/(png|jpeg);base64,(.+)$#i', $value, $m)) {
            $value = $m[2];
        }
        $bin = base64_decode($value, true);
        if ($bin === false || strlen($bin) < 256) {
            return null;
        }
        return $bin;
    }
}
