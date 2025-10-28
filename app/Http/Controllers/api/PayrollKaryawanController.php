<?php

namespace App\Http\Controllers\api;

use App\Models\PayrollHeader;
use App\Models\Payroll;
use App\Models\Kasbon;
use App\Models\PencadanganUpah;
use App\Models\DendaKaryawan;
use App\Models\MasterKaryawan;
use App\Models\RekapLiburKalender;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class PayrollKaryawanController extends Controller
{
    
    public function index(Request $request){
        try {
            $data = PayrollHeader::select(
                'payroll_header.*',
                DB::raw('SUM(payroll.take_home_pay) as total_take_home_pay'),
                DB::raw('COUNT(DISTINCT payroll.id) as total_payroll')
            )
            ->leftJoin('payroll', function($join) {
                $join->on('payroll.payroll_header_id', '=', 'payroll_header.id')
                     ->where('payroll.is_active', true);
            })
            ->where('payroll_header.is_active', true)
            ->where('payroll_header.is_approve', true)
            ->where('payroll_header.deleted_by', null)
            ->where('payroll_header.periode_payroll', 'like', $request->search . '%')
            ->groupBy(
                'payroll_header.id',
                'payroll_header.no_document',
                'payroll_header.status_karyawan',
                'payroll_header.periode_payroll',
                'payroll_header.status',
                'payroll_header.tgl_transfer',
                'payroll_header.keterangan',
                'payroll_header.is_active',
                'payroll_header.is_approve',
                'payroll_header.is_download',
                'payroll_header.created_at',
                'payroll_header.created_by',
                'payroll_header.deleted_at',
                'payroll_header.deleted_by'
            )
            ->orderBy('payroll_header.status', 'desc')
            ->orderBy('payroll_header.id', 'desc')
            ->get();

            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function transferPayroll(Request $request){
        DB::beginTransaction();
        try {
            PayrollHeader::where('id', $request->id)
                ->update(['tgl_transfer' => $request->tanggal_transfer]);
            
            DB::commit();
            return response()->json([
                'message' => 'Tanggal Transfer berhasil diatur!',
            ], 201);
            
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function approvePayroll(Request $request){
        DB::beginTransaction();
        try {
            PayrollHeader::where('id', $request->id)
                ->update([
                    'status' => 'TRANSFER',
                    'transfer_at' => DATE('Y-m-d H:i:s'),
                    'transfer_by' => $this->karyawan,
                ]);
            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil di Approve',
            ], 201);
            
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function rejectPayroll(Request $request){
        DB::beginTransaction();
        try {
            PayrollHeader::where('id', $request->id)
                ->update([
                    'is_approve' => false,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => $this->karyawan,
                    'rejected_at' => DATE('Y-m-d H:i:s')
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil di Reject',
            ], 201);
            
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function exportPerbankanBTPN(Request $request){
        
        try {
            $data = Payroll::with(['karyawan' => function ($query) {
                $query->select('id', 'nama_lengkap');
            }])->where('payroll_header_id', $request->id_header)->where('payroll.is_active', true)
            ->leftJoin('payroll_header', 'payroll.payroll_header_id', '=', 'payroll_header.id')
            ->orderBy('nik_karyawan', 'asc')
            ->get();
            // Create a new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set the header
            $sheet->setCellValue('A1', 'No. Rekening Penerima');
            $sheet->setCellValue('B1', 'Nama Penerima');
            $sheet->setCellValue('C1', 'Nominal Transfer');
            $sheet->setCellValue('D1', 'Catatan (Opsional)');

            // Fill the data starting from row 2
            $row = 2;
            
            foreach ($data as $value) {
                
                $sheet->setCellValue('A' . $row, $value->no_rekening);
                $sheet->setCellValue('B' . $row, $value->karyawan);
                $sheet->setCellValue('C' . $row, $value->take_home_pay);
                $sheet->setCellValue('D' . $row, 'PAYROLL ' . strtoupper(self::tanggal_indonesia($request->periode_payroll, "period")));
                $row++;
            }

            // Create a CSV writer
            // $writer = new Xlsx($spreadsheet);
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(','); // You can set the delimiter here
            $writer->setEnclosure('');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);

            $path = \public_path(). '/dokumen/payroll/payroll_karyawan/';
            $fileName = \str_replace(['/'], '-', $data[0]->no_document). '.csv';
            $writer->save($path.$fileName);

            $data = PayrollHeader::where('no_document',$data[0]->no_document)->update([
                'is_download' => 1,
            ]);

            return response()->json([
                'message' => 'Payroll berhasil di export!!',
                'link' => $fileName
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
        
    }

    public function exportPerbankanMyBank(Request $request){
        
        try {
            $data = Payroll::with(['karyawan' => function ($query) {
                $query->select('id', 'nama_lengkap');
            }])->where('payroll_header_id', $request->id_header)->where('payroll.is_active', true)
            ->leftJoin('payroll_header', 'payroll.payroll_header_id', '=', 'payroll_header.id')
            ->orderBy('nik_karyawan', 'asc')
            ->get();
            // Create a new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            // $spreadsheet->getActiveSheet()->getStyle('A1')
            //             ->getNumberFormat()
            //             ->setFormatCode(
            //                 '00000000000'
            //             );
            $sheet = $spreadsheet->getActiveSheet();

            // Set the header
            $sheet->setCellValue('A1', "00");
            $sheet->setCellValue('B1', 'IDINTI11118');
            $sheet->setCellValue('C1', 'PAYROLL ' . strtoupper(self::tanggal_indonesia($request->periode_payroll, "period")));
            // $sheet->setCellValue('D1', 'Catatan (Opsional)');

            // Fill the data starting from row 2
            $row = 2;

            $finalSalary = 0;
            foreach ($data as $k => $value) {
                
                $sheet->setCellValue('A' . $row, "01");
                $sheet->setCellValue('B' . $row, 'IT');
                $sheet->setCellValue('C' . $row, 'Payroll Within Bii');
                $sheet->setCellValue('D' . $row, 'ID');
                $sheet->setCellValue('E' . $row, date('dmY', strtotime($value->tgl_transfer)));
                $sheet->getColumnDimension('F')->setWidth(0);
                $sheet->getColumnDimension('G')->setWidth(0);
                $sheet->getColumnDimension('I')->setWidth(0);
                $sheet->getColumnDimension('Q')->setWidth(0);
                $sheet->getColumnDimension('R')->setWidth(0);
                $sheet->getColumnDimension('U')->setWidth(0);
                $sheet->setCellValue('H' . $row, $value->nik_karyawan);
                $sheet->setCellValue('J' . $row, 'Payroll ' . ucfirst(self::tanggal_indonesia($request->periode_payroll, "period")));
                $sheet->setCellValue('K' . $row, 'IDR');
                $sheet->setCellValue('L' . $row, $value->take_home_pay);//gaji
                $sheet->setCellValue('M' . $row, 'Y');
                $sheet->setCellValue('N' . $row, 'IDR');
                $sheet->setCellValue('O' . $row, '2782000269'); //norek
                $sheet->setCellValue('P' . $row, $value->no_rekening);
                $sheet->setCellValue('T' . $row, $value->karyawan);
                $sheet->setCellValue('AF' . $row, 'ID');
                $sheet->setCellValue('CX' . $row, 'Payroll ' . ucfirst(self::tanggal_indonesia($request->periode_payroll, "period")));
                $sheet->setCellValue('CY' . $row, 'Payroll ' . ucfirst(self::tanggal_indonesia($request->periode_payroll, "period")));
                $sheet->setCellValue('DF' . $row, "01");
                $sheet->setCellValue('LY' . $row, "");


                $finalSalary += $value->take_home_pay;
                $row++;

                if(!next($data)) {
                    $final = $row-2;
                    $sheet->setCellValue('A' . $row, '99');
                    $sheet->setCellValue('B' . $row, $final);
                    $sheet->setCellValue('C' . $row, $finalSalary);
                }

            }
            
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(','); // You can set the delimiter here
            $writer->setEnclosure('');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);

            $path = \public_path(). '/dokumen/payroll/payroll_karyawan/';
            $fileName = \str_replace(['/'], '-', $data[0]->no_document). '.csv';
            $writer->save($path.$fileName);

            $data = PayrollHeader::where('no_document',$data[0]->no_document)->update([
                'is_download' => 1,
            ]);

            return response()->json([
                'message' => 'Payroll berhasil di export!!',
                'link' => $fileName
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }   
    }

    private function tanggal_indonesia($tanggal, $mode = "")
    {
        $bulan = [
            1 => "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "November",
            "Desember",
        ];

        $var = explode("-", $tanggal);
        if ($mode == "period") {
            return $bulan[(int) $var[1]] . " " . $var[0];
        } else {
            return $var[2] . " " . $bulan[(int) $var[1]] . " " . $var[0];
        }
    }
}