<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use App\Models\QrDocument;
use Carbon\Carbon;

class GenerateQrDocumentLhp
{
    public function insert($type_doc, $data, $generated_by)
    {

        $cek = QrDocument::where('id_document', $data->id)
            ->whereJsonContains('data->no_document', $data->no_lhp)
            ->first();

        if ($cek)
            return $cek->file;
        DB::beginTransaction();
        try {

            $filename = 'LHP-' . \str_replace("/", "_", $data->no_lhp);
            $dir = public_path() . "/qr_documents/";

            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);
            // dd($path);

            $dataQr = [
                'id_document' => $data->id,
                'type_document' => $type_doc,
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode([
                    'Nomor_LHP' => $data->no_lhp,
                    'Nama_Pelanggan' => $data->nama_pelanggan,
                    'Pelanggan_ID' => substr($data->no_order, 0, 6),
                    'Tanggal_Pengesahan' => Carbon::parse($data->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $data->nama_karyawan,
                    'Jabatan' => $data->jabatan_karyawan
                ]),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $generated_by
            ];

            QrDocument::insert($dataQr);
            DB::commit();
            return $filename;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function update($type_doc, $data)
    {
        DB::beginTransaction();
        try {
            $qr = QrDocument::where('id_document', $data->id)->where('type_document', $type_doc)->first();
            if ($qr) {
                $qr->data = json_encode([
                    'Nomor_LHP' => $data->no_lhp,
                    'Nama_Pelanggan' => $data->nama_pelanggan,
                    'Pelanggan_ID' => substr($data->no_order, 0, 6),
                    'Tanggal_Pengesahan' => Carbon::parse($data->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $data->nama_karyawan,
                    'Jabatan' => $data->jabatan_karyawan
                ]);
                $qr->save();

                DB::commit();
                return $qr->file;
            }

            return false;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
