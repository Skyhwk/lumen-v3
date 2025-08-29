<?php
namespace App\Http\Controllers\external;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Repository;
use Illuminate\Support\Facades\Http;

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
        $data1 = DB::table('kontak_pelanggan')->where('email_perusahaan', 'like', '%@%')->where('is_active', 1)->pluck('email_perusahaan')->toArray();
        $data2 = DB::table('pic_pelanggan')->where('email_pic', 'like', '%@%')->where('is_active', 1)->pluck('email_pic')->toArray();
        $allArray = array_merge($data1, $data2);
        // dd(count($data1), count($data2), count($allArray));
        
        $cleanArray = array_values(array_unique($allArray));

        $response = Http::withHeaders([
            'X-MLMMJADMIN-API-AUTH-TOKEN' => 'lC16g5AzgC7M2ODh7lWedWGSL3rYPS'
        ])->get('https://mail.intilab.com/api/promotion@intilab.com/subscribers');

        if (!$response->successful()) {
            return response()->json([
            'error' => 'API request failed',
            'status' => $response->status(),
            'message' => $response->body()
            ], $response->status());
        }

        $return = $response->json();
        $dataCollection = collect($return['_data'] ?? []);
        $data = $dataCollection->pluck('mail')->toArray();
        
        $arraykedua = array_merge($cleanArray, $data);
        $arraykedua = array_values(array_unique($arraykedua));
        dd(count($arraykedua));
        // Repository::dir('daftar_email')->key('daftar_email')->save(json_encode($arraykedua));
        return response()->json(['message' => 'Data updated successfully', 'data' => $arraykedua], 200);
    }
}