<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\DateColumnFilterService;
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class RekapQuotationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tableName = $request->mode === 'kontrak'
                ? 'request_quotation_kontrak_H'
                : 'request_quotation';

            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            }

            // Scope index = hierarki atasan_langsung (sama dengan export).
            // Staff: diri sendiri | SPV: bawahan di bawahnya | Manager/SM: tree bawahan penuh.
            $scopedSalesIds = $this->resolveScopedSalesIdsForCurrentUser();
            if ($scopedSalesIds !== null) {
                $data->whereIn($tableName . '.sales_id', $scopedSalesIds);
            }

            $data->select($tableName . '.*');

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->filterColumn('deleted_at', function ($query, $keyword) use ($tableName) {
                    DateColumnFilterService::apply($query, $tableName . '.deleted_at', $keyword);
                })
                ->filterColumn('deleted_by', function ($query, $keyword) use ($tableName) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->where($tableName . '.deleted_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('updated_by', function ($query, $keyword) use ($tableName) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->where($tableName . '.updated_by', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('status_quotation', function ($query, $keyword) use ($tableName) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->where($tableName . '.status_quotation', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('sales.nama_lengkap', function ($query, $keyword) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->whereHas('sales', function ($sales) use ($keyword) {
                        $sales->where('nama_lengkap', 'like', '%' . $keyword . '%');
                    });
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }

    /**
     * Cek apakah user boleh export Recap QT.
     * Hanya MANAGER / SENIOR MANAGER departemen Sales.
     */
    public function canExportExcel(Request $request)
    {
        try {
            $access = $this->resolveRecapQtExportAccess();

            return response()->json([
                'can_export' => $access['allowed'],
                'grade' => $access['grade'],
                'message' => $access['message'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'can_export' => false,
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Export Excel mengikuti kolom index Recap QT (tanpa Action).
     * Akses: MANAGER / SENIOR MANAGER Sales saja.
     * Data: sama scope index — sales_id di tree atasan_langsung user (bukan semua sales).
     */
    public function exportExcel(Request $request)
    {
        try {
            $access = $this->resolveRecapQtExportAccess();
            if (!$access['allowed']) {
                return response()->json(['message' => $access['message']], 403);
            }

            $tableName = $request->mode === 'kontrak'
                ? 'request_quotation_kontrak_H'
                : 'request_quotation';

            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            } else {
                return response()->json(['message' => 'Mode tidak valid'], 422);
            }

            $salesIds = $access['sales_ids'];
            if (empty($salesIds)) {
                return response()->json(['message' => 'Tidak ada data bawahan yang dapat diekspor.'], 403);
            }
            $data->whereIn($tableName . '.sales_id', $salesIds);

            $rows = $data->select($tableName . '.*')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Recap QT');

            $sheet->setCellValue('A1', 'REPORT RECAP QUOTATION');
            $sheet->mergeCells('A1:V1');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $sheet->setCellValue('A2', $request->year . ' | ' . strtoupper(str_replace('_', ' ', (string) $request->mode)));
            $sheet->mergeCells('A2:V2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Kolom mengikuti index FE, tanpa Action
            $startRow = 4;
            $headers = [
                'No',
                'Status Quotation',
                'Kode Promo',
                'ID Pelanggan',
                'Nomor Quotation',
                'Nama Perusahaan',
                'Konsultan',
                'Status',
                'Status Sampling',
                'Void By',
                'Void At',
                'No Tlp Perusahaan',
                'Keterangan',
                'Nama Pic Order',
                'No Tlp Pic',
                'Total Price',
                'Total Discount',
                'Nilai Ppn',
                'Nilai Pph',
                'Nilai Tagihan',
                'Nama Sales',
                'Nama Administrasi',
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $startRow, $header);
                $col++;
            }

            $lastCol = 'V'; // 22 kolom (A-V)
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];
            $sheet->getStyle('A' . $startRow . ':' . $lastCol . $startRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($startRow)->setRowHeight(25);

            $rowNum = $startRow + 1;
            $no = 1;
            foreach ($rows as $row) {
                $voidAt = $row->deleted_at
                    ? Carbon::parse($row->deleted_at)->locale('id')->translatedFormat('d F Y H:i')
                    : '-';

                $sheet->setCellValue('A' . $rowNum, $no++);
                $sheet->setCellValue('B' . $rowNum, $row->status_quotation ?? '-');
                $sheet->setCellValue('C' . $rowNum, $row->kode_promo ?? '-');
                $sheet->setCellValue('D' . $rowNum, $row->pelanggan_ID ?? '-');
                $sheet->setCellValue('E' . $rowNum, $row->no_document ?? '-');
                $sheet->setCellValue('F' . $rowNum, $row->nama_perusahaan ?? '-');
                $sheet->setCellValue('G' . $rowNum, $row->konsultan ?? '-');
                $sheet->setCellValue('H' . $rowNum, $row->flag_status ?? '-');
                $sheet->setCellValue('I' . $rowNum, $row->status_sampling ?? '-');
                $sheet->setCellValue('J' . $rowNum, $row->deleted_by ?? '-');
                $sheet->setCellValue('K' . $rowNum, $voidAt);
                $sheet->setCellValueExplicit('L' . $rowNum, (string) ($row->no_tlp_perusahaan ?? '-'), DataType::TYPE_STRING);
                $sheet->setCellValue('M' . $rowNum, $row->keterangan ?? '-');
                $sheet->setCellValue('N' . $rowNum, $row->nama_pic_order ?? '-');
                $sheet->setCellValueExplicit('O' . $rowNum, (string) ($row->no_pic_order ?? '-'), DataType::TYPE_STRING);
                $sheet->setCellValue('P' . $rowNum, (float) ($row->grand_total ?? 0));
                $sheet->setCellValue('Q' . $rowNum, (float) ($row->total_discount ?? 0));
                $sheet->setCellValue('R' . $rowNum, (float) ($row->total_ppn ?? 0));
                $sheet->setCellValue('S' . $rowNum, (float) ($row->total_pph ?? 0));
                $sheet->setCellValue('T' . $rowNum, (float) ($row->piutang ?? 0));
                $sheet->setCellValue('U' . $rowNum, optional($row->sales)->nama_lengkap ?? '-');
                $sheet->setCellValue('V' . $rowNum, $row->updated_by ?? '-');

                $rowNum++;
            }

            if ($rowNum > $startRow + 1) {
                $sheet->getStyle('P' . ($startRow + 1) . ':T' . ($rowNum - 1))
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                $sheet->getStyle('A' . ($startRow + 1) . ':' . $lastCol . ($rowNum - 1))->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);
            }

            foreach (range('A', $lastCol) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Report_Recap_Qt_' . $request->mode . '_' . $request->year . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'recap_qt_');
            $writer->save($tempFile);

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * @return array{allowed:bool,grade:string,message:string,sales_ids:int[]}
     */
    private function resolveRecapQtExportAccess(): array
    {
        $user = request()->attributes->get('user');
        $karyawan = optional($user)->karyawan;
        if (!$karyawan) {
            return [
                'allowed' => false,
                'grade' => '',
                'message' => 'Data karyawan tidak ditemukan.',
                'sales_ids' => [],
            ];
        }

        $karyawan->loadMissing('divisi');
        $grade = strtoupper(trim(str_replace('_', ' ', (string) ($karyawan->grade ?? $this->grade ?? ''))));

        if (!in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            return [
                'allowed' => false,
                'grade' => $grade,
                'message' => 'Export Recap QT hanya untuk Manager / Senior Manager Sales.',
                'sales_ids' => [],
            ];
        }

        if (!$this->isSalesDepartmentKaryawan($karyawan)) {
            return [
                'allowed' => false,
                'grade' => $grade,
                'message' => 'Export Recap QT hanya untuk karyawan departemen Sales.',
                'sales_ids' => [],
            ];
        }

        // Scope sama dengan index: hanya diri + bawahan via atasan_langsung (bukan semua sales).
        $salesIds = $this->resolveScopedSalesIdsForCurrentUser();
        if ($salesIds === null) {
            $salesIds = $this->collectDescendantSalesIds((int) $karyawan->id);
        }

        if (empty($salesIds)) {
            return [
                'allowed' => false,
                'grade' => $grade,
                'message' => 'Tidak ada bawahan Sales yang dapat diekspor.',
                'sales_ids' => [],
            ];
        }

        return [
            'allowed' => true,
            'grade' => $grade,
            'message' => 'OK',
            'sales_ids' => $salesIds,
        ];
    }

    /**
     * Scope sales_id index/export via master_karyawan.atasan_langsung (BFS).
     * null = non-sales (admin/IT) lihat semua.
     * Sales: diri + seluruh descendant — Staff tanpa bawahan = diri sendiri;
     * SPV = timnya; Manager/SM = SPV + staff di bawah tree-nya.
     *
     * @return int[]|null
     */
    private function resolveScopedSalesIdsForCurrentUser(): ?array
    {
        $user = request()->attributes->get('user');
        $karyawan = optional($user)->karyawan;
        if (!$karyawan) {
            return [(int) $this->user_id];
        }

        $karyawan->loadMissing('divisi');

        if (!$this->isSalesDepartmentKaryawan($karyawan)) {
            return null;
        }

        return $this->collectDescendantSalesIds((int) $karyawan->id);
    }

    private function isSalesDepartmentKaryawan(MasterKaryawan $karyawan): bool
    {
        $divisiName = strtoupper(trim((string) optional($karyawan->divisi)->nama_divisi));
        if ($divisiName !== '' && $this->containsSalesDeptKeyword($divisiName)) {
            return true;
        }

        // Fallback kolom string departemen di master_karyawan (jika terisi).
        $departemen = strtoupper(trim((string) ($karyawan->departemen ?? '')));
        if ($departemen !== '' && $this->containsSalesDeptKeyword($departemen)) {
            return true;
        }

        return false;
    }

    private function containsSalesDeptKeyword(string $value): bool
    {
        return str_contains($value, 'SALES')
            || str_contains($value, 'PENJUALAN')
            || str_contains($value, 'MARKETING SALES');
    }

    /**
     * BFS: user + semua descendant aktif via master_karyawan.atasan_langsung.
     * Manager → SPV → Staff ter-cover selama relasi atasan_langsung benar.
     *
     * @return int[]
     */
    private function collectDescendantSalesIds(int $rootId): array
    {
        $result = [$rootId];
        $queue = [$rootId];
        $seen = [$rootId => true];

        while ($queue) {
            $current = array_shift($queue);
            // JSON bisa string ("123") atau number (123) — cek keduanya.
            $children = MasterKaryawan::where('is_active', true)
                ->where(function ($q) use ($current) {
                    $q->whereJsonContains('atasan_langsung', (string) $current)
                        ->orWhereJsonContains('atasan_langsung', $current);
                })
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                $childId = (int) $childId;
                if (isset($seen[$childId])) {
                    continue;
                }
                $seen[$childId] = true;
                $result[] = $childId;
                $queue[] = $childId;
            }
        }

        return $result;
    }
}
