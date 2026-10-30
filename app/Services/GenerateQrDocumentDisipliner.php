<?php

namespace App\Services;

use App\Models\QrDocument;
use App\Support\QrDocumentsStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generator QR terpusat untuk semua dokumen modul disipliner HR.
 */
class GenerateQrDocumentDisipliner
{
    public const TYPE_SURAT_PERINGATAN = 'surat_peringatan_disipliner';

    public const TYPE_BERITA_ACARA_KEJADIAN = 'berita_acara_kejadian_disipliner';

    public const TYPE_SURAT_TEGURAN = 'surat_teguran_disipliner';

    /** @var string[] */
    public const SUPPORTED_TYPES = [
        self::TYPE_SURAT_PERINGATAN,
        self::TYPE_BERITA_ACARA_KEJADIAN,
        self::TYPE_SURAT_TEGURAN,
    ];

    public function sync(string $typeDocument, object $document, object $detailRow, $generatedBy): string
    {
        $typeDocument = $this->normalizeType($typeDocument);

        $nomorSurat = trim((string) ($document->nomor_surat ?? ''));
        $documentId = (int) ($document->id ?? 0);
        if ($documentId <= 0) {
            throw new InvalidArgumentException('ID dokumen disipliner tidak valid.');
        }

        $filename = self::buildFilename($nomorSurat, $typeDocument);
        $qrData = self::buildQrPayload($typeDocument, $document, $detailRow);

        $linkedFile = trim((string) ($document->file_qr ?? ''));
        if ($linkedFile !== '') {
            $existingByLink = self::findRecord($typeDocument, $documentId, $linkedFile);
            if ($existingByLink !== null && trim((string) ($existingByLink->kode_qr ?? '')) !== '') {
                self::refreshMetadata($existingByLink, $typeDocument, $documentId, $qrData, $linkedFile);
                self::ensureSvgOnDisk($linkedFile, (string) $existingByLink->kode_qr);

                return $linkedFile;
            }
        }

        $existing = self::findRecord($typeDocument, $documentId, $filename);

        if ($existing !== null && trim((string) ($existing->kode_qr ?? '')) !== '') {
            $file = trim((string) ($existing->file ?? '')) ?: $filename;
            self::refreshMetadata($existing, $typeDocument, $documentId, $qrData, $file);
            self::ensureSvgOnDisk($file, (string) $existing->kode_qr);

            return $file;
        }

        $kodeQr = self::generateKodeQr();

        QrDocument::insert([
            'id_document' => $documentId,
            'type_document' => $typeDocument,
            'kode_qr' => $kodeQr,
            'file' => $filename,
            'data' => json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'created_by' => self::resolveGeneratedBy($generatedBy),
        ]);

        self::writePublicSvg($filename, $kodeQr, $typeDocument);

        return $filename;
    }

    public static function buildFilename(string $nomorSurat, ?string $typeDocument = null): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($nomorSurat));
        if ($safe !== '') {
            return $safe;
        }

        $prefix = self::filenameFallbackPrefix($typeDocument);

        return $prefix . time();
    }

    public static function ensurePublicSvgForFile(string $fileBase): bool
    {
        $fileBase = preg_replace('/\.svg$/i', '', trim($fileBase));
        if ($fileBase === '') {
            return false;
        }

        try {
            $path = QrDocumentsStorage::svgPath($fileBase);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        if (is_file($path)) {
            return true;
        }

        $record = QrDocument::query()
            ->where('file', $fileBase)
            ->whereIn('type_document', self::SUPPORTED_TYPES)
            ->first();

        if ($record === null || trim((string) ($record->kode_qr ?? '')) === '') {
            return false;
        }

        try {
            self::writePublicSvg($fileBase, (string) $record->kode_qr, (string) $record->type_document);

            return is_file($path);
        } catch (\Throwable $th) {
            Log::error('ensurePublicSvgForFile disipliner gagal', [
                'file' => $fileBase,
                'path' => $path,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public static function buildQrPayload(string $typeDocument, object $document, object $detailRow): array
    {
        $typeDocument = strtolower(trim($typeDocument));

        if ($typeDocument === self::TYPE_SURAT_PERINGATAN) {
            $uraianPlain = trim(strip_tags((string) ($document->uraian_pelanggaran ?? '')));

            return [
                'no_document' => trim((string) ($document->nomor_surat ?? '')),
                'nama_karyawan' => trim((string) ($detailRow->nama_lengkap ?? '')) ?: '-',
                'jenis_peringatan' => trim((string) ($document->jenis_peringatan ?? '')),
                'uraian_pelanggaran' => $uraianPlain !== '' ? $uraianPlain : '-',
                'modul' => 'disipliner',
                'jenis_dokumen' => 'surat_peringatan',
            ];
        }

        if ($typeDocument === self::TYPE_BERITA_ACARA_KEJADIAN) {
            $uraianPlain = trim(strip_tags((string) ($document->uraian_kejadian ?? '')));

            return [
                'no_document' => trim((string) ($document->nomor_surat ?? '')),
                'nama_karyawan' => trim((string) ($detailRow->nama_lengkap ?? '')) ?: '-',
                'perihal' => trim((string) ($document->perihal ?? '')) ?: '-',
                'uraian_kejadian' => $uraianPlain !== '' ? $uraianPlain : '-',
                'modul' => 'disipliner',
                'jenis_dokumen' => 'berita_acara_kejadian',
            ];
        }

        if ($typeDocument === self::TYPE_SURAT_TEGURAN) {
            $uraianPlain = trim(strip_tags((string) ($document->uraian_pelanggaran ?? '')));

            return [
                'no_document' => trim((string) ($document->nomor_surat ?? '')),
                'nama_karyawan' => trim((string) ($detailRow->nama_lengkap ?? '')) ?: '-',
                'perihal' => trim((string) ($document->perihal ?? '')) ?: '-',
                'uraian_pelanggaran' => $uraianPlain !== '' ? $uraianPlain : '-',
                'modul' => 'disipliner',
                'jenis_dokumen' => 'surat_teguran',
            ];
        }

        throw new InvalidArgumentException('Tipe dokumen disipliner untuk QR tidak dikenali: ' . $typeDocument);
    }

    private function normalizeType(string $typeDocument): string
    {
        $typeDocument = strtolower(trim($typeDocument));
        if (!in_array($typeDocument, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('Tipe dokumen disipliner untuk QR tidak dikenali: ' . $typeDocument);
        }

        return $typeDocument;
    }

    private static function findRecord(string $typeDocument, int $documentId, string $file): ?QrDocument
    {
        $file = preg_replace('/\.svg$/i', '', trim($file));
        if ($file === '') {
            return QrDocument::query()
                ->where('type_document', $typeDocument)
                ->where('id_document', $documentId)
                ->first();
        }

        return QrDocument::query()
            ->where('type_document', $typeDocument)
            ->where(function ($query) use ($documentId, $file) {
                $query->where('id_document', $documentId)
                    ->orWhere('file', $file);
            })
            ->first();
    }

    private static function refreshMetadata(
        QrDocument $record,
        string $typeDocument,
        int $documentId,
        array $qrData,
        string $file
    ): void {
        $record->id_document = $documentId;
        $record->type_document = $typeDocument;
        $record->file = $file;
        $record->data = json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->save();
    }

    private static function ensureSvgOnDisk(string $filename, string $kodeQr): void
    {
        try {
            $path = QrDocumentsStorage::svgPath($filename);
        } catch (InvalidArgumentException $e) {
            self::writePublicSvg($filename, $kodeQr, null);

            return;
        }

        if (is_file($path)) {
            return;
        }

        self::writePublicSvg($filename, $kodeQr, null);
    }

    private static function resolveGeneratedBy($generatedBy): string
    {
        return trim((string) $generatedBy) !== '' ? trim((string) $generatedBy) : 'system';
    }

    private static function generateKodeQr(): string
    {
        return 'isldc' . (int) floor(microtime(true) * 1000);
    }

    private static function filenameFallbackPrefix(?string $typeDocument): string
    {
        $typeDocument = strtolower(trim((string) $typeDocument));

        if ($typeDocument === self::TYPE_BERITA_ACARA_KEJADIAN) {
            return 'berita_acara_kejadian_';
        }

        if ($typeDocument === self::TYPE_SURAT_TEGURAN) {
            return 'surat_teguran_';
        }

        return 'surat_peringatan_';
    }

    public static function writePublicSvg(string $filename, string $kodeQr, ?string $typeDocument = null): void
    {
        if ($filename === '' || $kodeQr === '') {
            throw new \RuntimeException('Nama file atau kode QR tidak valid.');
        }

        QrDocumentsStorage::ensureDirectory();
        $path = QrDocumentsStorage::svgPath($filename);
        $link = 'https://www.intilab.com/validation/' . $kodeQr;

        QrCode::size(200)->generate($link, $path);

        if (!is_file($path)) {
            throw new \RuntimeException('File QR gagal disimpan ke: ' . $path);
        }

        Log::info('QR dokumen disipliner disimpan', [
            'path' => $path,
            'type_document' => $typeDocument,
        ]);
    }
}
