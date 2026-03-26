<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use DataTables;
use Carbon\Carbon;
use App\Models\SampelSD;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Models\CategoryValue;
use App\Models\QuotationKontrakH;
use App\Models\DataSampleDiantar;
use App\Models\SampelDiantar;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use App\Models\MasterSubKategori;
// use App\Jobs\RenderSampelSD;

use App\Services\RenderSD;

class SampelDatangController extends Controller
{
    /* public function index()
    {
        $samples = SampelSD::with('order')->latest()->get();

        return DataTables::of($samples)->make(true);
    } */
    /*16062025 public function index()
    {
        try {
            //code...
            $samples = SampelDiantar::with(['order.orderDetail' => function ($query) {
                $query->where('is_active', true)
                      ->where('kategori_1', 'SD');
            }])->get();
            $samples->each(function ($sample) {
                $uniquePeriode = collect();
                if ($sample->order && $sample->order->orderDetail) {
                    $uniquePeriode = $sample->order->orderDetail
                        ->pluck('periode')
                        ->filter()         // buang null
                        ->unique()         // hanya yang unik
                        ->values();        // reset index
                }

                // Tambahkan properti baru dinamis
                $sample->setAttribute('unique_periode', $uniquePeriode);
            });
            return DataTables::of($samples)
            ->addColumn('periode', function ($row) {
                return $row->unique_periode->implode(', '); // atau ->join(', ') tergantung versi
            })
            ->make(true);
        } catch (\Exception $e) {
            return response()->json(["message"=>$e->getMessage(),"line"=>$e->getLine()],404);
            //throw $th;
        }
    } */
    public function index()
    {
        try {
            //code...
            // dd('masuk');
            $samples = SampelDiantar::with(['order.orderDetail' => function ($query) {
                $query->where('is_active', true)
                      ->where('kategori_1', 'SD');
            },'detail'])->get();
            // $samples->each(function ($sample) {
            //     $uniquePeriode = collect();
            //     if ($sample->order && $sample->order->orderDetail) {
            //         $uniquePeriode = $sample->order->orderDetail
            //             ->pluck('periode')
            //             ->filter()         // buang null
            //             ->unique()         // hanya yang unik
            //             ->values();        // reset index
            //     }

            //     // Tambahkan properti baru dinamis
            //     $sample->setAttribute('unique_periode', $uniquePeriode);
            // });
            return DataTables::of($samples)
            // ->addColumn('periode', function ($row) {
            //     return $row->unique_periode->implode(', '); // atau ->join(', ') tergantung versi
            // })
            ->make(true);
        } catch (\Exception $e) {
            return response()->json(["message"=>$e->getMessage(),"line"=>$e->getLine()],404);
            //throw $th;
        }
    }

    public function getNoPenawaran(Request $request)
    {
        $models = [
            'Kontrak' => QuotationKontrakH::class,
            'Non-Kontrak' => QuotationNonKontrak::class
        ];

        $data = $models[$request->tipe_penawaran]::with('order')
            ->where('no_document', 'LIKE', '%' . $request->no_quotation . '%')
            ->whereHas('order')
            ->whereNotIn('flag_status', ['void', 'rejected'])
            ->where(fn($q) => $q->where('status_sampling', 'SD')->orWhereNull('status_sampling'))
            ->where('is_active', true)
            ->limit(10)
            ->get()
            ->map(fn($item) => ['no_quotation' => $item->no_document, 'no_order' => optional($item->order)->no_order]);

        return response()->json($data, 200);
    }

    public function updateSampelSD(Request $request)
    {
        try {
            $sampel = SampelSD::find($request->id);

            if($request->no_penawaran) $sampel->no_quotation = $request->no_penawaran;
            if($request->no_order) $sampel->no_order = $request->no_order;
            if($request->tanggal_sampel_diterima) $sampel->tanggal_sampel_diterima = $request->tanggal_sampel_diterima;
            if($request->waktu_sampel_diterima) $sampel->waktu_sampel_diterima = $request->waktu_sampel_diterima;
            if($request->kondisi_keamanan_wadah_sampel) $sampel->kondisi_keamanan_wadah_sampel = json_encode(!is_array($request->kondisi_keamanan_wadah_sampel) ? [$request->kondisi_keamanan_wadah_sampel] : $request->kondisi_keamanan_wadah_sampel);
            $sampel->updated_by = $this->karyawan;
            $sampel->updated_at = date('Y-m-d H:i:s');

            $sampel->save();

            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    public function getNoSampel(Request $request)
    {
        $data = OrderDetail::with('headerSD')
            ->select('no_sampel')
            ->where('no_order', $request->no_order)
            ->where('no_sampel', 'LIKE', '%' . $request->no_sampel . '%')
            ->whereNull('tanggal_terima')
            ->get();

        return response()->json($data, 200);
    }

    public function getJenisSampelAir(Request $request)
    {
        $subCategories = MasterSubKategori::where([
            'nama_kategori'=> 'AIR',
            'is_active' => true
        ])->get();

        $selectedSubCategory = OrderDetail::where('no_sampel', $request->no_sampel)->first()->kategori_3;

        return response()->json(['sub_categories' => $subCategories, 'selected_sub_category' => $selectedSubCategory], 200);
    }

    public function createSampelSD(Request $request)
    {
        try {
            $orderDetail = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('no_order', $request->no_order)
                ->where('is_active', true)
                // ->whereNull('tanggal_terima')
                ->first();

            // $orderDetail->tanggal_terima = date('Y-m-d H:i:s');

            // $orderDetail->save();

            if ($orderDetail) {
                $dataSample = new DataSampleDiantar();
                $dataSample->sampel_datang_id = $request->id;
                $dataSample->no_sample = $request->no_sampel;
                $dataSample->ph = $request->ph_sampel;
                $dataSample->suhu_air = $request->suhu_air;
                $dataSample->warna = $request->warna_sampel;
                $dataSample->jenis_sampel = explode('-', $request->jenis_sampel_air)[1];
                $dataSample->deskripsi_sampel = $request->deskripsi_titik;
                $dataSample->bau = $request->bau_sampel;
                $dataSample->dhl = $request->dhl;
                $dataSample->keruh = $request->keruh;
                $dataSample->no_order = $request->no_order;
                $dataSample->ph_sampel_lapangan = ($request->ph_sampel_lapangan != '') ? $request->ph_sampel_lapangan : null;
                $dataSample->suhu_air_lapangan = ($request->suhu_air_lapangan != '') ? $request->suhu_air_lapangan : null;
                $dataSample->dhl_lapangan = ($request->dhl_lapangan != '') ? $request->dhl_lapangan : null;
                $dataSample->created_by = $this->karyawan;
                $dataSample->created_at = date('Y-m-d H:i:s');

                $dataSample->save();
            }

            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    public function detailSampelSD(Request $request)
    {
        $samples = DataSampleDiantar::where('no_order', $request->no_order)->where('is_active', true)->orderBy('no_sample')->get();

        return Datatables::of($samples, 200)->make(true);
    }

    /* public function viewPdf(Request $request)
    {
        try {
            $render = new RenderSD();
            $render->renderHeader($request->id);

            // $job = new RenderSampelSD($request->id);
            // $this->dispatch($job);

            $data = SampelSD::where('id', $request->id)->first();
            return response()->json($data->filename, 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'failed',
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    } */

    /* 16062025 public function viewPdf(Request $request)
    {
        try {

            $render = new RenderSD();
            $render->renderHeader($request->id,$request->periode);
            // dd($render);
            // $job = new RenderSampelSD($request->id);
            // $this->dispatch($job);

            $data = SampelDiantar::where('id', $request->id)->first();
            return response()->json($data->filename, 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'failed',
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    } */
   public function viewPdf(Request $request)
    {
        try {

            $render = new RenderSD();
            if(!$request->mode){
                $render->renderHeader($request->id,null,null);
            }
            else if($request->mode == 'terima'){
                $render->renderHeader($request->id,$request->periode,$request->mode);
            }else if($request->mode == 'full'){
                $render->renderHeader($request->id,$request->periode,$request->mode);
            }
            // dd($render);
            // $job = new RenderSampelSD($request->id);
            // $this->dispatch($job);

            $data = SampelDiantar::where('id', $request->id)->first();
            return response()->json($data->filename, 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'failed',
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }

    public function deleteSampelSD(Request $request)
    {
        try {
            $orderDetail = OrderDetail::with('sampleDiantar')
                ->where(['no_sampel' => $request->no_sampel, 'no_order' => $request->no_order])
                ->first();

            if ($orderDetail) {
                $orderDetail->tanggal_terima = null;
                $orderDetail->save();

                $sampleDiantar = DataSampleDiantar::where('no_sample', $request->no_sampel)->first();
                $sampleDiantar->is_active = false;
                $sampleDiantar->deleted_by = $this->karyawan;
                $sampleDiantar->deleted_at = Carbon::now();
                $sampleDiantar->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Saved Succesfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage(), 'line' => $ex->getLine()], 500);
        }
    }

    // public function downloadSampelSD(Request $request)
    // {
    //     $data = DataSampleDiantar::where('no_order', $request->no_order)->where('is_active', true)->get();
    //     // dd($data);
    //     $header = SampelSD::where('no_order', $request->no_order)->first();

    //     if (!$header || $data->isEmpty()) {
    //         return response()->json([
    //             'message' => 'Data tidak ditemukan!'
    //         ], 404);
    //     }

    //     $samples = [];
    //     foreach ($data as $item) {
    //         $samples[] = $item->no_sample;
    //     }

    //     // Persiapkan data untuk template PDF

    //     $html = ' <style>
    //                 .detail {
    //                     border-collapse: collapse;
    //                 }

    //                 .detail1 {
    //                     border: 1px solid black;
    //                     /* Ganti warna dan ketebalan border */
    //                     border-radius: 5px;
    //                     /* Ganti radius border */
    //                     padding: 5px;
    //                     text-align: left;
    //                 }

    //                 .detail2 {
    //                     border: 1px solid black;
    //                     /* Ganti warna dan ketebalan border */
    //                     border-radius: 5px;
    //                     /* Ganti radius border */
    //                     padding: 5px;
    //                     text-align: center;
    //                 }

    //                 th {
    //                     background-color: rgb(255, 255, 255);
    //                 }

    //                 .center {
    //                     text-align: center;
    //                 }
    //             </style>
    //             <div style="border: 1px solid black; padding: 10px; width: 100%; height: 100%;">
    //                 <table width="100%" cellpadding="5" cellspacing="0">
    //                     <tr>
    //                         <td style="width: 30%; text-align: left;">
    //                             <!-- Logo -->
    //                             <img src="' . public_path('isl_logo.png') . '" alt="Logo" style="height: 40px;">
    //                         </td>
    //                         <td style="width: 70%; text-align: center;">
    //                             <!-- Teks -->
    //                             <h4 style="margin: 0;">LEMBAR TANDA TERIMA DAN INFORMASI SAMPEL DATANG KATEGORI AIR</h4>
    //                             <div style="margin-top: 10px;">
    //                                 <span style="font-size: 12px; margin-right: 100px;">' . $header->no_quotation . '</span>
    //                                 <span style="font-size: 12px; margin-right: 10px;">' . self::tanggalIndonesia(date('l, d F Y')) . '</span>
    //                                 <span style="font-size: 12px;">' . date('H:i') . ' WIB</span>
    //                             </div>
    //                         </td>
    //                     </tr>
    //                 </table>
    //                 <table class="detail" border="1" cellpadding="5" cellspacing="0" width="100%" style="margin-top: 10px;">
    //                     <tr>
    //                         <th colspan="2" class="detail2">Informasi Pelanggan</th>
    //                         <th colspan="2" class="detail2">Informasi Sampel</th>
    //                     </tr>
    //                     <tr>
    //                         <td class="detail1">Nama Perusahaan</td>
    //                         <td class="detail1">' . $header->nama_perusahaan . '</td>
    //                         <td class="detail1">Informasi Wadah Sampel</td>
    //                         <td class="detail1">' . str_replace(['[', ']', '"'], '', $header->jenis_wadah_sampel) . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td class="detail1">Nama Pengantar Sampel</td>
    //                         <td class="detail1">' . $header->nama_pengantar_sampel . '</td>
    //                         <td class="detail1">Kondisi Keamanan Wadah Sampel</td>
    //                         <td class="detail1">' . $header->lock_system_botol . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td class="detail1">Tujuan Pengujian</td>
    //                         <td class="detail1">' . str_replace(['[', ']', '"'], '', $header->tujuan_pengujian) . '</td>
    //                         <td class="detail1">Jenis Sampel Air</td>
    //                         <td class="detail1">' . $header->jenis_sampel . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td class="detail1">Alamat Pelanggan</td>
    //                         <td class="detail1">' . $header->alamat_perusahaan . '</td>
    //                         <td class="detail1">Kode Sampel</td>
    //                         <td class="detail1">' . implode(', ', $samples) . '</td>
    //                     </tr>
    //                     <tr>
    //                         <td class="detail1">Hari / Tanggal Penyerahan Sampel</td>
    //                         <td class="detail1">' . self::tanggalIndonesia($header->created_at) . '</td>
    //                         <td class="detail1">Durasi Transportasi Sampel</td>
    //                         <td class="detail1">' . $header->jenis_sampel . '</td>
    //                     </tr>
    //                 </table>
    //                 <table class="detail" border="1" cellpadding="5" cellspacing="0" width="100%" style="margin-top: 20px;"> thead> <tr>
    //                         <th colspan="4" class="detail2">Informasi Kegiatan Sampling</th>
    //                     </tr>
    //                     </thead>
    //                     <tbody>
    //                         <tr>
    //                             <td class="detail2">Nama Petugas Sampling</td>
    //                             <td class="detail1">' . $header->nama_petugas_sampling . '</td>
    //                             <td class="detail2">Waktu Sampling</td>
    //                             <td class="detail1">' . $header->waktu_sampling . '</td>
    //                         </tr>
    //                         <tr>
    //                             <td class="detail2">Hari / Tanggal Sampling</td>
    //                             <td class="detail1">' . self::tanggalIndonesia($header->tanggal_sampling) . '</td>
    //                             <td class="detail2">Teknik Sampling</td>
    //                             <td class="detail1">' . str_replace(['[', ']', '"'], '', $header->cara_pengambilan_sampel) . '</td>
    //                         </tr>
    //                     </tbody>
    //                 </table>
    //                 <table class="detail" border="1" cellpadding="5" cellspacing="0" width="100%" style="margin-top: 20px;">
    //                     <thead>
    //                         <tr>
    //                             <th colspan="6" class="detail2">Informasi Hasil Pengujian</th>
    //                             <th colspan="3" class="detail2">Informasi Hasil Pengamatan Fisik</th>
    //                         </tr>
    //                         <tr>
    //                             <th class="detail2">No. Sampel</th>
    //                             <th class="detail2">Deskripsi Sampel</th>
    //                             <th class="detail2">Jenis Sampel</th>
    //                             <th class="detail2">pH</th>
    //                             <th class="detail2">DHL (μS/cm)</th>
    //                             <th class="detail2">Suhu (°C)</th>
    //                             <th class="detail2">Berwarna</th>
    //                             <th class="detail2">Berbau</th>
    //                             <th class="detail2">Keruh</th>
    //                         </tr>
    //                     </thead>
    //                     <tbody>';

    //     foreach ($data as $index => $item) {
    //         $html .= ' <tr>
    //                     <td class="detail2">' . $item->no_sample . '</td>
    //                     <td class="detail2">' . $item->deskripsi_sampel . '</td>
    //                     <td class="detail2">' . $item->jenis_sampel . '</td>
    //                     <td class="detail2">' . $item->ph . '</td>
    //                     <td class="detail2">' . $item->dhl . '</td>
    //                     <td class="detail2">' . $item->suhu_air . '</td>
    //                     <td class="detail2">' . $item->warna . '</td>
    //                     <td class="detail2">' . $item->bau . '</td>
    //                     <td class="detail2">' . $item->keruh . '</td>
    //                 </tr>';
    //     }

    //     $html .= '</tbody>
    //                 </table>
    //                 <table width="100%" style="margin-top: 20px; margin-bottom: 30px;">
    //                     <tr>
    //                         <td width="50%" class="center">Diserahkan Oleh,</td>
    //                         <td width="50%" class="center">Diterima Oleh,</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td style="font-weight: bold;" class="center">PT. INTI SURYA LABORATORIUM</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td>&nbsp;</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td>&nbsp;</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td>&nbsp;</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td>&nbsp;</td>
    //                     </tr>
    //                     <tr>
    //                         <td>&nbsp;</td>
    //                         <td>&nbsp;</td>
    //                     </tr>
    //                 </table>
    //             </div>';


    //     // Buat PDF menggunakan mPDF
    //     try {
    //         $pdf = new \App\Services\MpdfService([
    //             'mode' => 'utf-8',
    //             'format' => 'A4-P', // A4 dengan orientasi landscape
    //             'margin_left' => 4,
    //             'margin_right' => 4,
    //             'margin_top' => 4,
    //             'margin_bottom' => 4,
    //         ]);

    //         $pdf->WriteHTML($html);

    //         // Simpan file ke folder publik
    //         $filename = 'sampel_diantar_' . $request->no_order . '.pdf';
    //         $path = public_path('TemplateSampelSD/' . $filename);
    //         $pdf->Output($path, \Mpdf\Output\Destination::FILE);

    //         // Kirimkan respons ke frontend
    //         return response()->json([
    //             'message' => 'File berhasil dibuat.',
    //             'link' => url('TemplateSampelSD/' . $filename),
    //         ]);
    //     } catch (\App\Services\MpdfService as MpdfException $e) {
    //         return response()->json([
    //             'message' => 'Terjadi kesalahan saat membuat PDF: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }


    // protected function tanggalIndonesia($tanggal)
    // {
    //     $hari = [
    //         'Sunday' => 'Minggu',
    //         'Monday' => 'Senin',
    //         'Tuesday' => 'Selasa',
    //         'Wednesday' => 'Rabu',
    //         'Thursday' => 'Kamis',
    //         'Friday' => 'Jumat',
    //         'Saturday' => 'Sabtu'
    //     ];

    //     $bulan = [
    //         'January' => 'Januari',
    //         'February' => 'Februari',
    //         'March' => 'Maret',
    //         'April' => 'April',
    //         'May' => 'Mei',
    //         'June' => 'Juni',
    //         'July' => 'Juli',
    //         'August' => 'Agustus',
    //         'September' => 'September',
    //         'October' => 'Oktober',
    //         'November' => 'November',
    //         'December' => 'Desember'
    //     ];

    //     $namaHari = $hari[date('l', strtotime($tanggal))];
    //     $namaBulan = $bulan[date('F', strtotime($tanggal))];
    //     $tanggalIndonesia = $namaHari . ', ' . date('d', strtotime($tanggal)) . ' ' . $namaBulan . ' ' . date('Y', strtotime($tanggal));

    //     return $tanggalIndonesia;
    // }
}
