<?php

namespace App\Http\Controllers\api;

use App\Models\SampelSD;
use Illuminate\Http\Request;
use App\Models\MasterPelanggan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SampelController extends Controller
{
    public function pelanggan(Request $request)
    {
        // from db_dev_2024
        return response()->json(['data' => MasterPelanggan::with('alamat_pelanggan')->where('is_active', true)->orderBy('nama_pelanggan')->limit(6876)->get()]);
    }

    public function pelangganV1(Request $request)
    {
        // from db_dev_2024
        $search = $request->input('search');
        $perPage = 20;

        $query = MasterPelanggan::with('alamat_pelanggan')
                    ->where('is_active', true)
                    ->orderBy('nama_pelanggan');

        if (!empty($search)) {
            $query->where('nama_pelanggan', 'like', "%{$search}%");
        } else {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false]
            ]);
        }

        $pelanggan = $query->paginate($perPage);

        $results = [];
        foreach ($pelanggan->items() as $item) {
            $results[] = [
                'id' => $item->nama_pelanggan,
                'text' => $item->nama_pelanggan,
                'alamat' => $item->alamat_pelanggan->first()->alamat ?? null // Ambil alamat pertama jika ada
            ];
        }

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $pelanggan->hasMorePages()]
        ]);
    }

    // lama public function convertFile($foto, $name)
    // {
    //     $img = str_replace('data:image/jpeg;base64,', '', $foto);
    //     $file = base64_decode($img);
    //     $safeName = $name . '_' . date("YmdHis") . '.jpeg';
    //     // $destinationPath = public_path() . '/sampel/dokumentasi/';
    //     $destinationPath = '/var/www/html/lims/backend/public/sampel/dokumentasi/';
    //     if (!file_exists($destinationPath)) mkdir($destinationPath, 0777, true);

    //     file_put_contents($destinationPath . $safeName, $file);

    //     return $safeName;
    // }

    public function convertFile($foto, $name)
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = $name . '_' . date("YmdHis") . '.jpeg';
        // $destinationPath = public_path() . '/sampel/dokumentasi/';
        $destinationPath = public_path() . '/sampel_datang/dokumentasi/';
        if (!file_exists($destinationPath)) mkdir($destinationPath, 0777, true);

        file_put_contents($destinationPath . $safeName, $file);

        return $safeName;
    }

    public function store(Request $request)
    {
        // $destinationPath = '/var/www/html/lims/backend/public/sampel/dokumentasi/';

        // if (file_exists($destinationPath)) {
        //     // Cari semua file di folder
        //     $files = glob($destinationPath . '*'); // Ambil semua file di folder

        //     // Hapus setiap file
        //     foreach ($files as $file) {
        //         if (is_file($file)) {
        //             unlink($file); // Hapus file
        //         }
        //     }
        // }

        try {
            $data = new SampelSD;
            if ($request->nama_perusahaan)              $data->nama_perusahaan              = $request->nama_perusahaan;
            if ($request->nama_pengantar_sampel)        $data->nama_pengantar_sampel        = $request->nama_pengantar_sampel;
            if ($request->alamat_perusahaan)            $data->alamat_perusahaan            = $request->alamat_perusahaan;
            if ($request->tujuan_pengujian)             $data->tujuan_pengujian             = $request->tujuan_pengujian;
            if ($request->tanggal_sampling)             $data->tanggal_sampling             = $request->tanggal_sampling;
            if ($request->waktu_sampling)               $data->waktu_sampling               = $request->waktu_sampling;
            if ($request->nama_petugas_sampling)        $data->nama_petugas_sampling        = $request->nama_petugas_sampling;
            if ($request->cara_pengambilan_sampel)      $data->cara_pengambilan_sampel      = $request->cara_pengambilan_sampel;
            if ($request->lock_system_botol)            $data->lock_system_botol            = $request->lock_system_botol;
            if ($request->jenis_wadah_sampel)           $data->jenis_wadah_sampel           = $request->jenis_wadah_sampel;
            if ($request->perlakuan_pencucian_wadah)    $data->perlakuan_pencucian_wadah    = $request->perlakuan_pencucian_wadah;
            if ($request->blanko_pencucian)             $data->blanko_pencucian             = $request->blanko_pencucian;
            if ($request->keterangan_botol_lainnya)     $data->keterangan_botol_lainnya     = $request->keterangan_botol_lainnya;
            if ($request->pengujian_insitu)             $data->pengujian_insitu             = $request->pengujian_insitu;
            if ($request->pengawetan_sampel)            $data->pengawetan_sampel            = $request->pengawetan_sampel;
            if ($request->dokumentasi_sampel)           $data->dokumentasi_sampel           = self::convertFile($request->dokumentasi_sampel, urlencode('doc_sample_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->nama_perusahaan)));
            if ($request->dokumentasi_lokasi_sampel)    $data->dokumentasi_lokasi_sampel    = self::convertFile($request->dokumentasi_lokasi_sampel, urlencode('doc_loc_sample_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->nama_perusahaan)));
            if ($request->dokumentasi_lainnya)          $data->dokumentasi_lainnya          = self::convertFile($request->dokumentasi_lainnya, urlencode('doc_others_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->nama_perusahaan)));

            // Generate nomor dokumen
            $bulanRomawi = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $prefix = 'ISL/TSD';
            $year = date('y'); // 2 digit tahun
            $month = $bulanRomawi[intval(date('n'))]; // bulan dalam Romawi
            $lastDocument = SampelSD::latest('no_document')->first();

            if ($lastDocument) {
                $lastNumber = intval(substr($lastDocument->no_document, -6));
                $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '000001';
            }

            $data->no_document = "{$prefix}/{$year}-{$month}/{$newNumber}";
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => 'Berhasil menyimpan',
                'status' => 0,
                'data' => $request->all()
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }
}
