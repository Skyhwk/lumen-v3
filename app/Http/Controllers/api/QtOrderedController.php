<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\QuotationNonKontrak;
use App\Models\{AlasanVoidQt, QuotationKontrakH, QuotationKontrakD};
use App\Models\MasterCabang;
use App\Models\OrderHeader;
use App\Models\SamplingPlan;
use App\Models\OrderDetail;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use App\Models\Ftc;
use App\Models\Ftct;
use App\Models\JobTask;
use Validator;
use App\Jobs\RenderPdfPenawaran;
use App\Jobs\CopyNonKontrakJob;
use App\Jobs\CopyKontrakJob;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\SamplingPlanServices;
use App\Jobs\RenderSamplingPlan;
use Carbon\Carbon;

// TAMBAHAN LIBRARY EXCEL
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class QtOrderedController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with(['sales', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
                    ->select('request_quotation.*') // tambahkan ini
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->where('request_quotation.flag_status', 'ordered')
                    ->where('request_quotation.is_approved', true)
                    ->where('request_quotation.is_emailed', true)
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc')
                    ->orderBy('request_quotation.id', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with(['sales', 'detail', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
                    ->select('request_quotation_kontrak_H.*')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->where('request_quotation_kontrak_H.flag_status', 'ordered')
                    ->where('request_quotation_kontrak_H.is_approved', true)
                    ->where('request_quotation_kontrak_H.is_emailed', true)
                    ->whereYear('request_quotation_kontrak_H.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation_kontrak_H.tanggal_penawaran', 'desc')
                    ->orderBy('request_quotation_kontrak_H.id', 'desc');
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
                ->addColumn('no_po', function ($row) {
                    if (is_null($row->konfirmasi)) {
                        return '-';
                    }
                    
                    if (is_iterable($row->konfirmasi)) {
                        // konfirmasi is a collection or array
                        $poList = collect($row->konfirmasi)
                            ->pluck('no_purchaseorder')
                            ->filter(fn ($po) => !is_null($po) && trim($po) !== '')
                            ->unique()
                            ->implode(', ');
                        return $poList ?: '-';
                    } elseif ($row->konfirmasi && !empty($row->konfirmasi->no_purchaseorder)) {
                        // single object
                        $po = $row->konfirmasi->no_purchaseorder;
                        return (!is_null($po) && trim($po) !== '') ? $po : '-';
                    } else if ($row->konfirmasi && empty($row->konfirmasi->no_purchaseorder)) {
                        return $row->konfirmasi->keterangan_approval_order;
                    } else {
                        return '-';
                    }
                })
                ->filterColumn('order.no_order', function ($query, $keyword) {
                    $query->whereHas('order', function ($query) use ($keyword) {
                        $query->where('no_order', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('no_po', function ($query, $keyword) {
                    $query->whereHas('konfirmasi', function ($q) use ($keyword) {
                        $q->whereNotNull('no_purchaseorder')
                            ->where('no_purchaseorder', '!=', '')
                            ->where('no_purchaseorder', 'like', '%' . $keyword . '%');
                        $q->orWhere('keterangan_approval_order', 'like', '%' . $keyword . '%');
                    });
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with(['sales', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
                    ->select('request_quotation.*') // tambahkan ini
                    ->where('request_quotation.id_cabang', $request->cabang)
                    ->where('request_quotation.flag_status', 'ordered')
                    ->where('request_quotation.is_approved', true)
                    ->where('request_quotation.is_emailed', true)
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc')
                    ->orderBy('request_quotation.id', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with(['sales', 'detail', 'sampling', 'konfirmasi', 'order:no_order,no_document'])
                    ->select('request_quotation_kontrak_H.*')
                    ->where('request_quotation_kontrak_H.id_cabang', $request->cabang)
                    ->where('request_quotation_kontrak_H.flag_status', 'ordered')
                    ->where('request_quotation_kontrak_H.is_approved', true)
                    ->where('request_quotation_kontrak_H.is_emailed', true)
                    ->whereYear('request_quotation_kontrak_H.tanggal_penawaran', $request->year)
                    ->orderBy('request_quotation_kontrak_H.tanggal_penawaran', 'desc')
                    ->orderBy('request_quotation_kontrak_H.id', 'desc');
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
            $sheet->setCellValue('A1', 'REPORT QUOTATION ORDERED');
            $sheet->mergeCells('A1:W1'); // Merge sampe W (nambah kolom)
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // --- SUB JUDUL ---
            $sheet->setCellValue('A2', $request->year . ' | ' . strtoupper(str_replace('_', ' ', $request->mode)));
            $sheet->mergeCells('A2:W2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // --- HEADER TABEL (Sesuai Frontend) ---
            $startRow = 4;
            $headers = [
                'No', 
                'Kode Promo', 
                'ID Pelanggan', 
                'No Quotation', 
                'No Order', 
                'No PO', 
                'Nama Perusahaan', 
                'Status', 
                'Status Sampling', 
                'Ket Reject SP', 
                'SP By', 
                'No Tlp Perusahaan', 
                'Konsultan', 
                'PIC Order', 
                'No Tlp PIC', 
                'Keterangan',
                'Total Price', 
                'Total Discount', 
                'Nilai PPN', 
                'Nilai PPh',     // TAMBAHAN
                'Nilai Tagihan', // TAMBAHAN
                'Nama Sales', 
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
            $sheet->getStyle('A' . $startRow . ':W' . $startRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($startRow)->setRowHeight(25);

            // --- ISI DATA ---
            $rowNum = $startRow + 1;
            $no = 1;

            foreach ($data as $row) {
                // Logic No PO
                $noPO = '-';
                if (!is_null($row->konfirmasi)) {
                    if (is_iterable($row->konfirmasi)) {
                        $poList = collect($row->konfirmasi)
                            ->pluck('no_purchaseorder')
                            ->filter(fn ($po) => !is_null($po) && trim($po) !== '')
                            ->unique()
                            ->implode(', ');
                        $noPO = $poList ?: '-';
                    } elseif ($row->konfirmasi && !empty($row->konfirmasi->no_purchaseorder)) {
                        $po = $row->konfirmasi->no_purchaseorder;
                        $noPO = (!is_null($po) && trim($po) !== '') ? $po : '-';
                    } else if ($row->konfirmasi && empty($row->konfirmasi->no_purchaseorder)) {
                        $noPO = $row->konfirmasi->keterangan_approval_order;
                    }
                }

                // Format Tanggal Indo
                $createdAt = $row->created_at 
                    ? \Carbon\Carbon::parse($row->created_at)->locale('id')->translatedFormat('d F Y H:i') 
                    : '-';

                $sheet->setCellValue('A' . $rowNum, $no++);
                $sheet->setCellValue('B' . $rowNum, $row->kode_promo ?? '-');
                $sheet->setCellValue('C' . $rowNum, $row->pelanggan_ID);
                $sheet->setCellValue('D' . $rowNum, $row->no_document);
                $sheet->setCellValue('E' . $rowNum, $row->order->no_order ?? '-');
                $sheet->setCellValue('F' . $rowNum, $noPO);
                $sheet->setCellValue('G' . $rowNum, $row->nama_perusahaan);
                $sheet->setCellValue('H' . $rowNum, $row->flag_status);
                $sheet->setCellValue('I' . $rowNum, $row->status_sampling ?? '-');
                $sheet->setCellValue('J' . $rowNum, $row->ket_reject_sp ?? '-');
                $sheet->setCellValue('K' . $rowNum, $row->sp_by ?? '-');
                
                // No Tlp
                $sheet->setCellValueExplicit('L' . $rowNum, $row->no_tlp_perusahaan ?? '-', DataType::TYPE_STRING);
                
                $sheet->setCellValue('M' . $rowNum, $row->konsultan ?? '-');
                $sheet->setCellValue('N' . $rowNum, $row->nama_pic_order ?? '-');
                
                // No Tlp PIC
                $sheet->setCellValueExplicit('O' . $rowNum, $row->no_tlp_pic_order ?? '-', DataType::TYPE_STRING);
                
                $sheet->setCellValue('P' . $rowNum, $row->keterangan ?? '-');
                
                // Angka Duit
                $sheet->setCellValue('Q' . $rowNum, $row->grand_total);
                $sheet->setCellValue('R' . $rowNum, $row->total_discount);
                $sheet->setCellValue('S' . $rowNum, $row->total_ppn);
                $sheet->setCellValue('T' . $rowNum, $row->total_pph); // Masukin PPH
                $sheet->setCellValue('U' . $rowNum, $row->piutang);   // Masukin Piutang
                
                $sheet->setCellValue('V' . $rowNum, $row->sales->nama_lengkap ?? '-');
                $sheet->setCellValue('W' . $rowNum, $createdAt);

                // Styling Promo
                if ($row->kode_promo) {
                    $sheet->getStyle('A' . $rowNum . ':W' . $rowNum)->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BEE5EB']]
                    ]);
                }

                $rowNum++;
            }

            // --- FINISHING ---
            $lastRow = $rowNum - 1;
            $sheet->getStyle('A' . $startRow . ':W' . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
            ]);

            // Format Currency (Q sampe U)
            $sheet->getStyle('Q'.($startRow+1).':U' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            
            // Alignment Center
            $alignCenterCols = ['A', 'C', 'H', 'I', 'W'];
            foreach ($alignCenterCols as $col) {
                $sheet->getStyle($col.($startRow+1).':'.$col.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Auto Fit Columns
            foreach (range('A', 'W') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Report_Qt_Ordered_' . date('YmdHis') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCabang(Request $request)
    {
        $data = MasterCabang::where('is_active', true)->get();
        return response()->json($data);
    }

    public function reschedule(Request $request)
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

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->mode == 'non_kontrak' || $request->mode == "null") {
                $data = QuotationNonKontrak::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation';
                if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                    $data->is_ready_order = 1;
                }
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation_kontrak';
            }

            $data->is_approved = false;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->keterangan_reject = $request->keterangan_reject;

            $order_h = OrderHeader::where('no_document', $data->no_document)->first();
            $order_h->is_revisi = true;
            $order_h->save();

            $json = [
                'id_qt' => $data->id,
                'no_qt' => $order_h->no_document,
                'no_order' => $order_h->no_order,
                'id_order' => $order_h->id,
                'status_sp' => $request->perubahan_sp
            ];

            $data->data_lama = json_encode($json);
            $data->flag_status = 'rejected';
            $data->is_rejected = true;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();

            DB::commit();
            return response()
                ->json(['message' => 'Success Reject Quotation Order!', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function copy(Request $request)
    {
        // DB::beginTransaction();
        try {
            if ($request->status_quotation == 'non_kontrak' || $request->status_quotation == 'null') {
                $job = new CopyNonKontrakJob($this->idcabang, $this->karyawan, $request->id);
                $this->dispatch($job);

                sleep(3);

                return response()->json([
                    'message' => "Penawaran berhasil dibuat dengan nomor dokumen",
                ], 200);
            } else {
                $job = new CopyKontrakJob($this->idcabang, $this->karyawan, $request->id);
                $this->dispatch($job);

                sleep(3);
                return response()->json(['message' => "Penawaran berhasil dibuat dengan nomor dokumen", 'status' => 200], 200);
            }
        } catch (\Throwable $th) {
            // DB::rollback();
            dd($th);
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function void(Request $request)
    {
        /*DB::beginTransaction();
        try {
            if ($request->mode == 'non_kontrak' || $request->mode == "null") {
                $data = QuotationNonKontrak::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation';
                if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                    $data->is_ready_order = 1;
                }
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::where('is_active', true)
                    ->where('id', $request->id)
                    ->first();
                $type_doc = 'quotation_kontrak';
            }
            $order_h = OrderHeader::where('no_document', $data->no_document)
                ->update(['is_active' => false, 'is_revisi' => 1]);

            $get_id_header = OrderHeader::where('no_document', $data->no_document)
                ->first();

            // UPDATE TABLE DETAIL MENJADI NON AKTIF
            $order_h = OrderDetail::where('id_order_header', $get_id_header->id)
                ->update(['is_active' => false]);

            // UPDATE SAMPLING PLAN TABLE
            $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                // ->get();
                ->update(['is_active' => false]);

            // if($sampling_plan->isNotEmpty()){

            // }

            // UPDATE JADWAL
            $jadwal = Jadwal::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // UPDATE TABLE PRODUKSI
            // DB::connection(env('DB_PRODUKSI'))->table('t_po')->where('no_order', $get_id_header->no_order)->update(['active' => 1]);

            // $order_d = DB::table('order_detail')->where('id_order_header', $get_id_header->id)->get();

            // foreach($order_d as $key => $v){
            //     // UPDATE TABLE PRODUKSI
            //     DB::connection(env('DB_PRODUKSI'))->table('t_ftc')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            //     DB::connection(env('DB_PRODUKSI'))->table('t_ftc_t')->where('no_sample', $v->no_sample)->update(['active' => 1]);

            //     // UPDATE TABLE APPS BARU
            //     DB::table('t_ftc')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            //     DB::table('t_ftc_t')->where('no_sample', $v->no_sample)->update(['active' => 1]);
            // }

            $data->flag_status = 'void';
            $data->is_active = false;
            $data->document_status = 'Non Aktif';
            $data->save();

            DB::commit();
            return response()
                ->json(['message' => 'Success Void Quotation Order!', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
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

                $order_h = OrderHeader::where('no_document', $data->no_document)->first();
                $order_h->is_active = false;
                $order_h->is_revisi = true;
                $order_h->save();

                $order_d = OrderDetail::where('id_order_header', $order_h->id);
                $no_sampels = $order_d->pluck('no_sampel');
                $order_d->update(['is_active' => false]);

                Ftc::whereIn('no_sample', $no_sampels)->update(['is_active' => false]);
                FtcT::whereIn('no_sample', $no_sampels)->update(['is_active' => false]);

                $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)->update(['is_active' => false]);
                $jadwal = Jadwal::where('no_quotation', $data->no_document)->update(['is_active' => false]);

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
}
