<?php
namespace App\Http\Controllers\mobile;

use App\Models\SarHeader;
use App\Models\SarDetail;
use App\Models\ProsesFdlSar;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\GenerateQrDocumentLhp;
use App\Services\RenderLhpSar;

class FdlSarController extends Controller
{
    public function checkQr(Request $request)
    {
        $data = SarHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
        if(!$data) {
            return response()->json([
                'message' => 'Qr Tidak Ditemukan',
                'data' => null
                ], 401);
        }
        return response()->json([
            'message' => 'Data Ditemukan',
            'data' => $data
            ], 200);
    }

    public function checkUsable(Request $request)
    {
        $data = ProsesFdlSar::where('karyawan_id', $this->user_id)->where('is_completed', false)->first();
        $header = [];
        $isUsable = false;
        if ($data) {
            $header = SarHeader::with('quotation', 'detail')->where('no_order', $data->no_order)->where('is_active', true)->first();
            $isUsable = true;
        }
        return response()->json([
            'message' => 'success',
            'is_usable' => $isUsable,
            'data' => $header
            ], 200);
    }

    public function processSar(Request $request)
    {
        try {
            $data = SarHeader::with('quotation', 'detail')
                ->where('no_order', $request->no_order)
                ->where('is_active', true)
                ->first();

            $cek = ProsesFdlSar::where('no_order', $request->no_order)->first();

            if ($cek) {
                if ($cek->is_completed) {
                    $cek->is_completed = false;
                    $cek->save();
                }
                return response()->json([
                    'message' => 'Proses sudah dimulai',
                    'data' => $data
                ], 200);
            } 

            $insert = ProsesFdlSar::create([
                'karyawan_id' => $this->user_id,
                'no_order' => $request->no_order,
                'waktu_mulai_sampling' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Data berhasil diinsert',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'data' => null
            ], 400);
        }
    }

    public function storeData(Request $request)
    {
        $cekHeader = SarHeader::with('quotation', 'detail')->where('no_order', $request->no_order)->where('is_active', true)->first();
        if(!$cekHeader) {
            return response()->json([
                'message' => 'Header tidak ditemukan',
                'data' => null
            ], 404);
        }

        if($cekHeader->waktu_mulai_sampling == null) {
            $cekHeader->waktu_mulai_sampling = Carbon::now()->format('Y-m-d H:i:s');
            $cekHeader->save();
        }

        $insert = new SarDetail();
        $insert->id_header = $cekHeader->id;
        $insert->hasil_uji_array = json_encode($request->hasil_uji_array);

        $hasilUjiArray = is_array($request->hasil_uji_array) ? $request->hasil_uji_array : json_decode($request->hasil_uji_array, true);
        if (is_array($hasilUjiArray) && count($hasilUjiArray) > 0) {
            $average = array_sum($hasilUjiArray) / count($hasilUjiArray);
        } else {
            $average = null;
        }

        $insert->hasil_uji = is_null($average) ? null : number_format($average, 1, '.', '');
        $insert->id_parameter = $request->id_parameter;
        $insert->koordinat = $request->koordinat;
        $insert->latitude = $request->lat;
        $insert->longitude = $request->long;
        $insert->nomor_sampel = $request->no_sampel;
        $insert->parameter = $request->parameter;
        $insert->lokasi_pengambilan_sampel = $request->nama_titik;
        $insert->created_by = $this->karyawan;
        $insert->created_at = Carbon::now()->format('Y-m-d H:i:s');

        if($cekHeader->detail->count() >= $cekHeader->jumlah_sampel) {
            $cekHeader->is_completed = true;
            $cekHeader->waktu_selesai_sampling = Carbon::now()->format('Y-m-d H:i:s');
            $cekHeader->save();
        }

        $insert->save();


        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $cekHeader
        ], 200);
    }

    public function prosesSelesai(Request $request)
    {
        $update = ProsesFdlSar::where('no_order', $request->no_order)
        ->update([
            'is_completed' => true,
            'waktu_selesai_sampling' => Carbon::now()->format('Y-m-d H:i:s')
        ]);

        if($update) {
            return response()->json([
                'message' => 'Proses selesai',
                'status' => true
            ], 200);
        } else {
            return response()->json([
                'message' => 'Proses gagal',
                'status' => false
            ], 400);
        }
    }

    public function renderPdf(Request $request)
    {
        $hasilUjiSAR = SarHeader::with('detail')->findOrFail($request->id);

        $hasilUjiSAR->tanggal_lhp = date('Y-m-d');

        $file_qr = new GenerateQrDocumentLhp();
        if ($path = $file_qr->insertSAR('LHP_SAR', $hasilUjiSAR, $this->karyawan)) {
            $hasilUjiSAR->file_qr = $path;
        }

        $filename = RenderLhpSar::setDataHeader($hasilUjiSAR)->setDataDetail($hasilUjiSAR->detail)->render();

        $hasilUjiSAR->file_lhp = $filename;
        $hasilUjiSAR->save();

        return response()->json($filename, 200);
    }
}