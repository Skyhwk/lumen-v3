<?php
namespace App\Http\Controllers\mobile;

use App\Models\SarOnthespotHeader;
use App\Models\SarOnthespotDetail;
use App\Models\SarDatalapangan;
use App\Models\ParameterSar;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\GenerateSkhpSarOnthespotService;

class FdlSarOnTheSpotController extends Controller
{
    public function indexOnproccess(Request $request)
    {
        $data = SarOnthespotHeader::with('detail', 'hasilUji')
            ->where('status_order', 'onproccess')
            ->orderByDesc('created_at')
            ->get();
        return response()->json([
            'message' => 'success',
            'data' => $data
        ], 200);
    }

    public function indexDone(Request $request)
    {
        $date = Carbon::now()->locale('id')->format('Y-m-d H:i:s');
        $endate = Carbon::parse($date)->subDays(2);

        $data = SarOnthespotHeader::with('detail', 'hasilUji')
            ->where('status_order', 'done')
            ->where('created_at', '>=', $endate)
            ->where('created_at', '<=', $date)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'success',
            'data' => $data,
            'dates' => $date
        ], 200);
    }

    public function listParameter(Request $request)
    {
        $data = ParameterSar::with([
            'hargaParameter:id_parameter,harga,id_kategori,nama_kategori',
            'parameterMaster:id,nama_kategori,id_kategori',
        ])
            ->where('is_active', true)
            ->select('id', 'id_parameter', 'nama_lab', 'nama_regulasi', 'nilai_rujukan')
            ->get()
            ->map(function ($item) {
                $namaKategori = optional($item->hargaParameter)->nama_kategori
                    ?: optional($item->parameterMaster)->nama_kategori;
                $idKategori = optional($item->hargaParameter)->id_kategori
                    ?: optional($item->parameterMaster)->id_kategori;

                return [
                    'id' => $item->id_parameter,
                    'id_parameter' => $item->id_parameter,
                    'nama_lab' => $item->nama_lab,
                    'nama_regulasi' => $item->nama_regulasi,
                    'nilai_rujukan' => $item->nilai_rujukan,
                    'nama_kategori' => $namaKategori ?: 'Lainnya',
                    'id_kategori' => $idKategori,
                    'harga_parameter' => $item->hargaParameter,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'success',
            'data' => $data
        ], 200);
    }

    public function storeOrder(Request $request)
    {
        try {
            $persentase_diskon = $request->persentase_diskon;
            $nilai_diskon = $request->nilai_diskon;
            $subtotal = $request->subtotal;

            $noOrder = \explode('.', microtime(true))[0];

            $header = new SarOnthespotHeader();
            $header->no_order = $noOrder;
            $header->status_order = 'onproccess'; // onproccess or done
            $header->nama_pelanggan = $request->nama_pelanggan;
            $header->no_hp = $request->no_hp;
            $header->email = $request->email;
            $header->alamat = $request->alamat;
            $header->lokasi_pengambilan = $request->lokasi_pengambilan;
            $header->latitude = $request->latitude;
            $header->longitude = $request->longitude;
            $header->koordinat = $request->koordinat;
            $header->subtotal = $subtotal;
            $header->persentase_diskon = $persentase_diskon;
            $header->nilai_diskon = $nilai_diskon;
            $header->harga_total = $request->harga_total;
            $header->metode_pembayaran = $request->metode_pembayaran;
            $header->kembalian = (isset($request->kembalian) && $request->kembalian !== null && $request->kembalian !== '') ? $request->kembalian : 0;
            $header->uang_diterima = (isset($request->uang_diterima) && $request->uang_diterima !== null && $request->uang_diterima !== '') ? $request->uang_diterima : 0;
            $header->created_by = $this->karyawan;
            $header->created_at = Carbon::now();
            $header->save();

            if($header)
            {
                foreach($request->parameters as $key => $value)
                {
                    $id_parameter = \explode(';', $value)[0];
                    $nama_parameter = \explode(';', $value)[1];
                    $harga_satuan = $request->harga_satuan[$id_parameter];

                    $detail = new SarOnthespotDetail();
                    $detail->id_header = $header->id;
                    $detail->id_parameter = $id_parameter;
                    $detail->nama_parameter = $nama_parameter;
                    $detail->harga_satuan = $harga_satuan;
                    $detail->subtotal = $harga_satuan * 1;
                    $detail->qty = 1;
                    $detail->save();
                }
            }

            $return = SarOnthespotHeader::with('detail')->where('id', $header->id)->first() ?? [];

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => $return
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function changeLokasiPengambilan(Request $request)
    {
        $update = SarOnthespotHeader::where('no_order', $request->no_order)->update([
            'lokasi_pengambilan' => $request->lokasi_pengambilan,
        ]);

        return response()->json([
            'message' => 'Nama lokasi pengambilan berhasil diubah',
            'data' => $update ?? []
        ], 200);
    }

    public function storeData(Request $request)
    {
        $cekHeader = SarOnthespotHeader::where('no_order', $request->no_order)->first();
        if(!$cekHeader)
        {
            return response()->json([
                'message' => 'No order tidak ditemukan',
                'data' => null
            ], 400);
        }

        $data = new SarDatalapangan();
        $data->id_header = $cekHeader->id;
        $data->id_parameter = $request->id_parameter;
        $data->parameter = $request->parameter;
        $data->hasil_uji_array = json_encode($request->hasil_uji_array);
        $data->hasil_uji = number_format(array_sum($request->hasil_uji_array) / count($request->hasil_uji_array), 2);
        $data->latitude = $request->lat;
        $data->longitude = $request->long;
        $data->koordinat = $request->koordinat;
        $data->created_by = $this->karyawan;
        $data->created_at = Carbon::now();
        $data->save();

        $return = sarOnthespotHeader::with('detail', 'hasilUji')->where('id', $cekHeader->id)->first();

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'data' => $return ?? []
        ], 200);
    }

    public function prosesSelesai(Request $request)
    {
        $cekHeader = SarOnthespotHeader::with('detail', 'hasilUji.acuan')->where('no_order', $request->no_order)->first();
        if(!$cekHeader)
        {
            return response()->json([
                'message' => 'No order tidak ditemukan',
                'data' => null
            ], 400);
        }

        try {
            $service = new GenerateSkhpSarOnthespotService();
            $result = $service->generate($cekHeader, $this->karyawan);

            $cekHeader->status_order = 'done';
            $cekHeader->save();

            $message = 'Proses selesai';
            if (!$result['email_sent']) {
                $message = empty($cekHeader->email)
                    ? 'Proses selesai, email tidak dikirim karena email pelanggan kosong'
                    : 'Proses selesai, namun gagal mengirim email ke pelanggan';
            }

            return response()->json([
                'message' => $message,
                'data' => $cekHeader,
                'file_skhp' => $result['filename'],
                'email_sent' => $result['email_sent'],
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => 'Gagal generate surat keterangan hasil pengujian: ' . $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

}