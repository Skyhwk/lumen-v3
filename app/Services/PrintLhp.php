<?php

namespace App\Services;
use App\Models\LhpsAirHeader;
use App\Models\LhpsAirDetail;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\Printers;
use App\Services\Printing;
use Illuminate\Support\Facades\DB;

class PrintLhp
{
    public function print($no_sampel)
    {
        // dd('masuk');
        // Implement the logic for handling the print LHP request
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $no_sampel)->where('is_active', true)->first();
            $detail = LhpsAirDetail::where('id_header', $header->id)->get();
            $kan = $this->cekAkreditasi($detail);
            // dd($kan);
            $id_printer = 67; // Default printer ID
            if ($kan)
                $id_printer = 68;

            $cek_printer = Printers::where('id', $id_printer)->first();
            // return $cek_printer;
            if ($kan) {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $header->file_lhp)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP/' . $header->file_lhp)
                    ->where('printer_name', $cek_printer->name)
                    ->where('destination', $cek_printer->full_path)
                    // ->where('pages', $request->pages)
                    ->print();
            } else {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $header->file_lhp)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP/' . $header->file_lhp)
                    ->where('printer_name', $cek_printer->name)
                    ->where('destination', $cek_printer->full_path)
                    // ->where('pages', $request->pages)
                    ->print();
            }
            // if (!$print) {
            //     // return false; // If printing fails, return false
            // } else {
            //     $header->is_printed = 1; // Update status print to 1
            //     $header->count_print = $header->count_print + 1; // Increment print count
            //     $header->save();
            // }
            DB::commit();
            // Return a success response
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            // Handle the exception and return an error response
            return response()->json([
                'status' => false,
                'message' => 'Error printing LHP: ' . $th->getMessage()
            ], 500);
        }
    }

    public function printErgonomi($no_sampel)
    {
        DB::beginTransaction();
        try {
            //code...
            // $id_printer = 67; // Default printer ID
            // $id_printer = 68; // kan
            $id_printer = 67; // debug Internal
            $cek_printer = Printers::where('id', $id_printer)->first();
          
            $print = Printing::where('pdf', env('APP_URL') . '/public/draft_ergonomi/lhp/' . $no_sampel->name_file)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'draft_ergonomi/lhp/' . $no_sampel->name_file)
                    ->where('printer_name', $cek_printer->name)
                    ->where('destination', $cek_printer->full_path)
                    // ->where('pages', $request->pages)
                    ->print();
            return true;
        } catch (\Throwable $th) {
            //throw $th;
             DB::rollBack();
            // Handle the exception and return an error response
            return response()->json([
                'status' => false,
                'message' => 'Error printing LHP: ' . $th->getMessage(),
                'line' => 'Line: ' . $th->getLine()
            ], 500);
        }
    }

    public function printPsikologi($no_sampel)
    {
        // Implement the logic for handling the print LHP request
        DB::beginTransaction();
        try {
            $header = LhpUdaraPsikologiHeader::where('no_cfr', $no_sampel)->where('is_active', true)->first();
            if (!$header) {
                return false;
            }
            $cek_printer = Printers::where('id', 67)->first();
            $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $header->no_dokumen)
                ->where('printer', $cek_printer->full_path)
                ->where('karyawan', 'System')
                ->where('filename', 'dokumen/LHP/' . $header->no_dokumen)
                ->where('printer_name', $cek_printer->name)
                ->where('destination', $cek_printer->full_path)
                // ->where('pages', $request->pages)
                ->print();
            if (!$print) {
                return false; // If printing fails, return false
            } else {
                $header->is_printed = 1; // Update status print to 1
                $header->count_print = $header->count_print + 1; // Increment print count
                $header->save();
            }
            DB::commit();
            // Return a success response
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            // Handle the exception and return an error response
            return false;
        }
    }

    public function printByFilename($filename, $detail, $kategori = null, $no_lhp = null){
        DB::beginTransaction();
        try {
            if($kategori == 'KPGI'){
                $kan = $this->cekAkreditasiKPGI($no_lhp);
            } else {
                $kan = $this->cekAkreditasi($detail);
            }
            $id_printer = 67; // Default printer ID
            if ($kan)
                $id_printer = 68;
            $cek_printer = Printers::where('id', $id_printer)->first();
            // return $cek_printer;
            if ($kan) {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $filename)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP/' . $filename)
                    ->where('printer_name', $cek_printer->name)
                    ->where('destination', $cek_printer->full_path)
                    // ->where('pages', $request->pages)
                    ->print();
            } else {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $filename)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP/' . $filename)
                    ->where('printer_name', $cek_printer->name)
                    ->where('destination', $cek_printer->full_path)
                    // ->where('pages', $request->pages)
                    ->print();
            }
            DB::commit();
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            // Handle the exception and return an error response
            return response()->json([
                'status' => false,
                'message' => 'Error printing LHP: ' . $th->getMessage()
            ], 500);
        }
    }

    private function cekAkreditasi($data)
    {
        // $dataDecode = json_decode($data);
        $parameterAkreditasi = 0;
        $parameterNonAkreditasi = 0;

        // $orderDetail = OrderDetail::where('no_sampel', $no_sampel)->first();

        // $kategori = explode('-', $orderDetail->kategori_2)[0];
        foreach ($data as $key => $value) {
            // $parameter = Parameter::where('nama_lab', $value)->where('id_kategori', $kategori)->first();
            if ($value->akr != 'ẍ') {
                $parameterAkreditasi++;
            } else {
                $parameterNonAkreditasi++;
            }
        }
        $total = count($data);
        // dd($total);
        if ($parameterAkreditasi == 0) {
            return false;
        }

        if ($total / $parameterAkreditasi >= 0.6) {
            return true;
        } else {
            return false;
        }


    }

    private function cekAkreditasiKPGI($no_lhp)
    {
        $parameterAkreditasi = 0;
        $parameterNonAkreditasi = 0;
        
        $orderDetail = OrderDetail::where('cfr', $no_lhp)->where('is_active', 1)->get();
        foreach ($orderDetail as  $value) {
            $kategori = explode('-', $value->kategori_2)[0];
            $sub_kategori = explode('-', $value->kategori_3)[0];
            $dataDecode = json_decode($value->parameter);
            $sub_kategori = intval(strval($sub_kategori));
            $kategori = intval(strval($kategori));
            $dataRegulasi = json_decode($value->regulasi)[0];
            
                foreach ($dataDecode as $val) {
                    $bakumutu = MasterBakumutu::where('id_regulasi', explode("-",$dataRegulasi)[0])->where('parameter', explode(";",$val)[1])->first();
                    if ($bakumutu && str_contains($bakumutu->akreditasi, 'AKREDITASI')) {
                        $parameterAkreditasi++;
                    } else {
                        $parameterNonAkreditasi++;
                    }

                }
            
        }
        if ($parameterAkreditasi == 0) {
            return false;
        }
        if(($parameterAkreditasi / ($parameterAkreditasi + $parameterNonAkreditasi)) >= 0.6) {
            return true;
        } else {
            return false;
        }
    }
}
