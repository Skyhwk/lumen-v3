<?php

namespace App\Http\Controllers\api;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImportDataCustomer;
use App\Models\MasterPelanggan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ImportDataCustomerController extends Controller
{
    public function index(Request $request)
    {
        $importDataCustomer = ImportDataCustomer::select('filename', 'created_by', 'created_at', 'is_generated')
        ->groupBy('filename', 'created_by', 'created_at', 'is_generated')
        ->get();

        return response()->json(['data' => $importDataCustomer], 200);
    }

    public function showDetail(Request $request){
        $data = ImportDataCustomer::where('filename', $request->filename)
        ->get();

        return response()->json(['data' => $data], 200);
    }

    public function create(Request $request){
        $this->validate($request, [
            'file' => 'required|file|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');
        $dataExists = array();
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $nameWithoutExt = str_replace(' ', '_', $nameWithoutExt);
        $microtime = str_replace('.', '', microtime(true));
        $filename = $nameWithoutExt . $microtime . '.' . $extension;
        
        DB::beginTransaction();
        try{
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet        = $spreadsheet->getActiveSheet();
            $row_limit    = $sheet->getHighestDataRow();
            $column_limit = $sheet->getHighestDataColumn();
            $row_range    = range( 2, $row_limit );
            $column_range = range( 'E', $column_limit );
            $startcount   = 1;
            $data = array();   
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');
            foreach ( $row_range as $row ) {
                // pattern Excel B->Nama Customer, C->No Telepon Perusahaan, D->email perusahaan E->alamat perusahaan

                $dirtyNameCustomer = strtoupper(str_replace([' ', "\t", ','], '', $sheet->getCell('B' . $row)->getValue()));

                // Ambil title di depan atau belakang (PT, CV, UD, PD, dll)
                $pattern = '/^(?P<title_depan>PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN)[\s\.,]*|[\s\.,]*(?P<title_belakang>PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN)[\s\.,]*$/i';

                $title = "";
                $namaPelangganBersih = $dirtyNameCustomer;

                if (preg_match($pattern, $dirtyNameCustomer, $matches)) {
                    if (!empty($matches['title_depan'])) {
                        $title = strtoupper($matches['title_depan']);
                        // Hilangkan title di depan
                        $namaPelangganBersih = preg_replace('/^(' . $title . ')[\s\.,]*/i', '', $namaPelangganBersih);
                    } elseif (!empty($matches['title_belakang'])) {
                        $title = strtoupper($matches['title_belakang']);
                        // Hilangkan title di belakang
                        $namaPelangganBersih = preg_replace('/[\s\.,]*(' . $title . ')[\s\.,]*$/i', '', $namaPelangganBersih);
                    }
                }

                $no_tlp_perusahaan = $sheet->getCell('C' . $row)->getValue();
                // Bersihkan karakter non-angka
                $no_tlp_perusahaan = preg_replace("/[^0-9]/", "", $no_tlp_perusahaan);
                // Jika diawali 62 atau +62, ubah jadi 0
                if (substr($no_tlp_perusahaan, 0, 2) === "62") {
                    $no_tlp_perusahaan = "0" . substr($no_tlp_perusahaan, 2);
                } elseif (substr($no_tlp_perusahaan, 0, 3) === "620") { // handle kasus 6208xxxx
                    $no_tlp_perusahaan = "0" . substr($no_tlp_perusahaan, 3);
                }

                $email_perusahaan = $sheet->getCell('D' . $row)->getValue();
                $alamat_perusahaan = $sheet->getCell('E' . $row)->getValue();

                $existingPelanggan = MasterPelanggan::whereRaw("
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REGEXP_REPLACE(
                                    UPPER(nama_pelanggan),
                                    '[[:space:]\.,]*(PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN)[[:space:]\.,]*$',
                                    '',
                                    'ig'
                                ),
                            ' ', ''), '\t', ''), ',', ''
                        ) = ?
                    ", [$namaPelangganBersih]
                )
                ->orWhereHas('kontak_pelanggan', function ($query) use ($no_tlp_perusahaan, $email_perusahaan) {
                    if (!empty($no_tlp_perusahaan)) {
                        $query->where('no_tlp_perusahaan', $no_tlp_perusahaan);
                    }
                    if (!empty($email_perusahaan)) {
                        $query->orWhere('email_perusahaan', $email_perusahaan);
                    }
                })
                ->orWhereHas('alamat_pelanggan', function ($query) use ($alamat_perusahaan) {
                    if (!empty($alamat_perusahaan)) {
                        $query->where('alamat', $alamat_perusahaan);
                    }
                })
                ->first();
                
                if($existingPelanggan) continue;

                $data[] = [
                    'filename' => $filename,
                    'nama_pelanggan' => trim($namaPelangganBersih . ($title != '' ? ', ' . $title : '')),
                    'no_tlp_perusahaan' => $no_tlp_perusahaan,
                    'email_perusahaan' => $email_perusahaan,
                    'alamat_perusahaan' => $alamat_perusahaan,
                    'created_by' => $this->karyawan,
                    'created_at' => $timestamp
                ];
                
                $startcount++;
            }

            $data = collect($data)
            ->unique(function ($item) {
                return strtolower($item['nama_pelanggan']);
            })
            ->values()
            ->all();

            
            if(!empty($data)){
                if(ImportDataCustomer::where('is_generated', false)->exists()){
                    return response()->json([
                        'message'=>'Ada data yang belum tergenerate, silahkan generated terlebih dahulu.!'
                    ],401);
                }

                ImportDataCustomer::insert($data);
                DB::commit();

                return response()->json([
                    'message'=>'Data berhasil diimport.!'
                ],200);
            } else {
                return response()->json([
                    'message'=>'Tidak ada data yang diimport.!'
                ],401);
            }
        
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message'=>'Error = '.$e->getMessage()
            ],401);
        }
    }

    public function generate(Request $request){
    }

    public function delete(Request $request){
        $importDataCustomer = ImportDataCustomer::where('filename', $request->filename)->delete();
        return response()->json(['message'=>'Data berhasil dihapus.!'], 200);
    }
}