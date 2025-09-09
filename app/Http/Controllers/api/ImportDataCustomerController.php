<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Exception;
use Carbon\Carbon;

Carbon::setLocale('id');

use Yajra\DataTables\DataTables;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Models\MasterKaryawan;
use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\ImportDataCustomer;

class ImportDataCustomerController extends Controller
{
    public function index()
    {
        $data = ImportDataCustomer::select('filename', 'created_by', 'created_at', 'is_generated')
            ->groupBy('filename', 'created_by', 'created_at', 'is_generated')
            ->latest();

        return DataTables::of($data)->make(true);
    }

    public function showDetail(Request $request)
    {
        $data = ImportDataCustomer::where('filename', $request->filename);

        return DataTables::of($data)->make(true);
    }

    private function convertCompanyName($companyName)
    {
        $titles = 'PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN';
        $name = trim($companyName);

        $name = preg_replace_callback(
            '/\((\b(?:' . $titles . ')\b)[\s.,-]+([^)]+)\)/i',
            fn($matches) => '(' . trim($matches[2]) . ', ' . strtoupper($matches[1]) . ')',
            $name
        );

        if (preg_match('/^(\b(?:' . $titles . ')\b)[\s.,-]+(.+)$/i', $name, $matches)) {
            $name = trim($matches[2]) . ', ' . strtoupper($matches[1]);
        } elseif (preg_match('/^(.+?)[\s.,-]+(\b(?:' . $titles . ')\b)[\s.,-]*$/i', $name, $matches)) {
            $name = trim($matches[1]) . ', ' . strtoupper($matches[2]);
        }

        $name = preg_replace('/\s*,\s*/', ', ', $name);
        $name = trim($name, " ,.-");

        return $name;
    }

    public function create(Request $request)
    {
        $isUnprocessedCustomersExists = ImportDataCustomer::where('is_generated', false)->exists();
        if ($isUnprocessedCustomersExists) {
            return response()->json(['message' => 'Terdapat data yang belum digenerate, silahkan melakukan generate terlebih dahulu.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xls,xlsx'
        ]);

        if ($validator->fails()) return response()->json(['message' => 'Format File tidak valid'], 401);

        $file = $request->file;

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $nameWithoutExt = str_replace(' ', '_', $nameWithoutExt);
        $microtime = str_replace('.', '', microtime(true));
        $filename = $nameWithoutExt . $microtime . '.' . $extension;

        DB::beginTransaction();
        try {
            $spreadsheet  = IOFactory::load($file->getRealPath());
            $sheet        = $spreadsheet->getActiveSheet();
            $row_limit    = $sheet->getHighestDataRow();
            $row_range    = range(2, $row_limit);
            $timestamp    = Carbon::now();

            $data = [];
            foreach ($row_range as $row) {
                $originalName = $sheet->getCell('B' . $row)->getValue();
                $cleanedName = strtoupper($this->convertCompanyName($originalName));

                $dirtyNumber = $sheet->getCell('C' . $row)->getValue();
                $cleanedNumber = preg_replace("/[^0-9]/", "", $dirtyNumber); // Bersihkan karakter non-angka
                if (substr($cleanedNumber, 0, 2) === "62") { // Jika diawali 62 atau +62, ubah jadi 0
                    $cleanedNumber = "0" . substr($cleanedNumber, 2);
                }
                if (substr($cleanedNumber, 0, 2) === "00") { // handle kasus 6208xxxx
                    $cleanedNumber = "0" . substr($cleanedNumber, 2);
                }

                $email_perusahaan = $sheet->getCell('D' . $row)->getValue();
                $alamat_perusahaan = $sheet->getCell('E' . $row)->getValue();

                $existingPelanggan = MasterPelanggan::whereRaw("REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(UPPER(nama_pelanggan),'[[:space:]\.,\-]*(PT|CV|UD|PD|KOPERASI|PERUM|PERSERO|BUMD|YAYASAN)[[:space:]\.,\-]*$',''),' ', ''), '\t', ''), ',', '') = ?", [$cleanedName])
                    ->orWhereHas('kontak_pelanggan', function ($query) use ($cleanedNumber, $email_perusahaan) {
                        if ($cleanedNumber) $query->where('no_tlp_perusahaan', $cleanedNumber);
                        if ($email_perusahaan) $query->orWhere('email_perusahaan', $email_perusahaan);
                    })
                    ->orWhereHas('alamat_pelanggan', function ($query) use ($alamat_perusahaan) {
                        if ($alamat_perusahaan) $query->where('alamat', $alamat_perusahaan);
                    })
                    ->first();

                if ($existingPelanggan) continue;

                $data[] = [
                    'filename' => $filename,
                    'nama_pelanggan' => $cleanedName,
                    'no_tlp_perusahaan' => $cleanedNumber,
                    'email_perusahaan' => $email_perusahaan,
                    'alamat_perusahaan' => $alamat_perusahaan,
                    'created_by' => $this->karyawan,
                    'created_at' => $timestamp
                ];
            }

            $data = collect($data)
                ->unique(fn($item) => strtolower($item['nama_pelanggan']))
                ->values()
                ->all();

            if ($data) {
                ImportDataCustomer::insert($data);

                DB::commit();
                return response()->json(['message' => 'Berhasil mengimport data', 'filename' => $filename], 201);
            }

            return response()->json(['message' => 'Tidak terdapat data yang diimport'], 401);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error = ' . $e->getMessage()], 401);
        }
    }

    public function randomstr($str, $no)
    {
        $str = preg_replace('/[^A-Z]/', '', $str);
        $result = substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
        return $result;
    }

    public function generate(Request $request)
    {
        $unprocessedCustomers = ImportDataCustomer::where('filename', $request->filename)->where('is_generated', false)->get();
        if ($unprocessedCustomers->isEmpty()) {
            return response()->json(['message' => 'Tidak ditemukan data yang belum digenerate'], 401);
        }

        $salespersons = MasterKaryawan::where('id_jabatan', 24)->where('is_active', true)->get();
        if ($salespersons->isEmpty()) {
            return response()->json(['message' => 'Tidak terdapat sales yang aktif'], 401);
        }

        DB::beginTransaction();
        try {
            $salesCount = $salespersons->count();

            foreach ($unprocessedCustomers as $index => $customer) {
                $assignedSales = $salespersons[$index % $salesCount];

                $noUrut = str_pad(MasterPelanggan::orderBy('no_urut', 'desc')->first()->no_urut + 1, 5, '0', STR_PAD_LEFT);

                $namaPelangganUpper = strtoupper(str_replace([' ', '\t', ','], '', $customer->nama_pelanggan));
                $idPelanggan = '';
                for ($i = 1; $i <= 10; $i++) {
                    $generatedId = $this->randomstr($namaPelangganUpper, $i);
                    if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                        $idPelanggan = $generatedId;
                        break;
                    }
                }

                if (!$idPelanggan) return response()->json(['message' => 'Terdapat duplikasi ID Pelanggan pada customer, silahkan coba lagi'], 401);

                $newCustomer = new MasterPelanggan();
                $newCustomer->id_cabang = $this->idcabang;
                $newCustomer->no_urut = $noUrut;
                $newCustomer->id_pelanggan = $idPelanggan;
                $newCustomer->nama_pelanggan = $customer->nama_pelanggan;
                $newCustomer->sales_id = $assignedSales->id;
                $newCustomer->sales_penanggung_jawab = $assignedSales->nama_lengkap;
                $newCustomer->created_by = $this->karyawan;
                $newCustomer->created_at = Carbon::now();
                $newCustomer->save();

                $newContact = new KontakPelanggan();
                $newContact->pelanggan_id = $newCustomer->id;
                $newContact->no_tlp_perusahaan = $customer->no_tlp_perusahaan;
                $newContact->email_perusahaan = $customer->email_perusahaan;
                $newContact->created_by = $this->karyawan;
                $newContact->created_at = Carbon::now();
                $newContact->save();

                $newAddress = new AlamatPelanggan();
                $newAddress->pelanggan_id = $newCustomer->id;
                $newAddress->type_alamat = 'kantor';
                $newAddress->alamat = $customer->alamat_perusahaan;
                $newAddress->created_by = $this->karyawan;
                $newAddress->created_at = Carbon::now();
                $newAddress->save();
            }

            $idsToUpdate = $unprocessedCustomers->pluck('id');
            ImportDataCustomer::whereIn('id', $idsToUpdate)
                ->update(['is_generated' => true]);

            DB::commit();

            return response()->json(['message' => 'Berhasil generate data'], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function delete(Request $request)
    {
        ImportDataCustomer::where('filename', $request->filename)->delete();

        return response()->json(['message' => 'Berhasil menghapus data'], 201);
    }
}
