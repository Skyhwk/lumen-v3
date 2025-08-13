<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Printing;
use App\Models\Printers;
use App\Models\Parameter;
use App\Models\OrderDetail;
use App\Models\LhpsAirHeader;
use App\Models\LhpUdaraPsikologiHeader;


class PrintLhp
{
    public function print($no_sampel)
    {
        // Implement the logic for handling the print LHP request
        DB::beginTransaction();
        try {
            // dd($no_sampel);
            $header = LhpsAirHeader::where('no_sampel', $no_sampel)->where('is_active', true)->first();
            $kan = $this->cekAkreditasi($header->parameter_uji, $no_sampel);
            $cek_printer = Printers::where('id', 47)->first();
            if ($kan) {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP_DOWNLOAD/' . $header->file_lhp)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP_DOWNLOAD/' . $header->file_lhp)
                    ->where('printer_name', '\\itcom2\EPSON L360 Series')
                    ->where('destination', '\\itcom2\EPSON L360 Series')
                    // ->where('pages', $request->pages)
                    ->print();
            } else {
                $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $header->file_lhp)
                    ->where('printer', $cek_printer->full_path)
                    ->where('karyawan', 'System')
                    ->where('filename', 'dokumen/LHP/' . $header->file_lhp)
                    ->where('printer_name', '\\itcom2\EPSON L360 Series')
                    ->where('destination', '\\itcom2\EPSON L360 Series')
                    // ->where('pages', $request->pages)
                    ->print();
            }
            if (!$print) {
                DB::rollBack();
                Log::channel('print_lhp')->info('[LHP Air] Failed to print ' . $header->no_sampel);
                return false; // If printing fails, return false
            } else {
                $header->is_printed = 1; // Update status print to 1
                $header->count_print = $header->count_print + 1; // Increment print count
                $header->save();
            }
            DB::commit();
            Log::channel('print_lhp')->info('[LHP Air] Successfully printed ' . $header->no_sampel);
            // Return a success response
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('print_lhp')->info('[LHP Air] Failed to print ' . $header->no_sampel . ' caused by ' . $th->getMessage());
            // Handle the exception and return an error response
            return false;
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
            $cek_printer = Printers::where('id', 47)->first();
            $print = Printing::where('pdf', env('APP_URL') . '/public/dokumen/LHP/' . $header->no_dokumen)
                ->where('printer', $cek_printer->full_path)
                ->where('karyawan', 'System')
                ->where('filename', 'dokumen/LHP/' . $header->no_dokumen)
                ->where('printer_name', '\\itcom2\EPSON L360 Series')
                ->where('destination', '\\itcom2\EPSON L360 Series')
                // ->where('pages', $request->pages)
                ->print();
            if (!$print) {
                DB::rollBack();
                Log::channel('print_lhp')->info('[LHP Psikologi] Failed to print ' . $header->no_sampel);
                return false; // If printing fails, return false
            } else {
                $header->is_printed = 1; // Update status print to 1
                $header->count_print = $header->count_print + 1; // Increment print count
                $header->save();
            }
            DB::commit();
            Log::channel('print_lhp')->info('[LHP Psikologi] Successfully printed ' . $header->no_sampel);
            // Return a success response
            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('print_lhp')->info('[LHP Psikologi] Failed to print ' . $header->no_sampel . ' caused by ' . $th->getMessage());
            // Handle the exception and return an error response
            return false;
        }
    }

    private function cekAkreditasi($data, $no_sampel)
    {
        $dataDecode = json_decode($data);

        $parameterAkreditasi = 0;
        $parameterNonAkreditasi = 0;
        $total = count($dataDecode);

        $orderDetail = OrderDetail::where('no_sampel', $no_sampel)->first();

        $kategori = explode('-', $orderDetail->kategori_2)[0];
        foreach ($dataDecode as $key => $value) {
            $parameter = Parameter::where('nama_lab', $value)->where('id_kategori', $kategori)->first();
            if ($parameter->status = 'AKREDITASI') {
                $parameterAkreditasi++;
            } else {
                $parameterNonAkreditasi++;
            }
        }

        if ($parameterAkreditasi == 0) {
            return false;
        }

        if ($total / $parameterAkreditasi >= 0.6) {
            return true;
        } else {
            return false;
        }
    }
}