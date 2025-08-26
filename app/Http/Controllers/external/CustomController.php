<?php
namespace App\Http\Controllers\external;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomController
{
    public function handle(Request $request)
    {
        // DB::beginTransaction();
        try {
            // Ambil data dalam bentuk chunk agar lebih efisien
            DB::table('lhps_air_header')->whereNotNull('file_qr')->orderBy('id')->chunk(100, function ($dataLhp) {
                foreach ($dataLhp as $item) {
                    $dataQrDocument = DB::table('qr_documents')
                        ->where('type_document', 'LHP_AIR')
                        ->where('file', $item->file_qr)
                        ->first();

                    if ($dataQrDocument) {
                        $dataQr = json_decode($dataQrDocument->data);
                        $dataQr->Nomor_LHP = $item->no_lhp;
                        $dataQr->Nama_Pelanggan = $item->nama_pelanggan;
                        $dataQr->Tanggal_Pengesahan = Carbon::parse($item->tanggal_lhp)->locale('id')->isoFormat('YYYY MMMM DD');
                        $dataQr->Disahkan_Oleh = $item->nama_karyawan;
                        $dataQr->Jabatan = $item->jabatan_karyawan;
                        DB::table('qr_documents')->where('id', $dataQrDocument->id)->update(['data' => json_encode($dataQr)]);

                        Log::info('Data updated successfully', ['no_lhp' => $item->no_lhp]);
                    }
                }
            });

            // DB::commit();
            return response()->json(['message' => 'Data updated successfully']);
        } catch (\Throwable $th) {
            // DB::rollBack();
            dd($th);
            return response()->json(['message' => 'Gagal memperbarui data'], 500);
        }
    }

    public function total(Request $request){
        $data = DB::table('lhps_air_header')->whereNotNull('file_qr')->where('created_at', '>', '2025-07-01')->get();
        dd(count($data));
    }
}