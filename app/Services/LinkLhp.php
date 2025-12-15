<?php

namespace App\Services;

use App\Models\OrderDetail;
class LinkLhp {
    public function insertLinkLhp ($request = null) 
    {
        try {
            $finalDirectoryPath = public_path('laporan/hasil_pengujian');
            $finalFilename = $request->no_order . '.pdf';
            $finalFullPath = $finalDirectoryPath . '/' . $finalFilename;

            if (!File::isDirectory($finalDirectoryPath)) {
                File::makeDirectory($finalDirectoryPath, 0777, true);
            }

            $httpClient = Http::asMultipart();
            $fileMetadata = [];

            $lhpPath = public_path('dokumen/LHP_DOWNLOAD/' . $namaFile);

            if (File::exists($lhpPath)) {
                $httpClient->attach('pdfs[]', File::get($lhpPath), $namaFile);
                $fileMetadata[] = 'skyhwk12';
            }

            $httpClient->attach('metadata', json_encode($fileMetadata));
                // $httpClient->attach('final_password', $orderHeader->id_pelanggan);

            $pythonServiceUrl = env('PDF_COMBINER_SERVICE', 'http://127.0.01:2999') . '/merge';
            $response = $httpClient->post($pythonServiceUrl);

            if (!$response->successful()) {
                throw new \Exception('Python PDF Service failed (' . $response->status() . '): ' . $response->body());
            }

            File::put($finalFullPath, $response->body());

            $linkLhp = new LinkLhp();
            $getRilisiLHP = $linkLhp->where('no_quotation', $request->no_quotation)
            ->where('no_order',$request->no_order)->first();
            if($getRilisiLHP != null){
                $jumlah_lhp=0;
                $list_lhp_rilis = json_decode($getRilisiLHP->list_lhp_rilis ?? '[]',true);
                if (!empty($list_lhp_rilis) && !in_array($request->no_lhp,$list_lhp_rilis)){
                    $jumlah_lhp++;
                }
            }
            $linkLhp->no_quotation = $request->no_quotation;
            $linkLhp->periode = $request->periode;
            $linkLhp->no_order = $request->no_order;
            $linkLhp->nama_perusahaan = $request->nama_perusahaan;
            $linkLhp->jumlah_lhp_rilis = $lhpRilis->count();
            $linkLhp->list_lhp_rilis = json_encode($lhpRilis->pluck('cfr')->toArray());
            $linkLhp->jumlah_lhp = $request->jumlah_lhp;
            $linkLhp->is_completed = $request->jumlah_lhp == $lhpRilis->count();
            $linkLhp->filename = $finalFilename;
            $linkLhp->created_by = $this->karyawan;
            $linkLhp->created_at = Carbon::now();
            $linkLhp->updated_by = $this->karyawan;
            $linkLhp->updated_at = Carbon::now();
            $linkLhp->save();

                $key = $request->no_order;
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $tokenId = GenerateLink::insertGetId([
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $linkLhp->id,
                    'quotation_status' => "lhp_rilis",
                    'type' => 'lhp_rilis',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $finalFilename,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                $linkLhp->update([
                    'id_token' => $tokenId,
                    'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
                ]);

                return response()->json(['message' => 'Hasil Pengujian berhasil digenerate'], 200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}