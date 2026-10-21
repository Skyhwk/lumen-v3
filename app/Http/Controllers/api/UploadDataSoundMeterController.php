<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DetailSoundMeter;
use App\Models\DeviceIntilab;
use App\Models\HistoryUploadSoundMeter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class UploadDataSoundMeterController extends Controller
{
    public function index()
    {
        $query = HistoryUploadSoundMeter::query()
            ->where('is_active', true)
            ->orderByDesc('id');

        return DataTables::of($query)->make(true);
    }

    public function upload(Request $request)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json([
                'message' => 'File TXT wajib diunggah.',
            ], 422);
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'txt') {
            return response()->json([
                'message' => 'Format file harus .txt dari memori sound meter.',
            ], 422);
        }

        $originalName = $file->getClientOriginalName();
        $fileSize = (int) $file->getSize();
        $fileHash = hash_file('sha256', $file->getRealPath());
        $now = Carbon::now();

        $storagePath = storage_path('app/sound_meter_uploads');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        $storedName = $now->format('YmdHis') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $file->move($storagePath, $storedName);
        $storedPath = $storagePath . DIRECTORY_SEPARATOR . $storedName;

        $history = new HistoryUploadSoundMeter();
        $history->nama_file = $originalName;
        $history->path_file = 'storage/app/sound_meter_uploads/' . $storedName;
        $history->ukuran_file = $fileSize;
        $history->file_hash = $fileHash;
        $history->status = 'pending';
        $history->uploaded_by = $this->karyawan;
        $history->uploaded_at = $now;
        $history->is_active = true;
        $history->created_at = $now;
        $history->updated_at = $now;

        try {
            $parsed = $this->parseSoundMeterTxt($storedPath);
        } catch (\Throwable $th) {
            $history->status = 'failed';
            $history->total_data = 0;
            $history->data_berhasil = 0;
            $history->data_dilewati = 0;
            $history->data_gagal = 0;
            $history->catatan = $th->getMessage();
            $history->save();

            return response()->json([
                'message' => $th->getMessage(),
                'summary' => $this->summaryPayload($history),
            ], 200);
        }

        $history->kode_device = $parsed['kode_device'];
        $history->no_sampel = $parsed['no_sampel'];
        $history->daftar_no_sampel = $parsed['daftar_no_sampel'];
        $history->daftar_shift = $parsed['daftar_shift'];
        $history->timestamp_awal = $parsed['timestamp_awal'];
        $history->timestamp_akhir = $parsed['timestamp_akhir'];
        $history->total_data = $parsed['total_data'];

        try {
            $result = $this->persistRows($parsed['rows'], $parsed['failed_rows']);
        } catch (\Throwable $th) {
            $history->status = 'failed';
            $history->data_berhasil = 0;
            $history->data_dilewati = 0;
            $history->data_gagal = $parsed['total_data'];
            $history->catatan = $th->getMessage();
            $history->detail_gagal = array_slice($parsed['failed_rows'], 0, 50);
            $history->save();

            return response()->json([
                'message' => 'Gagal memproses file: ' . $th->getMessage(),
                'summary' => $this->summaryPayload($history),
            ], 200);
        }

        $history->nama_device = $result['nama_device'];
        $history->data_berhasil = $result['inserted'];
        $history->data_dilewati = $result['skipped'];
        $history->data_gagal = $result['failed'];
        $history->detail_gagal = $result['detail_gagal'];
        $history->status = $this->resolveStatus($history->total_data, $result['inserted'], $result['skipped'], $result['failed']);
        $history->catatan = sprintf(
            'Total %d baris. Berhasil %d, dilewati %d, gagal %d.',
            $history->total_data,
            $result['inserted'],
            $result['skipped'],
            $result['failed']
        );
        $history->save();

        return response()->json([
            'message' => $history->catatan,
            'summary' => $this->summaryPayload($history),
        ], 200);
    }

    private function persistRows(array $rows, array $failedRows): array
    {
        $inserted = 0;
        $skipped = 0;
        $deviceNames = [];

        $kodes = array_values(array_unique(array_column($rows, 'id_device')));
        $samples = array_values(array_unique(array_column($rows, 'no_sampel')));

        DB::beginTransaction();
        try {
            foreach ($kodes as $kode) {
                $device = DeviceIntilab::where('kode', $kode)->first();
                if (!$device) {
                    $device = new DeviceIntilab();
                    $device->kode = $kode;
                    $device->nama = $kode;
                    $device->category = 'Sound Meter';
                    $device->created_at = Carbon::now();
                    $device->created_by = $this->karyawan;
                    $device->save();
                }
                $deviceNames[$kode] = $device->nama ?: $kode;
            }

            $existingKeys = [];
            if (!empty($kodes) && !empty($samples)) {
                $existing = DetailSoundMeter::whereIn('id_device', $kodes)
                    ->whereIn('no_sampel', $samples)
                    ->get(['id_device', 'timestamp', 'no_sampel', 'shift', 'db']);

                foreach ($existing as $row) {
                    $existingKeys[$this->duplicateKey(
                        $row->id_device,
                        $row->timestamp,
                        $row->no_sampel,
                        $row->shift,
                        $row->db
                    )] = true;
                }
            }

            $batch = [];
            foreach ($rows as $row) {
                $key = $this->duplicateKey(
                    $row['id_device'],
                    $row['timestamp'],
                    $row['no_sampel'],
                    $row['shift'],
                    $row['db']
                );

                if (isset($existingKeys[$key])) {
                    $skipped++;
                    continue;
                }

                $existingKeys[$key] = true;
                $batch[] = [
                    'id_device' => $row['id_device'],
                    'timestamp' => $row['timestamp'],
                    'no_sampel' => $row['no_sampel'],
                    'shift' => $row['shift'],
                    'LAeq' => $row['LAeq'],
                    'db' => $row['db'],
                    'data_pendukung' => null,
                ];
            }

            foreach (array_chunk($batch, 500) as $chunk) {
                DetailSoundMeter::insert($chunk);
                $inserted += count($chunk);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'failed' => count($failedRows),
            'detail_gagal' => array_slice($failedRows, 0, 50),
            'nama_device' => count($deviceNames) === 1 ? reset($deviceNames) : implode(', ', $deviceNames),
        ];
    }

    private function parseSoundMeterTxt(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('File tidak dapat dibaca.');
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            throw new \RuntimeException('File kosong.');
        }

        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', trim($headerLine));
        $map = $this->resolveHeaderMap($headerLine);
        if ($map === null) {
            fclose($handle);
            throw new \RuntimeException('Format header tidak sesuai. Gunakan datetime;no_sample;shift;dBA;LAeq;device.');
        }

        $rows = [];
        $failedRows = [];
        $samples = [];
        $shifts = [];
        $devices = [];
        $timestamps = [];
        $lineNumber = 1;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = explode(';', $line);
            if (count($cols) < 6) {
                $failedRows[] = [
                    'baris' => $lineNumber,
                    'alasan' => 'Jumlah kolom tidak lengkap',
                ];
                continue;
            }

            $datetime = trim($cols[$map['datetime']] ?? '');
            $noSampel = trim($cols[$map['no_sampel']] ?? '');
            $shift = trim($cols[$map['shift']] ?? '');
            $db = trim($cols[$map['db']] ?? '');
            $laeq = trim($cols[$map['laeq']] ?? '');
            $device = trim($cols[$map['device']] ?? '');

            if ($datetime === '' || $noSampel === '' || $shift === '' || $db === '' || $laeq === '' || $device === '') {
                $failedRows[] = [
                    'baris' => $lineNumber,
                    'alasan' => 'Ada kolom wajib yang kosong',
                ];
                continue;
            }

            try {
                $timestamp = Carbon::parse($datetime)->format('Y-m-d H:i:s');
            } catch (\Throwable $th) {
                $failedRows[] = [
                    'baris' => $lineNumber,
                    'alasan' => 'Timestamp tidak valid',
                ];
                continue;
            }

            if (!is_numeric($db) || !is_numeric($laeq)) {
                $failedRows[] = [
                    'baris' => $lineNumber,
                    'alasan' => 'Nilai dBA atau LAeq tidak valid',
                ];
                continue;
            }

            $rows[] = [
                'id_device' => $device,
                'timestamp' => $timestamp,
                'no_sampel' => $noSampel,
                'shift' => $shift,
                'LAeq' => $laeq,
                'db' => $this->normalizeNumber($db),
            ];

            $samples[$noSampel] = true;
            $shifts[$shift] = true;
            $devices[$device] = true;
            $timestamps[] = $timestamp;
        }

        fclose($handle);

        $sampleList = array_keys($samples);
        $shiftList = array_keys($shifts);
        usort($shiftList, function ($a, $b) {
            return ((int) substr($a, 1)) <=> ((int) substr($b, 1));
        });
        $deviceList = array_keys($devices);

        return [
            'rows' => $rows,
            'failed_rows' => $failedRows,
            'total_data' => count($rows) + count($failedRows),
            'kode_device' => count($deviceList) === 1 ? $deviceList[0] : (count($deviceList) ? implode(', ', $deviceList) : null),
            'no_sampel' => count($sampleList) === 1 ? $sampleList[0] : (count($sampleList) ? implode(', ', $sampleList) : null),
            'daftar_no_sampel' => $sampleList,
            'daftar_shift' => $shiftList,
            'timestamp_awal' => $timestamps ? min($timestamps) : null,
            'timestamp_akhir' => $timestamps ? max($timestamps) : null,
        ];
    }

    private function resolveHeaderMap(string $headerLine): ?array
    {
        $parts = array_map(function ($part) {
            return strtolower(trim($part));
        }, explode(';', $headerLine));

        $aliases = [
            'datetime' => ['datetime', 'timestamp', 'waktu'],
            'no_sampel' => ['no_sample', 'no_sampel', 'nosample', 'nosampel'],
            'shift' => ['shift'],
            'db' => ['dba', 'db', 'nilai'],
            'laeq' => ['laeq'],
            'device' => ['device', 'kode', 'kode_device'],
        ];

        $map = [];
        foreach ($aliases as $field => $names) {
            $index = null;
            foreach ($parts as $i => $part) {
                if (in_array($part, $names, true)) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                return null;
            }
            $map[$field] = $index;
        }

        return $map;
    }

    private function duplicateKey($device, $timestamp, $noSampel, $shift, $db): string
    {
        $normalizedTimestamp = $timestamp;
        try {
            $normalizedTimestamp = Carbon::parse($timestamp)->format('Y-m-d H:i:s');
        } catch (\Throwable $th) {
            $normalizedTimestamp = (string) $timestamp;
        }

        return implode('|', [
            (string) $device,
            $normalizedTimestamp,
            (string) $noSampel,
            (string) $shift,
            $this->normalizeNumber($db),
        ]);
    }

    private function normalizeNumber($value): string
    {
        if (!is_numeric($value)) {
            return trim((string) $value);
        }

        $number = (float) $value;
        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    }

    private function resolveStatus(int $total, int $inserted, int $skipped, int $failed): string
    {
        if ($total === 0 || ($inserted === 0 && $skipped === 0 && $failed > 0)) {
            return 'failed';
        }
        if ($failed > 0) {
            return 'partial';
        }
        return 'success';
    }

    private function summaryPayload(HistoryUploadSoundMeter $history): array
    {
        return [
            'id' => $history->id,
            'nama_file' => $history->nama_file,
            'kode_device' => $history->kode_device,
            'nama_device' => $history->nama_device,
            'no_sampel' => $history->no_sampel,
            'total_data' => (int) $history->total_data,
            'data_berhasil' => (int) $history->data_berhasil,
            'data_dilewati' => (int) $history->data_dilewati,
            'data_gagal' => (int) $history->data_gagal,
            'status' => $history->status,
            'catatan' => $history->catatan,
            'detail_gagal' => $history->detail_gagal ?: [],
        ];
    }
}
