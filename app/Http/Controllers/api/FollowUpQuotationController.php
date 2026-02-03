<?php

namespace App\Http\Controllers\api;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\MasterPelanggan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\HargaParameter;
use App\Models\Ftc;
use App\Models\FtcT;
use App\Models\ParameterAnalisa;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\SamplingPlanServices;
use App\Services\GetBawahan;
use App\Models\SampelDiantar;
use App\Services\{Notification, GetAtasan};
use App\Helpers\WorkerOperation;
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;
use App\Jobs\RenderSamplingPlan;
use App\Models\AlasanVoidQt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Datatables;
use Exception;

use App\Services\OrderChangeNotifier;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType; // PENTING BUAT NO TELEPON

class FollowUpQuotationController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::select('request_quotation.*', 'master_karyawan.nama_lengkap as sales_name')
                    ->with([
                        'sales',
                        'sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        }
                    ])
                    ->leftJoin('master_karyawan', 'request_quotation.sales_id', '=', 'master_karyawan.id')
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->whereIn('flag_status', ['emailed', 'sp'])
                    ->where('is_generate_data_lab', 1)
                    ->where('request_quotation.is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc')
                    ->orderBy('id', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::select('request_quotation_kontrak_H.*', 'master_karyawan.nama_lengkap as sales_name')
                    ->with([
                        'sales',
                        'detail',
                        'sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        }
                    ])
                    ->leftJoin('master_karyawan', 'request_quotation_kontrak_H.sales_id', '=', 'master_karyawan.id')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->whereIn('flag_status', ['emailed', 'sp'])
                    ->where('is_generate_data_lab', 1)
                    ->where('request_quotation_kontrak_H.is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc')
                    ->orderBy('id', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                    $data->where('sales_id', $this->user_id);
                    break;
                case 21: // Sales Supervisor
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('sales_id', $bawahan);
                    break;
            }

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->filterColumn('data_lama', function ($query, $keyword) {
                    if (Str::contains($keyword, 'QS U')) {
                        $query->whereNotNull('data_lama')
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) IS NOT NULL")
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) != 'null'");
                    }
                })
                ->filterColumn('created_at', function ($query, $keyword) use ($request) {
                    if ($request->mode == 'non_kontrak') {
                        $query->where('request_quotation.created_at', 'like', '%' . $keyword . '%');
                    } else if ($request->mode == 'kontrak') {
                        $query->where('request_quotation_kontrak_H.created_at', 'like', '%' . $keyword . '%');
                    }
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::select('request_quotation.*', 'master_karyawan.nama_lengkap as sales_name')
                    ->with([
                        'sales',
                        'sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        }
                    ])
                    ->leftJoin('master_karyawan', 'request_quotation.sales_id', '=', 'master_karyawan.id')
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->whereIn('flag_status', ['emailed', 'sp'])
                    ->where('is_generate_data_lab', 1)
                    ->where('request_quotation.is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc')
                    ->orderBy('id', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::select('request_quotation_kontrak_H.*', 'master_karyawan.nama_lengkap as sales_name')
                    ->with([
                        'sales',
                        'detail',
                        'sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        }
                    ])
                    ->leftJoin('master_karyawan', 'request_quotation_kontrak_H.sales_id', '=', 'master_karyawan.id')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->whereIn('flag_status', ['emailed', 'sp'])
                    ->where('is_generate_data_lab', 1)
                    ->where('request_quotation_kontrak_H.is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc')
                    ->orderBy('id', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                    $data->where('sales_id', $this->user_id);
                    break;
                case 21: // Sales Supervisor
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('sales_id', $bawahan);
                    break;
            }

            $data = $data->get();
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // --- JUDUL ---
            $sheet->setCellValue('A1', 'REPORT FOLLOW UP QUOTATION');
            $sheet->mergeCells('A1:N1'); // Merge sampe N
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // --- SUB JUDUL ---
            $sheet->setCellValue('A2', $request->year . ' | ' . strtoupper(str_replace('_', ' ', $request->mode)));
            $sheet->mergeCells('A2:N2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // --- HEADER TABEL (URUTAN SESUAI UI JS) ---
            $startRow = 4;
            $headers = [
                'No', 
                'Kode Promo', 
                'No Quotation', 
                'ID Pelanggan', 
                'Nama Perusahaan', 
                'Konsultan', 
                'No Tlp Perusahaan', 
                'Status QS',      // Di UI ini nampilin "QS Ulang" dari data_lama
                'Ket Reject SP', 
                'Keterangan',
                'Total Price', 
                'Total Discount',
                'Sales', 
                'Created At'
            ];
            
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $startRow, $header);
                $col++;
            }

            // Style Header
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
            ];
            $sheet->getStyle('A' . $startRow . ':N' . $startRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($startRow)->setRowHeight(25);

            // --- ISI DATA ---
            $rowNum = $startRow + 1;
            $no = 1;

            foreach ($data as $row) {
                // Logic Status QS (QS Ulang) - Sesuai UI index 8
                $statusQS = '-';
                if ($row->data_lama) {
                    $parsed = json_decode($row->data_lama);
                    if ($parsed && isset($parsed->no_order)) {
                        $statusQS = 'QS Ulang';
                    }
                }

                // Format Tanggal Indo
                $createdAt = $row->created_at 
                    ? \Carbon\Carbon::parse($row->created_at)->locale('id')->translatedFormat('d F Y H:i') 
                    : '-';

                $sheet->setCellValue('A' . $rowNum, $no++);
                $sheet->setCellValue('B' . $rowNum, $row->kode_promo ?? '-');
                $sheet->setCellValue('C' . $rowNum, $row->no_document);
                $sheet->setCellValue('D' . $rowNum, $row->pelanggan_ID);
                $sheet->setCellValue('E' . $rowNum, $row->nama_perusahaan);
                $sheet->setCellValue('F' . $rowNum, $row->konsultan ?? '-');
                
                // No Tlp Perusahaan (String biar 0 aman)
                $sheet->setCellValueExplicit('G' . $rowNum, $row->no_tlp_perusahaan ?? '-', DataType::TYPE_STRING);
                
                $sheet->setCellValue('H' . $rowNum, $statusQS);
                $sheet->setCellValue('I' . $rowNum, $row->ket_reject_sp ?? '-');
                $sheet->setCellValue('J' . $rowNum, $row->keterangan ?? '-');
                
                // Angka
                $sheet->setCellValue('K' . $rowNum, $row->grand_total);
                $sheet->setCellValue('L' . $rowNum, $row->total_ppn);
                
                $sheet->setCellValue('M' . $rowNum, $row->sales_name ?? '-');
                $sheet->setCellValue('N' . $rowNum, $createdAt);

                // --- LOGIKA WARNA (Sesuai RowCallback UI) ---
                $rowColor = null;

                if ($row->flag_status === 'rejected') {
                    $rowColor = 'D6D8DB'; // Secondary
                } elseif ($row->flag_status === 'void') {
                    $rowColor = 'F5C6CB'; // Danger
                } elseif ($row->flag_status === 'sp') {
                    $rowColor = 'FFEEBA'; // Warning
                } elseif ($row->flag_status === 'emailed') {
                    $rowColor = 'C3E6CB'; // Success
                }

                if ($row->kode_promo !== null && $row->flag_status !== 'rejected' && $row->flag_status !== 'sp') {
                    $rowColor = 'BEE5EB'; 
                }

                if ($rowColor) {
                    $sheet->getStyle('A' . $rowNum . ':N' . $rowNum)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowColor]]
                    ]);
                }

                $rowNum++;
            }

            // --- FINISHING ---
            $lastRow = $rowNum - 1;
            $sheet->getStyle('A' . $startRow . ':N' . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
            ]);

            // Format Currency (Kolom K dan L)
            $sheet->getStyle('K'.($startRow+1).':L' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            
            // Alignment Center (No, Kode Promo, No Qt, ID, Status QS, Sales, Created At)
            $alignCenterCols = ['A', 'B', 'C', 'D', 'H', 'N'];
            foreach ($alignCenterCols as $col) {
                $sheet->getStyle($col.($startRow+1).':'.$col.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Auto Fit Columns
            foreach (range('A', 'N') as $col) {
                if ($col === 'J') { 
                    // KHUSUS KOLOM J (KETERANGAN)
                    // Matikan AutoSize biar gak bablas lebarnya
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    
                    // Set Lebar Manual (35 unit Excel itu kira-kira 200px+)
                    $sheet->getColumnDimension($col)->setWidth(35); 
                    
                    // Nyalain Wrap Text biar turun ke bawah kalau kepanjangan
                    $sheet->getStyle($col . ($startRow + 1) . ':' . $col . $lastRow)
                        ->getAlignment()->setWrapText(true);
                        
                } else {
                    // Kolom lain tetep AutoSize
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Report_FollowUp_Qt_' . date('YmdHis') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        /*if ($request->mode == 'non_kontrak') {
            if (!isset($request->id) || $request->id == '') {
                return response()->json(['message' => 'Cannot reject data.!', 'status' => '401'], 401);
            }
            $data = QuotationNonKontrak::where('is_active', true)
                ->where('id', $request->id)
                ->first();

            $type_doc = 'quotation';

            if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                $data->is_ready_order = 1;
            }
            DB::beginTransaction();
            try {
                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->keterangan_reject = $request->keterangan_reject;

                $data_lama = null;
                if ($data->data_lama != null)
                    $data_lama = json_decode($data->data_lama);

                if ($data_lama != null && $data_lama->no_order != null) {
                    $json = json_encode([
                        'id_qt' => $data->id,
                        'no_qt' => $data->no_document,
                        'no_order' => $data_lama->no_order,
                        'id_order' => $data_lama->id_order,
                        'status_sp' => (string) $request->perubahan_sp
                    ]);
                    $data->data_lama = $json;
                } else {
                    if ($data->flag_status == 'sp') {
                        $json = json_encode([
                            'id_qt' => $data->id,
                            'no_qt' => $data->no_document,
                            'no_order' => null,
                            'id_order' => null,
                            'status_sp' => (string) $request->perubahan_sp
                        ]);
                        $data->data_lama = $json;
                    }
                }

                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => "Request Quotation number $data->no_document success rejected.!"
                ], 200);
            } catch (Exception $e) {
                DB::rollback();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ], 401);
            }
        } else if ($request->mode == 'kontrak') {
            if (!isset($request->id) || $request->id == '') {
                return response()->json(['message' => 'Cannot reject data.!', 'status' => '401'], 401);
            }

            $data = QuotationKontrakH::where('is_active', true)
                ->where('id', $request->id)
                ->first();

            DB::beginTransaction();
            try {
                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->keterangan_reject = $request->keterangan_reject;

                $data_lama = null;
                if ($data->data_lama != null)
                    $data_lama = json_decode($data->data_lama);

                if ($data_lama != null && $data_lama->no_order != null) {
                    $json = json_encode([
                        'id_qt' => $data->id,
                        'no_qt' => $data->no_document,
                        'no_order' => $data_lama->no_order,
                        'id_order' => $data_lama->id_order,
                        'status_sp' => $request->perubahan_sp
                    ]);
                    $data->data_lama = $json;
                } else {
                    if ($data->flag_status == 'sp') {
                        $json = json_encode([
                            'id_qt' => $data->id,
                            'no_qt' => $data->no_document,
                            'no_order' => null,
                            'id_order' => null,
                            'status_sp' => $request->perubahan_sp
                        ]);
                        $data->data_lama = $json;
                    }
                }

                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => "Request Quotation number $data->no_document success rejected.!"
                ], 200);
            } catch (Exception $e) {
                DB::rollback();
                return response()->json([
                    'message' => $e->getMessage()
                ], 401);
            }
        }*/

        /*
            Update Muhammad Afryan Saputra
            2025-03-12
        */
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }

                $data_lama = null;
                if ($data->data_lama != null)
                    $data_lama = json_decode($data->data_lama);

                if ($data_lama != null && $data_lama->no_order != null) {
                    $json = json_encode([
                        'id_qt' => $data_lama->id_qt,
                        'no_qt' => $data_lama->no_qt,
                        'no_order' => $data_lama->no_order,
                        'id_order' => $data_lama->id_order,
                        'status_sp' => (string) $request->perubahan_sp
                    ]);
                    $data->data_lama = $json;
                } else {
                    if ($data->flag_status == 'sp') {
                        $json = json_encode([
                            'id_qt' => $data->id,
                            'no_qt' => $data->no_document,
                            'no_order' => null,
                            'id_order' => null,
                            'status_sp' => (string) $request->perubahan_sp
                        ]);
                        $data->data_lama = $json;
                    }
                }

                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->keterangan_reject = $request->keterangan_reject;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success rejected.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot rejected data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function voidQuotation(Request $request)
    {
        /*switch ($request->mode) {
            case 'kontrak':
                return self::voidRecapQuotationKontrak($request);
            case 'non_kontrak':
                return self::voidRecapQuotationNon($request);
            default:
                return response()->json([
                    'message' => 'Invalid mode'
                ], 400);
        }*/

        /*
            Update Muhammad Afryan Saputra
            2025-03-12
        */
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }
                $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);
                $jadwal = Jadwal::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);

                $data->flag_status = 'void';
                $data->is_active = false;
                $data->document_status = 'Non Aktif';
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $keterangan = [];
                if ($request->tanggal_next_fu) {
                    $keterangan[] = ['tanggal_next_fu' => $request->tanggal_next_fu];
                }
                if ($request->nama_lab_lain) {
                    $keterangan[] = ['nama_lab_lain' => $request->nama_lab_lain];
                }
                if ($request->budget_customer) {
                    $keterangan[] = ['budget_customer' => $request->budget_customer];
                }
                if ($request->penawaran_yg_akan_dikirim) {
                    $keterangan[] = ['penawaran_yg_akan_dikirim' => $request->penawaran_yg_akan_dikirim];
                }
                if ($request->blacklist) {
                    $keterangan[] = ['blacklist' => $request->blacklist];
                }
                if ($request->keterangan) {
                    $keterangan[] = ['keterangan' => $request->keterangan];
                }

                $alasanVoidQt = new AlasanVoidQt();
                $alasanVoidQt->no_quotation = $data->no_document;
                $alasanVoidQt->alasan = $request->alasan;
                $alasanVoidQt->keterangan = json_encode($keterangan);
                $alasanVoidQt->voided_by = $this->karyawan;
                $alasanVoidQt->voided_at = Carbon::now()->format('Y-m-d H:i:s');
                $alasanVoidQt->save();

                DB::commit();
                return response()->json([
                    'message' => 'Success void request Quotation number ' . $data->no_document . '.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot void data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    // UNUSED
    public function voidRecapQuotationKontrak(Request $request)
    {
        if (isset($request->id) || $request->id != '') {
            $data = QuotationKontrakH::where('is_active', true)
                ->where('id', $request->id)
                ->first();

            DB::beginTransaction();
            try {
                SamplingPlan::where('no_quotation', $data->no_document)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                Jadwal::where('no_quotation', $data->no_document)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $data->flag_status = 'void';
                $data->is_active = false;
                $data->document_status = 'Non Aktif';
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                DB::commit();
                return response()->json([
                    'message' => "Success void request Quotation number $data->no_document.!"
                ], 200);
            } catch (Exception $e) {
                DB::rollback();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ], 401);
            }
        } else {
            return response()
                ->json(['message' => 'Cannot void data.!', 'status' => '401'], 401);
        }
    }

    // UNUSED
    public function voidRecapQuotationNon(Request $request)
    {
        if (isset($request->id) || $request->id != '') {
            $data = QuotationNonKontrak::where('is_active', true)
                ->where('id', $request->id)
                ->first();
            $type_doc = 'quotation';
            if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                $data->is_ready_order = 1;
            }
            DB::beginTransaction();
            try {
                SamplingPlan::where('no_quotation', $data->no_document)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                Jadwal::where('no_quotation', $data->no_document)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $data->flag_status = 'void';
                $data->is_active = false;
                $data->document_status = 'Non Aktif';
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                DB::commit();
                return response()->json([
                    'message' => "Success void request Quotation number $data->no_document.!"
                ], 200);
            } catch (Exception $e) {
                DB::rollback();
                return response()->json([
                    'message' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()
                ->json(['message' => 'Cannot reject data.!', 'status' => '401'], 401);
        }
    }

    public function getDetailKontrak(Request $request)
    {
        if (!empty($request->id)) {
            $data = QuotationKontrakD::where('id_request_quotation_kontrak_h', $request->id)
                ->orderBy('periode_kontrak', 'asc')
                ->get();

            return response()->json(['data' => $data, 'status' => '200'], 200);
        } else {
            return response()->json(['message' => 'Data not found.!', 'status' => 401], 401);
        }
    }

    // public function romawi($bulan = 0)
    // {
    //     $satuan = (int) $bulan - 1;
    //     $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    //     return $romawi[$satuan];
    // }

    // private function quotationExists(Request $request): bool
    // {
    //     try {
    //         return SamplingPlan::where(['no_quotation' => $request->no_quotation, 'is_active' => true])->exists();
    //     } catch (\Throwable $th) {
    //         throw $th;
    //     }
    // }

    public function requestSamplingPlan(Request $request)
    {
        try {
            $dataArray = (object) [
                'no_quotation' => $request->no_quotation,
                'quotation_id' => $request->quotation_id,
                'tanggal_penawaran' => $request->tanggal_penawaran,
                'sampel_id' => $request->sampel_id,
                'tanggal_sampling' => $request->tanggal_sampling,
                'jam_sampling' => $request->jam_sampling,
                'is_sabtu' => $request->is_sabtu,
                'is_minggu' => $request->is_minggu,
                'is_malam' => $request->is_malam,
                'tambahan' => $request->tambahan,
                'keterangan_lain' => $request->keterangan_lain,
                'karyawan' => $this->karyawan
            ];
            if ($request->status_quotation == 'kontrak') {
                $dataArray->periode = $request->periode;
                $spServices = SamplingPlanServices::on('insertKontrak', $dataArray)->insertSPKontrak();
            } else {
                $spServices = SamplingPlanServices::on('insertNon', $dataArray)->insertSP();
            }

            if ($spServices) {
                $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
                $this->dispatch($job);

                return response()->json(['message' => 'Add Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $th) {
            return response()->json(['message' => 'Add Request Sampling Plan Failed: ' . $th->getMessage() . ' Line: ' . $th->getLine() . ' File: ' . $th->getFile() . '', 'status' => 401], 401);
        }
    }

    public function rescheduleSamplingPlan(Request $request)
    {
        try {
            $dataArray = (object) [
                "no_document" => $request->no_document,
                "no_quotation" => $request->no_quotation,
                "quotation_id" => $request->quotation_id,
                "karyawan" => $this->karyawan,
                "tanggal_sampling" => $request->tanggal_sampling,
                "jam_sampling" => $request->jam_sampling,
                "tambahan" => $request->tambahan,
                "keterangan_lain" => $request->keterangan_lain,
                "tanggal_penawaran" => $request->tanggal_penawaran,
                'is_sabtu' => $request->is_sabtu,
                'is_minggu' => $request->is_minggu,
                'is_malam' => $request->is_malam,
            ];

            if ($request->sample_id && $request->periode) {
                $dataArray->sample_id = $request->sample_id;
                $dataArray->periode = $request->periode;
                $spServices = SamplingPlanServices::on('insertSingleKontrak', $dataArray)->insertSPSingleKontrak();
            } else {
                $spServices = SamplingPlanServices::on('insertSingleNon', $dataArray)->insertSPSingle();
            }

            if ($spServices) {
                $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
                $this->dispatch($job);

                return response()->json(['message' => 'Reschedule Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Reschedule Request Sampling Plan Failed: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'status' => 401], 401);
        }
    }

    public function writeOrder(Request $request)
    {
        try {
            if ($request->status_quotation == 'kontrak') {
                $prosess = self::generateOrderKontrak($request);
                $dataQuotation = QuotationKontrakH::where('no_document', $request->no_document)->where('is_active', true)->first();
                $message = "No. Penawaran : " . $request->no_document . " telah di order.";
                $sales = GetAtasan::where('id', $dataQuotation->sales_id)->get()->pluck('id');

                Notification::whereIn('id', $sales)->title('New Order')->message($message)->url('/qt-ordered')->send();
                return response()->json($prosess->getData(), $prosess->getStatusCode());
            } else {
                $prosess = $this->generateOrderNonKontrak($request);
                $dataQuotation = QuotationNonKontrak::where('no_document', $request->no_document)->where('is_active', true)->first();
                $message = "No. Penawaran : " . $request->no_document . " telah di order.";
                $sales = GetAtasan::where('id', $dataQuotation->sales_id)->get()->pluck('id');
                Notification::whereIn('id', $sales)->title('New Order')->message($message)->url('/qt-ordered')->send();
                return response()->json($prosess->getData(), $prosess->getStatusCode());
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Write Order Failed: ' . $th->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function generateOrderNonKontrak($request)
    {
        try {
            if (!$request->id) {
                return response()->json([
                    'message' => 'Data not found.!',
                    'status' => 401
                ], 401);
            }

            $dataQuotation = QuotationNonKontrak::with(['sales', 'sampling', 'pelanggan'])
                ->where('id', $request->id)
                ->first();

            if ($dataQuotation->pelanggan == null) {
                return response()->json([
                    'message' => 'ID Pelanggan not found.!',
                    'status' => 401
                ], 401);
            }

            //penentuan tahun berdasarkan penawaran
            $y = substr(explode('/', $dataQuotation->no_document)[2], 0, 2);

            $cek_order = OrderHeader::where('id_pelanggan', $dataQuotation->pelanggan_ID)
                ->where('no_document', 'like', '%' . $y . '-%')
                ->orderBy(DB::raw('CAST(SUBSTRING(no_order, 5) AS UNSIGNED)'), 'DESC')
                ->first();

            $id_pelanggan = $dataQuotation->pelanggan_ID;
            $no_urut = sprintf("%02d", 1);

            if ($cek_order != null) {
                $no_order_terakhir = $cek_order->no_order;
                $no_order_terakhir = \str_replace('R1', "", $no_order_terakhir);
                $no_order_terakhir = \str_replace($id_pelanggan, "", $no_order_terakhir);
                $no_order_terakhir = strlen($no_order_terakhir) > 4 ? substr($no_order_terakhir, -3) : substr($no_order_terakhir, -2);
                $no_urut = sprintf("%02d", (int) $no_order_terakhir + 1);
            }

            $no_order = $id_pelanggan . $y . $no_urut;
            // dd($no_order);
            if (count(json_decode($dataQuotation->data_pendukung_sampling)) == 0) {
                /*
                    Generate order kusus untuk tanpa pengujian
                */
                return self::orderNonKontrakNonPengujian($dataQuotation, $no_order);
            } else {
                $dataJadwal = null;
                if ($dataQuotation->status_sampling != 'SD') {
                    $jadwalCollection = collect($dataQuotation->sampling->first()->jadwal ?? []);
                    $dataJadwal = $jadwalCollection->map(function ($item) {
                        return [
                            'tanggal' => $item->tanggal,
                            'kategori' => json_decode($item->kategori, true)
                        ];
                    })->groupBy('tanggal')->map(function ($items, $tanggal) {
                        return [
                            'tanggal' => $tanggal,
                            'kategori' => $items->pluck('kategori')->flatten()->unique()->values()->all()
                        ];
                    })->values()->all();

                    if ($dataJadwal == null) {
                        return response()->json([
                            'message' => 'No Quotation Belum terjadwal',
                            'status' => 401
                        ], 401);
                    }

                    $array_kategori_jadwal = [];
                    $tanggal_jadwal = [];
                    // $array_jadwal_kategori = [];
                    foreach ($dataJadwal as $key => $value) {
                        $kategori_jadwal = $value['kategori'];
                        // array_push($array_jadwal_kategori, $kategori_jadwal);
                        array_push($array_kategori_jadwal, (int) count($kategori_jadwal));
                        array_push($tanggal_jadwal, $value['tanggal']);
                    }
                    // $mergedArray = array_merge(
                    //     ...$array_jadwal_kategori // Menggunakan spread operator agar lebih fleksibel
                    // );

                    // // Hapus duplikat jika diperlukan
                    // $mergedArray = array_unique($mergedArray);

                    // usort($mergedArray, function ($a, $b) {
                    //     // Ambil angka dari akhir string
                    //     preg_match('/\d+$/', $a, $aMatch);
                    //     preg_match('/\d+$/', $b, $bMatch);

                    //     return (int)$aMatch[0] - (int)$bMatch[0];
                    // });

                    // // Dump hasil setelah diurutkan
                    // dd($mergedArray);
                    $total_kategori = (int) array_sum($array_kategori_jadwal);
                    $tanggal_jadwal = array_values(array_unique($tanggal_jadwal));

                    $array_jumlah_titik = [];
                    $jumlah_kategori = 0;

                    foreach (json_decode($dataQuotation->data_pendukung_sampling) as $key => $data_pengujian) {
                        $jumlah_kategori++;
                        array_push($array_jumlah_titik, (int) $data_pengujian->jumlah_titik);
                    }

                    $total_titik = (int) array_sum($array_jumlah_titik);
                    // dd($total_titik, $total_kategori);
                    if ($total_titik > $total_kategori) {
                        return response()->json([
                            'message' => 'Terdapat perbedaan titik antara jadwal dan quotation, silahkan hubungi admin terkait untuk update jadwal.!',
                            'status' => 401
                        ], 401);
                    }
                }

                $data_lama = null;
                if ($dataQuotation->data_lama != null) {
                    $data_lama = json_decode($dataQuotation->data_lama);
                }

                if ($data_lama != null && $data_lama->no_order != null) {
                    /*
                        Jika data lama ada dan no order ada maka re-generate order
                    */
                    $no_order = $data_lama->no_order;
                    return self::reOrderNonKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama);
                } else {
                    /*
                        Jika data lama tidak ada atau no order tidak ada maka generate order
                    */
                    return self::orderNonKontrak($dataQuotation, $no_order, $dataJadwal);
                }
            }
        } catch (\Throwable $th) {

            return response()->json([
                'message' => 'Generate Order Non Kontrak Failed: ' . $th->getMessage() . ' - ' . $th->getLine(),
                'status' => 401
            ], 401);
        }
    }

    public function generateOrderKontrak($request)
    {
        try {
            if (!$request->id) {
                return response()->json([
                    'message' => 'Data not found.!',
                    'status' => 401
                ], 401);
            }

            $dataQuotation = QuotationKontrakH::with(['sales', 'sampling', 'pelanggan'])
                ->where('id', $request->id)
                ->first();

            if ($dataQuotation->pelanggan == null) {
                return response()->json([
                    'message' => 'ID Pelanggan not found.!',
                    'status' => 401
                ], 401);
            }

            //penentuan tahun berdasarkan penawaran
            $y = substr(explode('/', $dataQuotation->no_document)[2], 0, 2);

            $cek_order = OrderHeader::where('id_pelanggan', $dataQuotation->pelanggan_ID)
                ->where('no_document', 'like', '%' . $y . '-%')
                ->orderBy(DB::raw('CAST(SUBSTRING(no_order, 5) AS UNSIGNED)'), 'DESC')
                ->first();

            $id_pelanggan = $dataQuotation->pelanggan_ID;
            $no_urut = sprintf("%02d", 1);
            if ($cek_order != null) {
                $no_order_terakhir = $cek_order->no_order;
                $no_order_terakhir = \str_replace('R1', "", $no_order_terakhir);
                $no_order_terakhir = \str_replace($id_pelanggan, "", $no_order_terakhir);
                $no_order_terakhir = strlen($no_order_terakhir) > 4 ? substr($no_order_terakhir, -3) : substr($no_order_terakhir, -2);
                $no_urut = sprintf("%02d", (int) $no_order_terakhir + 1);
            }

            $no_order = $id_pelanggan . $y . $no_urut;

            if (count(json_decode($dataQuotation->data_pendukung_sampling)) == 0) {
                /*
                    Generate order kusus untuk tanpa pengujian
                */

                return response()->json([
                    'message' => 'Generate Order Kontrak Non Pengujian Belum dapat dilakukan.',
                    'status' => 200
                ], 200);
                // return self::orderNonKontrakNonPengujian($dataQuotation, $no_order);
            }
            $dataJadwal = [];
            if ($dataQuotation->status_sampling != 'SD') {
                $jadwalCollection = collect();
                foreach ($dataQuotation->sampling as $sampling) {
                    $periode = $sampling->periode_kontrak;
                    foreach ($sampling->jadwal as $jadwal) {
                        $jadwal->periode = $periode;
                        $jadwalCollection->push($jadwal);
                    }
                }
                // dd($jadwalCollection);
                $dataJadwal = $jadwalCollection->map(function ($item) {
                    $tanggal = $item->tanggal;
                    $periode = $item->periode;
                    return [
                        'periode_kontrak' => $periode,
                        'tanggal' => $tanggal,
                        'kategori' => json_decode($item->kategori, true)
                    ];
                })->groupBy('periode_kontrak')->map(function ($items, $periode) {
                    return [
                        'periode_kontrak' => $periode,
                        'jadwal' => $items->map(function ($item) {
                            return [
                                'tanggal' => $item['tanggal'],
                                'kategori' => $item['kategori']
                            ];
                        })->values()->all()
                    ];
                })->values()->all();

                $dataJadwal = collect($dataJadwal)->sortBy('periode_kontrak')->values()->all();
                // dd($dataJadwal);
                if ($dataJadwal == null) {
                    return response()->json([
                        'message' => 'No Quotation Belum terjadwal',
                        'status' => 401
                    ], 401);
                }

                // dump($dataJadwal);
                $array_kategori_jadwal = [];
                $tanggal_jadwal = [];
                foreach ($dataJadwal as $key => $value) {
                    foreach ($value['jadwal'] as $jadwal) {
                        $kategori_jadwal = $jadwal['kategori'];
                        // var_dump($kategori_jadwal);
                        array_push($array_kategori_jadwal, (int) count($kategori_jadwal));
                        array_push($tanggal_jadwal, $jadwal['tanggal']);
                    }
                }
                // dd($array_kategori_jadwal, $tanggal_jadwal);
                $total_kategori = (int) array_sum($array_kategori_jadwal);
                $tanggal_jadwal = array_values(array_unique($tanggal_jadwal));

                $array_jumlah_titik = [];
                $jumlah_kategori = 0;

                // dd($array_kategori_jadwal,$dataJadwal);
                foreach ($dataQuotation->detail as $detail) {
                    if ($detail->status_sampling == 'SD')
                        continue;
                    foreach (json_decode($detail->data_pendukung_sampling) as $data_pengujian) {
                        // dd($data_pengujian);
                        foreach ($data_pengujian->data_sampling as $data_sampling) {
                            // dump($data_sampling);
                            $jumlah_kategori++;
                            array_push($array_jumlah_titik, (int) $data_sampling->jumlah_titik);
                        }
                    }
                }

                $total_titik = (int) array_sum($array_jumlah_titik);
                // dd($total_titik, $total_kategori);
                if ($total_titik > $total_kategori) {
                    return response()->json([
                        'message' => 'Terdapat perbedaan titik antara jadwal dan quotation, silahkan hubungi admin terkait untuk update jadwal.!',
                        'status' => 401
                    ], 401);
                }
            }

            $data_lama = null;
            if ($dataQuotation->data_lama != null) {
                $data_lama = json_decode($dataQuotation->data_lama);
            }

            if ($data_lama != null && $data_lama->no_order != null) {
                /*
                    Jika data lama ada dan no order ada maka re-generate order
                */
                $no_order = $data_lama->no_order;
                return self::reOrderKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama);
            } else {
                /*
                    Jika data lama tidak ada atau no order tidak ada maka generate order
                */
                return self::orderKontrak($dataQuotation, $no_order, $dataJadwal);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Generate Order Kontrak Failed: ' . $th->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function orderNonKontrakNonPengujian($dataQuotation, $no_order)
    {
        DB::beginTransaction();
        try {
            $data_lama = null;
            if ($dataQuotation->data_lama != null) {
                $data_lama = json_decode($dataQuotation->data_lama);
                if ($data_lama->no_order != null) {
                    $no_order = $data_lama->no_order;
                }
            }
            // dd($no_order);
            if ($data_lama != null && $data_lama->no_order != null) {
                OrderDetail::where('no_order', $no_order)->where('is_active', 1)->update(['is_active' => 0]);

                $data = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                $data->no_document = $dataQuotation->no_document;
                $data->id_pelanggan = $dataQuotation->pelanggan_ID;
                $data->flag_status = 'ordered';
                $data->is_revisi = 0;
                $data->id_cabang = $dataQuotation->id_cabang;
                $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $data->konsultan = $dataQuotation->konsultan;
                $data->alamat_kantor = $dataQuotation->alamat_kantor;
                $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                $data->nama_pic_order = $dataQuotation->nama_pic_order;
                $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                $data->no_pic_order = $dataQuotation->no_pic_order;
                $data->email_pic_order = $dataQuotation->email_pic_order;
                $data->alamat_sampling = $dataQuotation->alamat_sampling;
                $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
                $data->kategori_customer = $dataQuotation->kategori_customer;
                $data->sub_kategori = $dataQuotation->sub_kategori;
                $data->bahan_customer = $dataQuotation->bahan_customer;
                $data->merk_customer = $dataQuotation->merk_customer;
                $data->status_wilayah = $dataQuotation->status_wilayah;
                $data->total_ppn = $dataQuotation->total_ppn;
                $data->grand_total = $dataQuotation->grand_total;
                $data->total_dicount = $dataQuotation->total_dicount;
                $data->total_dpp = $dataQuotation->total_dpp;
                $data->piutang = $dataQuotation->piutang;
                $data->biaya_akhir = $dataQuotation->biaya_akhir;
                $data->wilayah = $dataQuotation->wilayah;
                $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                $data->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
                $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            } else {
                $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', 1)->first();
                if ($cek_no_qt != null) {
                    return response()->json([
                        'message' => 'No Quotation already Ordered.!',
                    ], 401);
                } else {
                    $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                    if ($cek_no_order != null) {
                        return response()->json([
                            'message' => 'No Order already Ordered.!',
                        ], 401);
                    }
                    $data = new OrderHeader;
                    $data->id_pelanggan = $dataQuotation->pelanggan->id_pelanggan;
                    $data->no_order = $no_order;
                    $data->no_quotation = $dataQuotation->no_quotation;
                    $data->no_document = $dataQuotation->no_document;
                    $data->flag_status = 'ordered';
                    $data->id_cabang = $dataQuotation->id_cabang;
                    $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $data->konsultan = $dataQuotation->konsultan;
                    $data->alamat_kantor = $dataQuotation->alamat_kantor;
                    $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                    $data->nama_pic_order = $dataQuotation->nama_pic_order;
                    $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                    $data->no_pic_order = $dataQuotation->no_pic_order;
                    $data->email_pic_order = $dataQuotation->email_pic_order;
                    $data->alamat_sampling = $dataQuotation->alamat_sampling;
                    $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                    $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                    $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                    $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                    $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
                    $data->kategori_customer = $dataQuotation->kategori_customer;
                    $data->sub_kategori = $dataQuotation->sub_kategori;
                    $data->bahan_customer = $dataQuotation->bahan_customer;
                    $data->merk_customer = $dataQuotation->merk_customer;
                    $data->status_wilayah = $dataQuotation->status_wilayah;
                    $data->total_ppn = $dataQuotation->total_ppn;
                    $data->grand_total = $dataQuotation->grand_total;
                    $data->total_dicount = $dataQuotation->total_dicount;
                    $data->total_dpp = $dataQuotation->total_dpp;
                    $data->piutang = $dataQuotation->piutang;
                    $data->biaya_akhir = $dataQuotation->biaya_akhir;
                    $data->wilayah = $dataQuotation->wilayah;
                    $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                    $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                    $data->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
                    $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                    $data->is_revisi = 0;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->created_by = $this->karyawan;
                    $data->save();
                }
            }

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            DB::commit();
            return response()->json([
                'message' => "Generate Order Non Kontrak $dataQuotation->no_document Non Pengujian Success",
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Generate Order Non Kontrak Non Pengujian Failed: Line ' . $th->getLine() . ' Message: ' . $th->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function orderNonKontrak($dataQuotation, $no_order, $dataJadwal)
    {
        DB::beginTransaction();
        try {
            $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', 1)->first();
            if ($cek_no_qt != null) {
                throw new Exception("No Quotation already Ordered.!", 401);
            }

            $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
            if ($cek_no_order != null) {
                throw new Exception("No Order $no_order already Ordered.!", 401);
            }

            $generator = new Barcode();

            $dataOrderHeader = new OrderHeader;
            $dataOrderHeader->id_pelanggan = $dataQuotation->pelanggan_ID;
            $dataOrderHeader->no_order = $no_order;
            $dataOrderHeader->no_quotation = $dataQuotation->no_quotation;
            $dataOrderHeader->no_document = $dataQuotation->no_document;
            $dataOrderHeader->flag_status = 'ordered';
            $dataOrderHeader->id_cabang = $dataQuotation->id_cabang;
            $dataOrderHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $dataOrderHeader->konsultan = $dataQuotation->konsultan;
            $dataOrderHeader->alamat_kantor = $dataQuotation->alamat_kantor;
            $dataOrderHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $dataOrderHeader->nama_pic_order = $dataQuotation->nama_pic_order;
            $dataOrderHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $dataOrderHeader->no_pic_order = $dataQuotation->no_pic_order;
            $dataOrderHeader->email_pic_order = $dataQuotation->email_pic_order;
            $dataOrderHeader->alamat_sampling = $dataQuotation->alamat_sampling;
            $dataOrderHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $dataOrderHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $dataOrderHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $dataOrderHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $dataOrderHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $dataOrderHeader->kategori_customer = $dataQuotation->kategori_customer;
            $dataOrderHeader->sub_kategori = $dataQuotation->sub_kategori;
            $dataOrderHeader->bahan_customer = $dataQuotation->bahan_customer;
            $dataOrderHeader->merk_customer = $dataQuotation->merk_customer;
            $dataOrderHeader->status_wilayah = $dataQuotation->status_wilayah;
            $dataOrderHeader->total_ppn = $dataQuotation->total_ppn;
            $dataOrderHeader->grand_total = $dataQuotation->grand_total;
            $dataOrderHeader->total_dicount = $dataQuotation->total_dicount;
            $dataOrderHeader->total_dpp = $dataQuotation->total_dpp;
            $dataOrderHeader->piutang = $dataQuotation->piutang;
            $dataOrderHeader->biaya_akhir = $dataQuotation->biaya_akhir;
            $dataOrderHeader->wilayah = $dataQuotation->wilayah;
            $dataOrderHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $dataOrderHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $dataOrderHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $dataOrderHeader->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
            $dataOrderHeader->is_revisi = 0;
            $dataOrderHeader->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $dataOrderHeader->created_by = $this->karyawan;
            $dataOrderHeader->save();

            $n = 1;
            $no = 0;
            $kategori = '';
            $regulasi = [];
            $DataPendukungSampling = json_decode($dataQuotation->data_pendukung_sampling);
            foreach ($DataPendukungSampling as $key => $value) {
                // =================================================================
                for ($f = 0; $f < $value->jumlah_titik; $f++) {
                    $no_sample = $no_order . '/' . sprintf("%03d", $n);
                    /*
                     * Disini bagian pembuatan no sample dan no cfr/lhp
                     * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                     * Jika kategori sama maka no akan di increment
                     * Jika kategori tidak sama maka no akan di reset menjadi 0
                     * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                     */
                    if ($value->kategori_1 == '1-Air') {
                        $no++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                    } else {
                        if (count($value->parameter) <= 2) {
                            if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                $no++;
                            } else {
                                $trim = 0;
                                if ($key != 0)
                                    $trim = ($key - 1);
                                $nan_ = $DataPendukungSampling[$trim];
                                if (
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($nan_->parameter) > 2) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                                ) {
                                    $no++;
                                }
                            }
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                        } else {
                            $no++;
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                        }
                    }

                    $rand_str = strtoupper(md5($no_sample));
                    for ($i = 1; $i <= 5; $i++) {
                        $no_sampling = self::randomstr($rand_str);
                        $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                        if ($cek_no_sampling == null) {
                            break;
                        }
                    }

                    $number_imaginer = sprintf("%03d", $n);

                    $tanggal_sampling = Carbon::now()->format('Y-m-d');
                    if ($dataQuotation->status_sampling != 'SD') {
                        foreach ($dataJadwal as $jadwal) {
                            foreach ($jadwal['kategori'] as $index => $kat) {
                                if (\explode(' - ', $kat)[1] == $number_imaginer) {
                                    $tanggal_sampling = $jadwal['tanggal'];
                                    break 2;
                                }
                            }
                        }
                    }

                    $penamaan_titik = $value->penamaan_titik;
                    if (is_array($value->penamaan_titik)) {
                        $penamaan_titik = isset($value->penamaan_titik[$f]) ? $value->penamaan_titik[$f] : '';
                    }

                    $DataOrderDetail = new OrderDetail;
                    $DataOrderDetail->id_order_header = $dataOrderHeader->id;
                    $DataOrderDetail->no_order = $dataOrderHeader->no_order;
                    $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                    $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                    $DataOrderDetail->no_sampel = $no_sample;
                    $DataOrderDetail->koding_sampling = $no_sampling;
                    $DataOrderDetail->kontrak = 'N';
                    $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                    $DataOrderDetail->kategori_1 = $dataQuotation->status_sampling;
                    $DataOrderDetail->kategori_2 = $value->kategori_1;
                    $DataOrderDetail->kategori_3 = $value->kategori_2;
                    $DataOrderDetail->cfr = $no_cfr;
                    $DataOrderDetail->keterangan_1 = $penamaan_titik;
                    $DataOrderDetail->parameter = json_encode($value->parameter);
                    $DataOrderDetail->regulasi = json_encode($value->regulasi);
                    $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $DataOrderDetail->created_by = $this->karyawan;
                    $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                    $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                    // =================================================================

                    if (!file_exists(public_path() . '/barcode/sampling')) {
                        mkdir(public_path() . '/barcode/sampling', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                    if (!file_exists(public_path() . '/barcode/sample')) {
                        mkdir(public_path() . '/barcode/sample', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                    if (explode("-", $value->kategori_1)[1] == 'Air') {

                        $parameter_names = array_map(function ($p) {
                            return explode(';', $p)[1];
                        }, $value->parameter);

                        $id_kategori = explode("-", $value->kategori_1)[0];

                        $params = HargaParameter::where('id_kategori', $id_kategori)
                            ->where('is_active', true)
                            ->whereIn('nama_parameter', $parameter_names)
                            ->get();

                        $param_map = [];
                        foreach ($params as $param) {
                            $param_map[$param->nama_parameter] = $param;
                        }

                        $botol_volumes = [];
                        foreach ($value->parameter as $parameter) {
                            $param_name = explode(';', $parameter)[1];
                            if (isset($param_map[$param_name])) {
                                $param = $param_map[$param_name];
                                if (!isset($botol_volumes[$param->regen])) {
                                    $botol_volumes[$param->regen] = 0;
                                }
                                $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                            }
                        }

                        // Generate botol dan barcode
                        $botol = [];

                        $ketentuan_botol = [
                            'ORI' => 1000,
                            'H2SO4' => 1000,
                            'M100' => 100,
                            'HNO3' => 500,
                            'M1000' => 1000,
                            'BENTHOS' => 100
                        ];

                        foreach ($botol_volumes as $type => $volume) {
                            $koding = $no_sampling . strtoupper(Str::random(5));

                            // Hitung jumlah botol yang dibutuhkan
                            $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                            $botol[] = (object) [
                                'koding' => $koding,
                                'type_botol' => $type,
                                'volume' => $volume,
                                'file' => $koding . '.png',
                                'disiapkan' => $jumlah_botol
                            ];

                            if (!file_exists(public_path() . '/barcode/botol')) {
                                mkdir(public_path() . '/barcode/botol', 0777, true);
                            }

                            file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                        }

                        $DataOrderDetail->persiapan = json_encode($botol);
                    } else {
                        /*
                         * Jika kategori bukan air maka tidak perlu membuat botol
                         * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                         */
                        if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                            $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                ->whereIn('parameter', $value->parameter)
                                ->get();

                            foreach ($cek_ketentuan_parameter as $ketentuan) {
                                $koding = $no_sampling . strtoupper(Str::random(5));
                                $persiapan[] = [
                                    'parameter' => \explode(';', $ketentuan->parameter)[1],
                                    'disiapkan' => $ketentuan->ketentuan,
                                    'koding' => $koding,
                                    'file' => $koding . '.png'
                                ];

                                if (!file_exists(public_path() . '/barcode/penjerap')) {
                                    mkdir(public_path() . '/barcode/penjerap', 0777, true);
                                }

                                file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                            }
                            //2025-03-01 18:28
                            $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                        }
                    }

                    $DataOrderDetail->save();

                    Ftc::create([
                        'no_sample' => $no_sample
                    ]);

                    FtcT::create([
                        'no_sample' => $no_sample
                    ]);

                    foreach ($value->parameter as $v) {
                        $insert_analisa[] = [
                            'no_order' => $no_order,
                            'no_sampel' => $no_sample,
                            'tanggal_order' => $dataOrderHeader->tanggal_order,
                            'parameter' => $v
                        ];
                    }

                    ParameterAnalisa::insert($insert_analisa);

                    $n++;
                    $kategori = $value->kategori_2;
                    $regulasi = $value->regulasi;
                } //Penutup For
            } //Penutup For each
            // dd($dataQuotation->sampling->isNotEmpty());
            if ($dataQuotation->sampling->isNotEmpty()) {
                foreach ($dataQuotation->sampling->first()->jadwal as $jadwal) {
                    $jadwal->status = 1;
                    $jadwal->save();
                }
            }

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();
            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            DB::commit();

            return response()->json([
                'message' => 'Generate Order Non Kontrak Success',
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            // dd($th);
            throw new Exception($th->getMessage() . ' in line ' . $th->getLine(), 401);
        }
    }

    public function reOrderNonKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama)
    {

        $generator = new Barcode();
        DB::beginTransaction();
        try {

            $cek_order_lama = OrderHeader::where('no_order', $data_lama->no_order)->first();

            $data_detail_lama = OrderDetail::where('id_order_header', $cek_order_lama->id)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $qt_lama = QuotationNonKontrak::where('no_document', $cek_order_lama->no_document)->first();

            $qt_baru = QuotationNonKontrak::where('no_document', $dataQuotation->no_document)->first();

            $set = 0;

            $penambahan_data = [];
            $pengurangan_data = [];
            $perubahan_data = [];

            foreach ((array) json_decode($qt_lama->data_pendukung_sampling) as $value) {
                $value->status_sampling = $qt_lama->status_sampling;
                $data_qt_lama['non_kontrak'][] = $value;
            }

            foreach ((array) json_decode($qt_baru->data_pendukung_sampling) as $value) {
                $value->status_sampling = $qt_baru->status_sampling;
                $data_qt_baru['non_kontrak'][] = $value;
            }

            $different = array_map('json_decode', array_diff(array_map('json_encode', $data_qt_baru), array_map('json_encode', $data_qt_lama)));

            foreach ($different as $s => $fn) {

                $array_a = json_decode(json_encode($data_qt_lama[$s]), true);
                $array_b = json_decode(json_encode($fn), true);

                $different_kanan = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_b), array_map('json_encode', $array_a))));

                $different_kiri = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_a), array_map('json_encode', $array_b))));


                if ($different_kanan != null) {
                    foreach ($different_kanan as $z => $detail_baru) {
                        if (count($array_a) > 0) {
                            foreach ($array_a as $_x => $ss) {
                                $detail_lama = (object) $ss;

                                if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * Data ditemukan yang artinya ada pengurangan / penambahan titik
                                     */

                                    if ((int) $detail_lama->jumlah_titik > (int) $detail_baru->jumlah_titik) {
                                        /**
                                         * Pengurangan titik
                                         */
                                        $selisih = abs($detail_lama->jumlah_titik - $detail_baru->jumlah_titik);
                                        $detail_baru->jumlah_titik = $selisih;
                                        $pengurangan_data['non_kontrak'][] = $detail_baru;
                                    } else if ((int) $detail_lama->jumlah_titik < (int) $detail_baru->jumlah_titik) {
                                        /**
                                         * penambahan titik
                                         */
                                        $selisih = abs($detail_baru->jumlah_titik - $detail_lama->jumlah_titik);
                                        $detail_baru->jumlah_titik = $selisih;
                                        $penambahan_data['non_kontrak'][] = $detail_baru;
                                    }

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }

                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan Regulasi
                                     */
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data['non_kontrak'][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    // unset($array_a[$_x]);
                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 != $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan sub parameter
                                     */
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data['non_kontrak'][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }

                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter != $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan parameter
                                     */
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data['non_kontrak'][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }

                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter != $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    // ada perbedaan di reulasi dan parameter
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data['non_kontrak'][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : []) &&
                                            $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            /*
                                             * &&
                                             * $detail_lama->penamaan_titik == $sss->penamaan_titik
                                             */
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    break;
                                } else {
                                    /**
                                     * data tidak di temukan yang menandakan penambahan kategori
                                     */

                                    if ($_x == (count($array_a) - 1)) {
                                        $penambahan_data['non_kontrak'][] = $detail_baru;
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                        break;
                                    }
                                }
                            }
                        } else {
                            $penambahan_data[$s][] = $detail_baru;
                        }
                    }
                }

                if ($different_kiri) {
                    foreach ($different_kiri as $z => $detail_baru) {
                        $pengurangan_data['non_kontrak'][] = $detail_baru;
                    }
                }
            }

            $no_order_terakhir = $data_lama->no_order;
            if (\str_contains($no_order_terakhir, 'R1') == true) {
                $data = OrderHeader::where('no_order', $data_lama->no_order)->first();
                if ($data != null) {
                    $data->is_active = 0;
                    $data->save();
                }
            }
            $no_order_terakhir = \str_replace('R1', "", $no_order_terakhir);
            $no_order = $no_order_terakhir;


            $n = 0;
            $no_urut_cfr = 0;

            if ($perubahan_data != null) {
                foreach ($perubahan_data as $key => $value) {
                    foreach ($value as $k => $v) {
                        $data_qt_lama = $v['before'];
                        $data_qt_baru = $v['after'];
                        // dd(json_encode($data_qt_lama->regulasi, true), $data_qt_baru->regulasi);
                        $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                            ->where('kategori_2', $data_qt_lama->kategori_1)
                            ->where('kategori_3', $data_qt_lama->kategori_2)
                            // ->where('regulasi', str_replace('\/', '/', json_encode($data_qt_lama->regulasi)))
                            ->where('regulasi', json_encode($data_qt_lama->regulasi))
                            ->where('is_active', 1)
                            ->orderBy('no_sampel', 'DESC')
                            ->get()
                            ->filter(function ($item) use ($data_qt_lama, $k) {
                                return collect(json_decode($item->parameter))->sort()->values()->all() == collect($data_qt_lama->parameter)->sort()->values()->all();
                            });

                        $titik = $cek_order_detail_lama->take($data_qt_lama->jumlah_titik);

                        foreach ($titik as $kk => $vv) {

                            $search_kategori = '%' . \explode('-', $data_qt_baru->kategori_2)[1] . ' - ' . substr($vv->no_sampel, -3) . '%';

                            $cek_jadwal = Jadwal::where('no_quotation', $dataQuotation->no_document)
                                ->where('is_active', 1)
                                ->where('kategori', 'like', $search_kategori)
                                ->select('tanggal', 'kategori')
                                ->groupBy('tanggal', 'kategori')
                                ->first();


                            $vv->kategori_2 = $data_qt_baru->kategori_1;
                            $vv->kategori_3 = $data_qt_baru->kategori_2;
                            $vv->parameter = json_encode($data_qt_baru->parameter);
                            $vv->regulasi = json_encode($data_qt_baru->regulasi);
                            if (isset($cek_jadwal->tanggal))
                                $vv->tanggal_sampling = $cek_jadwal->tanggal;
                            $vv->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                            $vv->save();
                        }

                        if ((int) $data_qt_baru->jumlah_titik > (int) $data_qt_lama->jumlah_titik) {
                            /**
                             * Apabila ada penambahan titik
                             */
                            $selisih = (int) $data_qt_baru->jumlah_titik - (int) $data_qt_lama->jumlah_titik;
                            $data_qt_baru->jumlah_titik = $selisih;
                            $penambahan_data['non_kontrak'][] = $data_qt_baru;
                        }
                    }
                }
            }

            if ($penambahan_data != null) {
                // Add data
                $cek_detail = OrderDetail::where('id_order_header', $data_lama->id_order)
                    // ->where('active', 0)
                    ->orderBy('no_sampel', 'DESC')
                    ->first();

                $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
                $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
                $n = $no_urut_sample + 1;
                $trigger = 0;
                $kategori = '';
                $regulasi = [];
                foreach ($penambahan_data as $key => $values) {
                    foreach ($values as $keys => $value) {

                        for ($f = 0; $f < $value->jumlah_titik; $f++) {
                            // =================================================================
                            $no_sample = $no_order . '/' . sprintf("%03d", $n);
                            /*
                             * Disini bagian pembuatan no sample dan no cfr/lhp
                             * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                             * Jika kategori sama maka no akan di increment
                             * Jika kategori tidak sama maka no akan di reset menjadi 0
                             * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                             */
                            if ($value->kategori_1 == '1-Air') {
                                $no_urut_cfr++;
                                $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                            } else {
                                if (count($value->parameter) <= 2) {
                                    if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                        $no_urut_cfr++;
                                    } else {
                                        $trim = 0;
                                        if ($key != 0)
                                            $trim = ($key - 1);
                                        $data_sebeumnya = $values[$trim];
                                        if (
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($data_sebeumnya->parameter) > 2) ||
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                                        ) {
                                            $no_urut_cfr++;
                                        }
                                    }
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                                } else {
                                    $no_urut_cfr++;
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                                }
                            }

                            $rand_str = strtoupper(md5($no_sample));
                            for ($i = 1; $i <= 5; $i++) {
                                $no_sampling = self::randomstr($rand_str);
                                $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                                if ($cek_no_sampling == null) {
                                    break;
                                }
                            }

                            $number_imaginer = sprintf("%03d", $n);

                            $tanggal_sampling = Carbon::now()->format('Y-m-d');
                            if ($dataQuotation->status_sampling != 'SD') {
                                foreach ($dataJadwal as $jadwal) {
                                    foreach ($jadwal['kategori'] as $index => $kat) {
                                        if (\explode(' - ', $kat)[1] == $number_imaginer) {
                                            $tanggal_sampling = $jadwal['tanggal'];
                                            break 2;
                                        }
                                    }
                                }
                            }

                            // =================================================================
                            // dd((is_array($value->parameter)) ? $value->penamaan_titik[$f] : $value->penamaan_titik);
                            $dataD = new OrderDetail;
                            $dataD->id_order_header = $data_lama->id_order;
                            $dataD->no_order = $no_order;
                            $dataD->nama_perusahaan = $dataQuotation->nama_perusahaan;
                            $dataD->alamat_perusahaan = $dataQuotation->alamat_kantor;
                            $dataD->no_quotation = $dataQuotation->no_document;
                            $dataD->no_sampel = $no_sample;
                            $dataD->koding_sampling = $no_sampling;
                            $dataD->kontrak = 'N';
                            $dataD->tanggal_sampling = $tanggal_sampling; //belum di set
                            $dataD->kategori_1 = $value->status_sampling;
                            $dataD->kategori_2 = $value->kategori_1;
                            $dataD->kategori_3 = $value->kategori_2;
                            $dataD->cfr = $no_cfr;
                            $dataD->keterangan_1 = (is_array($value->penamaan_titik)) ? $value->penamaan_titik[$f] : $value->penamaan_titik;
                            $dataD->parameter = json_encode($value->parameter);
                            $dataD->regulasi = json_encode($value->regulasi);
                            $dataD->created_at = Carbon::now()->format('Y-m-d H:i:s');
                            $dataD->created_by = $this->karyawan;
                            $dataD->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                            $dataD->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                            // =================================================================
                            if (!file_exists(public_path() . '/barcode/sampling')) {
                                mkdir(public_path() . '/barcode/sampling', 0777, true);
                            }

                            file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                            if (!file_exists(public_path() . '/barcode/sample')) {
                                mkdir(public_path() . '/barcode/sample', 0777, true);
                            }

                            file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                            // =================================================================
                            if (explode("-", $value->kategori_1)[1] == 'Air') {

                                $parameter_names = array_map(function ($p) {
                                    return explode(';', $p)[1];
                                }, $value->parameter);

                                $id_kategori = explode("-", $value->kategori_1)[0];

                                $params = HargaParameter::where('id_kategori', $id_kategori)
                                    ->where('is_active', true)
                                    ->whereIn('nama_parameter', $parameter_names)
                                    ->get();

                                $param_map = [];
                                foreach ($params as $param) {
                                    $param_map[$param->nama_parameter] = $param;
                                }

                                $botol_volumes = [];
                                foreach ($value->parameter as $parameter) {
                                    $param_name = explode(';', $parameter)[1];
                                    if (isset($param_map[$param_name])) {
                                        $param = $param_map[$param_name];
                                        if (!isset($botol_volumes[$param->regen])) {
                                            $botol_volumes[$param->regen] = 0;
                                        }
                                        $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                                    }
                                }

                                // Generate botol dan barcode
                                $botol = [];

                                $ketentuan_botol = [
                                    'ORI' => 1000,
                                    'H2SO4' => 1000,
                                    'M100' => 100,
                                    'HNO3' => 500,
                                    'M1000' => 1000,
                                    'BENTHOS' => 100
                                ];

                                foreach ($botol_volumes as $type => $volume) {
                                    $koding = $no_sampling . strtoupper(Str::random(5));

                                    // Hitung jumlah botol yang dibutuhkan
                                    $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                                    $botol[] = (object) [
                                        'koding' => $koding,
                                        'type_botol' => $type,
                                        'volume' => $volume,
                                        'file' => $koding . '.png',
                                        'disiapkan' => $jumlah_botol
                                    ];

                                    if (!file_exists(public_path() . '/barcode/botol')) {
                                        mkdir(public_path() . '/barcode/botol', 0777, true);
                                    }

                                    file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                }

                                $dataD->persiapan = json_encode($botol);
                            } else {
                                /*
                                 * Jika kategori bukan air maka tidak perlu membuat botol
                                 * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                                 */
                                if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                                    $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                        ->whereIn('parameter', $value->parameter)
                                        ->get();

                                    foreach ($cek_ketentuan_parameter as $ketentuan) {
                                        $koding = $no_sampling . strtoupper(Str::random(5));
                                        $persiapan[] = [
                                            'parameter' => \explode(';', $ketentuan->parameter)[1],
                                            'disiapkan' => $ketentuan->ketentuan,
                                            'koding' => $koding,
                                            'file' => $koding . '.png'
                                        ];

                                        if (!file_exists(public_path() . '/barcode/penjerap')) {
                                            mkdir(public_path() . '/barcode/penjerap', 0777, true);
                                        }

                                        file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                    }

                                    $dataD->persiapan = json_encode($persiapan ?? []);
                                }
                            }

                            // =================================================================
                            $dataD->save();

                            Ftc::create([
                                'no_sample' => $no_sample
                            ]);

                            FtcT::create([
                                'no_sample' => $no_sample
                            ]);

                            // ======================================================================================= INSERT TO TABLE PARAMETER ANALISA ===================================================================================
                            foreach ($value->parameter as $v) {
                                $insert_analisa[] = [
                                    'no_order' => $no_order,
                                    'no_sampel' => $no_sample,
                                    'tanggal_order' => Carbon::now()->format('Y-m-d'),
                                    'parameter' => $v
                                ];
                            }

                            ParameterAnalisa::insert($insert_analisa);


                            $n++;
                            $kategori = $value->kategori_2;
                            $regulasi = $value->regulasi;
                        }
                    }
                }
            }

            if ($pengurangan_data != null) {
                // non aktifkan data
                foreach ($pengurangan_data as $key => $value) {
                    foreach ($value as $keys => $values) {
                        $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                            ->where('kategori_2', $values->kategori_1)
                            ->where('kategori_3', $values->kategori_2)
                            // ->where('parameter', json_encode($values->parameter))
                            // ->where('regulasi', json_encode($values->regulasi))
                            ->where('regulasi', str_replace('\/', '/', json_encode($values->regulasi)))
                            ->where('is_active', 1)
                            ->orderBy('no_sampel', 'DESC')
                            ->get()
                            ->filter(function ($item) use ($values, $keys) {
                                return collect(json_decode($item->parameter))->sort()->values()->all() == collect($values->parameter)->sort()->values()->all();
                            });
                        // dd($cek_order_detail_lama);
                        if ($cek_order_detail_lama->isNotEmpty()) {
                            foreach ($cek_order_detail_lama->take($values->jumlah_titik) as $hh => $change) {
                                Ftc::where('no_sample', $change->no_sampel)->update(['is_active' => 0]);
                                FtcT::where('no_sample', $change->no_sampel)->update(['is_active' => 0]);

                                $change->is_active = 0;
                                $change->save();
                            }
                        }
                    }
                }
            }
            // dd($perubahan_data, $penambahan_data, $pengurangan_data);
            $data = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
            $data->no_document = $dataQuotation->no_document;
            $data->id_pelanggan = $dataQuotation->pelanggan_ID;
            $data->flag_status = 'ordered';
            $data->is_revisi = 0;
            $data->id_cabang = $dataQuotation->id_cabang;
            $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $data->konsultan = $dataQuotation->konsultan;
            $data->alamat_kantor = $dataQuotation->alamat_kantor;
            $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $data->nama_pic_order = $dataQuotation->nama_pic_order;
            $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $data->no_pic_order = $dataQuotation->no_pic_order;
            $data->email_pic_order = $dataQuotation->email_pic_order;
            $data->alamat_sampling = $dataQuotation->alamat_sampling;
            $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $data->kategori_customer = $dataQuotation->kategori_customer;
            $data->sub_kategori = $dataQuotation->sub_kategori;
            $data->bahan_customer = $dataQuotation->bahan_customer;
            $data->merk_customer = $dataQuotation->merk_customer;
            $data->status_wilayah = $dataQuotation->status_wilayah;
            $data->total_ppn = $dataQuotation->total_ppn;
            $data->grand_total = $dataQuotation->grand_total;
            $data->total_dicount = $dataQuotation->total_dicount;
            $data->total_dpp = $dataQuotation->total_dpp;
            $data->piutang = $dataQuotation->piutang;
            $data->biaya_akhir = $dataQuotation->biaya_akhir;
            $data->wilayah = $dataQuotation->wilayah;
            $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $data->tanggal_order = Carbon::now()->format('Y-m-d');
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // update data sampelDiantar
            $sampelSD = SampelDiantar::where('no_quotation', $cek_order_lama->no_document)
                ->where('no_order', $cek_order_lama->no_order)->where('nama_perusahaan', $cek_order_lama->nama_perusahaan)->first();
            if ($sampelSD !== null) {
                $sampelSD->no_order = $no_order;
                $sampelSD->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $sampelSD->no_quotation = $dataQuotation->no_document;
                $sampelSD->save();
            }

            //update general order detail
            OrderDetail::where('no_order', $no_order)->where('is_active', 1)->update([
                'nama_perusahaan' => $dataQuotation->nama_perusahaan,
                'alamat_perusahaan' => $dataQuotation->alamat_kantor,
                'no_quotation' => $dataQuotation->no_document
            ]);

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            $data_detail_baru = OrderDetail::where('no_order', $no_order)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $data_to_log = [
                'data_lama' => $data_detail_lama->toArray(),
                'data_baru' => $data_detail_baru->toArray()
            ];

            $workerOperation = new WorkerOperation();
            $workerOperation->index($data, $data_to_log, GetAtasan::where('user_id', $this->user_id)->get()->pluck('email')->toArray());
            // $workerOperation->index($data, $data_to_log, []);

            DB::commit();
            return response()->json([
                'message' => 'Re- Order Non Kontrak Success',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }

    public function orderKontrak($dataQuotation, $no_order, $dataJadwal)
    {
        $generator = new Barcode();
        DB::beginTransaction();
        try {
            $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', true)->first();
            if ($cek_no_qt != null) {
                return response()->json([
                    'message' => 'No Quotation already Ordered.!',
                ], 401);
            } else {
                $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                if ($cek_no_order != null) {
                    return response()->json([
                        'message' => 'No Order already Ordered.!',
                    ], 401);
                }

                $dataOrderHeader = new OrderHeader;
                $dataOrderHeader->id_pelanggan = $dataQuotation->pelanggan_ID;
                $dataOrderHeader->no_order = $no_order;
                $dataOrderHeader->no_quotation = $dataQuotation->no_quotation;
                $dataOrderHeader->no_document = $dataQuotation->no_document;
                $dataOrderHeader->flag_status = 'ordered';
                $dataOrderHeader->id_cabang = $dataQuotation->id_cabang;
                $dataOrderHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $dataOrderHeader->konsultan = $dataQuotation->konsultan;
                $dataOrderHeader->alamat_kantor = $dataQuotation->alamat_kantor;
                $dataOrderHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                $dataOrderHeader->nama_pic_order = $dataQuotation->nama_pic_order;
                $dataOrderHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                $dataOrderHeader->no_pic_order = $dataQuotation->no_pic_order;
                $dataOrderHeader->email_pic_order = $dataQuotation->email_pic_order;
                $dataOrderHeader->alamat_sampling = $dataQuotation->alamat_sampling;
                $dataOrderHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                $dataOrderHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                $dataOrderHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                $dataOrderHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                $dataOrderHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
                $dataOrderHeader->kategori_customer = $dataQuotation->kategori_customer;
                $dataOrderHeader->sub_kategori = $dataQuotation->sub_kategori;
                $dataOrderHeader->bahan_customer = $dataQuotation->bahan_customer;
                $dataOrderHeader->merk_customer = $dataQuotation->merk_customer;
                $dataOrderHeader->status_wilayah = $dataQuotation->status_wilayah;
                $dataOrderHeader->total_ppn = $dataQuotation->total_ppn;
                $dataOrderHeader->grand_total = $dataQuotation->grand_total;
                $dataOrderHeader->total_dicount = $dataQuotation->total_dicount;
                $dataOrderHeader->total_dpp = $dataQuotation->total_dpp;
                $dataOrderHeader->piutang = $dataQuotation->piutang;
                $dataOrderHeader->biaya_akhir = $dataQuotation->biaya_akhir;
                $dataOrderHeader->wilayah = $dataQuotation->wilayah;
                $dataOrderHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                $dataOrderHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                $dataOrderHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                $dataOrderHeader->tanggal_order = Carbon::now()->format('Y-m-d');
                $dataOrderHeader->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $dataOrderHeader->created_by = $this->karyawan;
                $dataOrderHeader->save();

                $n = 1;
                $no = 0;

                $kategori = '';
                $regulasi = [];

                $detail = $dataQuotation->detail()->orderBy('periode_kontrak', 'asc')->get();

                foreach ($detail as $k => $t) {
                    $periode_kontrak = $t->periode_kontrak;
                    $tanggal_sampling = $periode_kontrak . '-01';
                    $sampling_plan = $dataJadwal;

                    foreach (json_decode($t->data_pendukung_sampling) as $ky => $val) {
                        // each mencari data pendukung sampling per periode
                        $DataPendukungSampling = $val->data_sampling;
                        foreach ($val->data_sampling as $key => $value) {
                            // each mencari data sampling per detail
                            for ($f = 0; $f < $value->jumlah_titik; $f++) {
                                // =================================================================
                                $no_sample = $no_order . '/' . sprintf("%03d", $n);
                                /*
                                 * Disini bagian pembuatan no sample dan no cfr/lhp
                                 * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                                 * Jika kategori sama maka no akan di increment
                                 * Jika kategori tidak sama maka no akan di reset menjadi 0
                                 * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                                 */
                                if ($value->kategori_1 == '1-Air') {
                                    $no++;
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                } else {
                                    if (count($value->parameter) <= 2) {
                                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                            $no++;
                                        } else {
                                            $trim = 0;
                                            if ($key != 0)
                                                $trim = ($key - 1);
                                            $nan_ = $DataPendukungSampling[$trim];
                                            if (
                                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($nan_->parameter) > 2) ||
                                                ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                                            ) {
                                                $no++;
                                            }
                                        }
                                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                    } else {
                                        $no++;
                                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                    }
                                }

                                $rand_str = strtoupper(md5($no_sample));
                                for ($i = 1; $i <= 5; $i++) {
                                    $no_sampling = self::randomstr($rand_str);
                                    $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                                    if ($cek_no_sampling == null) {
                                        break;
                                    }
                                }

                                $number_imaginer = sprintf("%03d", $n);

                                if ($dataQuotation->status_sampling != 'SD') {
                                    foreach ($dataJadwal as $sampling_plan) {
                                        if ($sampling_plan['periode_kontrak'] == $periode_kontrak) {
                                            foreach ($sampling_plan['jadwal'] as $jadwal) {
                                                foreach ($jadwal['kategori'] as $kategori) {
                                                    if (explode(' - ', $kategori)[1] == $number_imaginer) {
                                                        $tanggal_sampling = $jadwal['tanggal'];
                                                        break 2;
                                                    }
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }

                                $penamaan_titik = $value->penamaan_titik;
                                if (is_array($value->penamaan_titik)) {
                                    $penamaan_titik = isset($value->penamaan_titik[$f]) ? $value->penamaan_titik[$f] : '';
                                }

                                $DataOrderDetail = new OrderDetail;
                                $DataOrderDetail->id_order_header = $dataOrderHeader->id;
                                $DataOrderDetail->no_order = $dataOrderHeader->no_order;
                                $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                                $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                                $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                                $DataOrderDetail->no_sampel = $no_sample;
                                $DataOrderDetail->koding_sampling = $no_sampling;
                                $DataOrderDetail->kontrak = 'C';
                                $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                                $DataOrderDetail->kategori_1 = $t->status_sampling;
                                $DataOrderDetail->kategori_2 = $value->kategori_1;
                                $DataOrderDetail->kategori_3 = $value->kategori_2;
                                $DataOrderDetail->cfr = $no_cfr;
                                $DataOrderDetail->keterangan_1 = $penamaan_titik;
                                $DataOrderDetail->periode = $periode_kontrak;
                                $DataOrderDetail->parameter = json_encode($value->parameter);
                                $DataOrderDetail->regulasi = json_encode($value->regulasi);
                                $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                                $DataOrderDetail->created_by = $this->karyawan;
                                $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                                $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                                // =================================================================

                                if (!file_exists(public_path() . '/barcode/sampling')) {
                                    mkdir(public_path() . '/barcode/sampling', 0777, true);
                                }

                                file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                                if (!file_exists(public_path() . '/barcode/sample')) {
                                    mkdir(public_path() . '/barcode/sample', 0777, true);
                                }

                                file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                                if (explode("-", $value->kategori_1)[1] == 'Air') {

                                    $parameter_names = array_map(function ($p) {
                                        return explode(';', $p)[1];
                                    }, $value->parameter);

                                    $id_kategori = explode("-", $value->kategori_1)[0];

                                    $params = HargaParameter::where('id_kategori', $id_kategori)
                                        ->where('is_active', true)
                                        ->whereIn('nama_parameter', $parameter_names)
                                        ->get();

                                    $param_map = [];
                                    foreach ($params as $param) {
                                        $param_map[$param->nama_parameter] = $param;
                                    }

                                    $botol_volumes = [];
                                    foreach ($value->parameter as $parameter) {
                                        $param_name = explode(';', $parameter)[1];
                                        if (isset($param_map[$param_name])) {
                                            $param = $param_map[$param_name];
                                            if (!isset($botol_volumes[$param->regen])) {
                                                $botol_volumes[$param->regen] = 0;
                                            }
                                            $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                                        }
                                    }

                                    // Generate botol dan barcode
                                    $botol = [];

                                    $ketentuan_botol = [
                                        'ORI' => 1000,
                                        'H2SO4' => 1000,
                                        'M100' => 100,
                                        'HNO3' => 500,
                                        'M1000' => 1000,
                                        'BENTHOS' => 100
                                    ];

                                    foreach ($botol_volumes as $type => $volume) {
                                        $koding = $no_sampling . strtoupper(Str::random(5));

                                        // Hitung jumlah botol yang dibutuhkan
                                        $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                                        $botol[] = (object) [
                                            'koding' => $koding,
                                            'type_botol' => $type,
                                            'volume' => $volume,
                                            'file' => $koding . '.png',
                                            'disiapkan' => $jumlah_botol
                                        ];

                                        if (!file_exists(public_path() . '/barcode/botol')) {
                                            mkdir(public_path() . '/barcode/botol', 0777, true);
                                        }

                                        file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                    }

                                    $DataOrderDetail->persiapan = json_encode($botol);
                                } else {
                                    /*
                                     * Jika kategori bukan air maka tidak perlu membuat botol
                                     * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                                     */
                                    if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                                        $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                            ->whereIn('parameter', $value->parameter)
                                            ->get();

                                        foreach ($cek_ketentuan_parameter as $ketentuan) {
                                            $koding = $no_sampling . strtoupper(Str::random(5));
                                            $persiapan[] = [
                                                'parameter' => \explode(';', $ketentuan->parameter)[1],
                                                'disiapkan' => $ketentuan->ketentuan,
                                                'koding' => $koding,
                                                'file' => $koding . '.png'
                                            ];

                                            if (!file_exists(public_path() . '/barcode/penjerap')) {
                                                mkdir(public_path() . '/barcode/penjerap', 0777, true);
                                            }

                                            file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                        }
                                        //2025-03-01 18:28
                                        $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                                    }
                                }

                                $DataOrderDetail->save();

                                Ftc::create([
                                    'no_sample' => $no_sample
                                ]);

                                FtcT::create([
                                    'no_sample' => $no_sample
                                ]);

                                foreach ($value->parameter as $v) {
                                    $insert_analisa[] = [
                                        'no_order' => $no_order,
                                        'no_sampel' => $no_sample,
                                        'tanggal_order' => $dataOrderHeader->tanggal_order,
                                        'parameter' => $v
                                    ];
                                }

                                ParameterAnalisa::insert($insert_analisa);

                                $n++;
                                $kategori = $value->kategori_2;
                                $regulasi = $value->regulasi;
                            }
                        }
                    }
                }
            }

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            DB::commit();
            return response()->json([
                'message' => 'Generate Order Kontrak Success',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }

    public function reOrderKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama)
    {
        $generator = new Barcode();
        DB::beginTransaction();
        try {
            // $void = OrderHeader::where('no_order', $data_lama->no_order)->update(['is_active' => 0]);
            $data_detail_lama = OrderDetail::where('no_order', $data_lama->no_order)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $update_o = OrderDetail::where('no_order', $no_order)->update([
                'nama_perusahaan' => $dataQuotation->nama_perusahaan,
                'alamat_perusahaan' => $dataQuotation->alamat_kantor,
                'no_quotation' => $dataQuotation->no_document
            ]);
            // dd($data_lama->no_qt, $dataQuotation->no_document);
            $qt_lama = QuotationKontrakH::join('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
                ->where('request_quotation_kontrak_H.no_document', $data_lama->no_qt)
                ->orderBy('periode_kontrak', 'ASC')
                ->get();

            $qt_baru = QuotationKontrakH::join('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
                ->where('request_quotation_kontrak_H.no_document', $dataQuotation->no_document)
                ->where('request_quotation_kontrak_H.is_active', 1)
                ->orderBy('periode_kontrak', 'ASC')
                ->get();

            // dd($qt_baru, $qt_lama);

            $penambahan_data = [];
            $pengurangan_data = [];
            $perubahan_data = [];
            $count_periode_lama = $qt_lama->count();
            $count_periode_baru = $qt_baru->count();

            $array_periode_lama = [];
            $array_periode_baru = [];

            $data_qt_lama = [];
            $data_baru = [];

            foreach ($qt_lama as $z => $xx) {
                array_push($array_periode_lama, $xx->periode_kontrak);
            }
            foreach ($qt_baru as $z => $xx) {
                array_push($array_periode_baru, $xx->periode_kontrak);
            }

            // dd($array_periode_baru, $array_periode_lama);

            $pengurangan_periode_kontrak = array_diff($array_periode_lama, $array_periode_baru);
            $penambahan_periode_kontrak = array_diff($array_periode_baru, $array_periode_lama);
            // dd($penambahan_periode_kontrak);

            if ($pengurangan_periode_kontrak != null) {

                foreach ($qt_lama as $z => $xx) {
                    if (in_array($xx->periode_kontrak, $pengurangan_periode_kontrak)) {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {

                            foreach ($g->data_sampling as $key => $pe) {
                                $pengurangan_data[$xx->periode_kontrak][] = $pe;
                            }
                        }
                    } else {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                            foreach ($g->data_sampling as $key => $value) {
                                $value->status_sampling = $xx->status_sampling;
                                $data_qt_lama[$g->periode_kontrak][] = $value;
                            }
                        }
                    }
                }

                foreach ($pengurangan_periode_kontrak as $vv => $mm) {
                    unset($qt_lama[$vv]);
                }
                $qt_lama = array_values($qt_lama->toArray());
            } else {
                foreach ($qt_lama as $z => $xx) {
                    foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                        foreach ($g->data_sampling as $key => $value) {
                            $value->status_sampling = $xx->status_sampling;
                            $data_qt_lama[$g->periode_kontrak][] = $value;
                        }
                    }
                }
            }

            if ($penambahan_periode_kontrak != null) {
                foreach ($qt_baru as $z => $xx) {
                    if (in_array($xx->periode_kontrak, $penambahan_periode_kontrak)) {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                            foreach ($g->data_sampling as $key => $value) {

                                $value->status_sampling = $xx->status_sampling;
                                $penambahan_data[$xx->periode_kontrak][] = $value;
                            }
                        }
                    } else {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                            foreach ($g->data_sampling as $key => $value) {
                                $value->status_sampling = $xx->status_sampling;
                                $data_baru[$g->periode_kontrak][] = $value;
                            }
                        }
                    }
                }

                foreach ($penambahan_periode_kontrak as $vv => $mm) {
                    unset($qt_baru[$vv]);
                }
                $qt_baru = array_values($qt_baru->toArray());
            } else {
                foreach ($qt_baru as $z => $xx) {
                    foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                        foreach ($g->data_sampling as $key => $value) {
                            $value->status_sampling = $xx->status_sampling;
                            $data_baru[$g->periode_kontrak][] = $value;
                        }
                    }
                }
            }

            // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak);

            function deep_array_diff($array1, $array2)
            {
                $diff = [];
                foreach ($array1 as $key => $value1) {
                    if (!isset($array2[$key])) {
                        $diff[$key] = $value1;
                    } elseif (is_array($value1) && is_array($array2[$key])) {
                        $deep_diff = deep_array_diff($value1, $array2[$key]);
                        if (!empty($deep_diff)) {
                            $diff[$key] = $deep_diff;
                        }
                    } elseif ($value1 !== $array2[$key]) {
                        $diff[$key] = $value1;
                    }
                }
                return $diff;
            }

            $different = deep_array_diff($data_baru, $data_qt_lama);

            // Mencari data analisa yang berbeda secara menyeluruh di setiap periodenya dan dieliminasi data yang sama sehingga mempersingkat proses compare
            // $different = array_map('json_decode', array_diff(array_map('json_encode', $data_baru), array_map('json_encode', $data_qt_lama)));

            foreach ($different as $s => $fn) {

                $array_a = json_decode(json_encode($data_qt_lama[$s]), true);
                $array_b = json_decode(json_encode($fn), true);

                $different_kanan = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_b), array_map('json_encode', $array_a))));

                $different_kiri = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_a), array_map('json_encode', $array_b))));


                if ($different_kanan != null) {
                    foreach ($different_kanan as $z => $detail_baru) {
                        if (count($array_a) > 0) {
                            foreach ($array_a as $_x => $ss) {
                                $detail_lama = (object) $ss;

                                if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * Data ditemukan yang artinya ada pengurangan / penambahan titik
                                     */
                                    if ((int) $detail_lama->jumlah_titik > (int) $detail_baru->jumlah_titik) {
                                        /**
                                         * Pengurangan titik
                                         */
                                        $selisih = abs($detail_lama->jumlah_titik - $detail_baru->jumlah_titik);
                                        $detail_baru->jumlah_titik = $selisih;
                                        $pengurangan_data[$s][] = $detail_baru;
                                    } else if ((int) $detail_lama->jumlah_titik < (int) $detail_baru->jumlah_titik) {
                                        /**
                                         * penambahan titik
                                         */
                                        $selisih = abs($detail_baru->jumlah_titik - $detail_lama->jumlah_titik);
                                        $detail_baru->jumlah_titik = $selisih;
                                        $penambahan_data[$s][] = $detail_baru;
                                    }

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }

                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan Regulasi
                                     */
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data[$s][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    // unset($array_a[$_x]);
                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 != $detail_baru->kategori_2 &&
                                    $detail_lama->parameter == $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan sub kategori
                                     */

                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data[$s][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    // unset($array_a[$_x]);
                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter != $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    /**
                                     * data ditemukan dengan adanya perubahan parameter
                                     */
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data[$s][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                            && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    // unset($array_a[$_x]);
                                    break;
                                } else if (
                                    $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                    $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                    $detail_lama->parameter != $detail_baru->parameter &&
                                    (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                    && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                                ) {
                                    // ada perbedaan di reulasi dan parameter
                                    $array_perubahan = [
                                        'before' => $detail_lama,
                                        'after' => $detail_baru
                                    ];
                                    $perubahan_data['non_kontrak'][] = $array_perubahan;

                                    foreach ($different_kiri as $xxx => $sss) {
                                        if (
                                            $detail_lama->kategori_1 == $sss->kategori_1 &&
                                            $detail_lama->kategori_2 == $sss->kategori_2 &&
                                            $detail_lama->parameter == $sss->parameter &&
                                            (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : []) &&
                                            $detail_lama->penamaan_titik == $sss->penamaan_titik
                                        ) {
                                            /*
                                             * &&
                                             * $detail_lama->penamaan_titik == $sss->penamaan_titik
                                             */
                                            unset($different_kiri[$xxx]);
                                            unset($array_a[$_x]);
                                            $array_a = array_values($array_a);
                                        }
                                    }
                                    break;
                                } else {
                                    /**
                                     * data tidak di temukan yang menandakan penambahan kategori
                                     */

                                    if ($_x == (count($array_a) - 1)) {
                                        $penambahan_data[$s][] = $detail_baru;
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                        break;
                                    }
                                }
                            }
                        } else {
                            $penambahan_data[$s][] = $detail_baru;
                        }
                    }
                }

                if ($different_kiri) {
                    foreach ($different_kiri as $z => $detail_baru) {
                        $pengurangan_data[$s][] = $detail_baru;
                    }
                }
            }

            // dd($penambahan_data, $pengurangan_data, $perubahan_data);

            $n = 0;
            $no_urut_cfr = 0;

            if ($perubahan_data != null) {
                foreach ($perubahan_data as $key => $value) {
                    $periode = $key;
                    foreach ($value as $k => $v) {
                        $data_qt_lama = $v['before'];
                        $data_qt_baru = $v['after'];

                        // $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                        // ->where('periode', $periode)
                        // ->where('kategori_2', $data_qt_lama->kategori_1)
                        // ->where('kategori_3', $data_qt_lama->kategori_2);
                        // foreach ($data_qt_lama->parameter as $parameter) {
                        //     $cek_order_detail_lama = $cek_order_detail_lama->whereJsonContains('parameter', (string) $parameter);
                        // }
                        // $cek_order_detail_lama = $cek_order_detail_lama->where('regulasi', json_encode($data_qt_lama->regulasi))
                        // ->where('is_active', 1)
                        // ->orderBy('no_sampel', 'DESC')
                        // ->get();

                        $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                            ->where('periode', $periode)
                            ->where('kategori_2', $data_qt_lama->kategori_1)
                            ->where('kategori_3', $data_qt_lama->kategori_2)
                            ->where('regulasi', str_replace('\/', '/', json_encode($data_qt_lama->regulasi)))
                            ->where('is_active', 1)
                            ->orderBy('no_sampel', 'DESC')
                            ->get()
                            ->filter(function ($item) use ($data_qt_lama, $k) {
                                return collect(json_decode($item->parameter))->sort()->values()->all() == collect($data_qt_lama->parameter)->sort()->values()->all();
                            });

                        $titik = $cek_order_detail_lama->take($data_qt_lama->jumlah_titik);

                        foreach ($titik as $kk => $vv) {

                            $db_jadwal = \explode('-', $periode)[0];
                            $search_kategori = '%' . \explode('-', $data_qt_baru->kategori_2)[1] . ' - ' . substr($vv->no_sampel, -3) . '%';

                            $cek_jadwal = Jadwal::where('no_quotation', $dataQuotation->no_document)
                                ->where('is_active', 1)
                                ->where('periode', $periode)
                                ->where('kategori', 'like', $search_kategori)
                                ->select('tanggal', 'kategori')
                                ->groupBy('tanggal', 'kategori')
                                ->first();

                            $vv->kategori_2 = $data_qt_baru->kategori_1;
                            $vv->kategori_3 = $data_qt_baru->kategori_2;
                            $vv->parameter = json_encode($data_qt_baru->parameter);
                            $vv->regulasi = json_encode($data_qt_baru->regulasi);
                            if (isset($cek_jadwal->tanggal))
                                $vv->tanggal_sampling = $cek_jadwal->tanggal;
                            $vv->updated_at = Carbon::now()->format('Y-m-d H:i:s');

                            $par = [];
                            foreach ($data_qt_baru->parameter as $kc => $vk) {
                                array_push($par, explode(';', $vk)[1]);
                            }


                            if (($kk + 1) <= (int) $data_qt_baru->jumlah_titik) {
                                $vv->is_active = 1;
                            } else {
                                $vv->is_active = 0;
                            }

                            $vv->save();
                        }

                        if ((int) $data_qt_baru->jumlah_titik > (int) $data_qt_lama->jumlah_titik) {
                            /**
                             * Apabila ada penambahan titik
                             */
                            $selisih = (int) $data_qt_baru->jumlah_titik - (int) $data_qt_lama->jumlah_titik;
                            $data_qt_baru->jumlah_titik = $selisih;
                            $penambahan_data[$periode][] = $data_qt_baru;
                        }
                    }
                }
            }

            if ($penambahan_data != null) {
                $cek_detail = OrderDetail::where('id_order_header', $data_lama->id_order)
                    // ->where('active', 0)
                    ->orderBy('no_sampel', 'DESC')
                    ->first();

                $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
                $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
                $no = $no_urut_sample;
                $trigger = 0;
                $kategori = '';
                $regulasi = [];

                foreach ($penambahan_data as $periode => $values) {
                    $periode_kontrak = $periode;
                    foreach ($values as $key => $value) {
                        for ($f = 0; $f < $value->jumlah_titik; $f++) {
                            // =================================================================
                            $no++;
                            $no_sample = $no_order . '/' . sprintf("%03d", $no);
                            /*
                             * Disini bagian pembuatan no sample dan no cfr/lhp
                             * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                             * Jika kategori sama maka no akan di increment
                             * Jika kategori tidak sama maka no akan di reset menjadi 0
                             * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                             */
                            if ($value->kategori_1 == '1-Air') {
                                $no_urut_cfr++;
                                $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                            } else {
                                if (count($value->parameter) <= 2) {
                                    if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                        $no_urut_cfr++;
                                    } else {
                                        $trim = 0;
                                        if ($key != 0)
                                            $trim = ($key - 1);
                                        $nan_ = $values[$trim];
                                        if (
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($nan_->parameter) > 2) ||
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                                        ) {
                                            $no_urut_cfr++;
                                        }
                                    }
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                                } else {
                                    $no_urut_cfr++;
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                                }
                            }

                            $rand_str = strtoupper(md5($no_sample));
                            for ($i = 1; $i <= 5; $i++) {
                                $no_sampling = self::randomstr($rand_str);
                                $cek_no_sampling = DB::table('order_detail')->where('koding_sampling', $no_sampling)->first();
                                if ($cek_no_sampling == null) {
                                    break;
                                }
                            }

                            $number_imaginer = sprintf("%03d", $no);
                            $tanggal_sampling = $periode_kontrak . '-01';
                            //dedi 2025-02-14
                            if ($dataQuotation->status_sampling != 'SD') {
                                foreach ($dataJadwal as $sampling_plan) {
                                    if ($sampling_plan['periode_kontrak'] == $periode_kontrak) {
                                        foreach ($sampling_plan['jadwal'] as $jadwal) {
                                            foreach ($jadwal['kategori'] as $kategori) {
                                                if (explode(' - ', $kategori)[1] == $number_imaginer) {
                                                    $tanggal_sampling = $jadwal['tanggal'];
                                                    break 2;
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            }

                            $penamaan_titik = $value->penamaan_titik;
                            if (is_array($value->penamaan_titik)) {
                                $penamaan_titik = isset($value->penamaan_titik[$f]) ? $value->penamaan_titik[$f] : '';
                            }

                            // =================================================================
                            $DataOrderDetail = new OrderDetail;
                            $DataOrderDetail->id_order_header = $data_lama->id_order;
                            $DataOrderDetail->no_order = $data_lama->no_order;
                            $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                            $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                            $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                            $DataOrderDetail->no_sampel = $no_sample;
                            $DataOrderDetail->koding_sampling = $no_sampling;
                            $DataOrderDetail->kontrak = 'C';
                            $DataOrderDetail->periode = $periode_kontrak;
                            $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                            $DataOrderDetail->kategori_1 = $value->status_sampling;
                            $DataOrderDetail->kategori_2 = $value->kategori_1;
                            $DataOrderDetail->kategori_3 = $value->kategori_2;
                            $DataOrderDetail->cfr = $no_cfr;
                            $DataOrderDetail->keterangan_1 = $penamaan_titik;
                            $DataOrderDetail->parameter = json_encode($value->parameter);
                            $DataOrderDetail->regulasi = json_encode($value->regulasi);
                            $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                            $DataOrderDetail->created_by = $this->karyawan;
                            $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                            $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                            // =================================================================

                            if (!file_exists(public_path() . '/barcode/sampling')) {
                                mkdir(public_path() . '/barcode/sampling', 0777, true);
                            }

                            file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                            if (!file_exists(public_path() . '/barcode/sample')) {
                                mkdir(public_path() . '/barcode/sample', 0777, true);
                            }

                            file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                            if (explode("-", $value->kategori_1)[1] == 'Air') {

                                $parameter_names = array_map(function ($p) {
                                    return explode(';', $p)[1];
                                }, $value->parameter);

                                $id_kategori = explode("-", $value->kategori_1)[0];

                                $params = HargaParameter::where('id_kategori', $id_kategori)
                                    ->where('is_active', true)
                                    ->whereIn('nama_parameter', $parameter_names)
                                    ->get();

                                $param_map = [];
                                foreach ($params as $param) {
                                    $param_map[$param->nama_parameter] = $param;
                                }

                                $botol_volumes = [];
                                foreach ($value->parameter as $parameter) {
                                    $param_name = explode(';', $parameter)[1];
                                    if (isset($param_map[$param_name])) {
                                        $param = $param_map[$param_name];
                                        if (!isset($botol_volumes[$param->regen])) {
                                            $botol_volumes[$param->regen] = 0;
                                        }
                                        $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                                    }
                                }

                                // Generate botol dan barcode
                                $botol = [];

                                $ketentuan_botol = [
                                    'ORI' => 1000,
                                    'H2SO4' => 1000,
                                    'M100' => 100,
                                    'HNO3' => 500,
                                    'M1000' => 1000,
                                    'BENTHOS' => 100
                                ];

                                foreach ($botol_volumes as $type => $volume) {
                                    $koding = $no_sampling . strtoupper(Str::random(5));

                                    // Hitung jumlah botol yang dibutuhkan
                                    $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                                    $botol[] = (object) [
                                        'koding' => $koding,
                                        'type_botol' => $type,
                                        'volume' => $volume,
                                        'file' => $koding . '.png',
                                        'disiapkan' => $jumlah_botol
                                    ];

                                    if (!file_exists(public_path() . '/barcode/botol')) {
                                        mkdir(public_path() . '/barcode/botol', 0777, true);
                                    }

                                    file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                }

                                $DataOrderDetail->persiapan = json_encode($botol);
                            } else {
                                /*
                                 * Jika kategori bukan air maka tidak perlu membuat botol
                                 * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                                 */
                                if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                                    $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                        ->whereIn('parameter', $value->parameter)
                                        ->get();

                                    foreach ($cek_ketentuan_parameter as $ketentuan) {
                                        $koding = $no_sampling . strtoupper(Str::random(5));
                                        $persiapan[] = [
                                            'parameter' => \explode(';', $ketentuan->parameter)[1],
                                            'disiapkan' => $ketentuan->ketentuan,
                                            'koding' => $koding,
                                            'file' => $koding . '.png'
                                        ];

                                        if (!file_exists(public_path() . '/barcode/penjerap')) {
                                            mkdir(public_path() . '/barcode/penjerap', 0777, true);
                                        }

                                        file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                    }
                                    //2025-03-01 18:28
                                    $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                                }
                            }

                            $DataOrderDetail->save();

                            Ftc::create([
                                'no_sample' => $no_sample
                            ]);

                            FtcT::create([
                                'no_sample' => $no_sample
                            ]);

                            foreach ($value->parameter as $v) {
                                $insert_analisa[] = [
                                    'no_order' => $no_order,
                                    'no_sampel' => $no_sample,
                                    'tanggal_order' => Carbon::now()->format('Y-m-d'),
                                    'parameter' => $v
                                ];
                            }

                            ParameterAnalisa::insert($insert_analisa);

                            $kategori = $value->kategori_2;
                            $regulasi = $value->regulasi;
                        }
                    }
                }
            }

            if ($pengurangan_data != null) {
                // non aktifkan data
                // Memisahkan array yang memiliki status_sampling dan yang tidak
                $data_with_sampling = [];
                $data_without_sampling = [];

                foreach ($pengurangan_data as $key => $value) {
                    foreach ($value as $keys => $values) {
                        if (isset($values->status_sampling) && $values->status_sampling != null) {
                            // Menyimpan data dengan status_sampling ke array $data_with_sampling
                            $data_with_sampling[] = [
                                'key' => $key,
                                'value' => $values
                            ];
                        } else {
                            // Menyimpan data yang tidak memiliki status_sampling ke array $data_without_sampling
                            $data_without_sampling[] = [
                                'key' => $key,
                                'value' => $values
                            ];
                        }
                    }
                }

                // Mengelompokkan data tanpa status_sampling berdasarkan periode
                $data_without_sampling_grouped = [];

                foreach ($data_without_sampling as $data) {
                    $key = $data['key']; // Ambil periode sebagai kunci

                    // Simpan periode dalam array jika belum ada
                    if (!isset($data_without_sampling_grouped[$key])) {
                        $data_without_sampling_grouped[$key] = true; // Set true untuk menandai periode tersebut ada
                    }
                }
                // dd($data_without_sampling_grouped, $data_without_sampling, $data_with_sampling);
                // Proses data yang memiliki status_sampling
                foreach ($data_with_sampling as $data) {
                    $key = $data['key'];
                    $values = $data['value'];

                    // Proses data dengan status_sampling
                    $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                        ->where('periode', $key)
                        ->where('kategori_2', $values->kategori_1)
                        ->where('kategori_3', $values->kategori_2)
                        ->where('regulasi', str_replace('\/', '/', json_encode($values->regulasi)))
                        ->where('is_active', 1)
                        ->orderBy('no_sampel', 'DESC')
                        ->get()
                        ->filter(function ($item) use ($values, $key) {
                            return collect(json_decode($item->parameter))->sort()->values()->all() == collect($values->parameter)->sort()->values()->all();
                        });
                    // ->where('parameter', json_encode($values->parameter))
                    // ->where('regulasi', json_encode($values->regulasi))
                    // ->where('is_active', 1)
                    // ->orderBy('no_sampel', 'DESC')
                    // ->get();

                    $titik = $cek_order_detail_lama->take($values->jumlah_titik);

                    if ($cek_order_detail_lama != null) {
                        foreach ($titik as $hh => $change) {
                            // Set active pada objek $change
                            $change->is_active = 0;
                            $change->save();
                        }
                    }
                }

                // Proses data yang tidak memiliki status_sampling, hanya ambil periode-nya saja
                foreach (array_keys($data_without_sampling_grouped) as $key_periode) {
                    // dd($key_periode);
                    // Lakukan proses sekali untuk setiap periode (key_periode)
                    $cek_order_detail_lama = OrderDetail::where('id_order_header', $data_lama->id_order)
                        ->where('periode', $key_periode)
                        ->get();

                    $titik = $cek_order_detail_lama;

                    if ($cek_order_detail_lama != null) {
                        foreach ($titik as $hh => $change) {
                            // Set active pada objek $change
                            $change->is_active = 0;
                            $change->save();
                        }
                    }
                }
            }

            // $updateHeader = OrderHeader::where('no_order', $no_order)->where('is_active', true)->first();
            $updateHeader = OrderHeader::find($data_lama->id_order);

            $updateHeader->no_document = $dataQuotation->no_document;
            $updateHeader->flag_status = 'ordered';
            $updateHeader->is_revisi = 0;
            $updateHeader->id_cabang = $dataQuotation->id_cabang;
            $updateHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $updateHeader->konsultan = $dataQuotation->konsultan;
            $updateHeader->alamat_kantor = $dataQuotation->alamat_kantor;
            $updateHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $updateHeader->nama_pic_order = $dataQuotation->nama_pic_order;
            $updateHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $updateHeader->no_pic_order = $dataQuotation->no_pic_order;
            $updateHeader->email_pic_order = $dataQuotation->email_pic_order;
            $updateHeader->alamat_sampling = $dataQuotation->alamat_sampling;
            $updateHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $updateHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $updateHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $updateHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $updateHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $updateHeader->kategori_customer = $dataQuotation->kategori_customer;
            $updateHeader->sub_kategori = $dataQuotation->sub_kategori;
            $updateHeader->bahan_customer = $dataQuotation->bahan_customer;
            $updateHeader->merk_customer = $dataQuotation->merk_customer;
            $updateHeader->status_wilayah = $dataQuotation->status_wilayah;
            $updateHeader->total_ppn = $dataQuotation->total_ppn;
            $updateHeader->grand_total = $dataQuotation->grand_total;
            $updateHeader->total_dicount = $dataQuotation->total_dicount;
            $updateHeader->total_dpp = $dataQuotation->total_dpp;
            $updateHeader->piutang = $dataQuotation->piutang;
            $updateHeader->biaya_akhir = $dataQuotation->biaya_akhir;
            $updateHeader->wilayah = $dataQuotation->wilayah;
            $updateHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $updateHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $updateHeader->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
            $updateHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $updateHeader->updated_by = $this->karyawan;
            $updateHeader->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $updateHeader->save();

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            // update data sampelDiantar
            $sampelSD = SampelDiantar::where('no_quotation', $data_lama->no_qt)
                ->where('no_order', $data_lama->no_order)->first();
            if ($sampelSD !== null) {
                $sampelSD->no_order = $updateHeader->no_order;
                $sampelSD->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $sampelSD->no_quotation = $dataQuotation->no_document;
                $sampelSD->save();
            }

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            $data_detail_baru = OrderDetail::where('id_order_header', $data_lama->id_order)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $data_to_log = [
                'data_lama' => $data_detail_lama->toArray(),
                'data_baru' => $data_detail_baru->toArray()
            ];

            $workerOperation = new WorkerOperation();
            $workerOperation->index($updateHeader, $data_to_log, GetAtasan::where('user_id', $this->user_id)->get()->pluck('email')->toArray());
            // $workerOperation->index($updateHeader, $data_to_log, []);

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Re-Generate Order kontrak berhasil'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }

    public function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }
}
