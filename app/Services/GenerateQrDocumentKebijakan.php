<?php

namespace App\Services;

use App\Models\KebijakanDokumen;
use App\Models\QrDocument;
use App\Models\RequestKebijakanVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateQrDocumentKebijakan
{
    public const TYPE_DOCUMENT = 'kebijakan_perusahaan';

    public function sync(KebijakanDokumen $dokumen, $generatedBy): string
    {
        $filename = self::buildFilename($dokumen->no_dokumen);
        $qrData = self::buildQrData($dokumen);

        $existing = QrDocument::query()
            ->where('type_document', self::TYPE_DOCUMENT)
            ->where(function ($query) use ($dokumen, $filename) {
                $query->where('id_document', $dokumen->id)
                    ->orWhere('file', $filename);
            })
            ->first();

        if ($existing) {
            $existing->id_document = $dokumen->id;
            $existing->file = $filename;
            $existing->data = json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $existing->created_by = self::resolveGeneratedBy($generatedBy);
            $existing->save();

            return $existing->file;
        }

        return $this->insert($dokumen, $generatedBy, $filename, $qrData);
    }

    public static function buildFilename(string $noDokumen): string
    {
        return str_replace('/', '_', $noDokumen);
    }

    public static function buildQrData(KebijakanDokumen $dokumen): array
    {
        $verifiers = RequestKebijakanVerifier::query()
            ->where('request_kebijakan_id', $dokumen->request_kebijakan_id)
            ->where('is_active', true)
            ->where('status', 'verified')
            ->orderBy('id')
            ->get();

        $diverifikasiOleh = $verifiers->map(function ($row) {
            $tanggal = $row->verification_date ?? $row->verified_at;

            return [
                'nama' => $row->verifier_nama_lengkap,
                'jabatan' => $row->verifier_jabatan ?: '-',
                'tanggal_verifikasi' => self::formatDisplayDate($tanggal),
            ];
        })->values()->all();

        $payload = [
            'jenis_dokumen' => 'KETETAPAN PERUSAHAAN',
            'nomor_dokumen' => $dokumen->no_dokumen,
            'ketetapan_dokumen' => $dokumen->judul,
            'diverifikasi_oleh' => $diverifikasiOleh,
            'disahkan_oleh' => null,
            'disahkan_pada' => null,
        ];

        if ($dokumen->status === 'active' && $dokumen->director_approved_by) {
            $payload['disahkan_oleh'] = $dokumen->director_approved_by;
            $payload['disahkan_pada'] = self::formatDisplayDate($dokumen->tanggal_pengesahan);
        }

        return $payload;
    }

    public static function formatDisplayDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->locale('id')->isoFormat('D MMMM YYYY');
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function insert(KebijakanDokumen $dokumen, $generatedBy, string $filename, array $qrData): string
    {
        DB::beginTransaction();

        try {
            $dir = public_path('qr_documents/');

            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $path = $dir . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);

            QrDocument::insert([
                'id_document' => $dokumen->id,
                'type_document' => self::TYPE_DOCUMENT,
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => self::resolveGeneratedBy($generatedBy),
            ]);

            DB::commit();

            return $filename;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private static function resolveGeneratedBy($generatedBy): string
    {
        if (is_string($generatedBy) && $generatedBy !== '') {
            return $generatedBy;
        }

        if (is_object($generatedBy) && !empty($generatedBy->nama_lengkap)) {
            return $generatedBy->nama_lengkap;
        }

        return 'System';
    }
}
