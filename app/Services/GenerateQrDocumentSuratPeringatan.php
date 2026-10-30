<?php

namespace App\Services;

use App\Models\QrDocument;
use App\Models\SuratPeringatanDisipliner;
use App\Support\QrDocumentsStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateQrDocumentSuratPeringatan
{
    public const TYPE_DOCUMENT = 'surat_peringatan_disipliner';

    public function sync(SuratPeringatanDisipliner $letter, object $detailRow, $generatedBy): string
    {
        $filename = self::buildFilename($letter->nomor_surat);
        $qrData = self::buildQrData($letter, $detailRow);

        $linkedFile = trim((string) ($letter->file_qr ?? ''));
        if ($linkedFile !== '') {
            $existingByLink = self::findRecordForLetter($letter->id, $linkedFile);
            if ($existingByLink !== null && trim((string) ($existingByLink->kode_qr ?? '')) !== '') {
                self::refreshMetadata($existingByLink, $letter, $qrData, $generatedBy, $linkedFile);
                self::ensureSvgOnDisk($linkedFile, (string) $existingByLink->kode_qr);

                return $linkedFile;
            }
        }

        $existing = self::findRecordForLetter($letter->id, $filename);

        if ($existing !== null && trim((string) ($existing->kode_qr ?? '')) !== '') {
            $file = trim((string) ($existing->file ?? '')) ?: $filename;
            self::refreshMetadata($existing, $letter, $qrData, $generatedBy, $file);
            self::ensureSvgOnDisk($file, (string) $existing->kode_qr);

            return $file;
        }

        $kodeQr = self::generateKodeQr();

        QrDocument::insert([
            'id_document' => $letter->id,
            'type_document' => self::TYPE_DOCUMENT,
            'kode_qr' => $kodeQr,
            'file' => $filename,
            'data' => json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'created_by' => self::resolveGeneratedBy($generatedBy),
        ]);

        self::writePublicSvg($filename, $kodeQr);

        return $filename;
    }

    public static function buildFilename(string $nomorSurat): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($nomorSurat));

        return $safe !== '' ? $safe : 'surat_peringatan_' . time();
    }

    public static function ensurePublicSvgForFile(string $fileBase): bool
    {
        $fileBase = preg_replace('/\.svg$/i', '', trim($fileBase));
        if ($fileBase === '') {
            return false;
        }

        try {
            $path = QrDocumentsStorage::svgPath($fileBase);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        if (is_file($path)) {
            return true;
        }

        $record = QrDocument::query()
            ->where('file', $fileBase)
            ->first();

        if ($record === null || trim((string) ($record->kode_qr ?? '')) === '') {
            return false;
        }

        try {
            self::writePublicSvg($fileBase, (string) $record->kode_qr);

            return is_file($path);
        } catch (\Throwable $th) {
            Log::error('ensurePublicSvgForFile gagal', [
                'file' => $fileBase,
                'path' => $path,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public static function buildQrData(SuratPeringatanDisipliner $letter, object $detailRow): array
    {
        $uraianPlain = trim(strip_tags((string) $letter->uraian_pelanggaran));

        return [
            'no_document' => trim((string) $letter->nomor_surat),
            'nama_karyawan' => trim((string) ($detailRow->nama_lengkap ?? '')) ?: '-',
            'jenis_peringatan' => trim((string) $letter->jenis_peringatan),
            'uraian_pelanggaran' => $uraianPlain !== '' ? $uraianPlain : '-',
        ];
    }

    private static function findRecordForLetter(int $letterId, string $file): ?QrDocument
    {
        $file = preg_replace('/\.svg$/i', '', trim($file));
        if ($file === '') {
            return QrDocument::query()
                ->where('type_document', self::TYPE_DOCUMENT)
                ->where('id_document', $letterId)
                ->first();
        }

        return QrDocument::query()
            ->where('type_document', self::TYPE_DOCUMENT)
            ->where(function ($query) use ($letterId, $file) {
                $query->where('id_document', $letterId)
                    ->orWhere('file', $file);
            })
            ->first();
    }

    /** Update JSON metadata saja; kode_qr & file tidak diganti. */
    private static function refreshMetadata(
        QrDocument $record,
        SuratPeringatanDisipliner $letter,
        array $qrData,
        $generatedBy,
        string $file
    ): void {
        $record->id_document = $letter->id;
        $record->type_document = self::TYPE_DOCUMENT;
        $record->file = $file;
        $record->data = json_encode($qrData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->save();
    }

    /** Tulis SVG hanya jika belum ada di disk (kode QR tetap sama). */
    private static function ensureSvgOnDisk(string $filename, string $kodeQr): void
    {
        try {
            $path = QrDocumentsStorage::svgPath($filename);
        } catch (\InvalidArgumentException $e) {
            self::writePublicSvg($filename, $kodeQr);

            return;
        }

        if (is_file($path)) {
            return;
        }

        self::writePublicSvg($filename, $kodeQr);
    }

    private static function resolveGeneratedBy($generatedBy): string
    {
        return trim((string) $generatedBy) !== '' ? trim((string) $generatedBy) : 'system';
    }

    private static function generateKodeQr(): string
    {
        return 'isldc' . (int) floor(microtime(true) * 1000);
    }

    public static function writePublicSvg(string $filename, string $kodeQr): void
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

        Log::info('QR surat peringatan disimpan', ['path' => $path]);
    }
}
