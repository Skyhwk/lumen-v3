<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateQrDocument
{
    public function insert($type_doc, $data, $generated_by)
    {
        $cek = DB::table('qr_documents')->where('id_document' , $data->id)
            ->whereJsonContains('data->no_document', $data->no_document)
            ->first();
        
        // dd($cek);
        if($cek) return $cek->file;
        DB::beginTransaction();
        try {
            $filename = \str_replace("/", "_", $data->no_document);
            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int)floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);

            $dataQr = [
                'id_document' => $data->id,
                'type_document' => $type_doc,
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode([
                    'type_document' => $type_doc,
                    'no_document' => $data->no_document,
                    'nama_customer' => html_entity_decode($data->nama_perusahaan),
                ]),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $generated_by
            ];
            // dd($dataQr);
            DB::table('qr_documents')->insert($dataQr);
            DB::commit();

            return $filename;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }
}
