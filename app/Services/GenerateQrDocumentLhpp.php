<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use App\Models\QrDocument;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use App\Models\OrderHeader;

class GenerateQrDocumentLhpp
{
    public function insert($type_doc, $data, $generated_by, $status)
    {
        
        DB::beginTransaction();
        try {
            $doc = $type_doc . '-' . \str_replace("/", "_", $data->no_cfr . ($status == 'k3' ? '-' . $status : ''));

            $cek = QrDocument::where('file', $doc)->first();
            // dd($cek);
            if ($cek) {
                $cek->id_document = $data->id;
                $cek->data = json_encode([
                    'Nomor_LHP' => $data->no_lhp,
                    'Nama_Pelanggan' => $data->nama_pelanggan,
                    'Pelanggan_ID' => substr($data->no_order, 0, 6),
                    'Tanggal_Pengesahan' => Carbon::parse($data->tanggal_rilis_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $data->nama_karyawan,
                    'Jabatan' => $data->jabatan_karyawan
                ]);
                $cek->created_at =Carbon::now()->format('Y-m-d H:i:s');
                $cek->created_by = $generated_by;
                $cek->save();

                DB::commit();
                return $cek->file;
            }

            $filename = $type_doc . '-' . \str_replace("/", "_", $data->no_cfr . ($status == 'k3' ? '-' . $status : ''));
            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);
            
            $dataQr = [
                'id_document' => $data->id,
                'type_document' => $type_doc,
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode([
                    'Nomor_LHP' => $data->no_order,
                    'Nama_Pelanggan' => $data->nama_perusahaan,
                    'Pelanggan_ID' => substr($data->no_order, 0, 6),
                    'Tanggal_Pengesahan' => Carbon::parse($data->tanggal_rilis_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $data->nama_karyawan,
                    'Jabatan' => $data->jabatan_karyawan,
                    ...($status == 'k3' ? [
                        'Yang_Memeriksa_dan_Menguji_Ahli_K3_Lingkungan_Kerja_Muda' => $data->nama_skp_ahli_k3,
                        'Nomor_Registrasi' => $data->no_skp_ahli_k3,
                    ] : [])
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
                'message' => $th->getMessage()
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
                    'Tanggal_Pengesahan' => Carbon::parse($data->tanggal_lhp)->locale('id')->isoFormat('YYYY MMMM DD'),
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
