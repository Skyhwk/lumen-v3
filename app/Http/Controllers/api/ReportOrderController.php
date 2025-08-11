<?php

namespace App\Http\Controllers\api;

use Datatables;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\DataLapanganPsikologi;
use Illuminate\Http\Request;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MasterBakumutu;
use App\Models\MasterSubKategori;
use App\Models\MasterRegulasi;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

class ReportOrderController extends Controller
{
    public function index(Request $request)
    {
        $rekapOrder = OrderHeader::with(['orderDetail', 'jadwal', 'user.karyawan', 'user2.karyawan'])->where('is_active', true);

        if ($request->id_cabang)
            $rekapOrder->where('id_cabang', $request->id_cabang);
        if ($request->periode)
            $rekapOrder->whereYear('tanggal_penawaran', $request->periode);

        return Datatables::of($rekapOrder)
            ->filter(function ($query) use ($request) {
                if ($request->has('search') && !empty($request->search['value'])) {
                    $keyword = strtolower($request->search['value']);
                    $query->where(function ($q) use ($keyword) {
                        $q->orWhere(DB::raw('LOWER(no_order)'), 'LIKE', "%{$keyword}%")
                            ->orWhere(DB::raw('LOWER(nama_perusahaan)'), 'LIKE', "%{$keyword}%")
                            ->orWhere(DB::raw('LOWER(konsultan)'), 'LIKE', "%{$keyword}%")
                            ->orWhere(DB::raw('LOWER(tanggal_penawaran)'), 'LIKE', "%{$keyword}%");
                    });
                }
            })
            ->make(true);
    }

    public function show(Request $request)
    {
        $orderDetail = OrderDetail::where(['id_order_header' => $request->id, 'is_active' => true]);

        return Datatables::of($orderDetail)->make(true);
    }

    public function getCabang()
    {
        return response()->json(MasterCabang::whereIn('id', $this->privilageCabang)->where('is_active', true)->get());
    }

    public function getKategori()
    {
        return response()->json(MasterKategori::where('is_active', true)->get());
    }

    public function getSubkategori(Request $request)
    {
        return response()->json(MasterSubKategori::where(['is_active' => true, 'id_kategori' => $request->id_kategori])->get());
    }

    public function getParameter(Request $request)
    {
        $param = [];
        $bakumutu = MasterBakumutu::where(['id_regulasi' => explode('-', $request->regulasi)[0], 'is_active' => true])->get();
        foreach ($bakumutu as $a)
            array_push($param, $a->id_parameter . ';' . $a->parameter);

        $data = Parameter::where(['id_kategori' => $request->id_kategori, 'is_active' => true])->get();

        return response()->json(['data' => $data, 'value' => $param, 'status' => '200'], 200);
    }

    public function saveOrderDetail(Request $request)
    {
        DB::beginTransaction();
        try {
            $orderDetail = OrderDetail::where(['id' => $request->id, 'is_active' => true])->first();
            $dataLapanganPsikologi = DataLapanganPsikologi::where(['no_sampel' => $request->no_sampel])->first();
            $parameterArray = is_array($request->param) ? array_map('trim', explode(';', $request->param[0])) : [];


            $orderDetail->tanggal_sampling = $request->tgl_tugas;
            $orderDetail->tanggal_terima = $request->tgl_terima ?: null;

            // if ($orderDetail->regulasi != $request->regulasi) $orderDetail->regulasi = json_encode($request->regulasi);
            // if ($orderDetail->parameter != $request->param) $orderDetail->parameter = json_encode($request->param);

            $orderDetail->keterangan_1 = $request->keterangan_1;
            $orderDetail->keterangan_2 = $request->keterangan_2;
            $orderDetail->kategori_1 = $request->kategori_1;
            // $orderDetail->kategori_2 = $request->kategori_2;
            // $orderDetail->kategori_3 = $request->kategori_3;
            $orderDetail->updated_by = $this->karyawan;
            $orderDetail->updated_at = date('Y-m-d H:i:s');

            $orderDetail->save();

            if ($parameterArray[1] == 'Psikologi') {
                $dataExploded = explode('.', $request->keterangan_1);
                if ($dataLapanganPsikologi) {
                    $dataLapanganPsikologi->nama_pekerja = $dataExploded[0];
                    $dataLapanganPsikologi->divisi = $dataExploded[1];
                    $dataLapanganPsikologi->save();
                }
            }

            if ($orderDetail) {
                $isContract = str_contains($request->no_document, 'QTC');
                if (!$isContract) {
                    $qt = QuotationNonKontrak::where('no_document', $request->no_document)->first();
                    if ($qt) {
                        $data_pendukung_sampling = json_decode($qt->data_pendukung_sampling);
                        foreach ($data_pendukung_sampling as &$dps) {
                            foreach ($dps->penamaan_titik as &$pt) {
                                $nomor = key((array) $pt);
                                if ($nomor == explode('/', $request->no_sampel)[1]) {
                                    $pt->$nomor = $request->keterangan_1;
                                }
                            }
                        }

                        $qt->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                        $qt->save();
                    }
                } else {
                    $groupedNamedPoints = [];

                    $qtcHeader = QuotationKontrakH::where('no_document', $request->no_document)->first();
                    $qtcDetail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $qtcHeader->id)->get();

                    // UPDATE QTCD
                    if ($qtcDetail) {
                        foreach ($qtcDetail as $qtD) {
                            $data_pendukung_sampling = json_decode($qtD->data_pendukung_sampling);
                            foreach ($data_pendukung_sampling as &$dps) {
                                foreach ($dps->data_sampling as &$ds) {
                                    foreach ($ds->penamaan_titik as &$pt) {
                                        $nomor = key((array) $pt);
                                        if ($nomor == explode('/', $request->no_sampel)[1]) {
                                            $pt->$nomor = $request->keterangan_1;
                                        }
                                        $props = get_object_vars($pt);
                                        $nomor = key($props);
                                        $titik = $props[$nomor];
                                        $fullGroupKey = $ds->kategori_1 . ';' . $ds->kategori_2 . ';' . json_encode($ds->regulasi) . ';' . json_encode($ds->parameter);

                                        $groupedNamedPoints[$fullGroupKey][$dps->periode_kontrak][] = [
                                            $nomor => $titik
                                        ];
                                    }
                                }
                            }

                            $qtD->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                            $qtD->save();
                        }
                    }


                    // UPDATE QTCH
                    // dd(array_keys($groupedNamedPoints));
                    if ($qtcHeader) {
                        $data_pendukung_sampling = json_decode($qtcHeader->data_pendukung_sampling);
                        foreach ($data_pendukung_sampling as &$dps) {

                            $fullGroupKey = $dps->kategori_1 . ';' . $dps->kategori_2 . ';' . json_encode($dps->regulasi) . ';' . json_encode($dps->parameter);

                            // Filter penamaan titik
                            $penamaan_sampling_all = array_filter($groupedNamedPoints[$fullGroupKey], function ($group) {
                                if (!is_array($group))
                                    return false;
                                foreach ($group as $item) {
                                    if (is_array($item) || is_object($item)) {
                                        foreach ($item as $value) {
                                            if (!empty($value))
                                                return true;
                                        }
                                    }
                                }
                                return false;
                            });

                            // Proses penamaan titik Header
                            if ($penamaan_sampling_all) {
                                $penamaan_sampling = array_map(function ($item) {
                                    return array_values($item)[0] ?? "";
                                }, reset($penamaan_sampling_all));
                            } else {
                                $penamaan_sampling = array_fill(0, $dps->jumlah_titik, "");
                            }

                            $dps->penamaan_titik = $penamaan_sampling;
                        }

                        $qtcHeader->data_pendukung_sampling = json_encode($data_pendukung_sampling);
                        $qtcHeader->save();
                    }
                }
            }
            DB::commit();
            return response()->json(['message' => 'Saved Successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::where(['id_kategori' => $request->id_kategori, 'is_active' => true])->get();

        return response()->json($data, 200);
    }
}
