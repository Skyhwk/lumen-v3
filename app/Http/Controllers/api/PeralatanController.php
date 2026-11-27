<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MonitoringPeralatan;
use App\Models\Peralatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Yajra\Datatables\Datatables;

class PeralatanController extends Controller
{
    public function index()
    {
        $table = (new Peralatan())->getTable();
        $monitoringTable = (new MonitoringPeralatan())->getTable();

        $latestMonitoring = DB::table($monitoringTable)
            ->select('id_peralatan', DB::raw('MAX(id) as latest_id'))
            ->where('is_active', true)
            ->whereNotNull('id_peralatan')
            ->groupBy('id_peralatan');

        $data = Peralatan::query()
            ->from("{$table} as p")
            ->leftJoinSub($latestMonitoring, 'latest_mp', function ($join) {
                $join->on('latest_mp.id_peralatan', '=', 'p.id');
            })
            ->leftJoin("{$monitoringTable} as mp", 'mp.id', '=', 'latest_mp.latest_id')
            ->where('p.is_active', true)
            ->select([
                'p.id',
                'p.nama_alat',
                'p.kode_aset',
                'p.jenis_peralatan',
                'p.warna',
                'p.nama_kategori_pengujian',
                'p.id_cabang',
                'p.lokasi_penempatan_alat',
                'mp.status_alat',
                'mp.keterangan',
                'mp.start_date',
                'mp.end_date',
            ])
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS p2
                    LEFT JOIN (
                        SELECT id_peralatan, MAX(id) AS latest_id
                        FROM {$monitoringTable}
                        WHERE is_active = 1
                          AND id_peralatan IS NOT NULL
                        GROUP BY id_peralatan
                    ) AS lm2 ON lm2.id_peralatan = p2.id
                    LEFT JOIN {$monitoringTable} AS mp2 ON mp2.id = lm2.latest_id
                    WHERE p2.is_active = 1
                      AND p2.nama_alat = p.nama_alat
                      AND p2.jenis_peralatan <=> p.jenis_peralatan
                      AND p2.nama_kategori_pengujian <=> p.nama_kategori_pengujian
                      AND p2.warna <=> p.warna
                      AND p2.lokasi_penempatan_alat <=> p.lokasi_penempatan_alat
                      AND mp2.status_alat = ?
                ) AS jumlah_alat_tersedia",
                ['Tersedia']
            )
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS p3
                    LEFT JOIN (
                        SELECT id_peralatan, MAX(id) AS latest_id
                        FROM {$monitoringTable}
                        WHERE is_active = 1
                          AND id_peralatan IS NOT NULL
                        GROUP BY id_peralatan
                    ) AS lm3 ON lm3.id_peralatan = p3.id
                    LEFT JOIN {$monitoringTable} AS mp3 ON mp3.id = lm3.latest_id
                    WHERE p3.is_active = 1
                      AND p3.nama_alat = p.nama_alat
                      AND p3.jenis_peralatan <=> p.jenis_peralatan
                      AND p3.nama_kategori_pengujian <=> p.nama_kategori_pengujian
                      AND p3.warna <=> p.warna
                      AND p3.lokasi_penempatan_alat <=> p.lokasi_penempatan_alat
                      AND (mp3.status_alat IS NULL OR mp3.status_alat <> ?)
                ) AS jumlah_alat_belum_tersedia",
                ['Tersedia']
            )
            ->orderBy('p.nama_alat')
            ->orderBy('p.jenis_peralatan')
            ->orderBy('p.nama_kategori_pengujian')
            ->orderBy('p.warna')
            ->orderBy('p.lokasi_penempatan_alat')
            ->orderBy('p.kode_aset')
            ->orderBy('p.id');

        $datatable = Datatables::of($data);
        $searchableColumns = [
            'nama_alat' => 'p.nama_alat',
            'jenis_peralatan' => 'p.jenis_peralatan',
            'nama_kategori_pengujian' => 'p.nama_kategori_pengujian',
            'warna' => 'p.warna',
            'lokasi_penempatan_alat' => 'p.lokasi_penempatan_alat',
            'kode_aset' => 'p.kode_aset',
            'status_alat' => 'mp.status_alat',
        ];
        foreach ($searchableColumns as $column => $sqlColumn) {
            $datatable->filterColumn($column, function ($query, $keyword) use ($sqlColumn) {
                $query->where($sqlColumn, 'like', '%' . $keyword . '%');
            });
        }

        return $datatable->make(true);
    }

    public function getNames()
    {
        $data = [];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Nama peralatan sampling berhasil diambil',
        ]);
    }

    public function getKategori()
    {
        $fromMaster = MasterKategori::query()
            ->where('is_active', true)
            ->whereNotNull('nama_kategori')
            ->whereRaw("TRIM(nama_kategori) <> ''")
            ->pluck('nama_kategori');

        $fromSampling = Peralatan::query()
            ->where('is_active', true)
            ->whereNotNull('nama_kategori_pengujian')
            ->whereRaw("TRIM(nama_kategori_pengujian) <> ''")
            ->pluck('nama_kategori_pengujian');

        $data = $fromMaster
            ->merge($fromSampling)
            ->map(function ($name) {
                return trim((string) $name);
            })
            ->filter()
            ->unique(function ($name) {
                return mb_strtolower($name);
            })
            ->sort(function ($left, $right) {
                return strcasecmp($left, $right);
            })
            ->values()
            ->map(function ($name) {
                return ['nama_kategori' => $name];
            });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Kategori pengujian berhasil diambil',
        ]);
    }

    public function getJenis()
    {
        $data = Peralatan::query()
            ->where('is_active', true)
            ->whereNotNull('jenis_peralatan')
            ->whereRaw("TRIM(jenis_peralatan) <> ''")
            ->selectRaw('TRIM(jenis_peralatan) as jenis_peralatan')
            ->distinct()
            ->orderBy('jenis_peralatan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Jenis peralatan berhasil diambil',
        ]);
    }

    public function getCabang()
    {
        $data = MasterCabang::query()
            ->where('is_active', true)
            ->whereNotNull('nama_cabang')
            ->whereRaw("TRIM(nama_cabang) <> ''")
            ->orderBy('nama_cabang')
            ->get(['id', 'nama_cabang']);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Lokasi penempatan alat berhasil diambil',
        ]);
    }

    public function store(Request $request)
    {
        $namaAlat = trim((string) $request->input('nama_alat', ''));
        $jenisPeralatan = trim((string) $request->input('jenis_peralatan', ''));
        $namaKategori = trim((string) $request->input('nama_kategori_pengujian', ''));
        $rawDetails = $request->input('details', []);
        if (!is_array($rawDetails)) {
            $rawDetails = [];
        }

        $isUpdate = $request->filled('id');
        $details = [];
        $kodeSeen = [];

        $kodeIsExists = Peralatan::query()
            ->where('is_active', true)
            ->whereIn('kode_aset', array_column($rawDetails, 'kode_aset'))
            ->pluck('kode_aset')
            ->toArray();

        if (!empty($kodeIsExists)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode aset ' . implode(', ', $kodeIsExists) . ' sudah terdaftar',
            ], 422);
        }

        foreach ($rawDetails as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kodeAset = trim((string) ($item['kode_aset'] ?? $item['kode_alat'] ?? ''));
            $statusAlat = trim((string) ($item['status_alat'] ?? ''));
            if ($kodeAset === '') {
                continue;
            }
            if (!$isUpdate && $statusAlat === '') {
                continue;
            }

            $kodeKey = mb_strtolower($kodeAset);
            if (isset($kodeSeen[$kodeKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode aset "' . $kodeAset . '" duplikat di form',
                ], 422);
            }
            $kodeSeen[$kodeKey] = true;

            $details[] = [
                'kode_aset' => $kodeAset,
                'status_alat' => $statusAlat === '' ? null : $statusAlat,
                'keterangan' => trim((string) ($item['keterangan'] ?? '')),
                'start_date' => $this->normalizeDate($item['start_date'] ?? null),
                'end_date' => $this->normalizeDate($item['end_date'] ?? null),
            ];
        }

        $rules = [
            'nama_alat' => 'required|string|max:255',
            'jenis_peralatan' => 'required|string|max:255',
            'warna' => 'required|in:Merah,Biru,Hitam',
            'nama_kategori_pengujian' => 'required|string|max:70',
            'id_cabang' => 'required|integer',
            'details' => 'required|array|min:1',
            'details.*.kode_aset' => 'required|string|max:255',
            'details.*.keterangan' => 'nullable|string',
            'details.*.start_date' => 'nullable|date',
            'details.*.end_date' => 'nullable|date',
        ];
        if (!$isUpdate) {
            $rules['details.*.status_alat'] = 'required|in:' . implode(',', MonitoringPeralatan::STATUS_ALAT);
        } else {
            $rules['details.*.status_alat'] = 'nullable|in:' . implode(',', MonitoringPeralatan::STATUS_ALAT);
        }

        $validator = Validator::make(
            array_merge($request->all(), [
                'nama_alat' => $namaAlat,
                'jenis_peralatan' => $jenisPeralatan,
                'nama_kategori_pengujian' => $namaKategori,
                'details' => $details,
            ]),
            $rules
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($namaAlat === '' || $jenisPeralatan === '' || $namaKategori === '' || empty($details)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama alat, jenis peralatan, kategori pengujian, dan minimal satu detail kode aset wajib diisi',
            ], 422);
        }

        if (!$isUpdate) {
            foreach ($details as $index => $detail) {
                $needsPeriod = MonitoringPeralatan::requiresPeriod($detail['status_alat'] ?? null);
                if ($needsPeriod && (empty($detail['start_date']) || empty($detail['end_date']))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tanggal mulai dan berakhir wajib diisi untuk status maintenance/kalibrasi (detail ke-' . ($index + 1) . ')',
                    ], 422);
                }
                if (!$needsPeriod) {
                    $details[$index]['start_date'] = null;
                    $details[$index]['end_date'] = null;
                }
            }
        }

        $cabang = MasterCabang::query()
            ->where('is_active', true)
            ->where('id', $request->id_cabang)
            ->first(['id', 'nama_cabang']);

        if (!$cabang) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi penempatan alat tidak ditemukan',
            ], 404);
        }

        $kodeList = array_column($details, 'kode_aset');
        $duplicateQuery = Peralatan::query()
            ->where('is_active', true)
            ->whereIn('kode_aset', $kodeList)
            ->when($request->filled('id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->id);
            });

        $duplicateKode = $duplicateQuery->pluck('kode_aset')->all();
        if (!empty($duplicateKode)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode aset ' . implode(', ', $duplicateKode) . ' sudah terdaftar',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $savedCount = 0;

            if ($isUpdate) {
                $row = Peralatan::query()
                    ->where('is_active', true)
                    ->find($request->id);

                if (!$row) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Data peralatan tidak ditemukan',
                    ], 404);
                }

                $firstDetail = $details[0];
                $this->fillPeralatanMaster($row, $namaAlat, $jenisPeralatan, $namaKategori, $cabang, $request->warna, $firstDetail['kode_aset']);
                $row->updated_by = $this->karyawan;
                $row->updated_at = $now;
                $row->save();
                $savedCount = 1;

                $message = 'Data peralatan berhasil diubah';
            } else {
                foreach ($details as $detail) {
                    $row = new Peralatan();
                    $this->fillPeralatanMaster($row, $namaAlat, $jenisPeralatan, $namaKategori, $cabang, $request->warna, $detail['kode_aset']);
                    $row->created_by = $this->karyawan;
                    $row->created_at = $now;
                    $row->is_active = true;
                    $row->save();
                    $this->createMonitoringHistory($row->id, $detail, $now);
                    $savedCount++;
                }

                $message = 'Data peralatan berhasil disimpan (' . $savedCount . ' kode aset)';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'saved_count' => $savedCount,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $row = Peralatan::query()
            ->where('is_active', true)
            ->find($request->id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data peralatan tidak ditemukan',
            ], 404);
        }

        $now = date('Y-m-d H:i:s');
        $row->is_active = false;
        $row->deleted_at = $now;
        $row->deleted_by = $this->karyawan;
        $row->save();

        if (Schema::hasTable('monitoring_peralatan')) {
            MonitoringPeralatan::query()
                ->where('id_peralatan', $row->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deleted_at' => $now,
                    'deleted_by' => $this->karyawan,
                    'updated_at' => $now,
                    'updated_by' => $this->karyawan,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data peralatan berhasil dihapus',
        ]);
    }

    private function fillPeralatanMaster(
        Peralatan $row,
        string $namaAlat,
        string $jenisPeralatan,
        string $namaKategori,
        MasterCabang $cabang,
        $warna,
        string $kodeAset
    ): void {
        $row->nama_alat = $namaAlat;
        $row->kode_aset = $kodeAset;
        $row->jenis_peralatan = $jenisPeralatan;
        $row->warna = $warna;
        $row->nama_kategori_pengujian = $namaKategori;
        $row->id_cabang = $cabang->id;
        $row->lokasi_penempatan_alat = $cabang->nama_cabang;
    }

    private function createMonitoringHistory(int $idPeralatan, array $detail, string $now): void
    {
        $history = new MonitoringPeralatan();
        $history->id_peralatan = $idPeralatan;
        $history->status_alat = $detail['status_alat'];
        $history->keterangan = $detail['keterangan'] !== '' ? $detail['keterangan'] : null;
        $history->start_date = $detail['start_date'];
        $history->end_date = $detail['end_date'];
        $history->is_active = true;
        $history->created_by = $this->karyawan;
        $history->created_at = $now;
        $history->save();
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 20000000000) {
                $timestamp = (int) floor($timestamp / 1000);
            }
            return date('Y-m-d', $timestamp);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    public function import(Request $request)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel wajib diunggah.',
            ], 422);
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format file harus .xlsx atau .xls.',
            ], 422);
        }
        if ($file->getSize() > 8 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'Ukuran file maksimal 8 MB.',
            ], 422);
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel tidak dapat dibaca.',
            ], 422);
        }

        $sheet = $this->resolveImportSheet($spreadsheet);
        $headerMap = $this->resolveImportHeaderMap($sheet);
        $missingHeaders = array_diff(
            ['nama_alat', 'jenis_peralatan', 'nama_kategori_pengujian', 'warna', 'lokasi', 'kode_aset', 'status_alat'],
            array_keys($headerMap)
        );
        if (!empty($missingHeaders)) {
            return response()->json([
                'success' => false,
                'message' => 'Header Excel tidak lengkap. Wajib ada Nama Alat, Jenis Peralatan, Kategori Pengujian, Warna, Lokasi Penempatan Alat, Kode Aset, dan Status Alat.',
            ], 422);
        }

        $parsed = $this->parseImportRows($sheet, $headerMap);
        if (empty($parsed['rows']) && empty($parsed['skipped'])) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak memiliki baris data.',
            ], 422);
        }

        $cabangByName = $this->importCabangMap();
        $existingByKode = $this->importExistingPeralatanMap();

        $ready = [];
        $skipped = $parsed['skipped'];
        foreach ($parsed['rows'] as $item) {
            $error = $this->validateImportRow($item, $cabangByName);
            if ($error !== null) {
                $skipped[] = [
                    'row' => $item['row'],
                    'kode_aset' => $item['kode_aset'],
                    'message' => $error,
                ];
                continue;
            }
            $ready[] = $item;
        }

        $created = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');

        DB::beginTransaction();
        try {
            foreach ($ready as $item) {
                $key = mb_strtolower($item['kode_aset']);
                $existing = $existingByKode[$key] ?? null;

                if ($existing) {
                    $this->applyImportedPeralatan($existing, $item, $now, false);
                    $existingByKode[$key] = $existing;
                    $updated++;
                    continue;
                }

                $row = new Peralatan();
                $this->applyImportedPeralatan($row, $item, $now, true);
                $existingByKode[$key] = $row;
                $created++;
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import gagal disimpan: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import selesai. ' . $created . ' data dibuat, ' . $updated . ' data diubah, ' . count($skipped) . ' baris dilewati.',
            'created' => $created,
            'updated' => $updated,
            'skipped_count' => count($skipped),
            'warnings' => $parsed['warnings'],
            'skipped' => $skipped,
        ]);
    }

    private function resolveImportSheet($spreadsheet): Worksheet
    {
        $named = $spreadsheet->getSheetByName('1');
        if ($named) {
            return $named;
        }

        foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
            if ($candidate->getSheetState() === Worksheet::SHEETSTATE_VISIBLE) {
                return $candidate;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    private function resolveImportHeaderMap(Worksheet $sheet): array
    {
        $aliases = [
            'nama alat' => 'nama_alat',
            'jenis peralatan' => 'jenis_peralatan',
            'kategori pengujian' => 'nama_kategori_pengujian',
            'warna sp' => 'warna',
            'warna' => 'warna',
            'lokasi penempatan alat' => 'lokasi',
            'kode aset' => 'kode_aset',
            'kode asset' => 'kode_aset',
            'status alat' => 'status_alat',
            'keterangan' => 'keterangan',
            'tanggal mulai' => 'start_date',
            'tanggal berakhir' => 'end_date',
        ];

        $map = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = $this->normalizeImportLabel($this->readImportCell($sheet->getCellByColumnAndRow($column, 1)));
            if ($label === '' || !isset($aliases[$label]) || isset($map[$aliases[$label]])) {
                continue;
            }
            $map[$aliases[$label]] = $column;
        }

        return $map;
    }

    private function parseImportRows(Worksheet $sheet, array $headerMap): array
    {
        $rows = [];
        $skipped = [];
        $warnings = [];
        $rowOfKode = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $read = function ($field) use ($sheet, $headerMap, $rowNumber) {
                if (!isset($headerMap[$field])) {
                    return '';
                }
                return $this->readImportCell($sheet->getCellByColumnAndRow($headerMap[$field], $rowNumber));
            };

            $kodeAset = $read('kode_aset');
            $namaAlat = $read('nama_alat');
            $jenis = $read('jenis_peralatan');
            $kategori = $read('nama_kategori_pengujian');
            $warna = $read('warna');
            $lokasi = $read('lokasi');
            $status = $read('status_alat');
            $keterangan = $read('keterangan');

            if ($kodeAset === '' && $namaAlat === '' && $jenis === '' && $kategori === '' && $lokasi === '' && $status === '') {
                continue;
            }

            if ($kodeAset === '') {
                $skipped[] = [
                    'row' => $rowNumber,
                    'kode_aset' => '',
                    'message' => 'Kode aset kosong.',
                ];
                continue;
            }

            $key = mb_strtolower($kodeAset);
            if (isset($rowOfKode[$key])) {
                $warnings[] = [
                    'row' => $rowOfKode[$key],
                    'kode_aset' => $kodeAset,
                    'message' => 'Kode aset yang sama muncul lagi di baris ' . $rowNumber . '. Baris terakhir yang dipakai.',
                ];
            }
            $rowOfKode[$key] = $rowNumber;

            $rows[$key] = [
                'row' => $rowNumber,
                'kode_aset' => $kodeAset,
                'nama_alat' => $namaAlat,
                'jenis_peralatan' => $this->canonicalJenisPeralatan($jenis),
                'nama_kategori_pengujian' => $kategori,
                'warna' => $this->canonicalWarna($warna),
                'lokasi' => $lokasi,
                'status_alat' => $this->canonicalStatusAlat($status),
                'keterangan' => $keterangan,
                'start_date' => $this->normalizeDate($read('start_date')),
                'end_date' => $this->normalizeDate($read('end_date')),
            ];
        }

        return [
            'rows' => array_values($rows),
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    private function validateImportRow(array $item, array $cabangByName): ?string
    {
        if ($item['nama_alat'] === '') {
            return 'Nama alat wajib diisi.';
        }
        if (mb_strlen($item['nama_alat']) > 255) {
            return 'Nama alat melebihi 255 karakter.';
        }
        if ($item['jenis_peralatan'] === '') {
            return 'Jenis peralatan wajib diisi.';
        }
        if (mb_strlen($item['jenis_peralatan']) > 255) {
            return 'Jenis peralatan melebihi 255 karakter.';
        }
        if ($item['nama_kategori_pengujian'] === '') {
            return 'Kategori pengujian wajib diisi.';
        }
        if (mb_strlen($item['nama_kategori_pengujian']) > 70) {
            return 'Kategori pengujian melebihi 70 karakter.';
        }
        if (!in_array($item['warna'], ['Merah', 'Biru', 'Hitam'], true)) {
            return 'Warna harus Merah, Biru, atau Hitam.';
        }

        $lokasiKey = $this->normalizeImportLabel($item['lokasi']);
        if ($lokasiKey === '' || !isset($cabangByName[$lokasiKey])) {
            return 'Lokasi penempatan alat tidak ditemukan di master cabang.';
        }
        if ($item['status_alat'] === '') {
            return 'Status alat wajib diisi.';
        }
        if (!in_array($item['status_alat'], MonitoringPeralatan::STATUS_ALAT, true)) {
            return 'Status alat tidak dikenali.';
        }
        if (MonitoringPeralatan::requiresPeriod($item['status_alat']) && (empty($item['start_date']) || empty($item['end_date']))) {
            return 'Status ' . $item['status_alat'] . ' wajib punya tanggal mulai dan tanggal berakhir.';
        }

        return null;
    }

    private function applyImportedPeralatan(Peralatan $row, array $item, string $now, bool $isNew): void
    {
        $cabang = $this->importCabangMap()[$this->normalizeImportLabel($item['lokasi'])];
        $kategoriByName = $this->importKategoriMap();
        $kategoriKey = $this->normalizeImportLabel($item['nama_kategori_pengujian']);
        $kategori = $kategoriByName[$kategoriKey] ?? $item['nama_kategori_pengujian'];

        $this->fillPeralatanMaster(
            $row,
            $item['nama_alat'],
            $item['jenis_peralatan'],
            $kategori,
            $cabang,
            $item['warna'],
            $item['kode_aset']
        );

        if ($isNew) {
            $row->is_active = true;
            $row->created_by = $this->karyawan;
            $row->created_at = $now;
            $row->save();
            $this->createMonitoringHistory($row->id, [
                'status_alat' => $item['status_alat'],
                'keterangan' => $item['keterangan'],
                'start_date' => MonitoringPeralatan::requiresPeriod($item['status_alat']) ? $item['start_date'] : null,
                'end_date' => MonitoringPeralatan::requiresPeriod($item['status_alat']) ? $item['end_date'] : null,
            ], $now);
            return;
        }

        $row->is_active = true;
        $row->deleted_at = null;
        $row->deleted_by = null;
        $row->updated_by = $this->karyawan;
        $row->updated_at = $now;
        $row->save();

        if ($this->importedMonitoringChanged($row->id, $item)) {
            $this->createMonitoringHistory($row->id, [
                'status_alat' => $item['status_alat'],
                'keterangan' => $item['keterangan'],
                'start_date' => MonitoringPeralatan::requiresPeriod($item['status_alat']) ? $item['start_date'] : null,
                'end_date' => MonitoringPeralatan::requiresPeriod($item['status_alat']) ? $item['end_date'] : null,
            ], $now);
        }
    }

    private function importedMonitoringChanged(int $peralatanId, array $item): bool
    {
        $latest = MonitoringPeralatan::query()
            ->where('id_peralatan', $peralatanId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return true;
        }

        $currentStatus = trim((string) $latest->status_alat);
        $currentNote = trim((string) $latest->keterangan);
        if ($currentStatus !== $item['status_alat'] || $currentNote !== $item['keterangan']) {
            return true;
        }

        if (!MonitoringPeralatan::requiresPeriod($item['status_alat'])) {
            return false;
        }

        $currentStart = $this->formatMonitoringDate($latest->start_date);
        $currentEnd = $this->formatMonitoringDate($latest->end_date);

        return $currentStart !== $item['start_date'] || $currentEnd !== $item['end_date'];
    }

    private function formatMonitoringDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->normalizeDate($value);
    }

    private function importCabangMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $rows = MasterCabang::query()
            ->where('is_active', true)
            ->whereNotNull('nama_cabang')
            ->get(['id', 'nama_cabang']);

        foreach ($rows as $row) {
            $key = $this->normalizeImportLabel((string) $row->nama_cabang);
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    private function importKategoriMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $names = MasterKategori::query()
            ->where('is_active', true)
            ->whereNotNull('nama_kategori')
            ->pluck('nama_kategori');

        foreach ($names as $name) {
            $text = trim((string) $name);
            $key = $this->normalizeImportLabel($text);
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $text;
            }
        }

        return $map;
    }

    private function importExistingPeralatanMap(): array
    {
        $map = [];
        $rows = Peralatan::query()
            ->whereNotNull('kode_aset')
            ->where('kode_aset', '!=', '')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) $row->kode_aset));
            if ($key === '') {
                continue;
            }
            if (!isset($map[$key])) {
                $map[$key] = $row;
                continue;
            }
            $current = $map[$key];
            $rowActive = (bool) $row->is_active;
            $currentActive = (bool) $current->is_active;
            if (($rowActive && !$currentActive) || ($rowActive === $currentActive && $row->id > $current->id)) {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    private function readImportCell(Cell $cell): string
    {
        $raw = $cell->getValue();
        $text = '';

        if (is_string($raw) && isset($raw[0]) && $raw[0] === '=') {
            $cached = $cell->getOldCalculatedValue();
            if ($cached !== null && $cached !== '') {
                $text = (string) $cached;
            }
        } elseif ($raw !== null) {
            $text = (string) $raw;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || preg_match('/^#(NAME\?|REF!|N\/A|VALUE!|DIV\/0!)$/i', $text)) {
            return '';
        }

        return $text;
    }

    private function normalizeImportLabel(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));

        return $value;
    }

    private function canonicalJenisPeralatan(string $value): string
    {
        $key = $this->normalizeImportLabel($value);
        $map = [
            'alat pengambilan sampel' => 'Alat Pengambilan Sampel',
            'alat pengukuran' => 'Alat Pengukuran',
            'alat pendukung' => 'Alat Pendukung',
            'alat pelindung diri' => 'Alat Pelindung Diri',
        ];

        return $map[$key] ?? trim($value);
    }

    private function canonicalWarna(string $value): string
    {
        $key = $this->normalizeImportLabel($value);
        $map = [
            'merah' => 'Merah',
            'biru' => 'Biru',
            'hitam' => 'Hitam',
        ];

        return $map[$key] ?? trim($value);
    }

    private function canonicalStatusAlat(string $value): string
    {
        $key = $this->normalizeImportLabel($value);
        foreach (MonitoringPeralatan::STATUS_ALAT as $status) {
            if ($this->normalizeImportLabel($status) === $key) {
                return $status;
            }
        }

        return trim($value);
    }
}
