<?php

namespace App\Http\Controllers\api;

use Mpdf;

use App\Models\Jadwal;
use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PersiapanSampelHeader;

class CodingSampleSDController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order',
            ])->select([
                        'id_order_header',
                        'no_order',
                        'kategori_2',
                        'periode',
                        'tanggal_sampling',
                        DB::raw('GROUP_CONCAT(no_sampel) as no_sampel'),
                        DB::raw('GROUP_CONCAT(kategori_3) as kategori_3'),
                    ])
                ->where('is_active', true)
                ->where('kategori_1', '=', 'SD')
                ->whereBetween('tanggal_sampling', [
                    date('Y-m-01', strtotime($request->periode_awal)),
                    date('Y-m-t', strtotime($request->periode_akhir))
                ])
                ->groupBy([
                    'id_order_header',
                    'no_order',
                    'kategori_2',
                    'periode',
                    'tanggal_sampling',
                ]);

            return DataTables::of($data)
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('no_order', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_document', function ($query, $keyword) {
                    $query->whereHas('orderHeader', function ($q) use ($keyword) {
                        $q->where('no_document', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('no_document', function ($query, $keyword) {
                    $query->whereHas('orderHeader', function ($q) use ($keyword) {
                        $q->where('no_document', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                    $query->whereHas('orderHeader', function ($q) use ($keyword) {
                        $q->where('nama_perusahaan', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('periode', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                    $query->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                })
                ->addColumn('kategori_sampel', function ($item) {
                    $noSampelList = explode(',', $item->no_sampel ?? '');
                    $kategoriList = explode(',', $item->kategori_3 ?? '');
                    $combinedList = [];

                    foreach ($noSampelList as $index => $noSampel) {
                        $kategori = isset($kategoriList[$index]) ? $kategoriList[$index] : '';
                        $kategori = $kategori ? (explode('-', $kategori)[1] ?? '') : '';
                        $noSampel = $noSampel ? (explode('/', $noSampel)[1] ?? '') : '';
                        $combinedList[] = trim($kategori) . ' - ' . trim($noSampel);
                    }

                    return implode(', ', $combinedList);
                })
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }
    public function preview(Request $request)
    {

        try {

            $jsonDecode = html_entity_decode($request->info_sampling);
            $infoSampling = json_decode($jsonDecode, true);

            $request->kategori = str_contains($request->kategori, ',') ? explode(",", $request->kategori) : [$request->kategori];
            // dump($request->kategori);
            $noSample = [];
            foreach ($request->kategori as $item) {
                $parts = explode(" - ", $item);
                array_push($noSample, $request->no_order . '/' . $parts[1]);
            }

            $orderD = OrderDetail::with(['orderHeader'])
                ->where('no_order', $request->no_order)
                ->whereIn('no_sampel', $noSample)
                ->where('is_active', true)
                ->get();

            if (!$orderD)
                return response()->json(['message' => 'Terdapat Perbedaan Tanggal Sampling Pada Order Dan Penjadwalan, Silahkan Hubungi Bagian Penjadwalan Untuk Melakukan Update Jadwal.!'], 401);

            //    foreach ($orderD as $item) {
            //        if (!$item->keterangan_1 && $item->keterangan_1 != '') return response()->json(['message' => 'Ada deskripsi titik belum input  oleh sales.!'], 401);
            //    }

            $data_sampling = [];
            //bentuk data
            foreach ($orderD as $vv) {
                if ($vv->orderHeader->is_revisi == true) {
                    return response()->json(['message' => 'Order Ini Sedang Revisi'], 401);
                }

                // if($vv->keterangan_1 == null || $vv->keterangan_1 == ''){
                //     return response()->json(['message' => 'Ada deskripsi titik belum input  oleh sales.!'], 401);
                // }
                $kategori = explode('-', $vv->kategori_2)[1];
                $persiapan = []; // Reset array value
                $jumlahLabel = 0;
                if ($kategori == 'Air') {
                    $jsonDecode = html_entity_decode($vv->persiapan);
                    $jsonDecode = json_decode($jsonDecode, true);

                    foreach ($jsonDecode as $key => $val) {
                        $rumus = self::rumus($vv->kategori_2, $val, $vv->parameter);
                        array_push($persiapan, $rumus['data_par'][0]);
                        $jumlahLabel += $rumus['data_par'][0]['jumlah'];
                    }
                    $data_sampling[] = (object) [
                        'no_sampel' => $vv->no_sampel,
                        'kategori_2' => $vv->kategori_2,
                        'kategori_3' => $vv->kategori_3,
                        'nama_perusahaan' => $vv->nama_perusahaan,
                        'koding_sampling' => $vv->koding_sampling,
                        'file_koding_sample' => $vv->file_koding_sampel,
                        'file_koding_sampling' => $vv->file_koding_sampling,
                        'konsultan' => $vv->orderHeader->konsultan,
                        'tanggal_sampling' => $vv->tanggal_sampling,
                        'keterangan_1' => $vv->keterangan_1,
                        'jumlah_label' => $jumlahLabel ?? null,
                        'status_sampling' => $vv->kategori_1,
                        'id' => $vv->id,
                        'id_order_header' => $vv->id_order_header,
                        'id_req_detail' => $request->id_req_detail,
                        'periode_kontrak' => $vv->periode,
                        'persiapan' => $persiapan,
                        'parameter' => $vv->parameter,
                        'no_order' => $vv->orderHeader->no_order,
                        'no_document' => $request->no_document,

                    ];
                }

            }
            //    return response()->json($data_sampling, 200);


            if ($request->type_file == 'document') {
                $filename = $this->cetakPDF($data_sampling);
                return response()->json($filename, 200);
            } else if ($request->type_file == 'barcode') {
                $filename = $this->cetakBarcodePDF($data_sampling);
                return response()->json($filename, 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }



    private function rumus($kategori, $botol, $parameter)
    {
        try {
            $dat = explode("-", $kategori);
            $jmlh_label = 0;
            $data_par = []; // Pastikan selalu array

            if ($dat[0] == 1) {
                $volume = floatval($botol['volume']);
                $type_botol = $botol['type_botol'];

                if (str_contains($type_botol, 'M100')) {
                    $kon = ceil($volume / 100);
                } elseif (str_contains($type_botol, 'HNO3')) {
                    $kon = ceil($volume / 500);
                } else {
                    $kon = ceil($volume / 1000);
                }

                $jmlh_label = $kon * 2;
                $data_par[] = ['param' => $type_botol, 'jumlah' => $kon]; // Selalu array numerik
            } elseif (in_array($dat[0], [5, 4])) {
                $cek = KonfigurasiPraSampling::where('parameter', $parameter)->first();
                if ($cek) {
                    $jumlah = $cek->ketentuan + 1;
                    $jmlh_label = $jumlah;
                    $data_par[] = ['param' => $parameter, 'jumlah' => $cek->ketentuan]; // Selalu array numerik
                }
            } else {
                $jmlh_label = "-";
                $data_par = []; // Ubah menjadi array kosong agar tetap bisa diakses tanpa error
            }

            return [
                'jmlh_label' => $jmlh_label,
                'data_par' => $data_par,
            ];
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function cetakPDF($data_sampling)
    {
        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 3, // 30mm not pixel
            'margin_bottom' => 3, // 30mm not pixel
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'orientation' => 'P'
        );

        $konsultan = '';
        foreach ($data_sampling as $kk => $vv) {
            $konsultan = $vv->konsultan;
            $no_document = $vv->no_document;
            $no_order = $vv->no_order;
            $nama_perusahaan = $vv->nama_perusahaan;
            $tgl_sampling = $vv->tanggal_sampling;
        }

        if ($konsultan)
            $konsultan = ' (' . $konsultan . ')';

        $pdf = new Mpdf($mpdfConfig);

        $filename = 'DOC_CS_SD_' . $no_order . '.pdf';

        $footer = array(
            'odd' => array(
                'C' => array(
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 6,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060'
                ),
                'R' => array(
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    // 'font-style' => 'B',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'L' => array(
                    // 'content' => '' . $qr_img . '',
                    'font-size' => 4,
                    'font-style' => 'I',
                    // 'font-style' => 'B',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'line' => -1,
            )
        );

        $pdf->setFooter($footer);

        $pdf->WriteHTML('
            <!DOCTYPE html>
                <html>
                    <head>
                        <style>
                            .custom1 { font-size: 12px; font-weight: bold; }
                            .custom2 { font-size: 15px; font-weight: bold; text-align: center; padding: 5px; }
                            .custom3 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                            .custom4 { font-size: 12px; font-weight: bold; border: 1px solid #000000;padding: 5px;}
                            .custom5 { font-size: 10px; border: 1px solid #000000; padding: 5px;}
                            .custom6 { font-size: 10px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                        </style>
                    </head>
                    <body>
                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                            <td class="custom2" width="320">CODING SAMPLE SD</td>
                            <td class="custom3">' . self::tanggal_indonesia($tgl_sampling) . '</td>
                        </tr>
                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        <tr>
                            <td class="custom4">
                                <table width="100%">
                                    <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                    <tr><td style="text-align: center;">' . $no_document . '</td></tr>
                                </table>
                            </td>
                            <td width="120" class="custom4" style="text-align: center;">' . $nama_perusahaan . $konsultan . '</td>
                            <td class="custom3">' . $no_order . '</td>
                        </tr>
                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                    </table>
        ');

        $header = '
                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                        <tr>
                            <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                            <td class="custom2" width="320">CODING SAMPLING SD</td>
                            <td class="custom3">' . self::tanggal_indonesia($tgl_sampling) . '</td>
                        </tr>
                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                        <tr>
                            <td class="custom4">
                                <table width="100%">
                                    <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                    <tr><td style="text-align: center;">' . $no_document . '</td></tr>
                                </table>
                            </td>
                            <td width="120" class="custom4">' . $nama_perusahaan . $konsultan . '</td>
                            <td class="custom3">' . $no_order . '</td>
                        </tr>
                        
                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                    </table>';

        $pdf->defaultheaderline = 0;
        $pdf->SetHeader($header);

        $pdf->WriteHTML('
                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
						<tr>
							<th class="custom6" width="90">CS</th>
							<th class="custom6" width="70">KATEGORI</th>
							<th class="custom6">DESKRIPSI</th>
							<th class="custom6" width="128">BARCODE</th>
							<th class="custom6" width="28">CS</t>
							<th class="custom6" width="28">C-1</th>
							<th class="custom6" width="28">C-2</t>
							<th class="custom6" width="28">C-3</th>
						</tr>
        ');

        foreach ($data_sampling as $key => $val) {
            $dat = explode("-", $val->kategori_3);

            $kode = $val->no_sampel;
            $file_path = public_path() . '/barcode/sample/' . $val->file_koding_sample;

            $pdf->WriteHTML('
                        <tr>
							<td class="custom5" width="90">' . $kode . '</td>
							<td class="custom5" width="70">' . $dat[1] . '</td>
							<td class="custom5" height="60">' . $val->keterangan_1 . '</td>
							<td class="custom5" width="128"><img src="' . $file_path . '" style="height: 30px; width:180px;"></td>
							<td class="custom5" width="28">' . $val->jumlah_label . '</td>
							<td class="custom5" width="28"></td>
							<td class="custom5" width="28"></td>
							<td class="custom5" width="28"></td>
						</tr>
            ');
        }

        $pdf->WriteHTML('</table></body></html>');

        $pdf->Output(public_path() . '/cs/' . $filename, 'F');

        return $filename;
        // return $pdf->Output('', 'I');
    }


    private function cetakBarcodePDF($data_sampling)
    {
        $konsultan = null;
        $no_order = null;

        $konsultan = '';
        foreach ($data_sampling as $kk => $vv) {
            $konsultan = $vv->konsultan;
            $no_order = $vv->no_order;
        }

        if ($konsultan)
            $konsultan = ' (' . $konsultan . ')';

        $mpdfConfig = array(
            'mode' => 'utf-8',
            'format' => array(50, 15),
            'margin_left' => 1,
            'margin_right' => 1,
            'margin_top' => 0.5,
            'margin_header' => 0, // 30mm not pixel
            'margin_bottom' => 0, // 30mm not pixel
            'margin_footer' => 0,
        );

        $pdf = new Mpdf($mpdfConfig);

        $filename = 'BARCODE_CS_SD_' . $no_order . '.pdf';

        $pdf->WriteHTML('
            <!DOCTYPE html>
                <html>
                    <head>
                        <style>
                            .colom1 { text-align: center; padding-right: 40px; }
                            .line { border-width: 10; color: black; }
                        </style>
                    </head>
                    <body>
        ');


        $pdf->WriteHTML('<table width="100%"><tr>');

        $counter = 0;
        foreach ($data_sampling as $data) { //$data->jumlah_label
            for ($i = 0; $i < $data->jumlah_label; $i++) {
                if ($counter % 2 == 0)
                    $pdf->WriteHTML("<tr>");

                $padding = ($counter % 2 == 0) ? '8% 45% 0% 0%;' : '8% 0% 0% 0%;';
                $pdf->WriteHTML('
                    <th>
                        <td style="text-align: center; padding: ' . $padding . '">
                            <span class="custom5"><img src="' . public_path() . '/barcode/sample/' . $data->file_koding_sample . '" style="height: 50px; width:160px;"></span>
                            <br><br>
                            <span style="font-size: 18px; font-weight: bold;">' . $data->no_sampel . '</span>
                        </td>
                    </th>
                ');

                if ($counter % 2 == 1)
                    $pdf->WriteHTML("</tr>"); // Tutup row tiap 2 kolom

                $counter++;
            }
        }

        if ($counter % 2 == 1)
            $pdf->WriteHTML("<td></td></tr>");

        $pdf->WriteHTML('</table></body></html>');

        $pdf->Output(public_path() . '/cs/' . $filename, 'F');

        return $filename;
        // return $pdf->Output('', 'I');
    }

    public function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $hari_map = ['Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => "Jum'at", 'Sat' => 'Sabtu'];

        $hari = $hari_map[date('D', strtotime($tanggal))];
        $var = explode('-', $tanggal);

        if ($mode == 'period')
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        if ($mode == 'hari')
            return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];

        return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
    }
}
