<?php

namespace App\Http\Controllers\external;

use App\Helpers\HelperSatuan;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

//model
use App\Models\HoldHp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Invoice;
use App\Models\MasterBakumutu;
use App\Models\MdlEmisi;
use App\Models\MdlUdara;
use App\Services\GroupedCfrByLhp;
use Carbon\Carbon;

class LHPHandleController extends BaseController
{
    public function cekLHP(Request $request)
    {
        $token = str_replace(' ', '+', $request->token);
        // $token = $request->token;

        if ($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();
            if ($cekData) {
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();

                if ($cekData) {
                    $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                    $checkHold = HoldHp::where('no_order', $dataLhp->no_order)->first();
                    if ($checkHold && $checkHold->is_hold == 1) {
                        // Sudah di-hold, jangan tampilkan
                        return response()->json(['message' => 'Document On Hold'], 405);
                    } else {
                        if ($dataLhp && isset($dataLhp->filename) && $dataLhp->filename != null && $dataLhp->filename != '') {
                            if (file_exists(public_path('laporan/hasil_pengujian/' . $dataLhp->filename))) {
                                return response()
                                    ->json(
                                        [
                                            'data' => $dataLhp,
                                            'message' => 'data hasbenn show',
                                            'qt_status' => $cekData->quotation_status,
                                            'status' => '201',
                                            'uri' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename
                                        ],
                                        200
                                    );
                                return response()->json(['message' => 'Document found', 'data' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename], 200);
                            } else {
                                return response()->json(['message' => 'Document found but file not exists'], 403);
                            }
                            // return response()->json(['message' => 'Document found', 'data' => $dataLhp->filename], 200);
                        } else if ($dataLhp && $dataLhp->filename == null || $dataLhp->filename == '') {
                            return response()->json(['message' => 'Document found but file not exists'], 403);
                        } else {
                            return response()->json(['message' => 'Document not found'], 404);
                        }
                    }
                } else {
                    return response()->json(['message' => 'Token not found'], 401);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }

    public function newCheckLhp(Request $request)
    {
        $getIndex = new HelperSatuan;

        $token = str_replace(' ', '+', $request->token);
        if ($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();

            if ($cekData) {
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                $periode = $dataLhp->periode;
                $noOrder = $dataLhp->no_order;

                $fileName = $dataLhp->filename ?? null;

                $checkHold = HoldHp::where('no_order', $noOrder)->where('periode', $periode)->first();

                $dataOrder = OrderHeader::where('no_order', $noOrder)->where('is_active', true)->first();

                $cekInvoice = Invoice::with(['recordPembayaran', 'recordWithdraw'])->where('no_order', $noOrder);
                $all = false;
                foreach ($cekInvoice->get() as $invoice) {
                    if ($invoice->periode == "all") $all = true;
                    break;
                }

                if ($periode != null && $periode != '' && !$all) $cekInvoice = $cekInvoice->where('periode', $periode);
                $cekInvoice = $cekInvoice->where('is_active', true)->get() ?? null;

                if ($dataOrder) {
                    $dataGrouped = (new GroupedCfrByLhp($dataOrder, $periode))->get()->toArray();
                    foreach ($dataGrouped as &$item) {
                        $rekapPengujian = [];
                        foreach ($item['order_details'] as $od) {
                            $parameters = json_decode($od['parameter'], true);
                            foreach ($parameters as $parameter) {
                                [$parameterId, $parameterName] = explode(';', $parameter);

                                $hasResult = false;
                                $isOnProcess = false;

                                foreach (['ws_value_air', 'ws_value_udara', 'ws_value_emisi_cerobong'] as $ws) {
                                    if ($od[$ws]) {
                                        if ($ws == 'ws_value_air') {
                                            foreach ($od[$ws] as $value) {
                                                foreach (['gravimetri', 'titrimetri', 'colorimetri', 'subkontrak'] as $header) {
                                                    if (
                                                        isset($value[$header])
                                                        && $value[$header]['parameter'] == $parameterName
                                                    ) {
                                                        $isOnProcess = true;

                                                        if (isset($value[$header]['is_approved']) || isset($value[$header]['is_approve'])) {
                                                            $rekapPengujian[] = [
                                                                'no_sampel' => $od['no_sampel'],
                                                                'parameter' => $parameterName,
                                                                'hasil_uji' => $value['hasil'],
                                                            ];
                                                            $hasResult = true;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        if ($ws == 'ws_value_udara') {
                                            foreach ($od[$ws] as $value) {
                                                foreach (['lingkungan', 'microbiologi', 'medanLm', 'sinaruv', 'iklim', 'getaran', 'kebisingan', 'direct_lain', 'partikulat', 'pencahayaan', 'swab', 'subkontrak', 'dustfall', 'debuPersonal'] as $header) {
                                                    if (
                                                        isset($value[$header])
                                                        && $value[$header]['parameter'] == $parameterName
                                                    ) {
                                                        $isOnProcess = true;

                                                        $satuan = $value['satuan'];
                                                        if (!$satuan) {
                                                            $regulasiIds = collect(json_decode($od['regulasi'], true))->map(fn($item) => explode('-', $item)[0])->unique()->toArray();
                                                            $bakuMutu = MasterBakumutu::where(['id_parameter' => $parameterId, 'is_active' => true])->whereIn('id_regulasi', $regulasiIds)->first();
                                                            if ($bakuMutu && $bakuMutu->satuan) $satuan = $bakuMutu->satuan;
                                                        }

                                                        $index = $satuan ? ($getIndex->udara($satuan) ?: 1) : 1;
                                                        $column = "hasil$index";
                                                        $hasil = $value[$column] ?: $value['hasil1'];

                                                        $mdl = MdlUdara::where(['parameter_id' => $parameterId, 'is_active' => true])->latest()->first();
                                                        if ($mdl) $hasil = $hasil < $mdl->$column ? ('<' . $mdl->$column) : $hasil;

                                                        if ($parameterId == '242') { // Getaran
                                                            $result = [];
                                                            foreach (json_decode($hasil, true) as $key => $val) {
                                                                $result[] = "$key: $val";
                                                            }

                                                            $hasil = implode('<br />', $result);
                                                        }

                                                        if (isset($value[$header]['is_approved']) || isset($value[$header]['is_approve'])) {
                                                            $rekapPengujian[] = [
                                                                'no_sampel' => $od['no_sampel'],
                                                                'parameter' => $parameterName,
                                                                'hasil_uji' => $hasil,
                                                            ];
                                                            $hasResult = true;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        if ($ws == 'ws_value_emisi_cerobong') {
                                            foreach ($od[$ws] as $value) {
                                                foreach (['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'] as $header) {
                                                    if (
                                                        isset($value[$header])
                                                        && $value[$header]['parameter'] == $parameterName
                                                    ) {
                                                        $isOnProcess = true;

                                                        $satuan = $value['satuan'];
                                                        if (!$satuan) {
                                                            $regulasiIds = collect(json_decode($od['regulasi'], true))->map(fn($item) => explode('-', $item)[0])->unique()->toArray();
                                                            $bakuMutu = MasterBakumutu::where(['id_parameter' => $parameterId, 'is_active' => true])->whereIn('id_regulasi', $regulasiIds)->first();
                                                            if ($bakuMutu && $bakuMutu->satuan) $satuan = $bakuMutu->satuan;
                                                        }

                                                        $index = $satuan ? ($getIndex->emisi($satuan) ?: '') : '';
                                                        $column = "C$index";
                                                        $hasil = $value[$column] ?: $value['C'];

                                                        $mdl = MdlEmisi::where(['parameter_id' => $parameterId, 'is_active' => true])->latest()->first();
                                                        if ($mdl) $hasil = $hasil < $mdl->$column ? ('<' . $mdl->$column) : $hasil;

                                                        if (isset($value[$header]['is_approved']) || isset($value[$header]['is_approve'])) {
                                                            $rekapPengujian[] = [
                                                                'no_sampel' => $od['no_sampel'],
                                                                'parameter' => $parameterName,
                                                                'hasil_uji' => $hasil,
                                                            ];
                                                            $hasResult = true;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                foreach (['data_lapangan_ergonomi', 'data_lapangan_psikologi', 'data_lapangan_emisi_kendaraan'] as $dataLapangan) {
                                    if ($od[$dataLapangan]) {
                                        if ($parameterId == '318') { // Psikologi
                                            foreach ($od[$dataLapangan] as $dlPsikologi) {
                                                $isOnProcess = true;
                                                if (isset($dlPsikologi['is_approved']) || isset($dlPsikologi['is_approve'])) {
                                                    $hasil = json_decode($dlPsikologi['hasil'], true);

                                                    $result = [];
                                                    foreach ($hasil['kesimpulan'] as $key => $val) {
                                                        $titles = [
                                                            'kp' => 'Konflik Peran',
                                                            'pk' => 'Pengembangan Karir',
                                                            'tp' => 'Ketaksaan Peran',
                                                            'tjo' => 'Tanggung Jawab terhadap Orang Lain',
                                                            'bbkual' => 'Beban Berlebih Kualitatif',
                                                            'bbkuan' => 'Beban Berlebih Kuantitatif',
                                                        ];
                                                        $title = array_key_exists($key, $titles) ? $titles[$key] : '';

                                                        $nilai = $val['nilai'];
                                                        $kesimpulan = $val['kesimpulan'];
                                                        $result[] = "$title: $nilai ($kesimpulan)";
                                                    }

                                                    $hasilUji = implode('<br />', $result);

                                                    $rekapPengujian[] = [
                                                        'no_sampel' => $od['no_sampel'] . '<br />' . $od['keterangan_1'],
                                                        'parameter' => $parameterName,
                                                        'hasil_uji' => $hasilUji,
                                                    ];
                                                    $hasResult = true;
                                                }
                                            }
                                        }

                                        $isOnProcess = true;
                                        if (isset($dlPsikologi['is_approved']) || isset($dlPsikologi['is_approve'])) {
                                            if ($parameterId == '376' || $parameterId == '2275') { // Opasitas (Solar) || Opasitas (ESB) ?? diesel keknya
                                                $rekapPengujian[] = [
                                                    'no_sampel' => $od['no_sampel'],
                                                    'parameter' => $parameterName,
                                                    'hasil_uji' => $od[$dataLapangan]['opasitas'],
                                                ];
                                                $hasResult = true;
                                            }

                                            if ($parameterId == '392' || $parameterId == '1201') { // CO (Bensin) || CO (Gas)
                                                $rekapPengujian[] = [
                                                    'no_sampel' => $od['no_sampel'],
                                                    'parameter' => $parameterName,
                                                    'hasil_uji' => $od[$dataLapangan]['co'],
                                                ];
                                                $hasResult = true;
                                            }

                                            if ($parameterId == '393' || $parameterId == '1202') { // HC (Bensin) || HC (Gas)
                                                $rekapPengujian[] = [
                                                    'no_sampel' => $od['no_sampel'],
                                                    'parameter' => $parameterName,
                                                    'hasil_uji' => $od[$dataLapangan]['hc'],
                                                ];
                                                $hasResult = true;
                                            }
                                        }
                                    }
                                }

                                // 3. PENENTUAN: Kalau setelah muter-muter flagnya masih false, berarti ZONK (belum ada hasil).
                                if (!$hasResult) {
                                    $statusText = $isOnProcess ? 'Sedang dilakukan analisa' : 'Belum dilakukan analisa';

                                    $rekapPengujian[] = [
                                        'no_sampel' => $od['no_sampel'],
                                        'parameter' => $parameterName,
                                        'hasil_uji' => $statusText
                                    ];
                                }
                            }
                        }
                        $item['rekap_pengujian'] = $rekapPengujian;
                        unset($item['order_details']);
                    }

                    return response()->json(['message' => 'Data LHP found', 'data' => $dataGrouped, 'order' => $dataOrder, 'periode' => $periode, 'invoice' => collect($cekInvoice)->toArray(), 'fileName' => $fileName, 'hold' => $checkHold], 200);
                } else {
                    return response()->json(['message' => 'Data Order not found'], 404);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }
}
