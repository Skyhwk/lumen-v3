<?php

namespace App\Http\Controllers\api;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\SamplingPlanServices;
use App\Services\{ QuotationService};
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;
use App\Jobs\RenderSamplingPlan;
use App\Models\AlasanVoidQt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Exception;

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

    public function voidQuotation(Request $request, QuotationService $quotationService)
    {
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

                // Cek no Quotation apakah sudah ada di invoice dengan pembayaran > 0, jika iya maka tidak bisa di void
                $check = $quotationService->validateVoidQuotation($data->no_document);

                if (!$check['status']) {
                    return response()->json($check, 401);
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

    public function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }
}
