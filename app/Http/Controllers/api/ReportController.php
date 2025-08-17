<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderDetail;
use Carbon\Carbon;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yajra\Datatables\Datatables;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with(['TrackingSatu', 'TrackingDua'])->where('order_detail.is_active', true);
        return Datatables::of($data)
            // Start Add Column
            ->addColumn('tanggal_terima', function ($row) {
                return $row->tanggal_sampling;
            })
            ->addColumn('ftc_sd', function ($row) {
                return optional($row->TrackingSatu)->ftc_sd;
            })
            ->addColumn('ftc_sample', function ($row) {
                return optional($row->TrackingSatu)->ftc_sample;
            })
            ->addColumn('ftc_verifier', function ($row) {
                return optional($row->TrackingSatu)->ftc_verifier;
            })
            ->addColumn('ftc_laboratory', function ($row) {
                return optional($row->TrackingSatu)->ftc_laboratory;
            })
            ->addColumn('ftc_fd_sampling', function ($row) {
                return optional($row->TrackingSatu)->ftc_fd_sampling;
            })
            ->addColumn('ftc_fd_lab', function ($row) {
                return optional($row->TrackingSatu)->ftc_fd_lab;
            })
            ->addColumn('ftc_analysis_result_lab', function ($row) {
                return optional($row->TrackingSatu)->ftc_analysis_result_lab;
            })
            ->addColumn('ftc_analysis_admin', function ($row) {
                return optional($row->TrackingSatu)->ftc_analysis_admin;
            })
            ->addColumn('ftc_draft_admin', function ($row) {
                return optional($row->TrackingSatu)->ftc_draft_admin;
            })
            ->addColumn('ftc_draft_tc_result', function ($row) {
                return optional($row->TrackingSatu)->ftc_draft_tc_result;
            })
            ->addColumn('ftc_draft_tc_result_2', function ($row) {
                return optional($row->TrackingSatu)->ftc_draft_tc_result_2;
            })
            ->addColumn('ftc_draft_verifier', function ($row) {
                return optional($row->TrackingSatu)->ftc_draft_verifier;
            })
            ->addColumn('ftc_draft_send', function ($row) {
                return optional($row->TrackingDua)->ftc_draft_send;
            })
            ->addColumn('ftc_draft_send_a', function ($row) {
                return optional($row->TrackingDua)->ftc_draft_send_a;
            })
            ->addColumn('ftc_lhp_request', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_request;
            })
            ->addColumn('ftc_lhp_verifier', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_verifier;
            })
            ->addColumn('ftc_lhp_print', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_print;
            })
            ->addColumn('ftc_lhp_verifier_a', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_verifier_a;
            })
            ->addColumn('ftc_lhp_approval', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_approval;
            })
            ->addColumn('ftc_lhp_finance', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_finance;
            })
            ->addColumn('ftc_lhp_distribute', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_distribute;
            })
            ->addColumn('ftc_lhp_distribute_2', function ($row) {
                return optional($row->TrackingDua)->ftc_lhp_distribute_2;
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('no_sampel', $order);
            })
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('tanggal_sampling', $order);
            })
            ->orderColumn('cfr', function ($query, $order) {
                $query->orderBy('cfr', $order);
            })
            ->orderColumn('nama_perusahaan', function ($query, $order) {
                $query->orderBy('nama_perusahaan', $order);
            })
            ->orderColumn('ftc_sd', function ($query, $order) {
                $query->orderBy('ftc_sd', $order);
            })
            ->orderColumn('ftc_sample', function ($query, $order) {
                $query->orderBy('ftc_sample', $order);
            })
            ->orderColumn('ftc_verifier', function ($query, $order) {
                $query->orderBy('ftc_verifier', $order);
            })
            ->orderColumn('ftc_laboratory', function ($query, $order) {
                $query->orderBy('ftc_laboratory', $order);
            })
            ->orderColumn('ftc_fd_sampling', function ($query, $order) {
                $query->orderBy('ftc_fd_sampling', $order);
            })
            ->orderColumn('ftc_fd_lab', function ($query, $order) {
                $query->orderBy('ftc_fd_lab', $order);
            })
            ->orderColumn('ftc_analysis_result_lab', function ($query, $order) {
                $query->orderBy('ftc_analysis_result_lab', $order);
            })
            ->orderColumn('ftc_analysis_admin', function ($query, $order) {
                $query->orderBy('ftc_analysis_admin', $order);
            })
            ->orderColumn('ftc_draft_admin', function ($query, $order) {
                $query->orderBy('ftc_draft_admin', $order);
            })
            ->orderColumn('ftc_draft_tc_result', function ($query, $order) {
                $query->orderBy('ftc_draft_tc_result', $order);
            })
            ->orderColumn('ftc_draft_tc_result_2', function ($query, $order) {
                $query->orderBy('ftc_draft_tc_result_2', $order);
            })
            ->orderColumn('ftc_draft_verifier', function ($query, $order) {
                $query->orderBy('ftc_draft_verifier', $order);
            })
            ->orderColumn('ftc_draft_send', function ($query, $order) {
                $query->orderBy('ftc_draft_send', $order);
            })
            ->orderColumn('ftc_draft_send_a', function ($query, $order) {
                $query->orderBy('ftc_draft_send_a', $order);
            })
            ->orderColumn('ftc_lhp_request', function ($query, $order) {
                $query->orderBy('ftc_lhp_request', $order);
            })
            ->orderColumn('ftc_lhp_verifier', function ($query, $order) {
                $query->orderBy('ftc_lhp_verifier', $order);
            })
            ->orderColumn('ftc_lhp_print', function ($query, $order) {
                $query->orderBy('ftc_lhp_print', $order);
            })
            ->orderColumn('ftc_lhp_verifier_a', function ($query, $order) {
                $query->orderBy('ftc_lhp_verifier_a', $order);
            })
            ->orderColumn('ftc_lhp_approval', function ($query, $order) {
                $query->orderBy('ftc_lhp_approval', $order);
            })
            ->orderColumn('ftc_lhp_finance', function ($query, $order) {
                $query->orderBy('ftc_lhp_finance', $order);
            })
            ->orderColumn('ftc_lhp_distribute', function ($query, $order) {
                $query->orderBy('ftc_lhp_distribute', $order);
            })
            ->orderColumn('ftc_lhp_distribute_2', function ($query, $order) {
                $query->orderBy('ftc_lhp_distribute_2', $order);
            })
            ->filter(function ($query) use ($request) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'];
                            $searchValue = $column['search']['value'];

                            // Skip columns that aren't searchable
                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }

                            // Special handling for date fields in order_details
                            if ($columnName === 'tanggal_terima') {
                                $query->whereDate('order_detail.tanggal_sampling', 'like', "%{$searchValue}%");
                            } elseif ($columnName === 'created_at') {
                                $query->whereDate('order_detail.created_at', 'like', "%{$searchValue}%");
                            }
                            // Handle TrackingSatu datetime fields
                            elseif (in_array($columnName, [
                                'ftc_sd',
                                'ftc_sample',
                                'ftc_verifier',
                                'ftc_laboratory',
                                'ftc_fd_sampling',
                                'ftc_fd_lab',
                                'ftc_analysis_result_lab',
                                'ftc_analysis_admin',
                                'ftc_draft_admin',
                                'ftc_draft_tc_result',
                                'ftc_draft_tc_result_2',
                                'ftc_draft_verifier'
                            ])) {
                                $query->whereHas('TrackingSatu', function ($q) use ($columnName, $searchValue) {
                                    $q->whereRaw("DATE_FORMAT({$columnName}, '%Y-%m-%d') LIKE ?", ["%{$searchValue}%"]);
                                });
                            }
                            // Handle TrackingDua datetime fields
                            elseif (in_array($columnName, [
                                'ftc_draft_send',
                                'ftc_draft_send_a',
                                'ftc_lhp_request',
                                'ftc_lhp_verifier',
                                'ftc_lhp_print',
                                'ftc_lhp_verifier_a',
                                'ftc_lhp_approval',
                                'ftc_lhp_finance',
                                'ftc_lhp_distribute',
                                'ftc_lhp_distribute_2'
                            ])) {
                                $query->whereHas('TrackingDua', function ($q) use ($columnName, $searchValue) {
                                    $q->whereRaw("DATE_FORMAT({$columnName}, '%Y-%m-%d') LIKE ?", ["%{$searchValue}%"]);
                                });
                            }
                            // Standard text fields in order_details
                            elseif (in_array($columnName, [
                                'no_sampel',
                                'parameter',
                                'jenis_pengujian',
                                'cfr',
                                'nama_perusahaan'
                            ])) {
                                $query->where($columnName, 'like', "%{$searchValue}%");
                            }
                        }
                    }
                }
            })
            // End Order          
            ->make(true);
    }

    public function exportExcel(Request $request)
    {
        $tanggal = $request->input('date', Carbon::now()->format('Y-m-d'));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'No',
            'Tanggal Tugas',
            'CFR',
            'No Sampel',
            'Nama Perusahaan',
            'Deskripsi',
            'SD',
            'Sampel',
            'Ver Sample',
            'Lab Sample',
            'FDL',
            'Lab Data',
            'Val Data',
            'In Data',
            'Draft Doc',
            'In Draft',
            'Ver Result',
            'Draft Report',
            'In Send',
            'Sent',
            'Req Report',
            'Ver Report',
            'Print',
            'Val Report',
            'Approval',
            'Ver Finance',
            'Receipt',
            'Distributed'
        ];

        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $sheet->getStyle("A1:AB1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $baseQuery = OrderDetail::with(['TrackingSatu', 'TrackingDua'])
            ->where('order_detail.is_active', true)
            ->whereMonth('tanggal_sampling', date('m', strtotime($tanggal)))
            ->whereYear('tanggal_sampling', date('Y', strtotime($tanggal)))
            ->get();

        $rowNumber = 2;
        $i = 1;
        foreach ($baseQuery as $index => $row) {
            $sheet->setCellValue('A' . $rowNumber, $i++);
            $sheet->setCellValue('B' . $rowNumber, $row->tanggal_sampling);
            $sheet->setCellValue('C' . $rowNumber, $row->cfr);
            $sheet->setCellValue('D' . $rowNumber, $row->no_sampel);
            $sheet->setCellValue('E' . $rowNumber, $row->nama_perusahaan);
            $sheet->setCellValue('F' . $rowNumber, $row->keterangan_1);
            $sheet->setCellValue('G' . $rowNumber, $row->TrackingSatu->ftc_sd ?? '-');
            $sheet->setCellValue('H' . $rowNumber, $row->TrackingSatu->ftc_sample ?? '-');
            $sheet->setCellValue('I' . $rowNumber, $row->TrackingSatu->ftc_verifier ?? '-');
            $sheet->setCellValue('J' . $rowNumber, $row->TrackingSatu->ftc_laboratory ?? '-');
            $sheet->setCellValue('K' . $rowNumber, $row->TrackingSatu->ftc_fd_sampling ?? '-');
            $sheet->setCellValue('L' . $rowNumber, $row->TrackingSatu->ftc_fd_lab ?? '-');
            $sheet->setCellValue('M' . $rowNumber, $row->TrackingSatu->ftc_analysis_result_lab ?? '-');
            $sheet->setCellValue('N' . $rowNumber, $row->TrackingSatu->ftc_analysis_admin ?? '-');
            $sheet->setCellValue('O' . $rowNumber, $row->TrackingSatu->ftc_draft_admin ?? '-');
            $sheet->setCellValue('P' . $rowNumber, $row->TrackingSatu->ftc_draft_tc_result ?? '-');
            $sheet->setCellValue('Q' . $rowNumber, $row->TrackingSatu->ftc_draft_tc_result_2 ?? '-');
            $sheet->setCellValue('R' . $rowNumber, $row->TrackingSatu->ftc_draft_verifier ?? '-');
            $sheet->setCellValue('S' . $rowNumber, $row->TrackingDua->ftc_draft_send ?? '-');
            $sheet->setCellValue('T' . $rowNumber, $row->TrackingDua->ftc_draft_send_a ?? '-');
            $sheet->setCellValue('U' . $rowNumber, $row->TrackingDua->ftc_lhp_request ?? '-');
            $sheet->setCellValue('V' . $rowNumber, $row->TrackingDua->ftc_lhp_verifier ?? '-');
            $sheet->setCellValue('W' . $rowNumber, $row->TrackingDua->ftc_lhp_print ?? '-');
            $sheet->setCellValue('X' . $rowNumber, $row->TrackingDua->ftc_lhp_verifier_a ?? '-');
            $sheet->setCellValue('Y' . $rowNumber, $row->TrackingDua->ftc_lhp_approval ?? '-');
            $sheet->setCellValue('Z' . $rowNumber, $row->TrackingDua->ftc_lhp_finance ?? '-');
            $sheet->setCellValue('AA' . $rowNumber, $row->TrackingDua->ftc_lhp_distribute ?? '-');
            $sheet->setCellValue('AB' . $rowNumber, $row->TrackingDua->ftc_lhp_distribute ?? '-');

            // $sheet->getStyle('K' . $rowNumber)->getAlignment()->setWrapText(true);

            $rowNumber++;

            unset($row);
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $cellRange = "A1:$highestColumn$highestRow";

        $sheet->getStyle($cellRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        // foreach (['E', 'F', 'G', 'H', 'K'] as $col) {
        //     $sheet->getStyle("{$col}2:{$col}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        // }

        $path = public_path() . '/report-tc/';
        $writer = new Xlsx($spreadsheet);
        $fileName = 'REPORT_' . str_replace('-', '_', $tanggal) . '.xlsx';
        $writer->save($path . $fileName);

        return response()->json(['data' => $fileName], 200);
    }
}
