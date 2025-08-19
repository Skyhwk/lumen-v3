<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\{QuotationKontrakH, QuotationKontrakD, QuotationNonKontrak};
use App\Models\{KontakPelanggan, AlamatPelanggan, PicPelanggan};
use App\Models\{OrderHeader, OrderDetail};
use App\Models\{SamplingPlan, Jadwal};
use App\Models\{Ftc, FtcT};
use App\Models\{
    MasterCabang,
    MasterKategori,
    MasterSubKategori,
    MasterRegulasi,
    MasterBakumutu,
    MasterKaryawan,
    MasterPelanggan,
    KelengkapanKonfirmasiQs
};
use App\Models\JobTask;
use App\Models\HargaTransportasi;
use App\Models\HargaParameter;
use App\Models\TemplatePenawaran;
use App\Models\Parameter;
use App\Models\Invoice;
use App\Jobs\RenderPdfPenawaran;
use App\Services\GeneratePraSampling;
use App\Services\RenderNonKontrakCopy;
use App\Services\RenderKontrakCopy;
use App\Services\RenderNonKontrak;
use App\Services\RenderKontrak;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\RenderInvoice;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class RequestQuotationController extends Controller
{
    public function index(Request $request)
    {
        switch ($request->mode) {
            case 'non_kontrak':
                $data = QuotationNonKontrak::where('is_active', $request->active)
                    ->where('id_cabang', $request->id_cabang)
                    ->where('is_approved', $request->approve)
                    // ->where(function ($query) {
                    //     $query->whereNull('flag_status')
                    //         ->orWhere('flag_status', '=', 'rejected');
                    // })
                    ->whereRaw('COALESCE(flag_status, ?) = ?', ['rejected', 'rejected'])
                    ->where('is_active', true)
                    ->whereYear('tanggal_penawaran', $request->periode);
                break;
            case 'kontrak':
                $data = QuotationKontrakH::with('detail')
                    ->where('id_cabang', $request->id_cabang)
                    ->where('is_approved', $request->approve)
                    // ->where(function ($query) {
                    //     $query->whereNull('flag_status')
                    //         ->orWhere('flag_status', '=', 'rejected');
                    // })
                    ->whereRaw('COALESCE(flag_status, ?) = ?', ['rejected', 'rejected'])
                    ->where('is_active', true)
                    ->whereYear('tanggal_penawaran', $request->periode);
                break;
        }

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $data = $data->where('sales_id', $this->user_id);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);
                $data = $data->whereIn('sales_id', $bawahan);
                break;
        }

        return Datatables::of($data)
            ->editColumn('email_cc', function ($item) {
                $emailCC = json_decode($item['email_cc']) ?? null;
                if ($item->sales_id == 19 && $emailCC != null) {
                    $emailCC = array_unique(array_merge($emailCC, ['sisca@intilab.com']));
                } elseif ($item->sales_id == 19) {
                    $emailCC = ['sisca@intilab.com'];
                }
                return $emailCC;
            })
            ->make(true);
    }

    public function approve(Request $request)
    {
        /* try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with('sampling')->where('id', $request->id)->firstOrFail();
            } elseif ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with('sampling')->where('id', $request->id)->firstOrFail();
            }
            $data->flag_status = 'draft';
            if ($data->is_emailed == true && $data->is_generated == true) {
                $data->is_rejected = false;
                $data->is_approved = true;
                $data->rejected_by = null;
                $data->rejected_at = null;
                $data->flag_status = count($data->sampling) > 0 ? 'sp' : 'emailed';
            } else if ($data->is_emailed == false && $data->is_generated == true) {
                $data->is_rejected = false;
                $data->is_approved = true;
                $data->rejected_by = null;
                $data->rejected_at = null;
                $data->flag_status = 'draft';
            }

            $data->save();

            return response()->json([
                "message" => "Approved successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "Failed: " . $e->getMessage(),
            ], 500);
        } */

        /*
            Update Muhammad Afryan Saputra
            2025-03-12
        */
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }

                $order_h = OrderHeader::where('no_document', $data->no_document)->first();
                $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->first();

                if (!is_null($order_h)) {
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');

                    $order_h->is_active = true;
                    $order_h->is_revisi = false;
                    $order_h->save();

                    $order_d = OrderDetail::where('id_order_header', $order_h->id);
                    $no_sampels = $order_d->pluck('no_sampel');
                    $order_d->update(['is_active' => true]);

                    Ftc::whereIn('no_sample', $no_sampels)->update(['is_active' => true]);
                    FtcT::whereIn('no_sample', $no_sampels)->update(['is_active' => true]);

                    $data->flag_status = 'ordered';
                } else if ($cek_sp != null && $data->is_emailed == 1) {
                    $data->flag_status = 'sp';
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else if ($cek_sp == null && $data->is_emailed == 1) {
                    $data->flag_status = 'emailed';
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else if ($data->flag_status == 'draft') {
                    $data->is_approved = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $data->flag_status = 'draft';
                }
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success approved.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot approved data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::where('id', $request->id)->firstOrFail();
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::where('id', $request->id)->firstOrFail();
            }
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->is_active = false;
            $data->save();

            return response()->json([
                "status" => true,
                "message" => "Data deleted successfully"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "Failed to delete data: " . $e->getMessage()
            ], 500);
        }
    }

    public function getCabang(Request $request)
    {
        $cabang = MasterCabang::where('is_active', true)->get();
        return response()->json($cabang);
    }

    public function getSales(Request $request)
    {
        $data = MasterKaryawan::whereIn('id_jabatan', [140, 15, 21, 22, 23, 24, 25]) // Sales
            ->where('is_active', true)
            ->orWhere('id', 41) // Novva Novita Ayu Putri Rukmana
            ->get();

        return response()->json($data);
    }

    public function getSalesById(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)->where('id', $request->id)->first();
        return response()->json($data);
    }

    public function getPelanggan(Request $request)
    {
        $term = $request->input('term');
        $ext = $request->input('ext');
        $current = $request->input('current');

        $query = MasterPelanggan::where('is_active', true);

        if ($ext) {
            $query = $query->where('id_pelanggan', '<>', $ext);
        }

        if ($term) {
            $query = $query->where(function ($query) use ($term) {
                $query->where('nama_pelanggan', 'LIKE', '%' . $term . '%')
                    ->orWhere('id_pelanggan', 'LIKE', '%' . $term . '%');
            });
        }

        $data = $query->select('id', 'nama_pelanggan', 'id_pelanggan')
            ->limit(50);

        if ($current) {
            $currentPelanggan = MasterPelanggan::where('id_pelanggan', $current)
                ->select('id', 'nama_pelanggan', 'id_pelanggan');
            $data = $data->union($currentPelanggan)->get();
        } else {
            $data = $data->get();
        }

        return response()->json(['data' => $data], 200);
    }

    public function getInformation(Request $request)
    {
        switch ($request->mode) {
            case 'non_kontrak':
                $data = QuotationNonKontrak::where('pelanggan_ID', $request->id_pelanggan)
                    ->where('is_active', true)
                    ->orderBy('id', 'DESC')
                    ->get();
                $count = count($data);
                if ($count == 0) {
                    $message = "Pelanggan $request->id_pelanggan belum pernah melakukan penawaran.";
                    $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
                        ->where('id_pelanggan', $request->id_pelanggan)->first();
                    $status = 201;
                } else {
                    $message = "Pelanggan $request->id_pelanggan sudah melakukan penawaran sebanyak $count kali.";
                    $data = $data->first();
                    $status = 200;
                }

                return response()->json([
                    'data' => $data,
                    'message' => $message,
                    'status' => $status
                ], 200);
            case 'kontrak':
                $data = QuotationKontrakH::where('pelanggan_ID', $request->id_pelanggan)
                    ->where('is_active', true)
                    ->orderBy('id', 'DESC')
                    ->get();

                $count = count($data);
                if ($count == 0) {
                    $message = "Pelanggan $request->id_pelanggan belum pernah melakukan penawaran.";
                    $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
                        ->where('id_pelanggan', $request->id_pelanggan)->first();
                    $status = 201;
                } else {
                    $message = "Pelanggan $request->id_pelanggan sudah melakukan penawaran sebanyak $count kali.";
                    $data = $data->first();
                    $status = 200;
                }

                // dd($data);
                return response()->json([
                    'data' => $data,
                    'message' => $message,
                    'status' => $status
                ], 200);
            default:
                $data = [];
                $message = "Invalid mode.";
                return response()->json([
                    'data' => $data,
                    'message' => $message
                ], 400);
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json($data);
    }

    public function getSubkategori(Request $request)
    {
        $data = MasterSubKategori::where('is_active', true)->select('id', 'nama_sub_kategori', 'id_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $data = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('is_active', true)->get();
            // $data = Parameter::where('is_active', true)->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 401);
        }
    }

    public function getParameterRegulasi(Request $request)
    {
        try {
            $idBakumutut = explode('-', $request->id_regulasi);
            $sub_category = explode('-', $request->sub_category);
            $category = explode('-', $request->id_category);

            $bakumutu = MasterBakumutu::where('id_regulasi', $idBakumutut[0])->where('is_active', true)->get();
            $param = array();
            foreach ($bakumutu as $a) {
                array_push($param, $a->id_parameter . ';' . $a->parameter);
            }
            // dd($param);
            /* version 1 */
            $data = Parameter::where('is_active', true)
                ->where('id_kategori', $category[0])
                ->get();

            return response()->json([
                'data' => $data,
                'value' => $param,
                'status' => '200'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true)->get();
        return response()->json($data);
    }

    public function getWilayah(Request $request)
    {
        $data = HargaTransportasi::where('is_active', true)->where('status', $request->status_wilayah)->select('id', 'wilayah')->get();
        return response()->json($data);
    }

    public function getTemplateList(Request $request)
    {
        $jabatan = $request->attributes->get('user')->karyawan->grade;
        $query = TemplatePenawaran::where('tipe', $request->mode)
            ->where('is_active', true);

        // Gunakan LIKE untuk pencarian parsial jika ada search term
        if ($request->has('nama_template') && !empty($request->nama_template)) {
            $query->where('nama_template', 'LIKE', '%' . $request->nama_template . '%');
        }

        if ($jabatan == 'SUPERVISOR' || $jabatan == 'MANAGER') {
            $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
            $query->whereIn('created_by', $bawahan);
        } else {
            $query->where('created_by', $this->karyawan);
        }

        $data = $query->get();

        return response()->json($data);
    }

    public function writeQuotation(Request $request)
    {
        // dd($request->all());
        // Mengubah array menjadi objek
        $payload = json_decode(json_encode($request->all(), JSON_OBJECT_AS_ARRAY));
        // dd($payload);
        switch ($payload->informasi_pelanggan->modeQt) {
            case 'non_kontrak':
                return $this->handleNonKontrak($payload);
            case 'kontrak':
                return $this->handleKontrak($payload);
            default:
                return response()->json([
                    'message' => 'Type quotation tidak ditemukan.'
                ], 400);
        }
    }

    private function handleNonKontrak($payload)
    {
        switch ($payload->informasi_pelanggan->mode) {
            case 'create':
                return $this->createNonKontrak($payload);
            case 'update':
                return $this->updateNonKontrak($payload);
            case 'revisi':
                return $this->revisiNonKontrak($payload);
            default:
                return response()->json([
                    'message' => 'System tidak dapat membaca apakah create, update atau revisi.'
                ], 400);
        }
    }

    private function handleKontrak($payload)
    {
        switch ($payload->informasi_pelanggan->mode) {
            case 'create':
                return $this->createKontrak($payload);
            case 'update':
                return $this->updateKontrak($payload);
            case 'revisi':
                return $this->revisiKontrak($payload);
            default:
                return response()->json([
                    'message' => 'System tidak dapat membaca apakah create, update atau revisi.'
                ], 400);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function randomstr($str, $no)
    {
        $str = str_replace(' ', '', $str);
        $str = str_replace('\t', '', $str);
        $str = str_replace(',', '', $str);
        $result = substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
        return $result;
    }

    private function createNonKontrak($payload)
    {

        foreach ($payload->data_pendukung as $index => $pengujian) {
            $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
            $penamaanTitik = $pengujian->penamaan_titik ?? [];

            if (count($penamaanTitik) !== $jumlahTitik) {
                return response()->json([
                    'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                ], 403);
            }
        }
        // Implementasi untuk create non kontrak
        if (!isset($payload->informasi_pelanggan->tgl_penawaran) || $payload->informasi_pelanggan->tgl_penawaran == null) {
            return response()->json([
                'message' => 'Mohon isi tanggal penawaran terlebih dahulu.'
            ], 400);
        }

        $db = DATE('Y', strtotime($payload->informasi_pelanggan->tgl_penawaran));
        $sales_id = $payload->informasi_pelanggan->sales_id;
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $tahun_chek = date('y', strtotime($payload->informasi_pelanggan->tgl_penawaran));  // 2 digit tahun (misal: 25)
            $bulan_chek = date('m', strtotime($payload->informasi_pelanggan->tgl_penawaran));  // 2 digit bulan (misal: 01)
            $bulan_chek = self::romawi($bulan_chek);

            $cek = QuotationNonKontrak::where('id_cabang', $this->idcabang)
                ->where('no_document', 'not like', '%R%')
                ->where('no_document', 'like', '%/' . $tahun_chek . '-%')
                ->orderBy('id', 'DESC')
                ->first();

            // $no_urut = '1';

            // if ($cek != null) {
            //     $no_urut = floatval(explode('/', $cek->no_document)[3]) + 1;
            // }

            // $no_quotation = sprintf('%06d', ($no_urut));
            // $no_document = 'ISL/QT/' . DATE('y', strtotime($payload->informasi_pelanggan->tgl_penawaran)) . '-' . self::romawi(DATE('m', strtotime($payload->informasi_pelanggan->tgl_penawaran))) . '/' . $no_quotation;

            $no_ = 1;  // Set default nomor urut menjadi 1

            if ($cek != null) {
                // Pisahkan komponen no_document untuk mengambil tahun dan nomor urut terakhir
                $parts = explode('/', $cek->no_document);

                if (count($parts) > 3) {  // Pastikan formatnya sesuai
                    $tahun_cek_full = $parts[2];  // Tahun dan bulan dokumen terakhir
                    list($tahun_cek_docLast, $bulan_cek_docLast) = explode('-', $tahun_cek_full);

                    // Jika tahun dan bulan dokumen terakhir sama dengan tahun dan bulan sekarang
                    // if ((int) $tahun_chek == (int) $tahun_cek_docLast && $bulan_chek == $bulan_cek_docLast) {
                    //     // Ambil nomor urut terakhir dan tambah 1
                    //     $no_ = (int) explode('/', $cek->no_document)[3] + 1;
                    // }
                    if ((int) $tahun_chek == (int) $tahun_cek_docLast) {
                        // Ambil nomor urut terakhir dan tambah 1
                        $no_ = (int) explode('/', $cek->no_document)[3] + 1;
                    }
                }
            }

            // Format nomor dokumen menjadi 8 digit
            $no_quotation = sprintf('%06d', $no_);
            $no_document = 'ISL/QT/' . $tahun_chek . '-' . $bulan_chek . '/' . $no_quotation;

            $data = new QuotationNonKontrak;

            $data->no_quotation = $no_quotation;
            $data->no_document = $no_document;
            $data->pelanggan_ID = $payload->informasi_pelanggan->pelanggan_ID;
            $data->id_cabang = $this->idcabang;

            $data->nama_perusahaan = strtoupper(trim($payload->informasi_pelanggan->nama_perusahaan));
            $data->tanggal_penawaran = $payload->informasi_pelanggan->tgl_penawaran;
            $data->konsultan = strtoupper(trim($payload->informasi_pelanggan->konsultan));
            $data->alamat_kantor = $payload->informasi_pelanggan->alamat_kantor;
            $data->no_tlp_perusahaan = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
            $data->nama_pic_order = ucwords($payload->informasi_pelanggan->nama_pic_order);
            $data->jabatan_pic_order = $payload->informasi_pelanggan->jabatan_pic_order;
            $data->no_pic_order = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
            $data->email_pic_order = $payload->informasi_pelanggan->email_pic_order;
            $data->email_cc = isset($payload->informasi_pelanggan->email_cc) ? json_encode($payload->informasi_pelanggan->email_cc) : null;
            $data->alamat_sampling = $payload->informasi_pelanggan->alamat_sampling;
            $data->no_tlp_sampling = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->nama_pic_sampling = ucwords($payload->informasi_pelanggan->nama_pic_sampling);
            $data->jabatan_pic_sampling = $payload->informasi_pelanggan->jabatan_pic_sampling;
            $data->no_tlp_pic_sampling = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->email_pic_sampling = $payload->informasi_pelanggan->email_pic_sampling;

            $data_sampling = [];
            $harga_total = 0;
            $harga_air = 0;
            $harga_udara = 0;
            $harga_emisi = 0;
            $harga_padatan = 0;
            $harga_swab_test = 0;
            $harga_tanah = 0;
            $harga_pangan = 0;
            $grand_total = 0;
            // $total_diskon = 0;

            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    // dd($item);
                    $param = $item->parameter;
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        // dd();
                        // dump($cek_par->nama_lab);
                        array_push($parameter, $cek_par->nama_lab);
                    }
                    // dd($param);

                    $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                        ->where('is_active', true)
                        ->whereIn('nama_parameter', $parameter)
                        ->where('id_kategori', $kategori)
                        ->first();

                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item->jumlah_titik;

                    $data_sampling[$i] = [
                        'kategori_1' => $item->kategori_1,
                        'kategori_2' => $item->kategori_2,
                        'regulasi' => isset($item->regulasi) ? $item->regulasi : '',
                        'penamaan_titik' => $item->penamaan_titik,
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol
                    ];

                    switch ($kategori) {
                        case '1':
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '4':
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '5':
                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '6':
                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '7':
                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '8':
                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '9':
                            $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                    }
                }
            } else {
                $data_sampling = [];
            }

            $grand_total = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
            $data->data_pendukung_sampling = json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE);

            $data->harga_air = $harga_air;
            $data->harga_udara = $harga_udara;
            $data->harga_emisi = $harga_emisi;
            $data->harga_padatan = $harga_padatan;
            $data->harga_swab_test = $harga_swab_test;
            $data->harga_tanah = $harga_tanah;
            $data->harga_pangan = $harga_pangan;

            $data->grand_total = $grand_total;
            $data->sales_id = $sales_id;
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => "Penawaran berhasil dibuat dengan nomor dokumen $data->no_document",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat penawaran: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function updateNonKontrak($payload)
    {
        // Implementasi untuk update non kontrak
        // dd($payload->informasi_pelanggan);
        if (!isset($payload->informasi_pelanggan->tgl_penawaran) || $payload->informasi_pelanggan->tgl_penawaran == null) {
            return response()->json([
                'message' => 'Mohon isi tanggal penawaran terlebih dahulu.'
            ], 401);
        }

        $db = DATE('Y', strtotime($payload->informasi_pelanggan->tgl_penawaran));
        $sales_id = $payload->informasi_pelanggan->sales_id;
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }

        foreach ($payload->data_pendukung as $index => $pengujian) {
            $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
            $penamaanTitik = $pengujian->penamaan_titik ?? [];

            if (count($penamaanTitik) !== $jumlahTitik) {
                return response()->json([
                    'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                ], 403);
            }
        }
        DB::beginTransaction();
        try {

            $data = QuotationNonKontrak::where('is_active', true)
                ->where('id', $payload->informasi_pelanggan->id)
                ->first();

            //data customer order     -------------------------------------------------------> save ke master customer parrent
            // $data->nama_perusahaan = strtoupper(trim($payload->informasi_pelanggan->nama_perusahaan));
            $data->tanggal_penawaran = $payload->informasi_pelanggan->tgl_penawaran;
            // $data->konsultan = isset($payload->informasi_pelanggan->konsultan) && $payload->informasi_pelanggan->konsultan !== '' ? strtoupper(trim($payload->informasi_pelanggan->konsultan)) : null;
            $data->alamat_kantor = $payload->informasi_pelanggan->alamat_kantor;
            $data->no_tlp_perusahaan = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
            $data->nama_pic_order = ucwords($payload->informasi_pelanggan->nama_pic_order);
            $data->jabatan_pic_order = $payload->informasi_pelanggan->jabatan_pic_order;
            $data->no_pic_order = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
            $data->email_pic_order = $payload->informasi_pelanggan->email_pic_order;
            $data->email_cc = isset($payload->informasi_pelanggan->email_cc) ? json_encode($payload->informasi_pelanggan->email_cc) : null;
            $data->alamat_sampling = $payload->informasi_pelanggan->alamat_sampling;
            $data->no_tlp_sampling = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->nama_pic_sampling = ucwords($payload->informasi_pelanggan->nama_pic_sampling);
            $data->jabatan_pic_sampling = $payload->informasi_pelanggan->jabatan_pic_sampling;
            $data->no_tlp_pic_sampling = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->email_pic_sampling = $payload->informasi_pelanggan->email_pic_sampling;
            $data->sales_id = $sales_id;

            $data_sampling = [];
            $harga_total = 0;
            $harga_air = 0;
            $harga_udara = 0;
            $harga_emisi = 0;
            $harga_padatan = 0;
            $harga_swab_test = 0;
            $harga_tanah = 0;
            $harga_pangan = 0;
            $grand_total = 0;

            // dd($payload->data_pendukung);
            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    $param = $item->parameter;
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                    }

                    $harga_db = [];
                    $volume_db = [];
                    foreach ($parameter as $param_) {
                        $ambil_data = HargaParameter::where('id_kategori', $kategori)
                            ->where('nama_parameter', $param_)
                            ->orderBy('id', 'ASC')
                            ->get();

                        $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                            return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                        }) ?? $ambil_data->first();

                        $harga_db[] = $cek_harga_parameter->harga ?? 0;
                        $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                        //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
                        // }) ?? $ambil_data->first();

                        // // fix bug
                        // if ($cek_harga_parameter) {
                        //     $harga_db[] = $cek_harga_parameter->harga;
                        //     $volume_db[] = $cek_harga_parameter->volume;
                        // } else {
                        //     $harga_db[] = 0;
                        //     $volume_db[] = 0;
                        // }
                    }

                    $harga_pertitik = (object) [
                        'volume' => array_sum($volume_db),
                        'total_harga' => array_sum($harga_db)
                    ];

                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item->jumlah_titik;

                    $data_sampling[$i] = [
                        'kategori_1' => $item->kategori_1,
                        'kategori_2' => $item->kategori_2,
                        'penamaan_titik' => $item->penamaan_titik,
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol
                    ];

                    isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

                    // dd($data_sampling[$i]);
                    switch ($kategori) {
                        case '1':
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '4':
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '5':
                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '6':
                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '7':
                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '8':
                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '9':
                            $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                    }
                }
            } else {
                $data_sampling = [];
            }

            $grand_total = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;

            // data nama titik masuk
            $data->data_pendukung_sampling = json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE);

            $data->harga_air = $harga_air;
            $data->harga_udara = $harga_udara;
            $data->harga_emisi = $harga_emisi;
            $data->harga_padatan = $harga_padatan;
            $data->harga_swab_test = $harga_swab_test;
            $data->harga_tanah = $harga_tanah;
            $data->harga_pangan = $harga_pangan;

            // kalkulasi harga transportasi
            $expOp = explode("-", $payload->data_wilayah->wilayah);
            $id_wilayah = $expOp[0];

            $cekOperasional = HargaTransportasi::where('is_active', true)
                ->where('id', $id_wilayah)
                ->first();

            $data->status_wilayah = $payload->data_wilayah->status_wilayah;
            $data->wilayah = $payload->data_wilayah->wilayah;
            $data->transportasi = $payload->data_wilayah->status_sampling != 'SD' ? $payload->data_wilayah->transportasi : null;
            $data->kalkulasi_by_sistem = $payload->data_wilayah->kalkulasi_by_sistem;

            $harga_transport = 0;
            $jam = 0;
            $transport = 0;
            $perdiem = 0;

            if ($payload->data_wilayah->status_wilayah == 'DALAM KOTA') {
                if ($payload->data_wilayah->status_sampling != 'SD') {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam ?? 0;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam ?? 0;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == "on") {
                        $data->harga_transportasi = $cekOperasional->transportasi;
                        $data->harga_transportasi_total = ($cekOperasional->transportasi * (int) $payload->data_wilayah->transportasi);

                        $data->harga_personil = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang);
                        $data->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;

                        if (isset($payload->data_wilayah->jumlah_orang_24jam) && $payload->data_wilayah->jumlah_orang_24jam != '') {
                            $data->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam;
                            $data->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                        }

                        $transport = ($cekOperasional->transportasi * (int) $payload->data_wilayah->transportasi);
                        $perdiem = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;
                        $jam = ($payload->data_wilayah->jumlah_orang_24jam != '') ? ($cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam : 0;
                    } else {
                        // dd($payload->data_wilayah);
                        $data->harga_transportasi = ($payload->data_wilayah->harga_transportasi != '') ? \str_replace('.', '', $payload->data_wilayah->harga_transportasi) : 0;
                        $data->harga_transportasi_total = ($payload->data_wilayah->harga_transportasi_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_transportasi_total) : 0;
                        $data->harga_personil = ($payload->data_wilayah->harga_personil != '') ? \str_replace('.', '', $payload->data_wilayah->harga_personil) : 0;
                        $data->harga_perdiem_personil_total = ($payload->data_wilayah->harga_perdiem_personil_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_perdiem_personil_total) : 0;
                        $data->harga_24jam_personil = ($payload->data_wilayah->harga_24jam_personil != '') ? \str_replace('.', '', $payload->data_wilayah->harga_24jam_personil) : 0;
                        $data->harga_24jam_personil_total = ($payload->data_wilayah->harga_24jam_personil_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_24jam_personil_total) : 0;

                        $transport = ($payload->data_wilayah->harga_transportasi_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_transportasi_total) : 0;
                        $perdiem = ($payload->data_wilayah->harga_perdiem_personil_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_perdiem_personil_total) : 0;
                        $jam = ($payload->data_wilayah->harga_24jam_personil_total != '') ? \str_replace('.', '', $payload->data_wilayah->harga_24jam_personil_total) : 0;
                    }
                } else {
                    $data->transportasi = null;
                    $data->perdiem_jumlah_orang = null;
                    $data->perdiem_jumlah_hari = null;
                    $data->jumlah_orang_24jam = null;
                    $data->jumlah_hari_24jam = null;
                    $data->harga_transportasi = 0;
                    $data->harga_transportasi_total = null;
                    $data->harga_personil = 0;
                    $data->harga_perdiem_personil_total = null;
                    $data->harga_24jam_personil = 0;
                    $data->harga_24jam_personil_total = null;

                    $transport = 0;
                    $perdiem = 0;
                    $jam = 0;
                }
            } else {
                //implementasi untuk luar kota

                $harga_tiket = 0;
                $harga_transportasi_darat = 0;
                $harga_penginapan = 0;

                if ($payload->data_wilayah->status_sampling != 'SD') {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == 'on') {
                        $data->kalkukasi_by_sistem = $payload->data_wilayah->kalkukasi_by_sistem;
                        $harga_tiket = floatval($cekOperasional->tiket) * $payload->data_wilayah->perdiem_jumlah_orang;

                        $harga_transportasi_darat = $cekOperasional->transportasi;

                        $harga_penginapan = $cekOperasional->penginapan;

                        $data->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
                        $data->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $payload->data_wilayah->transportasi;

                        $data->harga_personil = $cekOperasional->per_orang * $payload->data_wilayah->perdiem_jumlah_orang;

                        $data->harga_perdiem_personil_total = ($cekOperasional->per_orang * $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;

                        if ($payload->data_wilayah->jumlah_orang_24jam != '')
                            $data->harga_24jam_personil = floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam;
                        if ($payload->data_wilayah->jumlah_hari_24jam != '' && $payload->data_wilayah->jumlah_orang_24jam != '') {
                            $data->harga_24jam_personil_total = (floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                            $jam = (floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                        }

                        $transport = (floatval($harga_tiket) + floatval($harga_transportasi_darat) + floatval($harga_penginapan)) * $payload->data_wilayah->transportasi;
                        $perdiem = (floatval($cekOperasional->per_orang) * $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;
                    } else {
                        $data->harga_transportasi = 0;
                        $data->harga_transportasi_total = null;

                        $data->harga_personil = 0;
                        $data->harga_perdiem_personil_total = null;

                        $data->harga_24jam_personil = 0;
                        $data->harga_24jam_personil_total = null;
                    }
                } else {
                    $data->transportasi = null;
                    $data->perdiem_jumlah_orang = null;
                    $data->perdiem_jumlah_hari = null;
                    $data->jumlah_orang_24jam = null;
                    $data->jumlah_hari_24jam = null;

                    $data->harga_transportasi = 0;
                    $data->harga_transportasi_total = null;

                    $data->harga_personil = 0;
                    $data->harga_perdiem_personil_total = null;

                    $data->harga_24jam_personil = 0;
                    $data->harga_24jam_personil_total = null;
                }
            }

            $data->status_sampling = $payload->data_wilayah->status_sampling;

            $total_diskon = 0;

            if (floatval($payload->data_diskon->discount_air) > 0) {
                $data->discount_air = \str_replace("%", "", $payload->data_diskon->discount_air);
                $data->total_discount_air = (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                $total_diskon += (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                $harga_total += floatval($harga_air) - (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                if (floatval(\str_replace("%", "", $payload->data_diskon->discount_air)) > 10) {
                    $message = $data->no_document . ' Discount Air melebihi 10%';
                    Notification::where('id', 19)
                        ->title('Peringatan.')
                        ->message($message)
                        ->url('/quote-request')
                        ->send();
                }
            } else {
                $harga_total += $harga_air;
                $data->discount_air = null;
                $data->total_discount_air = 0;
            }

            if (floatval($payload->data_diskon->discount_non_air) > 0) {
                $data->discount_non_air = \str_replace("%", "", $payload->data_diskon->discount_non_air);
                $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                $data->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $total_diskon += $disc_;
                if (floatval($payload->data_diskon->discount_non_air) > 10) {
                    $message = $data->no_document . ' Discount Non-Air melebihi 10%';
                    Notification::where('id', 19)
                        ->title('Peringatan.')
                        ->message($message)
                        ->url('/quote-request')
                        ->send();
                }

                if (floatval($payload->data_diskon->discount_non_air) > 0 && floatval($payload->data_diskon->discount_udara) > 0) {
                    $data->discount_udara = \str_replace("%", "", $payload->data_diskon->discount_udara);
                    $data->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    if (floatval($payload->data_diskon->discount_udara) > 10) {
                        $message = $data->no_document . ' Discount Udara melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $data->discount_udara = null;
                    $data->total_discount_udara = 0;
                }

                if (floatval($payload->data_diskon->discount_non_air) > 0 && floatval($payload->data_diskon->discount_emisi) > 0) {
                    $data->discount_emisi = \str_replace("%", "", $payload->data_diskon->discount_emisi);
                    $data->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));

                    if (floatval($payload->data_diskon->discount_emisi) > 10) {
                        $message = $data->no_document . ' Discount Emisi melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $data->discount_emisi = null;
                    $data->total_discount_emisi = 0;
                }
            } else {
                $data->discount_non_air = null;
                $data->total_discount_non_air = '0.00';
                $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                if (floatval($payload->data_diskon->discount_non_air) == 0 && floatval($payload->data_diskon->discount_udara) > 0 && $harga_udara != 0) {
                    $data->discount_udara = \str_replace("%", "", $payload->data_diskon->discount_udara);
                    $data->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    if (floatval($payload->data_diskon->discount_udara) > 10) {
                        $message = $data->no_document . ' Discount Udara melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $harga_total += $harga_udara;
                    $data->discount_udara = null;
                    $data->total_discount_udara = 0;
                }

                if (floatval($payload->data_diskon->discount_non_air) == 0 && floatval($payload->data_diskon->discount_emisi) > 0 && $harga_emisi != 0) {
                    $data->discount_emisi = \str_replace("%", "", $payload->data_diskon->discount_emisi);
                    $data->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));

                    if (floatval($payload->data_diskon->discount_emisi) > 10) {
                        $message = $data->no_document . ' Discount Emisi melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $harga_total += $harga_emisi;
                    $data->discount_emisi = null;
                    $data->total_discount_emisi = 0;
                }
            }
            //Penambahan untuk harga pangan
            $harga_total += $harga_pangan;
            $transport_ = 0;
            $perdiem_ = 0;
            $jam_ = 0;

            if (floatval($payload->data_diskon->discount_transport) > 0) {
                $data->discount_transport = \str_replace("%", "", $payload->data_diskon->discount_transport);
                $data->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
                $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
                $transport_ = floatval($transport - ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport)));
                // if ($payload->data_diskon->diluar_pajak->transportasi == 'true')
                //     $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
            } else {
                $data->discount_transport = null;
                $data->total_discount_transport = 0;
                $transport_ = $transport;
            }

            if (floatval($payload->data_diskon->discount_perdiem) > 0) {
                $data->discount_perdiem = \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                $data->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
                $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
                $perdiem_ = floatval($perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem)));
                // if ($payload->data_diskon->diluar_pajak->perdiem == 'true')
                //     $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
            } else {
                $data->discount_perdiem = null;
                $data->total_discount_perdiem = 0;
                $perdiem_ = $perdiem;
            }

            if (floatval($payload->data_diskon->discount_perdiem_24jam) > 0) {
                $data->discount_perdiem_24jam = \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                $data->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
                $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
                $jam_ = floatval($jam - ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam)));
                // if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true')
                //     $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
            } else {
                $data->discount_perdiem_24jam = null;
                $data->total_discount_perdiem_24jam = 0;
                $jam_ = $jam;
            }

            // dd($harga_transport);
            $harga_transport += ($transport_ + $perdiem_ + $jam_);
            // dd($transport_, $perdiem_, $jam_);

            if (floatval($payload->data_diskon->discount_gabungan) > 0) {
                $data->discount_gabungan = \str_replace("%", "", $payload->data_diskon->discount_gabungan);
                $data->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
                $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
                $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
            } else {
                $data->discount_gabungan = null;
                $data->total_discount_gabungan = 0;
            }

            if (floatval($payload->data_diskon->discount_group) > 0) {
                $data->discount_group = \str_replace("%", "", $payload->data_diskon->discount_group);
                $data->total_discount_group = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_group));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_group));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_group));
            } else {
                $data->discount_group = null;
                $data->total_discount_group = 0;
            }

            if (floatval($payload->data_diskon->discount_consultant) > 0) {
                $data->discount_consultant = \str_replace("%", "", $payload->data_diskon->discount_consultant);
                $data->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
            } else {
                $data->discount_consultant = null;
                $data->total_discount_consultant = 0;
            }

            if (floatval($payload->data_diskon->cash_discount_persen) > 0) {
                $data->cash_discount_persen = \str_replace("%", "", $payload->data_diskon->cash_discount_persen);
                $data->total_cash_discount_persen = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
            } else {
                $data->cash_discount_persen = null;
                $data->total_cash_discount_persen = 0;
            }

            if (floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount)) > 0) {
                $harga_total = $harga_total - floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount));
                $data->cash_discount = floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount));
            } else {
                $data->cash_discount = 0;
            }

            //CUSTOM DISCOUNT
            if (isset($payload->data_diskon->custom_discount) && !empty($payload->data_diskon->custom_discount)) {
                $custom_disc = array_map(function ($disc) {
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                    ];
                }, $payload->data_diskon->custom_discount);
                $harga_disc = 0;
                foreach ($payload->data_diskon->custom_discount as $disc) {
                    $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
                }

                $harga_total -= $harga_disc;
                $data->custom_discount = json_encode($custom_disc);
                $total_diskon += $harga_disc;
            } else {
                $data->custom_discount = null;
            }

            $biaya_lain = 0;

            // BIAYA LAIN
            if (isset($payload->data_diskon->biaya_lain) && !empty($payload->data_diskon->biaya_lain)) {
                $biaya_lain = 0;
                $data_lain = array_map(function ($disc) use (&$biaya_lain) {
                    $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
                        'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
                    ];
                }, $payload->data_diskon->biaya_lain);

                $data->biaya_lain = json_encode($data_lain);
                $data->total_biaya_lain = $biaya_lain;
            } else {
                $data->biaya_lain = null;
                $data->total_biaya_lain = 0;
            }

            // BIAYA PREPARASI PADATAN
            // name : biaya_preparasi_padatan[select][0][harga]
            // name : biaya_preparasi_padatan[select][0][deskirpsi]
            $biaya_preparasi = 0;
            if (isset($payload->data_diskon->biaya_preparasi_padatan) && !empty($payload->data_diskon->biaya_preparasi_padatan)) {
                $data->biaya_preparasi_padatan = json_encode(array_map(function ($disc) {
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
                    ];
                }, $payload->data_diskon->biaya_preparasi_padatan));
                foreach ($payload->data_diskon->biaya_preparasi_padatan as $biaya) {
                    $biaya_preparasi += floatval(str_replace(['Rp. ', ',', '.'], '', $biaya->harga));
                }
            }
            $data->total_biaya_preparasi = $biaya_preparasi;
            $grand_total += $biaya_preparasi;
            $harga_total += $biaya_preparasi;
            // $data->biaya_preparasi_padatan = null;
            // $data->total_biaya_preparasi = 0;
            // $biaya_preparasi = 0;

            //BIAYA DI LUAR PAJAK
            $biaya_akhir = 0;
            $biaya_diluar_pajak = 0;
            $txt = [];

            if (isset($payload->data_diskon->diluar_pajak)) {
                if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
                    // $harga_total += $transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport);
                    $biaya_akhir += $transport - $transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport);
                    $biaya_diluar_pajak += $transport;
                } else {
                    $grand_total += $transport;
                    $harga_total += $transport_;
                    // dd($transport, $transport_, $grand_total, $harga_total);
                }

                if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
                    // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                    $biaya_akhir += $perdiem - $perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                    $biaya_diluar_pajak += $perdiem;
                } else {
                    $grand_total += $perdiem;
                    $harga_total += $perdiem_;
                }

                if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
                    // $harga_total += $jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                    $biaya_akhir += $jam - $jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                    $biaya_diluar_pajak += $jam;
                } else {
                    $grand_total += $jam;
                    $harga_total += $jam_;
                }

                if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
                    $biaya_akhir += $biaya_lain;
                    $biaya_diluar_pajak += $biaya_lain;
                } else {
                    $grand_total += $biaya_lain;
                    $harga_total += $biaya_lain;
                }
            }

            $diluar_pajak = ['select' => $txt, 'body' => []];

            if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
                foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

                    $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                    $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                }
                $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
            }

            //biaya di luar pajak
            $data->biaya_di_luar_pajak = json_encode($diluar_pajak);
            $data->total_biaya_di_luar_pajak = $biaya_diluar_pajak;
            $data->diluar_pajak = isset($payload->data_diskon->diluar_pajak) ? json_encode($payload->data_diskon->diluar_pajak) : NULL;

            //Grand total sebelum kena diskon
            // dd($grand_total, $harga_total, $total_diskon);
            $data->grand_total = $grand_total;
            $data->total_dpp = $harga_total;
            $data->total_discount = $total_diskon;

            $piutang = $harga_total;
            if (isset($payload->data_diskon->ppn) && $payload->data_diskon->ppn != "" && floatval($payload->data_diskon->ppn) > 0 && $payload->data_diskon->ppn != "0%") {
                $data->ppn = (int) \str_replace("%", "", $payload->data_diskon->ppn);
                $data->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->ppn));
                $piutang += $data->total_ppn;
            }

            if (isset($payload->data_diskon->ppn) && ($payload->data_diskon->ppn == "" || floatval($payload->data_diskon->ppn) == 0 || $payload->data_diskon->ppn == "0%")) {
                // dd($masuk);
                $data->ppn = 0;
                $data->total_ppn = 0;
            }

            if (isset($payload->data_diskon->pph) && $payload->data_diskon->pph != "" && floatval($payload->data_diskon->ppn) > 0 && $payload->data_diskon->ppn != "0%") {
                $data->pph = (int) \str_replace("%", "", $payload->data_diskon->pph);
                $data->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->pph));
                $piutang -= $data->total_pph;
            }

            if (isset($payload->data_diskon->pph) && ($payload->data_diskon->pph == "" || floatval($payload->data_diskon->pph) == 0 || $payload->data_diskon->pph == "0%")) {
                $data->pph = 0;
                $data->total_pph = 0;
            }

            $data->piutang = $piutang;
            $biaya_akhir += $piutang;

            $data->biaya_akhir = $biaya_akhir;

            $data->syarat_ketentuan = (isset($payload->syarat_ketentuan) && !empty($payload->syarat_ketentuan)) ? json_encode($payload->syarat_ketentuan) : NULL;
            $data->keterangan_tambahan = (isset($payload->keterangan_tambahan) && !empty($payload->keterangan_tambahan)) ? json_encode($payload->keterangan_tambahan) : NULL;

            $data->updated_by = $this->karyawan;
            $data->updated_at = DATE('Y-m-d H:i:s');
            // $data->expired = $tgl;

            $data->save();

            $message = "Penawaran dengan nomor $data->no_document berhasil di update.";
            $data_lama = ($data->data_lama != null) ? json_decode($data->data_lama) : null;

            if ($data_lama != null) {

                if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                    $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', 1)->first();
                    $no_qt_lama = $cek_order->no_document;
                    $no_qt_baru = $data->no_document;
                    $id_order = $data_lama->id_order;

                    $parse = new GeneratePraSampling;
                    $parse->type('QT');
                    $parse->where('no_qt_lama', $no_qt_lama);
                    $parse->where('no_qt_baru', $no_qt_baru);
                    $parse->where('id_order', $id_order);
                    $parse->save();
                } else {
                    $parse = new GeneratePraSampling;
                    $parse->type('QT');
                    $parse->where('no_qt_baru', $data->no_document);
                    $parse->where('generate', 'new');
                    $parse->save();
                }
            } else {
                $parse = new GeneratePraSampling;
                $parse->type('QT');
                $parse->where('no_qt_baru', $data->no_document);
                $parse->where('generate', 'new');
                // dd($parse);
                $parse->save();
            }

            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $data->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            $job = new RenderPdfPenawaran($data->id, 'non kontrak');
            $this->dispatch($job);

            $array_id_user = GetAtasan::where('id', $data->sales_id)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $array_id_user)
                ->title('Penawaran telah diperbarui')
                ->message('Penawaran dengan nomor ' . $data->no_document . ' telah diperbarui.')
                ->url('/quote-request')
                ->send();

            return response()->json([
                'message' => "Penawaran dengan nomor $data->no_document berhasil di update."
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
    }

    private function revisiNonKontrak($payload)
    {
        // Implementasi untuk revisi non kontrak
        // dd($payload);

        if (!isset($payload->informasi_pelanggan->tgl_penawaran) || $payload->informasi_pelanggan->tgl_penawaran == null) {
            return response()->json([
                'message' => 'Mohon isi tanggal penawaran terlebih dahulu.'
            ], 401);
        }

        $sales_id = $payload->informasi_pelanggan->sales_id;
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }

        foreach ($payload->data_pendukung as $index => $pengujian) {
            $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
            $penamaanTitik = $pengujian->penamaan_titik ?? [];

            if (count($penamaanTitik) !== $jumlahTitik) {
                return response()->json([
                    'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // Update Master Pelanggan by 565 : 01-05-2025
            $cek_master_customer = MasterPelanggan::where('id_pelanggan', $payload->informasi_pelanggan->pelanggan_ID)->where('is_active', true)->first();
            if ($cek_master_customer != null) {
                // Update Kontak pelanggan
                if ($payload->informasi_pelanggan->no_tlp_perusahaan != '') {
                    $kontak = KontakPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->first();
                    if ($kontak != null) {
                        $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
                        if ($payload->informasi_pelanggan->email_pic_order != '')
                            $kontak->email_perusahaan = $payload->informasi_pelanggan->email_pic_order;
                        $kontak->save();
                    } else {
                        $kontak = new KontakPelanggan;
                        $kontak->pelanggan_id = $cek_master_customer->id;
                        $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
                        if ($payload->informasi_pelanggan->email_pic_order != '')
                            $kontak->email_perusahaan = $payload->informasi_pelanggan->email_pic_order;
                        $kontak->save();
                    }
                }

                // Update Alamat Kantor pelanggan
                if ($payload->informasi_pelanggan->alamat_kantor != '') {
                    $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'kantor')->first();
                    if ($alamat != null) {
                        $alamat->alamat = $payload->informasi_pelanggan->alamat_kantor;
                        $alamat->save();
                    } else {
                        $alamat = new AlamatPelanggan;
                        $alamat->pelanggan_id = $cek_master_customer->id;
                        $alamat->type_alamat = 'kantor';
                        $alamat->alamat = $payload->informasi_pelanggan->alamat_kantor;
                        $alamat->save();
                    }
                }

                // Update Alamat Sampling pelanggan
                if ($payload->informasi_pelanggan->alamat_sampling != '') {
                    $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'sampling')->first();
                    if ($alamat != null) {
                        $alamat->alamat = $payload->informasi_pelanggan->alamat_sampling;
                        $alamat->save();
                    } else {
                        $alamat = new AlamatPelanggan;
                        $alamat->pelanggan_id = $cek_master_customer->id;
                        $alamat->type_alamat = 'sampling';
                        $alamat->alamat = $payload->informasi_pelanggan->alamat_sampling;
                        $alamat->save();
                    }
                }

                // Update PIC Order pelanggan
                if ($payload->informasi_pelanggan->nama_pic_order != '') {
                    $picorder = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'order')->first();
                    if ($picorder != null) {
                        $picorder->nama_pic = $payload->informasi_pelanggan->nama_pic_order;
                        if ($payload->informasi_pelanggan->jabatan_pic_order != '')
                            $picorder->jabatan_pic = $payload->informasi_pelanggan->jabatan_pic_order;
                        $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
                        $picorder->wa_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
                        $picorder->email_pic = $payload->informasi_pelanggan->nama_pic_order;
                        $picorder->save();
                    } else {
                        $picorder = new PicPelanggan;
                        $picorder->pelanggan_id = $cek_master_customer->id;
                        $picorder->type_pic = 'order';
                        $picorder->nama_pic = $payload->informasi_pelanggan->nama_pic_order;
                        if ($payload->informasi_pelanggan->jabatan_pic_order != '')
                            $picorder->jabatan_pic = $payload->informasi_pelanggan->jabatan_pic_order;
                        $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
                        $picorder->wa_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
                        $picorder->email_pic = $payload->informasi_pelanggan->nama_pic_order;
                        $picorder->save();
                    }
                }

                // Update PIC Sampling pelanggan
                if ($payload->informasi_pelanggan->nama_pic_sampling != '') {
                    $picsampling = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'sampling')->first();
                    if ($picsampling != null) {
                        $picsampling->nama_pic = $payload->informasi_pelanggan->nama_pic_sampling;
                        if ($payload->informasi_pelanggan->jabatan_pic_sampling != '')
                            $picsampling->jabatan_pic = $payload->informasi_pelanggan->jabatan_pic_sampling;
                        $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
                        $picsampling->wa_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
                        if ($payload->informasi_pelanggan->email_pic_sampling != '')
                            $picsampling->email_pic = $payload->informasi_pelanggan->email_pic_sampling;
                        $picsampling->save();
                    } else {
                        $picsampling = new PicPelanggan;
                        $picsampling->pelanggan_id = $cek_master_customer->id;
                        $picsampling->type_pic = 'sampling';
                        $picsampling->nama_pic = $payload->informasi_pelanggan->nama_pic_sampling;
                        if ($payload->informasi_pelanggan->nama_pic_sampling != '')
                            $picsampling->jabatan_pic = $payload->informasi_pelanggan->jabatan_pic_sampling;
                        $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
                        $picsampling->wa_pic = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
                        if ($payload->informasi_pelanggan->email_pic_sampling != '')
                            $picsampling->email_pic = $payload->informasi_pelanggan->email_pic_sampling;
                        $picsampling->save();
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Pelanggan pada Quotation ini tidak ada atau telah dinonaktifkan!'
                ], 401);
            }

            $dataOld = QuotationNonKontrak::where('is_active', true)
                ->where('no_document', $payload->informasi_pelanggan->no_document)
                ->first();

            $data = new QuotationNonKontrak;
            // dd($dataOld);
            $data->no_quotation = $dataOld->no_quotation;
            $data->no_document = $payload->informasi_pelanggan->new_no_document;
            $data->pelanggan_ID = $dataOld->pelanggan_ID;
            $data->id_cabang = $this->idcabang;
            // $data->sales_id = $payload->informasi_pelanggan->sales_id;

            //data customer order     -------------------------------------------------------> save ke master customer parrent
            $data->nama_perusahaan = $dataOld->nama_perusahaan;
            $data->tanggal_penawaran = $payload->informasi_pelanggan->tgl_penawaran;
            $data->konsultan = $dataOld->konsultan;
            $data->alamat_kantor = $payload->informasi_pelanggan->alamat_kantor;

            $data->no_tlp_perusahaan = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
            $data->nama_pic_order = ucwords($payload->informasi_pelanggan->nama_pic_order);
            $data->jabatan_pic_order = $payload->informasi_pelanggan->jabatan_pic_order;
            $data->no_pic_order = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
            $data->email_pic_order = $payload->informasi_pelanggan->email_pic_order;
            $data->email_cc = isset($payload->informasi_pelanggan->email_cc) ? json_encode($payload->informasi_pelanggan->email_cc) : null;
            $data->alamat_sampling = $payload->informasi_pelanggan->alamat_sampling;
            $data->no_tlp_sampling = str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->nama_pic_sampling = ucwords($payload->informasi_pelanggan->nama_pic_sampling);
            $data->jabatan_pic_sampling = $payload->informasi_pelanggan->jabatan_pic_sampling;
            $data->no_tlp_pic_sampling = str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $data->email_pic_sampling = $payload->informasi_pelanggan->email_pic_sampling;
            $data->sales_id = $sales_id;

            $data_sampling = [];
            $harga_total = 0;
            $harga_air = 0;
            $harga_udara = 0;
            $harga_emisi = 0;
            $harga_padatan = 0;
            $harga_swab_test = 0;
            $harga_tanah = 0;
            $harga_pangan = 0;
            $grand_total = 0;

            foreach ($payload->data_pendukung as $i => $item) {
                $param = $item->parameter;
                $exp = explode("-", $item->kategori_1);
                $kategori = $exp[0];
                $vol = 0;

                $parameter = [];
                foreach ($param as $par) {
                    $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                    array_push($parameter, $cek_par->nama_lab);
                }

                $harga_db = [];
                $volume_db = [];
                foreach ($parameter as $param_) {
                    $ambil_data = HargaParameter::where('id_kategori', $kategori)
                        ->where('nama_parameter', $param_)
                        ->orderBy('id', 'ASC')
                        ->get();

                    $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                        return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                    }) ?? $ambil_data->first();

                    $harga_db[] = $cek_harga_parameter->harga ?? 0;
                    $volume_db[] = $cek_harga_parameter->volume ?? 0;
                    // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                    //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
                    // }) ?? $ambil_data->first();

                    // // fix bug
                    // if ($cek_harga_parameter) {
                    //     $harga_db[] = $cek_harga_parameter->harga;
                    //     $volume_db[] = $cek_harga_parameter->volume;
                    // } else {
                    //     $harga_db[] = 0;
                    //     $volume_db[] = 0;
                    // }
                }

                $harga_pertitik = (object) [
                    'volume' => array_sum($volume_db),
                    'total_harga' => array_sum($harga_db)
                ];

                if ($harga_pertitik->volume != null) {
                    $vol += floatval($harga_pertitik->volume);
                }

                $titik = $item->jumlah_titik;

                $regulasi = [];
                if (isset($item->regulasi) && !empty($item->regulasi)) {
                    $regulasi = $item->regulasi;
                }

                $data_sampling[$i] = [
                    'kategori_1' => $item->kategori_1,
                    'kategori_2' => $item->kategori_2,
                    'regulasi' => $regulasi,
                    'penamaan_titik' => $item->penamaan_titik,
                    'parameter' => $param,
                    'jumlah_titik' => $titik,
                    'total_parameter' => count($param),
                    'harga_satuan' => $harga_pertitik->total_harga,
                    'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                    'volume' => $vol
                ];

                switch ($kategori) {
                    case '1':
                        $harga_air += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '4':
                        $harga_udara += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '5':
                        $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '6':
                        $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '7':
                        $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '8':
                        $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                    case '9':
                        $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $titik;
                        break;
                }
            }

            $grand_total = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;

            // data nama titik masuk
            $data->data_pendukung_sampling = json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE);

            $data->harga_air = $harga_air;
            $data->harga_udara = $harga_udara;
            $data->harga_emisi = $harga_emisi;
            $data->harga_padatan = $harga_padatan;
            $data->harga_swab_test = $harga_swab_test;
            $data->harga_tanah = $harga_tanah;
            $data->harga_pangan = $harga_pangan;

            // kalkulasi harga transportasi
            $expOp = explode("-", $payload->data_wilayah->wilayah);
            $id_wilayah = $expOp[0];

            $cekOperasional = HargaTransportasi::where('is_active', true)
                ->where('id', $id_wilayah)
                ->first();

            $data->status_wilayah = $payload->data_wilayah->status_wilayah;
            $data->wilayah = $payload->data_wilayah->wilayah;
            $data->transportasi = $payload->data_wilayah->status_sampling != 'SD' ? $payload->data_wilayah->transportasi : null;

            $harga_transport = 0;
            $jam = 0;
            $transport = 0;
            $perdiem = 0;

            if ($payload->data_wilayah->status_wilayah == 'DALAM KOTA') {
                if ($payload->data_wilayah->status_sampling != 'SD') {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == "on") {
                        $data->harga_transportasi = $cekOperasional->transportasi;
                        $data->harga_transportasi_total = ($cekOperasional->transportasi * (int) $payload->data_wilayah->transportasi);

                        $data->harga_personil = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang);
                        $data->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;

                        if ($payload->data_wilayah->jumlah_orang_24jam != '') {
                            $data->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam;
                            $data->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                        }

                        $transport = ($cekOperasional->transportasi * (int) $payload->data_wilayah->transportasi);
                        $perdiem = ($cekOperasional->per_orang * (int) $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;
                        $jam = ($payload->data_wilayah->jumlah_orang_24jam != '') ? ($cekOperasional->{'24jam'} * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam : 0;
                    } else {
                        $data->harga_transportasi = 0;
                        $data->harga_transportasi_total = null;
                        $data->harga_personil = 0;
                        $data->harga_perdiem_personil_total = null;
                        $data->harga_24jam_personil = 0;
                        $data->harga_24jam_personil_total = null;
                    }
                } else {
                    $data->transportasi = null;
                    $data->perdiem_jumlah_orang = null;
                    $data->perdiem_jumlah_hari = null;
                    $data->jumlah_orang_24jam = null;
                    $data->jumlah_hari_24jam = null;
                    $data->harga_transportasi = 0;
                    $data->harga_transportasi_total = null;
                    $data->harga_personil = 0;
                    $data->harga_perdiem_personil_total = null;
                    $data->harga_24jam_personil = 0;
                    $data->harga_24jam_personil_total = null;
                }
            } else {
                //implementasi untuk luar kota

                $harga_tiket = 0;
                $harga_transportasi_darat = 0;
                $harga_penginapan = 0;

                if ($payload->data_wilayah->status_sampling != 'SD') {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == 'on') {
                        $harga_tiket = floatval($cekOperasional->tiket) * $payload->data_wilayah->perdiem_jumlah_orang;

                        $harga_transportasi_darat = $cekOperasional->transportasi;

                        $harga_penginapan = $cekOperasional->penginapan;

                        $data->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
                        $data->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $payload->data_wilayah->transportasi;

                        $data->harga_personil = $cekOperasional->per_orang * $payload->data_wilayah->perdiem_jumlah_orang;

                        $data->harga_perdiem_personil_total = ($cekOperasional->per_orang * $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;

                        if ($payload->data_wilayah->jumlah_orang_24jam != '')
                            $data->harga_24jam_personil = floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam;
                        if ($payload->data_wilayah->jumlah_hari_24jam != '' && $payload->data_wilayah->jumlah_orang_24jam != '') {
                            $data->harga_24jam_personil_total = (floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                            $jam = (floatval($cekOperasional->{'24jam'}) * (int) $payload->data_wilayah->jumlah_orang_24jam) * $payload->data_wilayah->jumlah_hari_24jam;
                        }

                        $transport = (floatval($harga_tiket) + floatval($harga_transportasi_darat) + floatval($harga_penginapan)) * $payload->data_wilayah->transportasi;
                        $perdiem = (floatval($cekOperasional->per_orang) * $payload->data_wilayah->perdiem_jumlah_orang) * $payload->data_wilayah->perdiem_jumlah_hari;
                    } else {
                        $data->harga_transportasi = 0;
                        $data->harga_transportasi_total = null;

                        $data->harga_personil = 0;
                        $data->harga_perdiem_personil_total = null;

                        $data->harga_24jam_personil = 0;
                        $data->harga_24jam_personil_total = null;
                    }
                } else {
                    $data->transportasi = null;
                    $data->perdiem_jumlah_orang = null;
                    $data->perdiem_jumlah_hari = null;
                    $data->jumlah_orang_24jam = null;
                    $data->jumlah_hari_24jam = null;

                    $data->harga_transportasi = 0;
                    $data->harga_transportasi_total = null;

                    $data->harga_personil = 0;
                    $data->harga_perdiem_personil_total = null;

                    $data->harga_24jam_personil = 0;
                    $data->harga_24jam_personil_total = null;
                }
            }

            $data->status_sampling = $payload->data_wilayah->status_sampling;

            $total_diskon = 0;

            if (floatval($payload->data_diskon->discount_air) > 0) {
                $data->discount_air = $payload->data_diskon->discount_air;
                $data->total_discount_air = (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                $total_diskon += (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                $harga_total += floatval($harga_air) - (floatval($harga_air) / 100 * floatval(\str_replace("%", "", $payload->data_diskon->discount_air)));
                if (floatval(\str_replace("%", "", $payload->data_diskon->discount_air)) > 10) {
                    //kirim ke tele atasan
                }
            } else {
                $harga_total += $harga_air;
                $data->discount_air = null;
                $data->total_discount_air = 0;
            }

            if (floatval($payload->data_diskon->discount_non_air) > 0) {
                $data->discount_non_air = $payload->data_diskon->discount_non_air;
                $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                $data->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_non_air));
                $total_diskon += $disc_;
                if (floatval($payload->data_diskon->discount_non_air) > 10) {
                    $message = $data->no_document . ' Discount Non-Air melebihi 10%';
                    Notification::where('id', 19)
                        ->title('Peringatan.')
                        ->message($message)
                        ->url('/quote-request')
                        ->send();
                }

                if (floatval($payload->data_diskon->discount_non_air) > 0 && floatval($payload->data_diskon->discount_udara) > 0) {
                    $data->discount_udara = $payload->data_diskon->discount_udara;
                    $data->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    if (floatval($payload->data_diskon->discount_udara) > 10) {
                        $message = $data->no_document . ' Discount Udara melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $data->discount_udara = null;
                    $data->total_discount_udara = 0;
                }

                if (floatval($payload->data_diskon->discount_non_air) > 0 && floatval($payload->data_diskon->discount_emisi) > 0) {
                    $data->discount_emisi = $payload->data_diskon->discount_emisi;
                    $data->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));

                    if (floatval($payload->data_diskon->discount_emisi) > 10) {
                        $message = $data->no_document . ' Discount Emisi melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $data->discount_emisi = null;
                    $data->total_discount_emisi = 0;
                }
            } else {
                $data->discount_non_air = null;
                $data->total_discount_non_air = '0.00';
                $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                if (floatval($payload->data_diskon->discount_non_air) == 0 && floatval($payload->data_diskon->discount_udara) > 0 && $harga_udara != 0) {
                    $data->discount_udara = $payload->data_diskon->discount_udara;
                    $data->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_udara));
                    if (floatval($payload->data_diskon->discount_udara) > 10) {
                        $message = $data->no_document . ' Discount Udara melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $harga_total += $harga_udara;
                    $data->discount_udara = null;
                    $data->total_discount_udara = 0;
                }

                if (floatval($payload->data_diskon->discount_non_air) == 0 && floatval($payload->data_diskon->discount_emisi) > 0 && $harga_emisi != 0) {
                    $data->discount_emisi = $payload->data_diskon->discount_emisi;
                    $data->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));
                    $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_emisi));

                    if (floatval($payload->data_diskon->discount_emisi) > 10) {
                        $message = $data->no_document . ' Discount Emisi melebihi 10%';
                        Notification::where('id', 19)
                            ->title('Peringatan.')
                            ->message($message)
                            ->url('/quote-request')
                            ->send();
                    }
                } else {
                    $harga_total += $harga_emisi;
                    $data->discount_emisi = null;
                    $data->total_discount_emisi = 0;
                }
            }
            //Penambahan untuk harga pangan
            $harga_total += $harga_pangan;
            $transport_ = 0;
            $perdiem_ = 0;
            $jam_ = 0;

            // dd($payload->data_diskon->discount_transport);
            if (floatval($payload->data_diskon->discount_transport) > 0) {
                $data->discount_transport = \str_replace("%", "", $payload->data_diskon->discount_transport);
                $data->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
                $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
                $transport_ = floatval($transport - ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport)));
                // if ($payload->data_diskon->diluar_pajak->transportasi == 'true')
                //     $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport));
            } else {
                $data->discount_transport = null;
                $data->total_discount_transport = 0;
                $transport_ = $transport;
            }

            if (floatval($payload->data_diskon->discount_perdiem) > 0) {
                $data->discount_perdiem = \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                $data->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
                $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
                $perdiem_ = floatval($perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem)));
                // if ($payload->data_diskon->diluar_pajak->perdiem == 'true')
                //     $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem));
            } else {
                $data->discount_perdiem = null;
                $data->total_discount_perdiem = 0;
                $perdiem_ = $perdiem;
            }

            if (floatval($payload->data_diskon->discount_perdiem_24jam) > 0) {
                $data->discount_perdiem_24jam = \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                $data->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
                $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
                $jam_ = floatval($jam - ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam)));
                // if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true')
                //     $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam));
            } else {
                $data->discount_perdiem_24jam = null;
                $data->total_discount_perdiem_24jam = 0;
                $jam_ = $jam;
            }


            $harga_transport += ($transport_ + $perdiem_ + $jam_);

            if (floatval($payload->data_diskon->discount_gabungan) > 0) {
                $data->discount_gabungan = $payload->data_diskon->discount_gabungan;
                $data->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
                $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
                $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
            } else {
                $data->discount_gabungan = null;
                $data->total_discount_gabungan = 0;
            }

            if (floatval($payload->data_diskon->discount_group) > 0) {
                $data->discount_group = $payload->data_diskon->discount_group;
                $data->total_discount_group = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_group));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_gabungan));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_group));
            } else {
                $data->discount_group = null;
                $data->total_discount_group = 0;
            }

            if (floatval($payload->data_diskon->discount_consultant) > 0) {
                $data->discount_consultant = $payload->data_diskon->discount_consultant;
                $data->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_consultant));
            } else {
                $data->discount_consultant = null;
                $data->total_discount_consultant = 0;
            }

            if (floatval($payload->data_diskon->cash_discount_persen) > 0) {
                $data->cash_discount_persen = $payload->data_diskon->cash_discount_persen;
                $data->total_cash_discount_persen = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
                $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
                $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->cash_discount_persen));
            } else {
                $data->cash_discount_persen = null;
                $data->total_cash_discount_persen = 0;
            }

            if (floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount)) > 0) {
                $harga_total = $harga_total - floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount));
                $data->cash_discount = floatval(str_replace(['Rp. ', ',', '.'], '', $payload->data_diskon->cash_discount));
            } else {
                $data->cash_discount = 0;
            }

            //CUSTOM DISCOUNT
            if (isset($payload->data_diskon->custom_discount) && !empty($payload->data_diskon->custom_discount)) {
                $custom_disc = array_map(function ($disc) {
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                    ];
                }, $payload->data_diskon->custom_discount);
                $harga_disc = 0;
                foreach ($payload->data_diskon->custom_discount as $disc) {
                    $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
                }

                $harga_total -= $harga_disc;
                $data->custom_discount = json_encode($custom_disc);
                $total_diskon += $harga_disc;
            } else {
                $data->custom_discount = null;
            }

            $biaya_lain = 0;

            // BIAYA LAIN
            if (isset($payload->data_diskon->biaya_lain) && !empty($payload->data_diskon->biaya_lain)) {
                $biaya_lain = 0;
                $data_lain = array_map(function ($disc) use (&$biaya_lain) {
                    $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
                        'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
                    ];
                }, $payload->data_diskon->biaya_lain);

                $data->biaya_lain = json_encode($data_lain);
                $data->total_biaya_lain = $biaya_lain;
            } else {
                $data->biaya_lain = null;
                $data->total_biaya_lain = 0;
            }

            // BIAYA PREPARASI PADATAN
            // name : biaya_preparasi_padatan[select][0][harga]
            // name : biaya_preparasi_padatan[select][0][deskirpsi]
            $biaya_preparasi = 0;
            if (isset($payload->data_diskon->biaya_preparasi_padatan) && !empty($payload->data_diskon->biaya_preparasi_padatan)) {
                $data->biaya_preparasi_padatan = json_encode(array_map(function ($disc) {
                    return (object) [
                        'deskripsi' => $disc->deskripsi,
                        'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
                    ];
                }, $payload->data_diskon->biaya_preparasi_padatan));
                foreach ($payload->data_diskon->biaya_preparasi_padatan as $biaya) {
                    $biaya_preparasi += floatval(str_replace(['Rp. ', ',', '.'], '', $biaya->harga));
                }
            }
            $data->total_biaya_preparasi = $biaya_preparasi;
            $grand_total += $biaya_preparasi;
            $harga_total += $biaya_preparasi;
            // $data->biaya_preparasi_padatan = null;
            // $data->total_biaya_preparasi = 0;
            // $biaya_preparasi = 0;

            //BIAYA DI LUAR PAJAK
            $biaya_akhir = 0;
            $biaya_diluar_pajak = 0;
            $txt = [];

            if (isset($payload->data_diskon->diluar_pajak)) {
                if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
                    // $harga_total += $transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport);
                    $biaya_akhir += $transport - $transport / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_transport);
                    $biaya_diluar_pajak += $transport;
                } else {
                    $grand_total += $transport;
                    $harga_total += $transport_;
                    // dd($transport, $transport_, $grand_total, $harga_total);
                }

                if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
                    // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                    $biaya_akhir += $perdiem - $perdiem / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem);
                    $biaya_diluar_pajak += $perdiem;
                } else {
                    $grand_total += $perdiem;
                    $harga_total += $perdiem_;
                }

                if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
                    // $harga_total += $jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                    $biaya_akhir += $jam - $jam / 100 * (int) \str_replace("%", "", $payload->data_diskon->discount_perdiem_24jam);
                    $biaya_diluar_pajak += $jam;
                } else {
                    $grand_total += $jam;
                    $harga_total += $jam_;
                }

                if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
                    $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
                    $biaya_akhir += $biaya_lain;
                    $biaya_diluar_pajak += $biaya_lain;
                } else {
                    $grand_total += $biaya_lain;
                    $harga_total += $biaya_lain;
                }
            }

            $diluar_pajak = ['select' => $txt];

            if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
                foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

                    $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], ['', '', ''], $item->harga));
                    $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], ['', '', ''], $item->harga));
                }
                $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
            } else {
                $diluar_pajak['body'] = [];
            }

            //biaya di luar pajak
            $data->biaya_di_luar_pajak = json_encode($diluar_pajak);
            $data->total_biaya_di_luar_pajak = $biaya_diluar_pajak;
            $data->diluar_pajak = isset($payload->data_diskon->diluar_pajak) ? json_encode($payload->data_diskon->diluar_pajak) : NULL;

            //Grand total sebelum kena diskon
            // dd($grand_total);
            $data->grand_total = $grand_total;
            $data->total_dpp = $harga_total;
            $data->total_discount = $total_diskon;


            $piutang = $harga_total;

            if (isset($payload->data_diskon->ppn) && $payload->data_diskon->ppn != "" && floatval($payload->data_diskon->ppn) > 0 && $payload->data_diskon->ppn != "0%") {
                $data->ppn = (int) \str_replace("%", "", $payload->data_diskon->ppn);
                $data->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->ppn));
                $piutang += $data->total_ppn;
            }

            if (isset($payload->data_diskon->ppn) && ($payload->data_diskon->ppn == "" || floatval($payload->data_diskon->ppn) == 0 || $payload->data_diskon->ppn == "0%")) {
                // dd($masuk);
                $data->ppn = 0;
                $data->total_ppn = 0;
            }

            if (isset($payload->data_diskon->pph) && $payload->data_diskon->pph != "" && floatval($payload->data_diskon->ppn) > 0 && $payload->data_diskon->ppn != "0%") {
                $data->pph = (int) \str_replace("%", "", $payload->data_diskon->pph);
                $data->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $payload->data_diskon->pph));
                $piutang -= $data->total_pph;
            }

            if (isset($payload->data_diskon->pph) && ($payload->data_diskon->pph == "" || floatval($payload->data_diskon->pph) == 0 || $payload->data_diskon->pph == "0%")) {
                $data->pph = 0;
                $data->total_pph = 0;
            }

            $data->piutang = $piutang;
            $biaya_akhir += $piutang;

            $data->biaya_akhir = $biaya_akhir;

            $data->syarat_ketentuan = (isset($payload->syarat_ketentuan) && !empty($payload->syarat_ketentuan)) ? json_encode($payload->syarat_ketentuan) : NULL;
            $data->keterangan_tambahan = (isset($payload->keterangan_tambahan) && !empty($payload->keterangan_tambahan)) ? json_encode($payload->keterangan_tambahan) : NULL;


            $data->created_by = $dataOld->created_by;
            $data->created_at = $dataOld->created_at;
            $data->updated_at = DATE('Y-m-d H:i:s');
            $data->updated_by = $this->karyawan;
            // $data->expired = $tgl;

            ($dataOld->data_lama != null) ? $data_lama = json_decode($dataOld->data_lama) : $data_lama = null;

            ($data_lama != null) ? $data->data_lama = json_encode($data_lama) : null;
            $data->save();

            $dataOld->document_status = 'Non Aktif';
            $dataOld->is_active = false;
            $dataOld->is_emailed = true;
            $dataOld->is_approved = true;
            $dataOld->save();

            if ($data_lama != null) {
                $message = '';
                if ($data_lama->status_sp == 'true') { //merubah jadwal dalam arti menon aktifkan SP
                    SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
                    Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => false, 'canceled_by' => 'system']);

                    $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $data->no_document dan data yang sudah terjadwal akan di tarik otomatis oleh system dan menunggu request baru dari sales";
                } else { //tidak merubah jadwal dalam arti update no doc dalam sp  if($data_lama->status_sp == 'false')
                    if ($payload->data_wilayah->status_sampling == 'SD') {
                        SamplingPlan::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'quotation_id' => $data->id,
                                'status_jadwal' => 'SD',
                                'is_active' => false
                            ]);

                        Jadwal::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($data->nama_perusahaan))),
                                'is_active' => false,
                                'canceled_by' => 'system'
                            ]);

                        /*Jadwal::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($data->nama_perusahaan))),
                                'is_active' => false
                            ]);*/
                    } else {
                        SamplingPlan::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'quotation_id' => $data->id
                            ]);

                        Jadwal::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($data->nama_perusahaan)))
                            ]);

                        /*Jadwal::where('no_quotation', $dataOld->no_document)
                            ->update([
                                'no_quotation' => $data->no_document,
                                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($data->nama_perusahaan)))
                            ]);*/
                    }

                    $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $data->no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $data->no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
                }

                if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                    $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
                    $no_qt_lama = $cek_order->no_document;
                    $no_qt_baru = $data->no_document;
                    $id_order = $data_lama->id_order;

                    $parse = new GeneratePraSampling;
                    $parse->type('QT');
                    $parse->where('no_qt_lama', $no_qt_lama);
                    $parse->where('no_qt_baru', $no_qt_baru);
                    $parse->where('id_order', $id_order);
                    $parse->save();
                } else {
                    $parse = new GeneratePraSampling;
                    $parse->type('QT');
                    $parse->where('no_qt_baru', $data->no_document);
                    $parse->where('generate', 'new');
                    $parse->save();
                }
            } else {
                $parse = new GeneratePraSampling;
                $parse->type('QT');
                $parse->where('no_qt_baru', $data->no_document);
                $parse->where('generate', 'new');
                $parse->save();
            }

            if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                $invoices = Invoice::where('no_quotation', $dataOld->no_document)
                    ->where('is_active', true)
                    ->get();

                $invoiceNumbersTobeChecked = [];
                foreach ($invoices as $invoice) {
                    if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
                        $invoice->is_generate = false;
                        $invoice->is_emailed = false;

                        $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
                    }
                    $invoice->no_quotation = $data->no_document;
                    $invoice->save();

                    // $render = new RenderInvoice();
                    // $render->renderInvoice($invoice->no_invoice);
                }

                $invoiceNumbersStr = implode(', ', $invoiceNumbersTobeChecked);

                $message = count($invoiceNumbersTobeChecked) > 0
                    ? "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $data->no_document dengan nomor order $data_lama->no_order. Oleh karena itu nomor invoice $invoiceNumbersStr akan dikembalikan ke menu generate invoice untuk dilakukan pengecekan."
                    : "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $data->no_document dengan nomor order $data_lama->no_order.";

                Notification::where('id_department', 5)->title('Revisi Penawaran')->message($message)->url('/generate-invoice')->send();

                // update persiapan sampel
                DB::table('persiapan_sampel_header')
                    ->where('no_quotation', $dataOld->no_document)
                    ->where('is_active', true)
                    ->update([
                        'no_quotation' => $data->no_document,
                    ]);
            }

            // UPDATE KONFIRMASI ORDER ===================
            $konfirmasi = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)
                ->where('is_active', true)->get();

            foreach ($konfirmasi as $k) {
                $k->no_quotation = $data->no_document;
                $k->id_quotation = $data->id;
                $k->save();
            }

            // UPDATE KONFIRMASI ORDER ===================
            // $orderConfirmation = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)->where('is_active', true)->first();
            // if ($orderConfirmation)
            //     $orderConfirmation->update(['no_quotation' => $data->no_document]);
            // ===========================================
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $data->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            $job = new RenderPdfPenawaran($data->id, 'non kontrak');
            $this->dispatch($job);

            $array_id_user = GetAtasan::where('id', $data->sales_id)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $array_id_user)
                ->title('Penawaran telah di revisi')
                ->message('Penawaran dengan nomor ' . $dataOld->no_document . ' telah di revisi menjadi ' . $data->no_document . '.')
                ->url('/quote-request')
                ->send();

            return response()->json([
                'message' => "Penawaran dengan nomor $dataOld->no_document berhasil di revisi menjadi $data->no_document."
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'message' => $e->getMessage() . ' ' . $e->getLine()
            ], 401);
        }
    }

    private function createKontrak($payload)
    {
        foreach ($payload->data_pendukung as $index => $pengujian) {
            $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
            $penamaanTitik = $pengujian->penamaan_titik ?? [];

            if (count($penamaanTitik) !== $jumlahTitik) {
                return response()->json([
                    'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                ], 403);
            }
        }
        if (isset($payload->informasi_pelanggan->tgl_penawaran) && $payload->informasi_pelanggan->tgl_penawaran != null) {
            $db = DATE('Y', \strtotime($payload->informasi_pelanggan->tgl_penawaran));
        } else {
            return response()->json([
                'message' => 'Please field date quotation first.!'
            ], 401);
        }

        $sales_id = $payload->informasi_pelanggan->sales_id;
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }


        DB::beginTransaction();
        try {
            $tahun_chek = date('y', strtotime($payload->informasi_pelanggan->tgl_penawaran));  // 2 digit tahun (misal: 25)
            $bulan_chek = date('m', strtotime($payload->informasi_pelanggan->tgl_penawaran));  // 2 digit bulan (misal: 01)
            $bulan_chek = self::romawi($bulan_chek);

            $cek = QuotationKontrakH::where('id_cabang', $this->idcabang)
                ->where('no_document', 'not like', '%R%')
                ->where('no_document', 'like', '%/' . $tahun_chek . '-%')
                ->orderBy('id', 'DESC')
                ->first();

            // $no_urut = '1';
            // if ($cek != null)
            //     $no_urut = floatval(explode('/', $cek->no_document)[3]) + 1;

            // $no_quotation = sprintf('%06d', ($no_urut));
            // $no_document = 'ISL/QTC/' . DATE('y', \strtotime($payload->informasi_pelanggan->tgl_penawaran)) . '-' . self::romawi(DATE('m', \strtotime($payload->informasi_pelanggan->tgl_penawaran))) . '/' . $no_quotation;

            $no_ = 1;  // Set default nomor urut menjadi 1

            if ($cek != null) {
                // Pisahkan komponen no_document untuk mengambil tahun dan nomor urut terakhir
                $parts = explode('/', $cek->no_document);

                if (count($parts) > 3) {  // Pastikan formatnya sesuai
                    $tahun_cek_full = $parts[2];  // Tahun dan bulan dokumen terakhir
                    list($tahun_cek_docLast, $bulan_cek_docLast) = explode('-', $tahun_cek_full);

                    // Jika tahun dan bulan dokumen terakhir sama dengan tahun dan bulan sekarang
                    // if ((int) $tahun_chek == (int) $tahun_cek_docLast && $bulan_chek == $bulan_cek_docLast) {
                    //     // Ambil nomor urut terakhir dan tambah 1
                    //     $no_ = (int) explode('/', $cek->no_document)[3] + 1;
                    // }
                    if ((int) $tahun_chek == (int) $tahun_cek_docLast) {
                        // Ambil nomor urut terakhir dan tambah 1
                        $no_ = (int) explode('/', $cek->no_document)[3] + 1;
                    }
                }
            }

            // Format nomor dokumen menjadi 8 digit
            $no_quotation = sprintf('%06d', $no_);
            $no_document = 'ISL/QTC/' . $tahun_chek . '-' . $bulan_chek . '/' . $no_quotation;

            // Implementasi untuk create kontrak
            // Insert Data Quotation Kontrak Header
            $dataH = new QuotationKontrakH;
            $dataH->no_quotation = $no_quotation;  //penentian nomor Quotation
            $dataH->no_document = $no_document;
            $dataH->pelanggan_ID = $payload->informasi_pelanggan->pelanggan_ID;
            $dataH->id_cabang = $this->idcabang;

            //dataH customer order     -------------------------------------------------------> save ke master customer parrent
            $dataH->nama_perusahaan = strtoupper(htmlspecialchars_decode($payload->informasi_pelanggan->nama_perusahaan));
            $dataH->tanggal_penawaran = strtoupper($payload->informasi_pelanggan->tgl_penawaran);
            if ($payload->informasi_pelanggan->konsultan != '')
                $dataH->konsultan = strtoupper(trim(htmlspecialchars_decode($payload->informasi_pelanggan->konsultan)));
            if ($payload->informasi_pelanggan->alamat_kantor != '')
                $dataH->alamat_kantor = $payload->informasi_pelanggan->alamat_kantor;
            $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_perusahaan);
            $dataH->nama_pic_order = ucwords($payload->informasi_pelanggan->nama_pic_order);
            $dataH->jabatan_pic_order = $payload->informasi_pelanggan->jabatan_pic_order;
            $dataH->no_pic_order = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_pic_order);
            $dataH->email_pic_order = $payload->informasi_pelanggan->email_pic_order;
            $dataH->email_cc = (!empty($payload->informasi_pelanggan->email_cc) && sizeof($payload->informasi_pelanggan->email_cc) !== 0) ? json_encode($payload->informasi_pelanggan->email_cc) : null;
            $dataH->alamat_sampling = $payload->informasi_pelanggan->alamat_sampling;
            // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $dataH->nama_pic_sampling = ucwords($payload->informasi_pelanggan->nama_pic_sampling);
            $dataH->jabatan_pic_sampling = $payload->informasi_pelanggan->jabatan_pic_sampling;
            $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $dataH->email_pic_sampling = $payload->informasi_pelanggan->email_pic_sampling;
            //end lokasi sampling customer
            // $dataH->status_wilayah = $payload->status_wilayah;
            // $dataH->wilayah = $payload->wilayah;
            $dataH->periode_kontrak_awal = $payload->data_pendukung[0]->periodeAwal;
            $dataH->periode_kontrak_akhir = $payload->data_pendukung[0]->periodeAkhir;
            $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
            $dataH->created_by = $this->karyawan;
            $dataH->created_at = DATE('Y-m-d H:i:s');
            $data_pendukung_h = [];
            $data_s = [];
            $period = [];

            $globalTitikCounter = 1; // <======= BUAT NOMOR DI PENAMAAN TITIK
            foreach ($payload->data_pendukung as $key => $data_pendukungH) {
                $param = [];
                $regulasi = '';
                $periode = '';

                if ($data_pendukungH->parameter != null)
                    $param = $data_pendukungH->parameter;
                if (isset($data_pendukungH->regulasi))
                    $regulasi = $data_pendukungH->regulasi;
                if ($data_pendukungH->periode != null)
                    $periode = $data_pendukungH->periode;

                $exp = explode("-", $data_pendukungH->kategori_1);
                $kategori = $exp[0];
                $vol = 0;

                // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                $parameter = [];
                foreach ($data_pendukungH->parameter as $va) {
                    $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
                    array_push($parameter, $cek_par->nama_lab);
                }

                $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                    ->where('is_active', true)
                    ->whereIn('nama_parameter', $parameter)
                    ->where('id_kategori', $kategori)
                    ->first();

                if ($harga_pertitik->volume != null)
                    $vol += floatval($harga_pertitik->volume);
                if ($data_pendukungH->jumlah_titik == '') {
                    $reqtitik = 0;
                } else {
                    $reqtitik = $data_pendukungH->jumlah_titik;
                }

                $temp_prearasi = [];
                if ($data_pendukungH->biaya_preparasi != null || $data_pendukungH->biaya_preparasi != "") {
                    foreach ($data_pendukungH->biaya_preparasi as $pre) {
                        if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                            $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan))];
                    }
                }
                $biaya_preparasi = $temp_prearasi;

                array_push($data_pendukung_h, (object) [
                    'kategori_1' => $data_pendukungH->kategori_1,
                    'kategori_2' => $data_pendukungH->kategori_2,
                    'regulasi' => $regulasi,
                    'parameter' => $param,
                    'jumlah_titik' => $data_pendukungH->jumlah_titik,
                    'penamaan_titik' => $data_pendukungH->penamaan_titik,
                    'total_parameter' => count($param),
                    'harga_satuan' => $harga_pertitik->total_harga,
                    'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                    'volume' => $vol,
                    'periode' => $periode,
                    'biaya_preparasi' => $biaya_preparasi
                ]);

                foreach ($data_pendukungH->periode as $key => $v) {
                    array_push($period, $v);
                }
            }

            $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);

            $dataH->save();

            $period = array_values(array_unique($period));

            foreach ($period as $key => $per) {
                // Insert Data Quotation Kontrak Detail
                $dataD = new QuotationKontrakD;
                $dataD->id_request_quotation_kontrak_h = $dataH->id;

                $data_sampling = [];
                $datas = [];
                $harga_total = 0;
                $harga_air = 0;
                $harga_udara = 0;
                $harga_emisi = 0;
                $harga_padatan = 0;
                $harga_swab_test = 0;
                $harga_tanah = 0;
                $grand_total = 0;
                $total_diskon = 0;
                $j = $key + 1;
                $n = 0;

                $desc_preparasi = [];
                $harga_preparasi = 0;
                foreach ($payload->data_pendukung as $m => $data_pendukungD) {
                    if (in_array($per, $data_pendukungD->periode)) {
                        $param = [];
                        $regulasi = '';
                        if ($data_pendukungD->parameter != null)
                            $param = $data_pendukungD->parameter;
                        if (isset($data_pendukungD->regulasi))
                            $regulasi = $data_pendukungD->regulasi;

                        $exp = explode("-", $data_pendukungD->kategori_1);
                        $kategori = $exp[0];
                        $vol = 0;

                        // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                        $parameter = [];
                        foreach ($data_pendukungD->parameter as $va) {
                            $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
                            array_push($parameter, $cek_par->nama_lab);
                        }

                        $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                            ->where('is_active', true)
                            ->whereIn('nama_parameter', $parameter)
                            ->where('id_kategori', $kategori)
                            ->first();

                        if ($harga_pertitik->volume != null)
                            $vol += floatval($harga_pertitik->volume);
                        if ($data_pendukungD->jumlah_titik == '') {
                            $reqtitik = 0;
                        } else {
                            $reqtitik = $data_pendukungD->jumlah_titik;
                        }

                        //============= BIAYA PREPARASI ==================
                        $temp_prearasi = [];
                        if ($data_pendukungD->biaya_preparasi != null || $data_pendukungD->biaya_preparasi != "") {
                            foreach ($data_pendukungD->biaya_preparasi as $pre) {
                                if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                                    $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
                                if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
                                    $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                            }
                        }
                        $biaya_preparasi = $temp_prearasi;

                        // dd($biaya_preparasi);

                        // PENENTUAN NOMOR PENAMAAN TITIK
                        $penamaan_titik_fixed = [];
                        if ($data_pendukungD->penamaan_titik != null) {
                            foreach ($data_pendukungD->penamaan_titik as $pt) {
                                $penamaan_titik_fixed[] = [sprintf('%03d', $globalTitikCounter) => trim($pt)];
                                $globalTitikCounter++;
                            }
                        }

                        $data_sampling[$n++] = [
                            'kategori_1' => $data_pendukungD->kategori_1,
                            'kategori_2' => $data_pendukungD->kategori_2,
                            'regulasi' => $regulasi,
                            'parameter' => $param,
                            'jumlah_titik' => $data_pendukungD->jumlah_titik,
                            'penamaan_titik' => $penamaan_titik_fixed,
                            'total_parameter' => count($param),
                            'harga_satuan' => $harga_pertitik->total_harga,
                            'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                            'volume' => $vol,
                            'biaya_preparasi' => $biaya_preparasi
                        ];

                        // kalkulasi harga parameter sesuai titik
                        if ($kategori == 1) { // air
                            // dd('masuk');
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        } else if ($kategori == 4) { //  udara
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        } else if ($kategori == 5) { // emisi

                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        } else if ($kategori == 6) { // padatan

                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        } else if ($kategori == 7) { // swab test

                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        } else if ($kategori == 8) { // tanah

                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
                        }
                        // end kalkulasi harga parameter sesuai titik
                    }
                }

                $datas[$j] = [
                    'periode_kontrak' => $per,
                    'data_sampling' => array_values($data_sampling)
                    // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
                ];

                $dataD->periode_kontrak = $per;
                $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;
                $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
                // $dataD->data_pendukung_sampling = json_encode($datas);
                // end data sampling
                $dataD->harga_air = $harga_air;
                $dataD->harga_udara = $harga_udara;
                $dataD->harga_emisi = $harga_emisi;
                $dataD->harga_padatan = $harga_padatan;
                $dataD->harga_swab_test = $harga_swab_test;
                $dataD->harga_tanah = $harga_tanah;

                //============= BIAYA PREPARASI
                $dataD->biaya_preparasi = json_encode($desc_preparasi);
                $dataD->total_biaya_preparasi = $harga_preparasi;
                // dd($dataD);
                $dataD->save();
            }

            DB::commit();

            return response()->json([
                'message' => "Request Quotation number $no_document success created",
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    // Yang mulia - 2025-08-06
    private function updateKontrak($payload)
    {
        // Implementasi untuk update kontrak
        // dd($payload);
        try {
            $informasi_pelanggan = $payload->informasi_pelanggan;
            $data_pendukung = $payload->data_pendukung;
            $data_wilayah = $payload->data_wilayah;
            $syarat_ketentuan = $payload->syarat_ketentuan;
            $data_diskon = $payload->data_diskon;
            if (isset($payload->keterangan_tambahan))
                $keterangan_tambahan = $payload->keterangan_tambahan;

            foreach ($data_pendukung as $index => $pengujian) {
                $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
                $penamaanTitik = $pengujian->penamaan_titik ?? [];

                if (count($penamaanTitik) !== $jumlahTitik) {
                    return response()->json([
                        'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                    ], 403);
                }
            }
            if (!isset($payload->informasi_pelanggan->sales_id) || $payload->informasi_pelanggan->sales_id == '') {
                return response()->json([
                    'message' => 'Sales penanggung jawab tidak boleh kosong',
                ], 403);
            }

            DB::BeginTransaction();
            try {
                $dataH = QuotationKontrakH::where('is_active', true)
                    ->where('id', $informasi_pelanggan->id)
                    ->first();


                // dd(json_decode($dataH->data_lama));
                //dataH customer order     -------------------------------------------------------> save ke master customer parrent
                // $dataH->nama_perusahaan = $informasi_pelanggan->nama_perusahaan;
                $dataH->tanggal_penawaran = $informasi_pelanggan->tgl_penawaran;
                // if (isset($informasi_pelanggan->konsultan) && $informasi_pelanggan->konsultan != '')
                //     $dataH->konsultan = $informasi_pelanggan->konsultan;
                if (isset($informasi_pelanggan->alamat_kantor) && $informasi_pelanggan->alamat_kantor != '')
                    $dataH->alamat_kantor = $informasi_pelanggan->alamat_kantor;
                $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
                $dataH->nama_pic_order = ucwords($informasi_pelanggan->nama_pic_order);
                $dataH->jabatan_pic_order = $informasi_pelanggan->jabatan_pic_order;
                $dataH->no_pic_order = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                $dataH->email_pic_order = $informasi_pelanggan->email_pic_order;
                $dataH->email_cc = (!empty($informasi_pelanggan->email_cc) && sizeof($informasi_pelanggan->email_cc) !== 0) ? json_encode($informasi_pelanggan->email_cc) : null;
                $dataH->alamat_sampling = $informasi_pelanggan->alamat_sampling;
                // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_sampling);
                $dataH->nama_pic_sampling = ucwords($informasi_pelanggan->nama_pic_sampling);
                $dataH->jabatan_pic_sampling = $informasi_pelanggan->jabatan_pic_sampling;
                $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                $dataH->email_pic_sampling = $informasi_pelanggan->email_pic_sampling;

                $dataH->data_pendukung_diskon = json_encode($data_diskon);
                $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
                // dd($payload);
                $dataH->ppn = $data_diskon->ppn;
                $dataH->pph = $data_diskon->pph;
                // Periode Kontrak
                $dataH->periode_kontrak_awal = $data_pendukung[0]->periodeAwal;
                $dataH->periode_kontrak_akhir = $data_pendukung[0]->periodeAkhir;

                $dataH->status_wilayah = $data_wilayah->status_Wilayah;
                $dataH->wilayah = $data_wilayah->wilayah;
                $uniqueStatusSampling = array_unique(array_map(function ($wilayah) {
                    return $wilayah->status_sampling;
                }, $data_wilayah->wilayah_data));

                $dataH->status_sampling = count($uniqueStatusSampling) === 1 ? $uniqueStatusSampling[0] : null;
                // $dataH->add_by = $this->userid;
                // $dataH->add_at = DATE('Y-m-d H:i:s');
                $data_transport_h = [];
                $data_pendukung_h = [];
                $data_s = [];
                $period = [];

                $data_pendukung_lain = [];
                $total_biaya_lain = 0;
                // BIAYA LAIN
                if (isset($data_diskon->biaya_lain) && !empty($data_diskon->biaya_lain)) {
                    $biaya_lains = 0;
                    $data_pendukung_lain = array_map(function ($disc) use (&$biaya_lains) {
                        $biaya_lains += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
                        return (object) [
                            'deskripsi' => $disc->deskripsi,
                            'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
                            'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
                        ];
                    }, $data_diskon->biaya_lain);
                    $total_biaya_lain = $biaya_lains;
                    $dataH->biaya_lain = json_encode($data_pendukung_lain);
                    $dataH->total_biaya_lain = $total_biaya_lain;
                } else {
                    $dataH->biaya_lain = null;
                    $dataH->total_biaya_lain = 0;
                }
                // END BIAYA LAIN
                //CUSTOM DISCOUNT
                $custom_disc = [];
                if (isset($data_diskon->custom_discount) && !empty($data_diskon->custom_discount)) {
                    $custom_disc = array_map(function ($disc) {
                        return (object) [
                            'deskripsi' => $disc->deskripsi,
                            'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                        ];
                    }, $data_diskon->custom_discount);
                    $dataH->custom_discount = json_encode($custom_disc);
                } else {
                    $dataH->custom_discount = null;
                }
                // END CUSTOM DISCOUNT
                // dd($payload->data_pendukung);

                $globalTitikCounter = 1; // <======= BUAT NOMOR DI PENAMAAN TITIK
                foreach ($payload->data_pendukung as $i => $item) {
                    $param = $item->parameter;
                    // dd($i);

                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                    }
                    // dd($item);
                    $harga_db = [];
                    $volume_db = [];
                    foreach ($parameter as $param_) {
                        $ambil_data = HargaParameter::where('id_kategori', $kategori)
                            ->where('nama_parameter', $param_)
                            ->orderBy('id', 'ASC')
                            ->get();

                        if (count($ambil_data) > 1) {
                            foreach ($ambil_data as $xc => $zx) {
                                // if($m == 8) dd($zx, $informasi_pelanggan->tgl_penawaran);
                                // $zx->tgl_order $request->tgl_order
                                if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
                                    // if($m == 8) dd($zx);
                                    array_push($harga_db, $zx->harga);
                                    array_push($volume_db, $zx->volume);
                                    break;
                                }

                                if ((count($ambil_data) - 1) == $xc) {
                                    $zx = $ambil_data[0];
                                    array_push($harga_db, $zx->harga);
                                    array_push($volume_db, $zx->volume);
                                    break;
                                }
                            }
                        } else if (count($ambil_data) == 1) {
                            foreach ($ambil_data as $xc => $zx) {
                                array_push($harga_db, $zx->harga);
                                array_push($volume_db, $zx->volume);
                                break;
                            }
                        } else {
                            array_push($harga_db, 0);
                            array_push($volume_db, 0);
                        }

                        // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                        //     return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                        // }) ?? $ambil_data->first();

                        // $harga_db[] = $cek_harga_parameter->harga ?? 0;
                        // $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        // dd($ambil_data);
                        // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                        //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
                        // }) ?? $ambil_data->first();
                        // if($i == 13) dd($cek_harga_parameter,  $payload->informasi_pelanggan->tgl_penawaran);
                        // fix bug
                        // if ($cek_harga_parameter) {
                        //     $harga_db[] = $cek_harga_parameter->harga;
                        //     $volume_db[] = $cek_harga_parameter->volume;
                        // } else {
                        //     $harga_db[] = 0;
                        //     $volume_db[] = 0;
                        // }
                    }
                    $harga_pertitik = (object) [
                        'volume' => array_sum($volume_db),
                        'total_harga' => array_sum($harga_db)
                    ];
                    // if($i == 13) dd($harga_pertitik, $volume_db, $harga_db);
                    // dd($harga_pertitik);
                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item->jumlah_titik;

                    $temp_prearasi = [];
                    if ($item->biaya_preparasi != null || $item->biaya_preparasi != "") {
                        foreach ($item->biaya_preparasi as $pre) {
                            if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                                $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
                            // HARGA PREPARASI UNUSED
                            // if($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") $harga_preparasi += floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan));
                        }
                    }
                    $biaya_preparasi = $temp_prearasi;

                    $data_sampling[$i] = [
                        'kategori_1' => $item->kategori_1,
                        'kategori_2' => $item->kategori_2,
                        'penamaan_titik' => $item->penamaan_titik,
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol,
                        'periode' => $item->periode,
                        'biaya_preparasi' => $biaya_preparasi
                    ];

                    isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

                    foreach ($item->periode as $key => $v) {
                        array_push($period, $v);
                    }

                    array_push($data_pendukung_h, $data_sampling[$i]);
                }

                // dd($data_pendukung_h);
                $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);
                // dd($data_pendukung_h);
                $period_pendukung = [];

                for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {

                    $data_lain = $data_pendukung_lain;

                    $kalkulasi = 0;
                    $harga_transportasi = 0;
                    $harga_perdiem = 0;
                    $harga_perdiem24 = 0;

                    if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi))
                        $harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                    if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem))
                        $harga_perdiem = $data_wilayah->wilayah_data[$c]->harga_perdiem;
                    if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem24))
                        $harga_perdiem24 = $data_wilayah->wilayah_data[$c]->harga_perdiem24;

                    if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem))
                        $kalkulasi = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;
                    $data_lain_arr = [];
                    foreach ($data_lain as $dl) {
                        array_push($data_lain_arr, (array) $dl);
                    }
                    // dd($data_wilayah->wilayah_data[$c]);
                    array_push($data_transport_h, (object) ['status_sampling' => $data_wilayah->wilayah_data[$c]->status_sampling, 'jumlah_transportasi' => $data_wilayah->wilayah_data[$c]->transportasi, 'jumlah_orang_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang, 'jumlah_hari_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari, 'jumlah_orang_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam, 'jumlah_hari_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam, 'biaya_lain' => $data_lain_arr, 'harga_transportasi' => $harga_transportasi, 'harga_perdiem' => $harga_perdiem, 'harga_perdiem24' => $harga_perdiem24, 'periode' => $data_wilayah->wilayah_data[$c]->periode, 'kalkulasi_by_sistem' => $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem]);

                    foreach ($data_wilayah->wilayah_data[$c]->periode as $key => $v) {
                        array_push($period_pendukung, $v);
                    }
                }

                // dd($data_transport_h);

                $dataH->data_pendukung_lain = json_encode($data_transport_h);
                // $status_sampling = array_map(function ($wilayah) {
                //     return $wilayah->status_sampling;
                // }, $data_wilayah->wilayah_data);
                // if (count($status_sampling) == 1)
                //     $dataH->status_sampling = implode(',', $status_sampling);
                isset($syarat_ketentuan) ? $dataH->syarat_ketentuan = json_encode($syarat_ketentuan) : $dataH->syarat_ketentuan = null;
                isset($keterangan_tambahan) ? $dataH->keterangan_tambahan = json_encode($keterangan_tambahan) : $dataH->keterangan_tambahan = null;
                isset($data_diskon->diluar_pajak) ? $dataH->diluar_pajak = json_encode($data_diskon->diluar_pajak) : $dataH->diluar_pajak = null;
                $dataH->save();

                // END HEADER DATA
                // START DETAIL DATA
                $data_detail = DB::table('request_quotation_kontrak_D')
                    ->where('id_request_quotation_kontrak_h', $informasi_pelanggan->id)
                    ->select('id')
                    ->get();
                // dd($data_detail);

                // Period Data Pendukung
                $period_pendukung = array_values(array_unique($period_pendukung));

                $period = [];
                foreach ($data_pendukung as $key => $v) {
                    foreach ($v->periode as $a) {
                        array_push($period, $a);
                    }
                }

                $period = array_values(array_unique($period));

                $diff1 = array_values(array_diff($period, $period_pendukung));

                if (count($diff1) != 0) {
                    DB::rollBack();
                    $periode = [];
                    foreach ($diff1 as $k => $val) {
                        array_push($periode, self::tanggal_indonesia($val, 'period'));
                    }

                    return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Wilayah Tidak Ada Di Periode Data Pendukung..! '], 500);
                }
                // dd($period_pendukung);

                $diff2 = array_values(array_diff($period_pendukung, $period));

                if (count($diff2) != 0) {
                    DB::rollBack();
                    $periode = [];
                    foreach ($diff2 as $k => $val) {
                        array_push($periode, self::tanggal_indonesia($val, 'period'));
                    }

                    return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Pendukung Tidak Ada Di Periode Data Wilayah..! '], 500);
                }

                $num = count($period);
                $detailCount = count($data_detail);
                // if ($num > 12) {
                //     DB::rollBack();
                //     return response()->json(['message' => 'Periode Kontrak Lebih Dari 12 Bulan'], 201);
                // }
                $id_det = [];
                $tgl = null;

                $dataLama = json_decode($dataH->data_lama);
                if (isset($dataLama->id_order)) {
                    // =========================================================================
                    // STEP 1: Bongkar & Petakan Nomor Titik dari Kontrak Lama (dataOld)
                    // =========================================================================
                    $titikGroupMapping = [];
                    $lastTitikNumber = 0;
                    $periodeOld = [];

                    $detailsFromOldContract = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)->get();

                    foreach ($detailsFromOldContract as $item) {
                        $dataPendukungSampling = json_decode($item->data_pendukung_sampling, true);
                        if (is_array($dataPendukungSampling)) {
                            foreach ($dataPendukungSampling as $samplingData) {
                                $dataSamplingList = $samplingData['data_sampling'] ?? [];
                                foreach ($dataSamplingList as $item) {
                                    $periodeOld[] = $samplingData['periode_kontrak'];

                                    $groupKey = $samplingData['periode_kontrak'] . ';' . $item['kategori_1'] . ';' . $item['kategori_2'] . ';' . json_encode($item['regulasi']) . ';' . json_encode($item['parameter']);
                                    if (!isset($titikGroupMapping[$groupKey])) {
                                        $titikGroupMapping[$groupKey] = [];
                                    }

                                    if (isset($item['penamaan_titik']) && is_array($item['penamaan_titik'])) {
                                        foreach ($item['penamaan_titik'] as $titikObject) {
                                            $titikObject = (array) $titikObject;
                                            $nomor = key($titikObject);
                                            $nama = current($titikObject);

                                            // Mapping: 'Outlet' => '001'
                                            $titikGroupMapping[$groupKey][$nomor] = $nama;

                                            // Cari nomor titik tertinggi untuk counter
                                            if (intval($nomor) > $lastTitikNumber) {
                                                $lastTitikNumber = intval($nomor);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $globalTitikCounter = $lastTitikNumber + 1;
                }
                $allDetailQuotNow = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)->get();
                // foreach ($allDetailQuotOld as $detail) {
                //     dump(json_decode($detail->data_pendukung_sampling));
                // }
                $id_order_header = null;
                $periodNow = $allDetailQuotNow->pluck('periode_kontrak')->toArray();
                $alreadyOrdered = false;
                if ($dataH->data_lama !== null) {
                    $dataNowDecoded = json_decode($dataH->data_lama, true);
                    if (isset($dataNowDecoded['id_order'])) {
                        $id_order_header = $dataNowDecoded['id_order'];
                        $alreadyOrdered = true;
                    }
                }

                $biggestNumberOfSampel = 0;

                if ($alreadyOrdered) {
                    $OrderDetails = OrderDetail::where('id_order_header', $id_order_header)->get();
                    foreach ($OrderDetails as $detail) {
                        $no_sampel = explode('/', $detail->no_sampel)[1];
                        if ($no_sampel > $biggestNumberOfSampel) {
                            $biggestNumberOfSampel = $no_sampel;
                        }
                    }
                    foreach ($allDetailQuotNow as $detail) {
                        $oldData = json_decode($detail->data_pendukung_sampling, true);
                        $first = reset($oldData)['data_sampling'];
                        foreach ($first as $item) {
                            foreach ($item['penamaan_titik'] as $titik) {
                                $key = array_key_first($titik);
                                $number = (int) $key;
                                if ($number > $biggestNumberOfSampel) {
                                    $biggestNumberOfSampel = $number;
                                }
                            }
                        }
                    }
                }
                // dd($biggestNumberOfSampel);
                $diffPeriod = array_diff($period, $periodNow);
                $diffCurrentPeriod = array_diff($periodNow, $period);
                foreach ($period as $k => $per) {
                    // dump($per);
                    // dd($data_detail);
                    if (!isset($data_detail[$k]->id)) {
                        $id_detail = '';
                    } else {
                        $id_detail = $data_detail[$k]->id;
                        // dd($num, $detail, $id_detail);
                        if ($num < $detailCount) {
                            array_push($id_det, $id_detail);
                        }
                    }

                    $cek = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)->where('periode_kontrak', $per)->first();

                    if (!is_null($cek)) {
                        $dataD = $cek;
                    } else {
                        $dataD = new QuotationKontrakD;
                        $dataD->id_request_quotation_kontrak_h = $dataH->id;
                    }


                    // dd($period);

                    $data_sampling = [];
                    $datas = [];
                    $harga_total = 0;
                    $harga_air = 0;
                    $harga_udara = 0;
                    $harga_emisi = 0;
                    $harga_padatan = 0;
                    $harga_swab_test = 0;
                    $harga_tanah = 0;
                    $harga_pangan = 0;
                    $grand_total = 0;
                    $total_diskon = 0;
                    $desc_preparasi = [];
                    $harga_preparasi = 0;

                    $j = $k + 1;
                    $n = 0;
                    // dd($data_pendukung);
                    foreach ($data_pendukung as $m => $xyz) {
                        // dd($m);

                        // dump(in_array($per, $xyz->periode));
                        if (in_array($per, $xyz->periode)) {
                            $param = [];
                            $regulasi = '';
                            if (isset($xyz->parameter) && $xyz->parameter != null)
                                $param = $xyz->parameter;
                            if (isset($xyz->regulasi) && $xyz->regulasi != null)
                                $regulasi = $xyz->regulasi;

                            $exp = explode("-", $xyz->kategori_1);
                            $kategori = $exp[0];
                            $vol = 0;

                            // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                            $parameter = [];
                            $id_param = [];
                            foreach ($xyz->parameter as $va) {
                                $cek_par = DB::table('parameter')
                                    ->where('id', explode(';', $va)[0])->first();
                                array_push($parameter, $cek_par->nama_lab);
                                array_push($id_param, $cek_par->id);
                            }

                            $harga_db = [];
                            $volume_db = [];
                            foreach ($parameter as $ix => $param_) {
                                // dd($param_);
                                $ambil_data = HargaParameter::where('id_kategori', $kategori)
                                    ->where('nama_parameter', $param_)
                                    ->orderBy('id', 'ASC')
                                    ->get();
                                // dd($ambil_data);

                                if (count($ambil_data) > 1) {
                                    foreach ($ambil_data as $xc => $zx) {
                                        // if($m == 8) dd($zx, $informasi_pelanggan->tgl_penawaran);
                                        // $zx->tgl_order $request->tgl_order
                                        if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
                                            // if($m == 8) dd($zx);
                                            array_push($harga_db, $zx->harga);
                                            array_push($volume_db, $zx->volume);
                                            break;
                                        }

                                        if ((count($ambil_data) - 1) == $xc) {
                                            $zx = $ambil_data[0];
                                            array_push($harga_db, $zx->harga);
                                            array_push($volume_db, $zx->volume);
                                            break;
                                        }
                                    }
                                } else if (count($ambil_data) == 1) {
                                    foreach ($ambil_data as $xc => $zx) {
                                        array_push($harga_db, $zx->harga);
                                        array_push($volume_db, $zx->volume);
                                        break;
                                    }
                                } else {
                                    array_push($harga_db, 0);
                                    array_push($volume_db, 0);
                                }
                            }
                            // dd($harga_db, $volume_db);
                            $vol_db = array_sum($volume_db);
                            $har_db = array_sum($harga_db);

                            $harga_pertitik = (object) [
                                'volume' => $vol_db,
                                'total_harga' => $har_db
                            ];
                            // if($m == 8) dd($harga_pertitik);
                            // if($m == 6)dd($harga_pertitik, $parameter);
                            if ($harga_pertitik->volume != null)
                                $vol += floatval($harga_pertitik->volume);
                            if ($xyz->jumlah_titik == '') {
                                $reqtitik = 0;
                            } else {
                                $reqtitik = $xyz->jumlah_titik;
                            }

                            //============= BIAYA PREPARASI ==================

                            // $temp_prearasi = [];
                            if ($xyz->biaya_preparasi != null || $xyz->biaya_preparasi != "") {
                                foreach ($xyz->biaya_preparasi as $pre) {
                                    if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                                        $desc_preparasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
                                    if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
                                        $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                                }
                            }
                            // $biaya_preparasi = $temp_prearasi;

                            // $desc_preparasi = $biaya_preparasi;





                            // PENENTUAN NOMOR PENAMAAN TITIK
                            $penamaan_titik_fixed = [];
                            // dump($dataH->data_lama);
                            $dataLama = json_decode($dataH->data_lama);
                            if (isset($dataLama->id_order)) {
                                $fullGroupKey = $per . ';' . $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);
                                $fallbackGroupKey = $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);

                                $pengurangan_periode_kontrak = array_values(array_diff($periodeOld, $xyz->selectedPeriode));
                                $penambahan_periode_kontrak = array_values(array_diff($xyz->selectedPeriode, $periodeOld));

                                $key_penambahan = array_search($per, $penambahan_periode_kontrak);
                                $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

                                $oldNumberMappingForGroup = [];
                                if (isset($titikGroupMapping[$fullGroupKey])) {
                                    $oldNumberMappingForGroup = $titikGroupMapping[$fullGroupKey];
                                } else {
                                    $keyPeriod = in_array($per, $periodeOld) ? $per : $periode_pengganti;
                                    $tempKeyGroup = array_filter(array_keys($titikGroupMapping), fn($key) => str_contains($key, $keyPeriod));

                                    $foundKey = null;
                                    foreach ($tempKeyGroup as $key) {
                                        if (count(array_intersect($titikGroupMapping[$key], $xyz->penamaan_titik)) > 0) {
                                            $foundKey = $key;
                                        }
                                    }

                                    $oldNumberMappingForGroup = isset($titikGroupMapping[$foundKey]) ? $titikGroupMapping[$foundKey] : [];
                                }

                                foreach ($xyz->penamaan_titik as $pt) {
                                    $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
                                    if (in_array($namaTitik, $oldNumberMappingForGroup)) {
                                        $nomor = array_search($namaTitik, $oldNumberMappingForGroup);
                                        $penamaan_titik_fixed[] = (object) [$nomor => $namaTitik];
                                    } else {
                                        $nomorLama = null;

                                        foreach ($titikGroupMapping as $key => $map) {
                                            if (strpos($key, $fallbackGroupKey) !== false && in_array($namaTitik, $map)) {
                                                $nomorLama = array_search($namaTitik, $map);
                                                if (!array_key_exists($nomorLama, $penamaan_titik_fixed)) {
                                                    $nomorLama = null;
                                                }
                                                break;
                                            }
                                        }

                                        if ($nomorLama) {
                                            $penamaan_titik_fixed[] = (object) [$nomorLama => $namaTitik];

                                            $titikGroupMapping[$fullGroupKey][$nomorLama] = $namaTitik;
                                        } else {
                                            $nomorBaru = sprintf('%03d', $globalTitikCounter);
                                            $penamaan_titik_fixed[] = (object) [$nomorBaru => $namaTitik];

                                            $titikGroupMapping[$fullGroupKey][$nomorBaru] = $namaTitik;
                                            $globalTitikCounter++;
                                        }
                                    }
                                }
                            } else {
                                if ($xyz->penamaan_titik != null) {
                                    foreach ($xyz->penamaan_titik as $pt) {
                                        $penamaan_titik_fixed[] = [sprintf('%03d', $globalTitikCounter) => trim($pt)];
                                        $globalTitikCounter++;
                                    }
                                }
                            }

                            $data_sampling[$n++] = [
                                'kategori_1' => $xyz->kategori_1,
                                'kategori_2' => $xyz->kategori_2,
                                'regulasi' => $regulasi,
                                'parameter' => $param,
                                'jumlah_titik' => $xyz->jumlah_titik,
                                'penamaan_titik' => $penamaan_titik_fixed,
                                'total_parameter' => count($param),
                                'harga_satuan' => $harga_pertitik->total_harga,
                                'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                                'volume' => $vol,
                                'biaya_preparasi' => $desc_preparasi
                            ];

                            // dump([
                            //     'kategori_1' => $xyz->kategori_1,
                            //     'kategori_2' => $xyz->kategori_2,
                            //     'regulasi' => $regulasi,
                            //     'parameter' => $param,
                            //     'jumlah_titik' => $xyz->jumlah_titik,
                            //     'penamaan_titik' => $penamaan_titik_fixed,
                            //     'total_parameter' => count($param),
                            //     'harga_satuan' => $harga_pertitik->total_harga,
                            //     'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                            //     'volume' => $vol,
                            //     'biaya_preparasi' => $desc_preparasi
                            // ]);

                            // kalkulasi harga parameter sesuai titik
                            switch ($kategori) {
                                case '1':
                                    $harga_air += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '4':
                                    $harga_udara += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '5':
                                    $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '6':
                                    $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '7':
                                    $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '8':
                                    $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '9':
                                    $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                            }
                        }
                    }
                    // dump($data_sampling);
                    foreach ($data_sampling as $index => $newItem) {
                        $checkOldQt = $allDetailQuotNow->where('periode_kontrak', $per)->first();
                        $first = [];
                        // if($per == '2026-06' || $per == '2026-07'){
                        //     dump("sebelum");
                        //     dump($data_sampling);
                        // }
                        $isMutated = false;
                        if ($checkOldQt) {
                            // dump('ada old data sesuai periode');
                            $oldData = json_decode($checkOldQt->data_pendukung_sampling, true);
                            $first = reset($oldData)['data_sampling'];
                        } else {
                            // dump('tidak ada old data sesuai periode');
                            if ($alreadyOrdered) {
                                $checkOldQtRemaining = $allDetailQuotNow->whereIn('periode_kontrak', $diffCurrentPeriod);
                                // dump('already ordered');

                                if ($checkOldQtRemaining->count() > 0) {
                                    // dump('ada data diff periode', $per);

                                    $foundFromOldRemaining = false;
                                    $matchedOldPenamaan = null;
                                    foreach ($checkOldQtRemaining as $oldDetail) {
                                        $oldData = json_decode($oldDetail->data_pendukung_sampling, true);
                                        $oldSamplingList = reset($oldData)['data_sampling'] ?? [];
                                        foreach ($oldSamplingList as $oldSampling) {
                                            if (
                                                ($oldSampling['kategori_1'] ?? null) === ($newItem['kategori_1'] ?? null) &&
                                                ($oldSampling['kategori_2'] ?? null) === ($newItem['kategori_2'] ?? null)
                                            ) {
                                                // Match found
                                                $matchedOldPenamaan = $oldSampling['penamaan_titik'] ?? [];
                                                $foundFromOldRemaining = $oldDetail->periode_kontrak;
                                                // dump('Pengecekan match lama');
                                                // dump($matchedOldPenamaan, $foundFromOldRemaining);
                                                break 2;
                                            }
                                        }
                                    }

                                    if ($matchedOldPenamaan !== null) {
                                        // Langsung assign titik dari match lama
                                        $data_sampling[$index]['penamaan_titik'] = $matchedOldPenamaan;

                                        $isMutated = true;
                                        // Buang periode yang udah kepake dari diffCurrentPeriod
                                        $diffCurrentPeriod = array_filter($diffCurrentPeriod, function ($periode) use ($foundFromOldRemaining) {
                                            return $periode !== $foundFromOldRemaining;
                                        });

                                        // Skip ke iterasi berikutnya (ga pake logic bawah)
                                        continue;
                                    }

                                }
                            }
                        }

                        if (!$isMutated) {
                            // dump('tidak mutated');
                            $diffRegulasi = false;
                            $diffKategori = false;
                            $diffSubKategori = false;
                            $diffParameter = false;
                            $oldItem = $first[$index] ?? [];

                            // Cek kategori_1
                            if ((isset($oldItem['kategori_1']) && $newItem['kategori_1'] !== $oldItem['kategori_1'] ?? null)) {
                                $diffKategori = true;
                            }

                            // Cek kategori_2
                            if ((isset($oldItem['kategori_2']) && $newItem['kategori_2'] !== $oldItem['kategori_2'] ?? null)) {
                                $diffSubKategori = true;
                            }

                            // Cek regulasi
                            if ((isset($oldItem['regulasi']) && json_encode($newItem['regulasi']) !== json_encode($oldItem['regulasi'] ?? []))) {
                                $diffRegulasi = true;
                            }

                            // Cek parameter (urutan juga dibandingkan)
                            if ((isset($oldItem['parameter']) && json_encode($newItem['parameter']) !== json_encode($oldItem['parameter'] ?? []))) {
                                $diffParameter = true;
                            }

                            $penamaanBaru = array_map(function ($obj) {
                                return (array) $obj;
                            }, $newItem['penamaan_titik']);

                            $penamaanLama = $oldItem['penamaan_titik'] ?? [];

                            if (!$alreadyOrdered) {

                                // dump('tidak pernah Order');
                                $penamaanFix = [];

                                foreach ($penamaanBaru as $titikBaru) {
                                    $valBaru = array_values($titikBaru)[0];

                                    $biggestNumberOfSampel++;
                                    $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                                    $penamaanFix[] = [$newKey => $valBaru];
                                }

                                $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                            } else {
                                // dump('sudah pernah Order');
                                if ($diffKategori) {
                                    $penamaanFix = [];

                                    foreach ($penamaanBaru as $titikBaru) {
                                        $valBaru = array_values($titikBaru)[0];

                                        // Selalu generate nomor baru
                                        $biggestNumberOfSampel++;
                                        $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                                        $penamaanFix[] = [$newKey => $valBaru];
                                    }

                                    $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                                } else {
                                    $penamaanFix = [];
                                    $used = [];

                                    // Loop data baru
                                    foreach ($penamaanBaru as $titikBaru) {
                                        $valBaru = array_values($titikBaru)[0];
                                        $found = false;

                                        // Coba cari val yang sama di penamaan lama
                                        foreach ($penamaanLama as $titikLama) {
                                            $keyLama = array_key_first($titikLama);
                                            $valLama = array_values($titikLama)[0];

                                            if ((string) $valBaru === (string) $valLama && !in_array($keyLama, $used)) {
                                                $penamaanFix[] = [$keyLama => $valBaru];
                                                $used[] = $keyLama;
                                                $found = true;
                                                break;
                                            }
                                        }

                                        // Kalau tidak ditemukan di data lama, pakai nomor baru
                                        if (!$found) {
                                            $biggestNumberOfSampel++;
                                            $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                                            $penamaanFix[] = [$newKey => $valBaru];
                                            $used[] = $newKey;
                                        }
                                    }

                                    // Set hasil akhirnya
                                    $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                                }
                            }
                        }
                        // if($per == '2026-08'){
                        //     dump($data_sampling);
                        // }
                        // if($per != '2026-08'){
                        //     dump(json_decode($checkOldQt->data_pendukung_sampling), $data_sampling);
                        // } else {
                        //     dump('Mulai 2026 - 08');
                        //     dump($data_sampling);
                        // }
                    }
                    // dd($desc_preparasi);

                    $datas[$j] = [
                        'periode_kontrak' => $per,
                        'data_sampling' => array_values($data_sampling)
                        // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
                    ];
                    $dataD->periode_kontrak = $per;
                    $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

                    $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
                    // end data sampling
                    $dataD->harga_air = $harga_air;
                    $dataD->harga_udara = $harga_udara;
                    $dataD->harga_emisi = $harga_emisi;
                    $dataD->harga_padatan = $harga_padatan;
                    $dataD->harga_swab_test = $harga_swab_test;
                    $dataD->harga_tanah = $harga_tanah;

                    // kalkulasi harga
                    $expOp = explode("-", $data_wilayah->wilayah);
                    $id_wilayah = $expOp[0];
                    $cekOperasional = HargaTransportasi::where('is_active', true)->where('id', $id_wilayah)->first();

                    // START FOR
                    $disc_transport = 0;
                    $disc_perdiem = 0;
                    $disc_perdiem_24 = 0;

                    $harga_transport = 0;
                    $jam = 0;
                    $transport = 0;
                    $perdiem = 0;

                    $data_lain = $data_pendukung_lain;
                    $biaya_lain = 0;

                    for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {
                        if (in_array($per, $data_wilayah->wilayah_data[$c]->periode)) {

                            // Menjumlahkan total % discount transport kedalam variable
                            if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD')
                                $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi;
                            // dd($data_wilayah->status_Wilayah);
                            if ($data_wilayah->status_Wilayah == 'DALAM KOTA') {
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
                                    // dd($data_wilayah->wilayah_data[$c]);

                                    $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                    $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                        $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                    } else {
                                        $dataD->jumlah_orang_24jam = null;
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
                                        $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        $dataD->jumlah_hari_24jam = 0;
                                    }

                                    if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem)) {
                                        $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true' ? $dataD->kalkulasi_by_sistem = 'on' : $dataD->kalkulasi_by_sistem = 'off';
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {
                                        $dataD->harga_transportasi = $cekOperasional->transportasi;
                                        $dataD->harga_transportasi_total = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);

                                        $dataD->harga_personil = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang);
                                        $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                        }

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                        }

                                        $transport = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);
                                        $perdiem = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '')
                                            $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        // IF NOT CALCULATE BY SYSTEM
                                        // JUMLAH TRANSPORTASI
                                        // dd($data_wilayah->wilayah_data);
                                        isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
                                        // JUMLAH ORANG PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
                                        // JUMLAH HARI PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
                                        // JUMLAH ORANG 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
                                        // JUMLAH HARI 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
                                        // HARGA SATUAN TRANSPORTASI
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
                                            $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
                                            $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi);
                                        // HARGA SATUAN PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
                                        // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
                                            $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_personil);
                                        // HARGA 24 JAM PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
                                        // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
                                            $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil);

                                        // PERDIEM, JAM, TRANSPORT
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
                                            $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
                                            $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
                                            $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
                                    }
                                } else {
                                    $dataD->transportasi = null;
                                    $dataD->perdiem_jumlah_orang = null;
                                    $dataD->perdiem_jumlah_hari = null;
                                    $dataD->jumlah_orang_24jam = null;
                                    $dataD->jumlah_hari_24jam = null;

                                    $dataD->harga_transportasi = 0;
                                    $dataD->harga_transportasi_total = null;

                                    $dataD->harga_personil = 0;
                                    $dataD->harga_perdiem_personil_total = null;

                                    $dataD->harga_24jam_personil = 0;
                                    $dataD->harga_24jam_personil_total = null;
                                }

                                $harga_tiket = 0;
                                $harga_transportasi_darat = 0;
                                $harga_penginapan = 0;
                            } else {
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

                                    $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                    $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                    if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                        $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                    } else {
                                        $dataD->jumlah_orang_24jam = null;
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
                                        $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        $dataD->jumlah_hari_24jam = 0;
                                    }
                                }

                                $harga_tiket = 0;
                                $harga_transportasi_darat = 0;
                                $harga_penginapan = 0;

                                // dd($data_wilayah->wilayah_data);
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
                                    //hitung harga tiket perjalanan
                                    $dataD->kalkulasi_by_sistem = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;

                                    if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {

                                        $harga_tiket = $cekOperasional->tiket * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                        $harga_transportasi_darat = $cekOperasional->transportasi;
                                        $harga_penginapan = $cekOperasional->penginapan;
                                        $dataD->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
                                        $dataD->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;

                                        $dataD->harga_personil = $cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;

                                        $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                        }

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                            $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                        }

                                        $transport = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;
                                        $perdiem = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                    } else {
                                        // IF NOT CALCULATE BY SYSTEM
                                        // JUMLAH TRANSPORTASI
                                        isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
                                        // JUMLAH ORANG PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
                                        // JUMLAH HARI PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
                                        // JUMLAH ORANG 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
                                        // JUMLAH HARI 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
                                        // HARGA SATUAN TRANSPORTASI
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
                                            $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
                                            $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi_total);
                                        // HARGA SATUAN PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = null;
                                        // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
                                            $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total);
                                        // HARGA 24 JAM PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = null;
                                        // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
                                            $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total);

                                        // PERDIEM, JAM, TRANSPORT
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
                                            $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
                                            $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
                                            $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
                                    }
                                } else {
                                    $dataD->transportasi = null;
                                    $dataD->perdiem_jumlah_orang = null;
                                    $dataD->perdiem_jumlah_hari = null;
                                    $dataD->jumlah_orang_24jam = null;
                                    $dataD->jumlah_hari_24jam = null;

                                    $dataD->harga_transportasi = 0;
                                    $dataD->harga_transportasi_total = null;

                                    $dataD->harga_personil = 0;
                                    $dataD->harga_perdiem_personil_total = null;

                                    $dataD->harga_24jam_personil = 0;
                                    $dataD->harga_24jam_personil_total = null;
                                }
                            }
                            // dd($total_biaya_lain);
                            $dataD->status_sampling = $data_wilayah->wilayah_data[$c]->status_sampling;
                            // $l = $c + 1;
                            // $desc = 'desc' . $l;
                            // $harga = 'harga' . $l;
                            // $jum_desc = count(array_filter($request->$desc));
                            // if($jum_desc > 0){
                            //     for ($a = 0;$a < $jum_desc;$a++)
                            //     {
                            //         if ($request->$desc[$a] != "")
                            //         {
                            //             $data_lain[$a] = ['deskripsi' => $request->$desc[$a], 'harga' => floatval(\str_replace(['Rp. ', ','], '', $request->$harga[$a])) ];
                            //             $biaya_lain += floatval(\str_replace(['Rp. ', ','], '', $request->$harga[$a]));
                            //         }
                            //     }
                            // }

                        }
                    }

                    // dd($transport, $perdiem, $jam);

                    // =======================================================================DATA DISKON===========================================================================
                    // ==================================================DISKON ANALISA=============================================================================================
                    // SEARCH DISCOUNT DATA MATCHES PERIOD
                    $isPeriodeDiskonExist = false;
                    $periodeNotExist = '';
                    $indexDataDiskon = 0;
                    if (count($data_diskon->discount_data) > 0) {
                        foreach ($data_diskon->discount_data as $d => $discount) {
                            if (in_array($per, $discount->periode)) {
                                $isPeriodeDiskonExist = true;
                                $indexDataDiskon = $d;
                                break;
                            }
                            if (!$isPeriodeDiskonExist && $d == count($data_diskon->discount_data) - 1) {
                                $periodeNotExist = $per;
                            }
                        }
                    }

                    if (!$isPeriodeDiskonExist) {
                        return response()->json(['message' => 'Periode ' . $periodeNotExist . ' tidak ditemukan pada group diskon', 'status' => '500'], 403);
                    }
                    // $indexDataDiskon == 1 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_transport) : '';
                    // dd($isPeriodeDiskonExist);

                    if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_air > 0) {
                        // $indexDataDiskon == 0 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_air) : '';
                        // dd($data_diskon->discount_data[$indexDataDiskon]->discount_non_air);
                        $dataD->discount_air = $data_diskon->discount_data[$indexDataDiskon]->discount_air;
                        $diskon_air = ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));
                        $dataD->total_discount_air = $diskon_air;

                        $harga_total += $harga_air - $diskon_air;

                        $total_diskon += $diskon_air;
                        if (floatval(\str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air)) > 10) {
                            $message = $dataH->no_document . ' Discount Air melebihi 10%';
                            Notification::where('id', 19)
                                ->title('Peringatan.')
                                ->message($message)
                                ->url('/quote-request')
                                ->send();
                        }
                    } else {
                        $harga_total += $harga_air;
                        $dataD->discount_air = null;
                        $dataD->total_discount_air = 0;
                    }

                    if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_non_air > 0) {
                        $dataD->discount_non_air = $data_diskon->discount_data[$indexDataDiskon]->discount_non_air;
                        $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                        $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
                        $dataD->total_discount_non_air = $disc_;
                        $harga_total += ($jumlah - $disc_);
                        $total_diskon += $disc_;

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 10) {
                            $message = $dataH->no_document . ' Discount Non-Air melebihi 10%';
                            Notification::where('id', 19)
                                ->title('Peringatan.')
                                ->message($message)
                                ->url('/quote-request')
                                ->send();
                        }



                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 0) {
                            $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
                            $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
                                $message = $dataH->no_document . ' Discount Udara melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $dataD->discount_udara = null;
                            $dataD->total_discount_udara = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 0) {
                            $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
                            $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
                                $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $dataD->discount_emisi = null;
                            $dataD->total_discount_emisi = 0;
                        }
                    } else {
                        $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                        $dataD->discount_non_air = null;
                        $dataD->total_discount_non_air = '0.00';

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) == 0) {
                            $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
                            $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
                                $message = $dataH->no_document . ' Discount Udara melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $harga_total += $harga_udara;
                            $dataD->discount_udara = null;
                            $dataD->total_discount_udara = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) == 0) {
                            $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
                            $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
                                $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $harga_total += $harga_emisi;
                            $dataD->discount_emisi = null;
                            $dataD->total_discount_emisi = 0;
                        }
                    }

                    // ========================================================END DISKON ANALISA========================================================================
                    // ====================================================DISKON TRANSPORTASI========================================================================================
                    $harga_total += $harga_pangan;
                    $transport_ = 0;
                    $perdiem_ = 0;
                    $jam_ = 0;
                    // dd($data_diskon->discount_data[$indexDataDiskon], $isPeriodeDiskonExist);
                    if ($isPeriodeDiskonExist) {
                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0 && $data_diskon->discount_data[$indexDataDiskon]->discount_transport !== "") {
                            $dataD->discount_transport = $data_diskon->discount_data[$indexDataDiskon]->discount_transport;
                            $dataD->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            // Harga Total
                            // $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $transport_ = $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                        } else {
                            $dataD->discount_transport = null;
                            $dataD->total_discount_transport = 0;
                            $transport_ = $transport;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem) > 0) {
                            $dataD->discount_perdiem = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem;
                            $dataD->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            // $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $perdiem_ = $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                        } else {
                            $dataD->discount_perdiem = null;
                            $dataD->total_discount_perdiem = 0;
                            $perdiem_ = $perdiem;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam) > 0) {
                            $dataD->discount_perdiem_24jam = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam;
                            $dataD->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            // $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $jam_ = $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                        } else {
                            $dataD->discount_perdiem_24jam = null;
                            $dataD->total_discount_perdiem_24jam = 0;
                            $jam_ = $jam;
                        }
                        // --------------- AKTIFKAN JIKA INGIN BY PASS PERIODE DISKON BISA TIDAK DIMASUKKAN KE GROUP DISKON -------------------------------------//
                        // }else{
                        //     $transport_ = $transport;
                        //     $perdiem_ = $perdiem;
                        //     $jam_ = $jam;
                    }

                    $harga_transport += ($transport_ + $perdiem_ + $jam_);
                    // ==================================================END DISKON TRANSPORTASI======================================================================================
                    // =======================================================DISKON GABUNGAN=========================================================================================
                    if ($isPeriodeDiskonExist) {
                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_gabungan) > 0) {
                            $dataD->discount_gabungan = $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan;
                            $dataD->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                            $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                            $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                        } else {
                            $dataD->discount_gabungan = null;
                            $dataD->total_discount_gabungan = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_consultant) > 0) {
                            $dataD->discount_consultant = $data_diskon->discount_data[$indexDataDiskon]->discount_consultant;
                            $dataD->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                            $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                            $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                        } else {
                            $dataD->discount_consultant = null;
                            $dataD->total_discount_consultant = 0;
                        }

                        // BIAYA LAIN

                        // dd($data_diskon->discount_data[$indexDataDiskon]);
                        if (isset($data_diskon->discount_data[$indexDataDiskon]->biaya_lains) && !empty($data_diskon->discount_data[$indexDataDiskon]->biaya_lains)) {
                            $data_lain = array_values(array_filter(array_map(function ($disc) use (&$biaya_lain) {
                                if ($disc->harga == 0)
                                    return null;
                                $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga));
                                return (object) [
                                    'deskripsi' => $disc->deskripsi,
                                    'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
                                ];
                            }, $data_diskon->discount_data[$indexDataDiskon]->biaya_lains)));
                            $dataD->biaya_lain = count($data_lain) > 0 ? json_encode($data_lain) : null;
                            $dataD->total_biaya_lain = $biaya_lain;
                            // $grand_total += $biaya_lain;
                            // $harga_total += $biaya_lain;
                        } else {
                            $dataD->biaya_lain = null;
                            $dataD->total_biaya_lain = 0;
                        }

                        // ====================================================END BIAYA LAIN=======================================================================================


                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_group) > 0) {
                            $totalTransport = $harga_transport;
                            if (isset($payload->data_diskon->diluar_pajak)) {

                                if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
                                    $totalTransport -= $transport_;
                                }

                                if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
                                    $totalTransport -= $perdiem_;
                                }

                                if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
                                    $totalTransport -= $jam_;
                                }
                            }
                            // if($per == '2025-02') dd($harga_total + $totalTransport);
                            $dataD->discount_group = $data_diskon->discount_data[$indexDataDiskon]->discount_group;
                            $diskon_group = ((($harga_total + $totalTransport) - $biaya_lain) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_group));
                            $dataD->total_discount_group = $diskon_group;
                            $total_diskon += $diskon_group;
                            $harga_total = $harga_total - $diskon_group;
                        } else {
                            $dataD->discount_group = null;
                            $dataD->total_discount_group = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen) > 0) {
                            $dataD->cash_discount_persen = $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen;
                            $dataD->total_cash_discount_persen = (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                            $total_diskon += (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                            $harga_total = $harga_total - (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                        } else {
                            $dataD->cash_discount_persen = null;
                            $dataD->total_cash_discount_persen = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount) > 0) {
                            $harga_total = $harga_total - floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                            $dataD->cash_discount = floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                            $total_diskon += floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                        } else {

                            $dataD->cash_discount = floatval(0);
                        }


                        // CUSTOM DISKON
                        if (isset($data_diskon->discount_data[$indexDataDiskon]->custom_discounts) && !empty($data_diskon->discount_data[$indexDataDiskon]->custom_discounts)) {
                            $custom_disc = array_values(array_filter(array_map(function ($disc) {
                                if ($disc->discount == 0)
                                    return null; // Tidak mengembalikan apa-apa jika discount = 0
                                return (object) [
                                    'deskripsi' => $disc->deskripsi,
                                    'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                                ];
                            }, $data_diskon->discount_data[$indexDataDiskon]->custom_discounts)));

                            $harga_disc = 0;
                            foreach ($data_diskon->discount_data[$indexDataDiskon]->custom_discounts as $disc) {
                                $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
                            }

                            $total_diskon += $harga_disc;
                            $harga_total -= $harga_disc;
                            $dataD->custom_discount = count($custom_disc) > 0 ? json_encode($custom_disc) : null;
                            $dataD->total_custom_discount = $harga_disc;
                        } else {
                            $dataD->custom_discount = null;
                            $dataD->total_custom_discount = 0;
                        }
                        // ====================================================END CUSTOM DISKON=======================================================================================
                    }

                    //============= BIAYA PREPARASI
                    // dd($desc_preparasi);
                    $dataD->biaya_preparasi = json_encode($desc_preparasi);
                    $dataD->total_biaya_preparasi = $harga_preparasi;
                    $grand_total += $harga_preparasi;
                    $harga_total += $harga_preparasi;
                    // dd($grand_total);

                    //jika Transportasi di luar pajak
                    $biaya_akhir = 0;
                    $biaya_diluar_pajak = 0;
                    $txt = [];

                    if (isset($payload->data_diskon->diluar_pajak)) {
                        // if($per == '2025-02')dd($payload->data_diskon->diluar_pajak->transportasi);
                        if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
                            $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
                            // $harga_total += $transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport);
                            $biaya_akhir += $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $biaya_diluar_pajak += $transport;
                        } else {
                            $grand_total += $transport;
                            $harga_total += $transport_;
                        }

                        if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
                            $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
                            // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem);
                            $biaya_akhir += $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $biaya_diluar_pajak += $perdiem;
                        } else {
                            $grand_total += $perdiem;
                            $harga_total += $perdiem_;
                        }

                        if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
                            $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
                            // $harga_total += $jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam);
                            $biaya_akhir += $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $biaya_diluar_pajak += $jam;
                        } else {
                            $grand_total += $jam;
                            $harga_total += $jam_;
                        }

                        if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
                            $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
                            $biaya_akhir += $biaya_lain;
                            $biaya_diluar_pajak += $biaya_lain;
                        } else {
                            // dump($biaya_lain);
                            $grand_total += $biaya_lain;
                            $harga_total += $biaya_lain;
                        }
                    }

                    //Grand total sebelum kena diskon
                    $dataD->grand_total = $grand_total;
                    $dataD->total_dpp = $harga_total;

                    if (floatval($data_diskon->ppn) >= 0) {
                        $dataD->ppn = $data_diskon->ppn;
                        $dataD->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
                        $piutang = $harga_total + ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
                    }

                    if (floatval($data_diskon->pph) >= 0) {
                        $dataD->pph = $data_diskon->pph;
                        $dataD->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
                        $piutang = $piutang - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
                    }

                    $diluar_pajak = ['select' => $txt, 'body' => []];

                    if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
                        foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

                            $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                            $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                        }
                        $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
                    }

                    //biaya di luar pajak
                    $dataD->biaya_di_luar_pajak = json_encode($diluar_pajak);
                    $dataD->total_biaya_di_luar_pajak = $biaya_diluar_pajak;

                    $dataD->piutang = $piutang;
                    $dataD->total_discount = $total_diskon;
                    $biaya_akhir += $piutang;

                    $dataD->biaya_akhir = $biaya_akhir;

                    //==========================END BIAYA DI LUAR PAJAK======================================
                    $dataD->save();
                    // END FOR EACH
                }

                // ----------------- Start Delete If Periode Not Exist In Current Quotation ---------------------- //
                $deleted = QuotationKontrakD::whereNotIn('periode_kontrak', $period)->where('id_request_quotation_kontrak_h', $dataH->id)->delete();
                // ----------------- End Delete If Periode Not Exist In Current Quotation   --------------------- //

                $Dd = DB::select(" SELECT SUM(harga_air) as harga_air, SUM(harga_udara) as harga_udara,
                                            SUM(harga_emisi) as harga_emisi,
                                            SUM(harga_padatan) as harga_padatan,
                                            SUM(harga_swab_test) as harga_swab_test,
                                            SUM(harga_tanah) as harga_tanah,
                                            SUM(transportasi) as transportasi,
                                            SUM(perdiem_jumlah_orang) as perdiem_jumlah_orang,
                                            SUM(perdiem_jumlah_hari) as perdiem_jumlah_hari,
                                            SUM(jumlah_orang_24jam) as jumlah_orang_24jam,
                                            SUM(jumlah_hari_24jam) as jumlah_hari_24jam,
                                            SUM(harga_transportasi) as harga_transportasi,
                                            SUM(harga_transportasi_total) as harga_transportasi_total,
                                            SUM(harga_personil) as harga_personil,
                                            SUM(harga_perdiem_personil_total) as harga_perdiem_personil_total,
                                            SUM(harga_24jam_personil) as harga_24jam_personil,
                                            SUM(harga_24jam_personil_total) as harga_24jam_personil_total,
                                            SUM(discount_air) as discount_air,
                                            SUM(total_discount_air) as total_discount_air,
                                            SUM(discount_non_air) as discount_non_air,
                                            SUM(total_discount_non_air) as total_discount_non_air,
                                            SUM(discount_udara) as discount_udara,
                                            SUM(total_discount_udara) as total_discount_udara,
                                            SUM(discount_emisi) as discount_emisi,
                                            SUM(total_discount_emisi) as total_discount_emisi,
                                            SUM(discount_gabungan) as discount_gabungan,
                                            SUM(total_discount_gabungan) as total_discount_gabungan,
                                            SUM(cash_discount_persen) as cash_discount_persen,
                                            SUM(total_cash_discount_persen) as total_cash_discount_persen,
                                            SUM(discount_consultant) as discount_consultant,
                                            SUM(discount_group) as discount_group,
                                            SUM(total_discount_group) as total_discount_group,
                                            SUM(total_discount_consultant) as total_discount_consultant,
                                            SUM(cash_discount) as cash_discount,
                                            SUM(total_custom_discount) as total_custom_discount,
                                            SUM(discount_transport) as discount_transport,
                                            SUM(total_discount_transport) as total_discount_transport,
                                            SUM(discount_perdiem) as discount_perdiem,
                                            SUM(total_discount_perdiem) as total_discount_perdiem,
                                            SUM(discount_perdiem_24jam) as discount_perdiem_24jam,
                                            SUM(total_discount_perdiem_24jam) as total_discount_perdiem_24jam,
                                            SUM(ppn) as ppn,
                                            SUM(total_ppn) as total_ppn,
                                            SUM(total_pph) as total_pph,
                                            SUM(pph) as pph,
                                            SUM(biaya_lain) as biaya_lain,
                                            SUM(total_biaya_lain) as total_biaya_lain,
                                            SUM(total_biaya_preparasi) as total_biaya_preparasi,
                                            SUM(biaya_di_luar_pajak) as biaya_di_luar_pajak,
                                            SUM(total_biaya_di_luar_pajak) as total_biaya_di_luar_pajak,
                                            SUM(grand_total) as grand_total,
                                            SUM(total_discount) as total_discount,
                                            SUM(total_dpp) as total_dpp,
                                            SUM(piutang) as piutang,
                                            SUM(biaya_akhir) as biaya_akhir FROM request_quotation_kontrak_D WHERE id_request_quotation_kontrak_h = '$dataH->id' GROUP BY id_request_quotation_kontrak_h ");
                // UPDATE HEADER DATA
                // dd($Dd);
                $editH = QuotationKontrakH::where('id', $dataH->id)
                    ->first();
                $editH->syarat_ketentuan = json_encode($payload->syarat_ketentuan);
                // dd($syarat);
                if (isset($payload->keterangan_tambahan) && $payload->keterangan_tambahan != null)
                    $editH->keterangan_tambahan = json_encode($payload->keterangan_tambahan);
                if ($tgl == null || !isset($tgl)) {
                    $tgl = date('Y-m-d', strtotime("+30 days", strtotime(DATE('Y-m-d'))));
                }
                // dd($Dd);
                $editH->expired = $tgl;
                $editH->total_harga_air = $Dd[0]->harga_air;
                $editH->total_harga_udara = $Dd[0]->harga_udara;
                $editH->total_harga_emisi = $Dd[0]->harga_emisi;
                $editH->total_harga_padatan = $Dd[0]->harga_padatan;
                $editH->total_harga_swab_test = $Dd[0]->harga_swab_test;
                $editH->total_harga_tanah = $Dd[0]->harga_tanah;
                $editH->transportasi = $Dd[0]->transportasi;
                $editH->perdiem_jumlah_orang = $Dd[0]->perdiem_jumlah_orang;
                $editH->perdiem_jumlah_hari = $Dd[0]->perdiem_jumlah_hari;
                $editH->jumlah_orang_24jam = $Dd[0]->jumlah_orang_24jam;
                $editH->jumlah_hari_24jam = $Dd[0]->jumlah_hari_24jam;
                if (!is_null($Dd[0]->harga_transportasi))
                    $editH->harga_transportasi = $Dd[0]->harga_transportasi;
                if (!is_null($Dd[0]->harga_transportasi_total))
                    $editH->harga_transportasi_total = $Dd[0]->harga_transportasi_total;
                if (!is_null($Dd[0]->harga_personil))
                    $editH->harga_personil = $Dd[0]->harga_personil;
                if (!is_null($Dd[0]->harga_perdiem_personil_total))
                    $editH->harga_perdiem_personil_total = $Dd[0]->harga_perdiem_personil_total;

                if (!is_null($Dd[0]->harga_24jam_personil))
                    $editH->harga_24jam_personil = $Dd[0]->harga_24jam_personil;
                if (!is_null($Dd[0]->harga_24jam_personil_total))
                    $editH->harga_24jam_personil_total = $Dd[0]->harga_24jam_personil_total;

                $editH->total_discount_air = $Dd[0]->total_discount_air;

                $editH->total_discount_non_air = $Dd[0]->total_discount_non_air;

                $editH->total_discount_udara = $Dd[0]->total_discount_udara;

                $editH->total_discount_emisi = $Dd[0]->total_discount_emisi;

                $editH->total_discount_gabungan = $Dd[0]->total_discount_gabungan;

                $editH->total_cash_discount_persen = $Dd[0]->total_cash_discount_persen;
                $editH->total_discount_group = $Dd[0]->total_discount_group;
                $editH->total_discount_consultant = $Dd[0]->total_discount_consultant;
                if (!is_null($Dd[0]->cash_discount))
                    $editH->total_cash_discount = round($Dd[0]->cash_discount);

                $editH->total_discount_transport = $Dd[0]->total_discount_transport;

                $editH->total_discount_perdiem = $Dd[0]->total_discount_perdiem;

                $editH->total_discount_perdiem_24jam = $Dd[0]->total_discount_perdiem_24jam;

                $editH->total_custom_discount = $Dd[0]->total_custom_discount;

                $editH->total_ppn = $Dd[0]->total_ppn;
                $editH->total_pph = $Dd[0]->total_pph;

                $editH->total_biaya_lain = $Dd[0]->total_biaya_lain;
                // dd($Dd[0]->total_biaya_preparasi);
                $editH->total_biaya_preparasi = $Dd[0]->total_biaya_preparasi;
                $editH->biaya_diluar_pajak = json_encode($diluar_pajak);
                $editH->total_biaya_di_luar_pajak = $Dd[0]->total_biaya_di_luar_pajak;
                $editH->grand_total = $Dd[0]->grand_total;
                $editH->total_discount = $Dd[0]->total_discount;
                $editH->total_dpp = $Dd[0]->total_dpp;
                $editH->piutang = $Dd[0]->piutang;
                $editH->biaya_akhir = $Dd[0]->biaya_akhir;
                $editH->updated_by = $this->karyawan;
                $editH->updated_at = date('Y-m-d H:i:s');
                $editH->save();

                $data_lama = null;
                if ($dataH->data_lama != null)
                    $data_lama = json_decode($dataH->data_lama);

                if ($data_lama != null) {
                    if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                        $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
                        $no_qt_lama = $cek_order->no_document;
                        $no_qt_baru = $dataH->no_document;
                        $id_order = $data_lama->id_order;

                        // $parse = new GeneratePraSampling;
                        // $parse->type('QTC');
                        // $parse->where('no_qt_lama', $no_qt_lama);
                        // $parse->where('no_qt_baru', $no_qt_baru);
                        // $parse->where('id_order', $id_order);
                        // $parse->save();
                    } else {
                        // $parse = new GeneratePraSampling;
                        // $parse->type('QTC');
                        // $parse->where('no_qt_baru', $dataH->no_document);
                        // $parse->where('generate', 'new');
                        // $parse->save();
                    }
                } else {
                    // $parse = new GeneratePraSampling;
                    // $parse->type('QTC');
                    // $parse->where('no_qt_baru', $dataH->no_document);
                    // $parse->where('generate', 'new');
                    // $parse->save();
                }

                JobTask::insert([
                    'job' => 'RenderPdfPenawaran',
                    'status' => 'processing',
                    'no_document' => $dataH->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                DB::commit();

                $job = new RenderPdfPenawaran($dataH->id, 'kontrak');
                $this->dispatch($job);

                $array_id_user = GetAtasan::where('id', $dataH->sales_id)->get()->pluck('id')->toArray();

                Notification::whereIn('id', $array_id_user)
                    ->title('Penawaran telah diperbarui')
                    ->message('Penawaran dengan nomor ' . $dataH->no_document . ' telah diperbarui.')
                    ->url('/quote-request')
                    ->send();

                return response()->json([
                    'message' => "Request Quotation number $dataH->no_document success updated"
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                // dd($e);
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], 401);
            }
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }

    // Yang mulia - 2025-08-06
    private function revisiKontrak($payload)
    {
        // Implementasi untuk revisi kontrak
        try {
            $informasi_pelanggan = $payload->informasi_pelanggan;
            $data_pendukung = $payload->data_pendukung;
            $data_wilayah = $payload->data_wilayah;
            $syarat_ketentuan = $payload->syarat_ketentuan;
            $data_diskon = $payload->data_diskon;

            foreach ($data_pendukung as $index => $pengujian) {
                $jumlahTitik = (int) ($pengujian->jumlah_titik ?? 0);
                $penamaanTitik = $pengujian->penamaan_titik ?? [];

                if (count($penamaanTitik) !== $jumlahTitik) {
                    return response()->json([
                        'message' => "Jumlah titik tidak sesuai dengan jumlah penamaan titik pada pengujian ke-" . ($index + 1),
                    ], 403);
                }
            }

            // dd($informasi_pelanggan);
            if (!isset($payload->informasi_pelanggan->sales_id) || $payload->informasi_pelanggan->sales_id == '') {
                return response()->json([
                    'message' => 'Sales penanggung jawab tidak boleh kosong',
                ], 403);
            }
            if (isset($payload->keterangan_tambahan))
                $keterangan_tambahan = $payload->keterangan_tambahan;
            DB::BeginTransaction();
            try {

                $dataOld = QuotationKontrakH::where('is_active', true)
                    ->where('no_document', $informasi_pelanggan->no_document)
                    ->first();

                if (isset($payload->informasi_pelanggan->new_no_document) && $payload->informasi_pelanggan->new_no_document != null) {

                    $no_quotation = $dataOld->no_quotation;
                    $no_document = $payload->informasi_pelanggan->new_no_document;

                    $dataOld->updated_by = $this->karyawan;
                    $dataOld->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $dataOld->document_status = 'Non Aktif';
                    $dataOld->is_active = false;
                    $dataOld->is_emailed = true;
                    $dataOld->is_approved = true;
                    $dataOld->save();

                    ($dataOld->data_lama != null) ? $data_lama = json_decode($dataOld->data_lama) : $data_lama = null;

                    $cek_master_customer = MasterPelanggan::where('id_pelanggan', $informasi_pelanggan->pelanggan_ID)->where('is_active', true)->first();
                    if ($cek_master_customer != null) {
                        // Update kontak pelanggan
                        if ($informasi_pelanggan->no_tlp_perusahaan != '') {
                            $kontak = KontakPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->first();
                            if ($kontak != null) {
                                $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
                                if ($informasi_pelanggan->email_pic_order != '')
                                    $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
                                $kontak->save();
                            } else {
                                $kontak = new KontakPelanggan;
                                $kontak->pelanggan_id = $cek_master_customer->id;
                                $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
                                if ($informasi_pelanggan->email_pic_order != '')
                                    $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
                                $kontak->save();
                            }
                        }

                        // Update alamat pelanggan
                        if ($informasi_pelanggan->alamat_kantor != '') {
                            $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'kantor')->first();
                            if ($alamat != null) {
                                $alamat->alamat = $informasi_pelanggan->alamat_kantor;
                                $alamat->save();
                            } else {
                                $alamat = new AlamatPelanggan;
                                $alamat->pelanggan_id = $cek_master_customer->id;
                                $alamat->type_alamat = 'kantor';
                                $alamat->alamat = $informasi_pelanggan->alamat_kantor;
                                $alamat->save();
                            }
                        }

                        if ($informasi_pelanggan->alamat_sampling != '') {
                            $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'sampling')->first();
                            if ($alamat != null) {
                                $alamat->alamat = $informasi_pelanggan->alamat_sampling;
                                $alamat->save();
                            } else {
                                $alamat = new AlamatPelanggan;
                                $alamat->pelanggan_id = $cek_master_customer->id;
                                $alamat->type_alamat = 'sampling';
                                $alamat->alamat = $informasi_pelanggan->alamat_sampling;
                                $alamat->save();
                            }
                        }

                        if ($informasi_pelanggan->nama_pic_order != '') {
                            $picorder = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'order')->first();
                            if ($picorder != null) {
                                $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
                                if ($informasi_pelanggan->jabatan_pic_order != '')
                                    $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
                                $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                                $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                                $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
                                $picorder->save();
                            } else {
                                $picorder = new PicPelanggan;
                                $picorder->pelanggan_id = $cek_master_customer->id;
                                $picorder->type_pic = 'order';
                                $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
                                if ($informasi_pelanggan->jabatan_pic_order != '')
                                    $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
                                $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                                $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                                $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
                                $picorder->save();
                            }
                        }

                        if ($informasi_pelanggan->nama_pic_sampling != '') {
                            $picsampling = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'sampling')->first();
                            if ($picsampling != null) {
                                $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
                                if ($informasi_pelanggan->jabatan_pic_sampling != '')
                                    $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
                                $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                                $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                                if ($informasi_pelanggan->email_pic_sampling != '')
                                    $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
                                $picsampling->save();
                            } else {
                                $picsampling = new PicPelanggan;
                                $picsampling->pelanggan_id = $cek_master_customer->id;
                                $picsampling->type_pic = 'sampling';
                                $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
                                if ($informasi_pelanggan->nama_pic_sampling != '')
                                    $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
                                $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                                $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                                if ($informasi_pelanggan->email_pic_sampling != '')
                                    $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
                                $picsampling->save();
                            }
                        }
                    }
                } else {
                    return response()->json([
                        'message' => 'Ada masalah pada data silahkan hubungi tim IT.!'
                    ], 401);
                }

                $dataH = new QuotationKontrakH;

                //dataH customer order     -------------------------------------------------------> save ke master customer parrent
                $dataH->no_quotation = $no_quotation; //penentian nomor Quotation
                $dataH->no_document = $no_document;
                $dataH->pelanggan_ID = $dataOld->pelanggan_ID;
                $dataH->id_cabang = $this->idcabang;

                // $dataH->nama_perusahaan = $informasi_pelanggan->nama_perusahaan;
                // if (isset($informasi_pelanggan->konsultan) && $informasi_pelanggan->konsultan != '')
                // $dataH->konsultan = $informasi_pelanggan->konsultan;

                $dataH->nama_perusahaan = $dataOld->nama_perusahaan;
                $dataH->konsultan = $dataOld->konsultan;

                $dataH->tanggal_penawaran = $informasi_pelanggan->tgl_penawaran;

                if (isset($informasi_pelanggan->alamat_kantor) && $informasi_pelanggan->alamat_kantor != '')
                    $dataH->alamat_kantor = $informasi_pelanggan->alamat_kantor;
                $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
                $dataH->nama_pic_order = ucwords($informasi_pelanggan->nama_pic_order);
                $dataH->jabatan_pic_order = $informasi_pelanggan->jabatan_pic_order;
                $dataH->no_pic_order = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
                $dataH->email_pic_order = $informasi_pelanggan->email_pic_order;
                $dataH->email_cc = (!empty($informasi_pelanggan->email_cc) && sizeof($informasi_pelanggan->email_cc) !== 0) ? json_encode($informasi_pelanggan->email_cc) : null;
                $dataH->alamat_sampling = $informasi_pelanggan->alamat_sampling;
                // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_sampling);
                $dataH->nama_pic_sampling = ucwords($informasi_pelanggan->nama_pic_sampling);
                $dataH->jabatan_pic_sampling = $informasi_pelanggan->jabatan_pic_sampling;
                $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
                $dataH->email_pic_sampling = $informasi_pelanggan->email_pic_sampling;

                $dataH->data_pendukung_diskon = json_encode($data_diskon);
                $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
                // dd($payload);
                $dataH->ppn = $data_diskon->ppn;
                $dataH->pph = $data_diskon->pph;
                // Periode Kontrak
                $dataH->periode_kontrak_awal = $data_pendukung[0]->periodeAwal;
                $dataH->periode_kontrak_akhir = $data_pendukung[0]->periodeAkhir;

                $dataH->status_wilayah = $data_wilayah->status_Wilayah;
                $dataH->wilayah = $data_wilayah->wilayah;

                // $dataH->is_generated = $dataOld->is_generated;
                // $dataH->is_emailed = $dataOld->is_emailed;

                $uniqueStatusSampling = array_unique(array_map(function ($wilayah) {
                    return $wilayah->status_sampling;
                }, $data_wilayah->wilayah_data));

                $dataH->status_sampling = count($uniqueStatusSampling) === 1 ? $uniqueStatusSampling[0] : null;
                // $dataH->add_by = $this->userid;
                // $dataH->add_at = DATE('Y-m-d H:i:s');
                $data_transport_h = [];
                $data_pendukung_h = [];
                $data_s = [];
                $period = [];

                $data_pendukung_lain = [];
                $total_biaya_lain = 0;
                // BIAYA LAIN
                if (isset($data_diskon->biaya_lain) && !empty($data_diskon->biaya_lain)) {
                    $biaya_lains = 0;
                    $data_pendukung_lain = array_map(function ($disc) use (&$biaya_lains) {
                        $biaya_lains += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
                        return (object) [
                            'deskripsi' => $disc->deskripsi,
                            'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
                            'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
                        ];
                    }, $data_diskon->biaya_lain);
                    $total_biaya_lain = $biaya_lains;
                    $dataH->biaya_lain = json_encode($data_pendukung_lain);
                    $dataH->total_biaya_lain = $total_biaya_lain;
                } else {
                    $dataH->biaya_lain = null;
                    $dataH->total_biaya_lain = 0;
                }
                // END BIAYA LAIN
                //CUSTOM DISCOUNT
                $custom_disc = [];
                if (isset($data_diskon->custom_discount) && !empty($data_diskon->custom_discount)) {
                    $custom_disc = array_map(function ($disc) {
                        return (object) [
                            'deskripsi' => $disc->deskripsi,
                            'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                        ];
                    }, $data_diskon->custom_discount);
                    $dataH->custom_discount = json_encode($custom_disc);
                } else {
                    $dataH->custom_discount = null;
                }
                // END CUSTOM DISCOUNT

                foreach ($payload->data_pendukung as $i => $item) {
                    $param = $item->parameter;
                    // dd($item);
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                    }

                    $harga_db = [];
                    $volume_db = [];
                    foreach ($parameter as $param_) {
                        $ambil_data = HargaParameter::where('id_kategori', $kategori)
                            ->where('nama_parameter', $param_)
                            ->orderBy('id', 'ASC')
                            ->get();


                        $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                            return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                        }) ?? $ambil_data->first();

                        $harga_db[] = $cek_harga_parameter->harga ?? 0;
                        $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                        //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
                        // }) ?? $ambil_data->first();

                        // // fix bug
                        // if ($cek_harga_parameter) {
                        //     $harga_db[] = $cek_harga_parameter->harga;
                        //     $volume_db[] = $cek_harga_parameter->volume;
                        // } else {
                        //     $harga_db[] = 0;
                        //     $volume_db[] = 0;
                        // }
                    }

                    $harga_pertitik = (object) [
                        'volume' => array_sum($volume_db),
                        'total_harga' => array_sum($harga_db)
                    ];

                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item->jumlah_titik;

                    $temp_prearasi = [];
                    if ($item->biaya_preparasi != null || $item->biaya_preparasi != "") {
                        foreach ($item->biaya_preparasi as $pre) {
                            if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                                $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
                            // HARGA PREPARASI UNUSED
                            // if($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") $harga_preparasi += floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan));
                        }
                    }
                    $biaya_preparasi = $temp_prearasi;

                    $data_sampling[$i] = [
                        'kategori_1' => $item->kategori_1,
                        'kategori_2' => $item->kategori_2,
                        'penamaan_titik' => $item->penamaan_titik,
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol,
                        'periode' => $item->periode,
                        'biaya_preparasi' => $biaya_preparasi
                    ];

                    isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

                    foreach ($item->periode as $key => $v) {
                        array_push($period, $v);
                    }

                    array_push($data_pendukung_h, $data_sampling[$i]);
                }

                // dd($data_pendukung_h);
                $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);
                // dd($data_pendukung_h);
                $period_pendukung = [];

                for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {

                    $data_lain = $data_pendukung_lain;

                    $kalkulasi = 0;
                    $harga_transportasi = 0;
                    $harga_perdiem = 0;
                    $harga_perdiem24 = 0;

                    if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi))
                        $harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                    if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem))
                        $harga_perdiem = $data_wilayah->wilayah_data[$c]->harga_perdiem;
                    if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem24))
                        $harga_perdiem24 = $data_wilayah->wilayah_data[$c]->harga_perdiem24;

                    if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem))
                        $kalkulasi = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;
                    $data_lain_arr = [];
                    foreach ($data_lain as $dl) {
                        array_push($data_lain_arr, (array) $dl);
                    }
                    // dd($data_wilayah->wilayah_data[$c]);
                    array_push($data_transport_h, (object) ['status_sampling' => $data_wilayah->wilayah_data[$c]->status_sampling, 'jumlah_transportasi' => $data_wilayah->wilayah_data[$c]->transportasi, 'jumlah_orang_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang, 'jumlah_hari_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari, 'jumlah_orang_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam, 'jumlah_hari_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam, 'biaya_lain' => $data_lain_arr, 'harga_transportasi' => $harga_transportasi, 'harga_perdiem' => $harga_perdiem, 'harga_perdiem24' => $harga_perdiem24, 'periode' => $data_wilayah->wilayah_data[$c]->periode, 'kalkulasi_by_sistem' => $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem]);

                    foreach ($data_wilayah->wilayah_data[$c]->periode as $key => $v) {
                        array_push($period_pendukung, $v);
                    }
                }

                // dd($data_transport_h);

                $dataH->data_pendukung_lain = json_encode($data_transport_h);
                // $status_sampling = array_map(function ($wilayah) {
                //     return $wilayah->status_sampling;
                // }, $data_wilayah->wilayah_data);
                // if (count($status_sampling) == 1)
                //     $dataH->status_sampling = implode(',', $status_sampling);
                isset($syarat_ketentuan) ? $dataH->syarat_ketentuan = json_encode($syarat_ketentuan) : $dataH->syarat_ketentuan = null;
                isset($keterangan_tambahan) ? $dataH->keterangan_tambahan = json_encode($keterangan_tambahan) : $dataH->keterangan_tambahan = null;
                isset($data_diskon->diluar_pajak) ? $dataH->diluar_pajak = json_encode($data_diskon->diluar_pajak) : $dataH->diluar_pajak = null;

                ($data_lama != null) ? $dataH->data_lama = json_encode($data_lama) : $dataH->data_lama = null;
                $dataH->created_by = $dataOld->created_by;
                $dataH->created_at = $dataOld->created_at;
                $dataH->save();

                // END HEADER DATA
                // START DETAIL DATA
                $data_detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)
                    ->select('id')
                    ->get();
                // dd($data_detail);

                // Period Data Pendukung
                $period_pendukung = array_values(array_unique($period_pendukung));

                $period = [];
                foreach ($data_pendukung as $key => $v) {
                    foreach ($v->periode as $a) {
                        array_push($period, $a);
                    }
                }

                $period = array_values(array_unique($period));

                $diff1 = array_values(array_diff($period, $period_pendukung));

                if (count($diff1) != 0) {
                    DB::rollBack();
                    $periode = [];
                    foreach ($diff1 as $k => $val) {
                        array_push($periode, self::tanggal_indonesia($val, 'period'));
                    }

                    return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Wilayah Tidak Ada Di Periode Data Pendukung..! '], 500);
                }

                $diff2 = array_values(array_diff($period_pendukung, $period));

                if (count($diff2) != 0) {
                    DB::rollBack();
                    $periode = [];
                    foreach ($diff2 as $k => $val) {
                        array_push($periode, self::tanggal_indonesia($val, 'period'));
                    }

                    return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Pendukung Tidak Ada Di Periode Data Wilayah..! '], 500);
                }

                $num = count($period);
                $detail = count($data_detail);
                if ($num > 12) {
                    DB::rollBack();
                    return response()->json(['message' => 'Periode Kontrak Lebih Dari 12 Bulan'], 201);
                }
                $id_det = [];
                $tgl = null;
                // =========================================================================
                // STEP 1: Bongkar & Petakan Nomor Titik dari Kontrak Lama (dataOld)
                // =========================================================================
                $titikGroupMapping = [];
                $lastTitikNumber = 0;
                $periodeOld = [];
                // Ambil semua detail dari kontrak LAMA
                $detailsFromOldContract = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();

                foreach ($detailsFromOldContract as $detail) {
                    // Decode data pendukung sampling dari JSON
                    $dataPendukungSampling = json_decode($detail->data_pendukung_sampling, true);
                    // Iterasi setiap data sampling di dalamnya
                    if (is_array($dataPendukungSampling)) {
                        foreach ($dataPendukungSampling as $samplingData) {
                            // Ambil data sampling yang relevan
                            $dataSamplingList = $samplingData['data_sampling'] ?? [];
                            if (!is_array($dataSamplingList)) {
                                $dataSamplingList = json_decode($dataSamplingList, true);
                            }
                            foreach ($dataSamplingList as $item) {
                                $periodeOld[] = $samplingData['periode_kontrak'];

                                // Buat unique key berdasarkan kategori, ini patokan kita!
                                $groupKey = $samplingData['periode_kontrak'] . ';' . $item['kategori_1'] . ';' . $item['kategori_2'] . ';' . json_encode($item['regulasi']) . ';' . json_encode($item['parameter']);
                                if (!isset($titikGroupMapping[$groupKey])) {
                                    $titikGroupMapping[$groupKey] = [];
                                }

                                // Petakan NAMA titik ke NOMOR titik
                                if (isset($item['penamaan_titik']) && is_array($item['penamaan_titik'])) {
                                    foreach ($item['penamaan_titik'] as $titikObject) {
                                        $titikObject = (array) $titikObject;
                                        $nomor = key($titikObject);
                                        $nama = current($titikObject);

                                        // Mapping: 'Outlet' => '001'
                                        $titikGroupMapping[$groupKey][$nomor] = $nama;

                                        // Cari nomor titik tertinggi untuk counter
                                        if (intval($nomor) > $lastTitikNumber) {
                                            $lastTitikNumber = intval($nomor);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // dump($titikGroupMapping);

                // Counter untuk titik BARU akan dimulai dari nomor tertinggi + 1
                $globalTitikCounter = $lastTitikNumber + 1;
                $allDetailQuotOld = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();
                // foreach ($allDetailQuotOld as $detail) {
                //     dump(json_decode($detail->data_pendukung_sampling));
                // }
                $id_order_header = null;
                $oldPeriod = $allDetailQuotOld->pluck('periode_kontrak')->toArray();
                $alreadyOrdered = false;
                if ($dataOld->data_lama !== null) {
                    $dataLamaDecoded = json_decode($dataOld->data_lama, true);
                    if (isset($dataLamaDecoded['id_order'])) {
                        $id_order_header = $dataLamaDecoded['id_order'];
                        $alreadyOrdered = true;
                    }
                }

                $biggestNumberOfSampel = 0;

                if ($alreadyOrdered) {
                    $OrderDetails = OrderDetail::where('id_order_header', $id_order_header)->get();
                    foreach ($OrderDetails as $detail) {
                        $no_sampel = explode('/', $detail->no_sampel)[1];
                        if ($no_sampel > $biggestNumberOfSampel) {
                            $biggestNumberOfSampel = $no_sampel;
                        }
                    }
                    foreach ($allDetailQuotOld as $detail) {
                        $oldData = json_decode($detail->data_pendukung_sampling, true);
                        $first = reset($oldData)['data_sampling'];
                        foreach ($first as $item) {
                            foreach ($item['penamaan_titik'] as $titik) {
                                $key = array_key_first($titik);
                                $number = (int) $key;
                                if ($number > $biggestNumberOfSampel) {
                                    $biggestNumberOfSampel = $number;
                                }
                            }
                        }
                    }
                }
                $biggestNumberOfSampel++;
                $diffPeriod = array_diff($period, $oldPeriod);
                $diffOldPeriod = array_diff($oldPeriod, $period);
                foreach ($period as $k => $per) {
                    dump($per);
                    if (!isset($data_detail[$k]->id)) {
                        $id_detail = '';
                    } else {
                        $id_detail = $data_detail[$k]->id;
                        if ($num < $detail) {
                            array_push($id_det, $id_detail);
                        }
                    }
                    
                    $cek = QuotationKontrakD::where('id', $id_detail)->first();

                    if (!is_null($cek)) {
                        $dataD = QuotationKontrakD::where('id', $data_detail[$k]->id)->first();
                    } else {
                        $dataD = new QuotationKontrakD;
                        $dataD->id_request_quotation_kontrak_h = $dataH->id;
                    }

                    $checkOldQt = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->where('periode_kontrak', $per)->first();
                    $first = [];
                    $tempUsedOldData = [];
                    $isMutated = false;
                    if ($checkOldQt) {
                        $oldData = json_decode($checkOldQt->data_pendukung_sampling, true);
                        $first = reset($oldData)['data_sampling'];
                    }
                    $checkOldQtRemaining = [];
                    if($alreadyOrdered){
                        $checkOldQtRemaining = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)
                            ->whereIn('periode_kontrak', $diffOldPeriod)
                            ->get();
                    } 

                    $data_sampling = [];
                    $datas = [];
                    $harga_total = 0;
                    $harga_air = 0;
                    $harga_udara = 0;
                    $harga_emisi = 0;
                    $harga_padatan = 0;
                    $harga_swab_test = 0;
                    $harga_tanah = 0;
                    $harga_pangan = 0;
                    $grand_total = 0;
                    $total_diskon = 0;
                    $desc_preparasi = [];
                    $harga_preparasi = 0;

                    $j = $k + 1;
                    $n = 0;
                    // dump($per);
                    foreach ($data_pendukung as $m => $xyz) {
                        if (in_array($per, $xyz->periode)) {
                            $param = [];
                            $regulasi = '';
                            if ($xyz->parameter != null)
                                $param = $xyz->parameter;
                            if ($xyz->regulasi != null)
                                $regulasi = $xyz->regulasi;

                            $exp = explode("-", $xyz->kategori_1);
                            $kategori = $exp[0];
                            $vol = 0;

                            // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                            $parameter = [];
                            $id_param = [];
                            foreach ($xyz->parameter as $va) {
                                $cek_par = DB::table('parameter')
                                    ->where('id', explode(';', $va)[0])->first();
                                array_push($parameter, $cek_par->nama_lab);
                                array_push($id_param, $cek_par->id);
                            }

                            $harga_db = [];
                            $volume_db = [];
                            foreach ($parameter as $ix => $param_) {

                                $ambil_data = HargaParameter::where('id_kategori', $kategori)
                                    ->where('nama_parameter', $param_)
                                    ->orderBy('id', 'ASC')
                                    ->get();
                                if (count($ambil_data) > 1) {
                                    foreach ($ambil_data as $xc => $zx) {
                                        // $zx->tgl_order $request->tgl_order
                                        if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
                                            array_push($harga_db, $zx->harga);
                                            array_push($volume_db, $zx->volume);
                                            break;
                                        }

                                        if ((count($ambil_data) - 1) == $xc) {
                                            $zx = $ambil_data[0];
                                            array_push($harga_db, $zx->harga);
                                            array_push($volume_db, $zx->volume);
                                            break;
                                        }
                                    }
                                } else if (count($ambil_data) == 1) {
                                    foreach ($ambil_data as $xc => $zx) {
                                        array_push($harga_db, $zx->harga);
                                        array_push($volume_db, $zx->volume);
                                        break;
                                    }
                                } else {
                                    array_push($harga_db, 0);
                                    array_push($volume_db, 0);
                                }
                            }

                            $vol_db = array_sum($volume_db);
                            $har_db = array_sum($harga_db);

                            $harga_pertitik = (object) [
                                'volume' => $vol_db,
                                'total_harga' => $har_db
                            ];

                            if ($harga_pertitik->volume != null)
                                $vol += floatval($harga_pertitik->volume);
                            if ($xyz->jumlah_titik == '') {
                                $reqtitik = 0;
                            } else {
                                $reqtitik = $xyz->jumlah_titik;
                            }

                            //============= BIAYA PREPARASI ==================
                            if ($xyz->biaya_preparasi != null || $xyz->biaya_preparasi != "") {
                                foreach ($xyz->biaya_preparasi as $pre) {
                                    if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
                                        $desc_preparasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
                                    if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
                                        $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                                }
                            }
                            // =========================================================================
                            // STEP 2: Terapkan Nomor Titik (Lama atau Baru)
                            // =========================================================================
                            $penamaan_titik_fixed = [];

                            $fullGroupKey = $per . ';' . $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);
                            $fallbackGroupKey = $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);

                            $pengurangan_periode_kontrak = array_values(array_diff($periodeOld, $xyz->selectedPeriode));
                            $penambahan_periode_kontrak = array_values(array_diff($xyz->selectedPeriode, $periodeOld));

                            $key_penambahan = array_search($per, $penambahan_periode_kontrak);
                            $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

                            // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak, $key_penambahan, $periode_pengganti);
                            $oldNumberMappingForGroup = [];
                            if (isset($titikGroupMapping[$fullGroupKey])) {
                                $oldNumberMappingForGroup = $titikGroupMapping[$fullGroupKey];
                            } else {
                                /* Base By Penamaan Titik
                                if (in_array($per, $periodeOld)) {
                                    $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($per) {
                                        return strpos($item, $per) !== false;
                                    });
                                } else {
                                    $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($periode_pengganti) {
                                        return strpos($item, $periode_pengganti) !== false;
                                    });
                                }
                                // dump($tempKeyGroup);

                                $foundKey = null;
                                foreach ($tempKeyGroup as $key) {
                                    $titikOld = $titikGroupMapping[$key];
                                    $foundNamaTitik = array_intersect($titikOld, $xyz->penamaan_titik);
                                    if (count($foundNamaTitik) > 0) {
                                        $foundKey = $key;
                                        break;
                                    }
                                }*/

                                $keyPeriod = in_array($per, $periodeOld) ? $per : $periode_pengganti;
                                $tempKeyGroup = array_filter(array_keys($titikGroupMapping), fn($key) => str_contains($key, $keyPeriod));

                                $foundKey = null;
                                foreach ($tempKeyGroup as $key) {
                                    if (count(array_intersect($titikGroupMapping[$key], $xyz->penamaan_titik)) > 0) {
                                        $foundKey = $key;
                                        // break;
                                    }
                                }

                                $oldNumberMappingForGroup = isset($titikGroupMapping[$foundKey]) ? $titikGroupMapping[$foundKey] : [];

                                /* Only By Period
                                dd($titikGroupMapping[$foundKey], $xyz->penamaan_titik);
                                $existingPeriods = array_map(function ($key) {
                                    return explode(';', $key)[0];
                                }, array_keys($titikGroupMapping));
                                if (!in_array($per, $existingPeriods)) {
                                    foreach (array_keys($titikGroupMapping) as $key) {
                                        $temp = explode(';', $key);
                                        $period = array_shift($temp);
                                        $partialKey = implode(';', $temp);

                                        if ($partialKey === $fallbackGroupKey) {
                                            $oldNumberMappingForGroup = $titikGroupMapping[$key];
                                            break;
                                        }
                                    }
                                }*/
                            }
                            // dd($oldNumberMappingForGroup);
                            // dump($oldNumberMappingForGroup, $titikGroupMapping);
                            $countOld = count($oldNumberMappingForGroup);
                            $countNew = count($xyz->penamaan_titik);

                            // dd($oldNumberMappingForGroup, $xyz->penamaan_titik);
                            // dump($oldNumberMappingForGroup, $xyz->penamaan_titik);
                            $isSameCount = $countOld == $countNew;
                            $oldKeys = array_keys($oldNumberMappingForGroup);
                            // dump($per, $isSameCount, $oldNumberMappingForGroup, $xyz->penamaan_titik);

                            if(!$checkOldQt && $checkOldQtRemaining->count() > 0){
                                $foundFromOldRemaining = false;
                                $matchedOldPenamaan = null;
                                // dump("parameter old");
                                foreach ($checkOldQtRemaining as $oldDetail) {
                                    $oldData = json_decode($oldDetail->data_pendukung_sampling, true);
                                    $oldSamplingList = reset($oldData)['data_sampling'] ?? [];
                                    // dump($oldSamplingList);
                                    foreach ($oldSamplingList as $oldSampling) {
                                        $kategori1Same = $oldSampling['kategori_1'] === $xyz->kategori_1;
                                        $kategori2Same = $oldSampling['kategori_2'] === $xyz->kategori_2;
                                        $regulasiSame = $oldSampling['regulasi'] === $xyz->regulasi;
                                        // dump($oldSampling['kategori_1'], $oldSampling['regulasi']);

                                        if ($kategori1Same && $kategori2Same && $regulasiSame) {
                                            $matchedOldPenamaan = $oldSampling['penamaan_titik'] ?? [];
                                            if (in_array($oldSampling, $tempUsedOldData, true)) {
                                                continue;
                                            }
                                            $tempUsedOldData[] = $oldSampling;

                                            $foundFromOldRemaining = $oldDetail->periode_kontrak;
                                        } 
                                    }
                                }
                                if ($matchedOldPenamaan !== null) {
                                    // $data_sampling[$index]['penamaan_titik'] = $matchedOldPenamaan;

                                    $diffOldPeriod = array_filter($diffOldPeriod, function ($periode) use ($foundFromOldRemaining) {
                                        return $periode !== $foundFromOldRemaining;
                                    });
                                    $mutationKey = array_keys(reset($matchedOldPenamaan));
                                    // dd($mutationKey);
                                    foreach ($xyz->penamaan_titik as $i => $pt) {
                                        $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
                                        if($alreadyOrdered){
                                            // dump('sudah Ordered');
                                            if($i < count($mutationKey)){
                                                $penamaan_titik_fixed[] = (object) [$mutationKey[$i] => $namaTitik];
                                            } else {
                                                $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                                $biggestNumberOfSampel++;
                                            }
                                        } else {
                                            // dump('Belum Ordered');
                                            $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                            $biggestNumberOfSampel++;
                                        }
                                    }

                                } else {
                                    foreach ($xyz->penamaan_titik as $i => $pt) {
                                        $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
                                        if($alreadyOrdered){
                                            dump('sudah Ordered');
                                            if($i < $countOld){
                                                $penamaan_titik_fixed[] = (object) [$oldKeys[$i] => $namaTitik];
                                            } else {
                                                $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                                $biggestNumberOfSampel++;
                                            }
                                        } else {
                                            // dump('Belum Ordered');
                                            $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                            $biggestNumberOfSampel++;
                                        }
                                    }
                                }
                                // dump('-----');
                            } else {
                                // kalau ada data lama yang sama periodenya
                                $foundOldPenamaanTitik = null;
                                if($checkOldQt){
                                    $dataSamplingOld = json_decode($checkOldQt->data_pendukung_sampling, true);
                                    foreach (reset($dataSamplingOld)['data_sampling'] as $oldSampling) {
                                        $kategori1Same = $oldSampling['kategori_1'] === $xyz->kategori_1;
                                        $kategori2Same = $oldSampling['kategori_2'] === $xyz->kategori_2;
                                        $regulasiSame = $oldSampling['regulasi'] === $xyz->regulasi;

                                        if ($kategori1Same && $kategori2Same && $regulasiSame) {
                                            if (in_array($oldSampling, $tempUsedOldData, true)) {
                                                continue;
                                            }

                                            $foundOldPenamaanTitik = $oldSampling['penamaan_titik'] ?? [];
                                            $tempUsedOldData[] = $oldSampling;
                                            break;
                                        } 
                                    }
                                }

                                $keysOldFoundPenamaanTitik = [];
                                
                                if($foundOldPenamaanTitik !== null){
                                    foreach ($foundOldPenamaanTitik as $item) {
                                        $keysOldFoundPenamaanTitik = array_merge(
                                            $keysOldFoundPenamaanTitik,
                                            array_keys($item)
                                        );
                                    }
                                }
                                
                                foreach ($xyz->penamaan_titik as $i => $pt) {
                                    $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
                                    if($alreadyOrdered && $foundOldPenamaanTitik !== null){
                                        // dump('sudah Ordered');
                                        // dump($i, $countOld, $oldKeys);
                                        if($i < count($keysOldFoundPenamaanTitik)){
                                            $penamaan_titik_fixed[] = (object) [$keysOldFoundPenamaanTitik[$i] => $namaTitik];
                                        } else {
                                            $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                            $biggestNumberOfSampel++;
                                        }
                                    } else {
                                        // dump('Belum Ordered');
                                        $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                                        $biggestNumberOfSampel++;
                                    }
                                }
                            }
                            

                            // foreach ($xyz->penamaan_titik as $i => $pt) {
                            //     $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
                            //     if($alreadyOrdered){
                            //         if (in_array($namaTitik, $oldNumberMappingForGroup) && $namaTitik != "") {
                            //             // dump($oldNumberMappingForGroup);
                            //             $nomor = array_search($namaTitik, $oldNumberMappingForGroup);
                            //             $penamaan_titik_fixed[] = (object) [$nomor => $namaTitik];
                            //         } else {
                            //             $nomorLama = null;

                            //             foreach ($titikGroupMapping as $key => $map) {
                            //                 if (strpos($key, $fallbackGroupKey) !== false && in_array($namaTitik, $map)) {
                            //                     $nomorLama = array_search($namaTitik, $map);
                            //                     if (!array_key_exists($nomorLama, $penamaan_titik_fixed)) {
                            //                         $nomorLama = null;
                            //                     }
                            //                     break;
                            //                 }
                            //             }
                            //             // dump($nomorLama);

                            //             if ($nomorLama) {
                            //                 $penamaan_titik_fixed[] = (object) [$nomorLama => $namaTitik];

                            //                 // Daftarin ke current group juga biar ke-cache
                            //                 $titikGroupMapping[$fullGroupKey][$nomorLama] = $namaTitik;
                            //             } else {
                            //                 $nomorBaru = sprintf('%03d', $globalTitikCounter);
                            //                 $penamaan_titik_fixed[] = (object) [$nomorBaru => $namaTitik];

                            //                 $titikGroupMapping[$fullGroupKey][$nomorBaru] = $namaTitik;
                            //                 $globalTitikCounter++;
                            //             }
                            //         }
                                    
                            //     } else {
                            //         $penamaan_titik_fixed[] = (object) [$biggestNumberOfSampel => $namaTitik];
                            //         $biggestNumberOfSampel++;
                            //     }
                            // }

                            // dump($penamaan_titik_fixed);

                            $data_sampling[$n++] = [
                                'kategori_1' => $xyz->kategori_1,
                                'kategori_2' => $xyz->kategori_2,
                                'regulasi' => $regulasi,
                                'parameter' => $param,
                                'jumlah_titik' => $xyz->jumlah_titik,
                                'penamaan_titik' => $penamaan_titik_fixed,
                                'total_parameter' => count($param),
                                'harga_satuan' => $harga_pertitik->total_harga,
                                'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                                'volume' => $vol,
                                'biaya_preparasi' => $desc_preparasi
                            ];


                            // dump($per);
                            // dump($penamaan_titik_fixed);

                            // kalkulasi harga parameter sesuai titik
                            switch ($kategori) {
                                case '1':
                                    $harga_air += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '4':
                                    $harga_udara += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '5':
                                    $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '6':
                                    $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '7':
                                    $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '8':
                                    $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                                case '9':
                                    $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
                                    break;
                            }
                        }
                    }


                    // dump($data_sampling);
                    // foreach ($data_sampling as $index => $newItem) {
                    //     $checkOldQt = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->where('periode_kontrak', $per)->first();
                    //     $first = [];
                    //     $isMutated = false;
                    //     if ($checkOldQt) {
                    //         // dump('ada old data sesuai periode', $per);
                    //         $oldData = json_decode($checkOldQt->data_pendukung_sampling, true);
                    //         $first = reset($oldData)['data_sampling'];
                    //     } else {
                    //         if ($alreadyOrdered) {
                    //             $checkOldQtRemaining = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)
                    //                 ->whereIn('periode_kontrak', $diffOldPeriod)
                    //                 ->get();
                    //             // dump('masuk else periode', $per);

                    //             if ($checkOldQtRemaining->count() > 0) {
                    //                 // dump('ada data diff periode', $per);

                    //                 $foundFromOldRemaining = false;
                    //                 $matchedOldPenamaan = null;
                    //                 foreach ($checkOldQtRemaining as $oldDetail) {
                    //                     $oldData = json_decode($oldDetail->data_pendukung_sampling, true);
                    //                     $oldSamplingList = reset($oldData)['data_sampling'] ?? [];
                    //                     foreach ($oldSamplingList as $oldSampling) {
                    //                         if (
                    //                             ($oldSampling['kategori_1'] ?? null) === ($newItem['kategori_1'] ?? null) &&
                    //                             ($oldSampling['kategori_2'] ?? null) === ($newItem['kategori_2'] ?? null)
                    //                         ) {
                    //                             // Match found
                    //                             $matchedOldPenamaan = $oldSampling['penamaan_titik'] ?? [];
                    //                             $foundFromOldRemaining = $oldDetail->periode_kontrak;
                    //                             // dump('Pengecekan match lama');
                    //                             // dump($matchedOldPenamaan, $foundFromOldRemaining);
                    //                             break 2;
                    //                         }
                    //                     }
                    //                 }

                    //                 if ($matchedOldPenamaan !== null) {
                    //                     // Langsung assign titik dari match lama
                    //                     $data_sampling[$index]['penamaan_titik'] = $matchedOldPenamaan;

                    //                     $isMutated = true;
                    //                     // Buang periode yang udah kepake dari diffOldPeriod
                    //                     $diffOldPeriod = array_filter($diffOldPeriod, function ($periode) use ($foundFromOldRemaining) {
                    //                         return $periode !== $foundFromOldRemaining;
                    //                     });

                    //                     // Skip ke iterasi berikutnya (ga pake logic bawah)
                    //                     continue;
                    //                 }

                    //             }
                    //         }
                    //     }

                    //     if (!$isMutated) {
                    //         // dump('tidak mutated');
                    //         $diffRegulasi = false;
                    //         $diffKategori = false;
                    //         $diffSubKategori = false;
                    //         $diffParameter = false;
                    //         $oldItem = $first[$index] ?? [];

                    //         // Cek kategori_1
                    //         if ((isset($oldItem['kategori_1']) && $newItem['kategori_1'] !== $oldItem['kategori_1'] ?? null)) {
                    //             $diffKategori = true;
                    //         }

                    //         // Cek kategori_2
                    //         if ((isset($oldItem['kategori_2']) && $newItem['kategori_2'] !== $oldItem['kategori_2'] ?? null)) {
                    //             $diffSubKategori = true;
                    //         }

                    //         // Cek regulasi
                    //         if ((isset($oldItem['regulasi']) && json_encode($newItem['regulasi']) !== json_encode($oldItem['regulasi'] ?? []))) {
                    //             $diffRegulasi = true;
                    //         }

                    //         // Cek parameter (urutan juga dibandingkan)
                    //         if ((isset($oldItem['parameter']) && json_encode($newItem['parameter']) !== json_encode($oldItem['parameter'] ?? []))) {
                    //             $diffParameter = true;
                    //         }

                    //         $penamaanBaru = array_map(function ($obj) {
                    //             return (array) $obj;
                    //         }, $newItem['penamaan_titik']);

                    //         $penamaanLama = $oldItem['penamaan_titik'] ?? [];

                    //         if (!$alreadyOrdered) {

                    //             // dump('tidak pernah Order');
                    //             $penamaanFix = [];

                    //             foreach ($penamaanBaru as $titikBaru) {
                    //                 $valBaru = array_values($titikBaru)[0];

                    //                 $biggestNumberOfSampel++;
                    //                 $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                    //                 $penamaanFix[] = [$newKey => $valBaru];
                    //             }

                    //             $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                    //         } else {
                    //             // dump('sudah pernah Order');
                    //             if ($diffKategori) {
                    //                 $penamaanFix = [];

                    //                 foreach ($penamaanBaru as $titikBaru) {
                    //                     $valBaru = array_values($titikBaru)[0];

                    //                     // Selalu generate nomor baru
                    //                     $biggestNumberOfSampel++;
                    //                     $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                    //                     $penamaanFix[] = [$newKey => $valBaru];
                    //                 }

                    //                 $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                    //             } else {
                    //                 $penamaanFix = [];
                    //                 $used = [];

                    //                 // Loop data baru
                    //                 foreach ($penamaanBaru as $titikBaru) {
                    //                     $valBaru = array_values($titikBaru)[0];
                    //                     $found = false;

                    //                     // Coba cari val yang sama di penamaan lama
                    //                     foreach ($penamaanLama as $titikLama) {
                    //                         $keyLama = array_key_first($titikLama);
                    //                         $valLama = array_values($titikLama)[0];

                    //                         if ((string) $valBaru === (string) $valLama && !in_array($keyLama, $used)) {
                    //                             $penamaanFix[] = [$keyLama => $valBaru];
                    //                             $used[] = $keyLama;
                    //                             $found = true;
                    //                             break;
                    //                         }
                    //                     }

                    //                     // Kalau tidak ditemukan di data lama, pakai nomor baru
                    //                     if (!$found) {
                    //                         $biggestNumberOfSampel++;
                    //                         $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
                    //                         $penamaanFix[] = [$newKey => $valBaru];
                    //                         $used[] = $newKey;
                    //                     }
                    //                 }

                    //                 // Set hasil akhirnya
                    //                 $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
                    //             }
                    //         }
                    //     }
                    // }
                    // dump($data_sampling);
                    // if($per == '2026-06' || $per == '2026-07'){
                    //         dump('setelah');
                    //         dump($data_sampling);
                    //     }

                    $datas[$j] = [
                        'periode_kontrak' => $per,
                        'data_sampling' => array_values($data_sampling)
                        // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
                    ];

                    $dataD->periode_kontrak = $per;
                    $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

                    $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
                    // end data sampling
                    $dataD->harga_air = $harga_air;
                    $dataD->harga_udara = $harga_udara;
                    $dataD->harga_emisi = $harga_emisi;
                    $dataD->harga_padatan = $harga_padatan;
                    $dataD->harga_swab_test = $harga_swab_test;
                    $dataD->harga_tanah = $harga_tanah;

                    // kalkulasi harga
                    $expOp = explode("-", $data_wilayah->wilayah);
                    $id_wilayah = $expOp[0];
                    $cekOperasional = HargaTransportasi::where('is_active', true)->where('id', $id_wilayah)->first();

                    // START FOR
                    $disc_transport = 0;
                    $disc_perdiem = 0;
                    $disc_perdiem_24 = 0;

                    $harga_transport = 0;
                    $jam = 0;
                    $transport = 0;
                    $perdiem = 0;

                    $data_lain = $data_pendukung_lain;
                    $biaya_lain = 0;

                    for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {
                        if (in_array($per, $data_wilayah->wilayah_data[$c]->periode)) {

                            // Menjumlahkan total % discount transport kedalam variable
                            if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD')
                                $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi;
                            // dd($data_wilayah->status_Wilayah);
                            if ($data_wilayah->status_Wilayah == 'DALAM KOTA') {
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

                                    $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                    $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                        $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                    } else {
                                        $dataD->jumlah_orang_24jam = null;
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
                                        $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        $dataD->jumlah_hari_24jam = 0;
                                    }

                                    if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem)) {
                                        $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true' ? $dataD->kalkulasi_by_sistem = 'on' : $dataD->kalkulasi_by_sistem = 'off';
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {
                                        $dataD->harga_transportasi = $cekOperasional->transportasi;
                                        $dataD->harga_transportasi_total = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);

                                        $dataD->harga_personil = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang);
                                        $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                        }

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                        }

                                        $transport = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);
                                        $perdiem = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '')
                                            $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        // IF NOT CALCULATE BY SYSTEM
                                        // JUMLAH TRANSPORTASI
                                        // dd($data_wilayah->wilayah_data);
                                        isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
                                        // JUMLAH ORANG PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
                                        // JUMLAH HARI PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
                                        // JUMLAH ORANG 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
                                        // JUMLAH HARI 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
                                        // HARGA SATUAN TRANSPORTASI
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
                                            $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
                                            $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi);
                                        // HARGA SATUAN PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
                                        // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
                                            $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_personil);
                                        // HARGA 24 JAM PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
                                        // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
                                            $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil);

                                        // PERDIEM, JAM, TRANSPORT
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
                                            $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
                                            $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
                                            $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
                                    }
                                } else {
                                    $dataD->transportasi = null;
                                    $dataD->perdiem_jumlah_orang = null;
                                    $dataD->perdiem_jumlah_hari = null;
                                    $dataD->jumlah_orang_24jam = null;
                                    $dataD->jumlah_hari_24jam = null;

                                    $dataD->harga_transportasi = 0;
                                    $dataD->harga_transportasi_total = null;

                                    $dataD->harga_personil = 0;
                                    $dataD->harga_perdiem_personil_total = null;

                                    $dataD->harga_24jam_personil = 0;
                                    $dataD->harga_24jam_personil_total = null;
                                }

                                $harga_tiket = 0;
                                $harga_transportasi_darat = 0;
                                $harga_penginapan = 0;
                            } else {
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

                                    $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                    $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                    if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                        $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                    } else {
                                        $dataD->jumlah_orang_24jam = null;
                                    }

                                    if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
                                        $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                    } else {
                                        $dataD->jumlah_hari_24jam = 0;
                                    }
                                }

                                $harga_tiket = 0;
                                $harga_transportasi_darat = 0;
                                $harga_penginapan = 0;

                                // dd($data_wilayah->wilayah_data);
                                if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
                                    //hitung harga tiket perjalanan
                                    $dataD->kalkulasi_by_sistem = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;

                                    if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {

                                        $harga_tiket = $cekOperasional->tiket * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
                                        $harga_transportasi_darat = $cekOperasional->transportasi;
                                        $harga_penginapan = $cekOperasional->penginapan;
                                        $dataD->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
                                        $dataD->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;

                                        $dataD->harga_personil = $cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;

                                        $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
                                        }

                                        if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
                                            $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                            $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
                                        }

                                        $transport = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;
                                        $perdiem = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
                                    } else {
                                        // IF NOT CALCULATE BY SYSTEM
                                        // JUMLAH TRANSPORTASI
                                        isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
                                        // JUMLAH ORANG PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
                                        // JUMLAH HARI PERDIEM
                                        isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
                                        // JUMLAH ORANG 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
                                        // JUMLAH HARI 24 JAM
                                        isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
                                        // HARGA SATUAN TRANSPORTASI
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
                                            $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
                                            $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi_total);
                                        // HARGA SATUAN PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
                                        // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
                                            $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total);
                                        // HARGA 24 JAM PERSONIL
                                        isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
                                        // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
                                            $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total);

                                        // PERDIEM, JAM, TRANSPORT
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
                                            $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
                                            $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
                                        if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
                                            $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
                                    }
                                } else {
                                    $dataD->transportasi = null;
                                    $dataD->perdiem_jumlah_orang = null;
                                    $dataD->perdiem_jumlah_hari = null;
                                    $dataD->jumlah_orang_24jam = null;
                                    $dataD->jumlah_hari_24jam = null;

                                    $dataD->harga_transportasi = 0;
                                    $dataD->harga_transportasi_total = null;

                                    $dataD->harga_personil = 0;
                                    $dataD->harga_perdiem_personil_total = null;

                                    $dataD->harga_24jam_personil = 0;
                                    $dataD->harga_24jam_personil_total = null;
                                }
                            }

                            $dataD->status_sampling = $data_wilayah->wilayah_data[$c]->status_sampling;
                        }
                    }

                    // =======================================================================DATA DISKON===========================================================================
                    // ==================================================DISKON ANALISA=============================================================================================
                    $isPeriodeDiskonExist = false;
                    $periodeNotExist = '';
                    $indexDataDiskon = 0;
                    if (count($data_diskon->discount_data) > 0) {
                        foreach ($data_diskon->discount_data as $d => $discount) {
                            if (in_array($per, $discount->periode)) {
                                $isPeriodeDiskonExist = true;
                                $indexDataDiskon = $d;
                                break;
                            }
                            if (!$isPeriodeDiskonExist && $d == count($data_diskon->discount_data) - 1) {
                                $periodeNotExist = $per;
                            }
                        }
                    }

                    if (!$isPeriodeDiskonExist) {
                        return response()->json(['message' => 'Periode ' . $periodeNotExist . ' tidak ditemukan pada group diskon', 'status' => '500'], 403);
                    }
                    // $indexDataDiskon == 1 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_transport) : '';
                    // dd($isPeriodeDiskonExist);

                    if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_air > 0) {
                        // $indexDataDiskon == 0 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_air) : '';
                        // dd($data_diskon->discount_data[$indexDataDiskon]->discount_non_air);
                        $dataD->discount_air = $data_diskon->discount_data[$indexDataDiskon]->discount_air;
                        $dataD->total_discount_air = ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

                        $harga_total += $harga_air - ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

                        $total_diskon += ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));
                        if (floatval(\str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air)) > 10) {
                            $message = $dataH->no_document . ' Discount Air melebihi 10%';
                            Notification::where('id', 19)
                                ->title('Peringatan.')
                                ->message($message)
                                ->url('/quote-request')
                                ->send();
                        }
                    } else {
                        $harga_total += $harga_air;
                        $dataD->discount_air = null;
                        $dataD->total_discount_air = 0;
                    }

                    if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_non_air > 0) {
                        $dataD->discount_non_air = $data_diskon->discount_data[$indexDataDiskon]->discount_non_air;
                        $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                        $dataD->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
                        $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
                        $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
                        $total_diskon += ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 10) {
                            $message = $dataH->no_document . ' Discount Non-Air melebihi 10%';
                            Notification::where('id', 19)
                                ->title('Peringatan.')
                                ->message($message)
                                ->url('/quote-request')
                                ->send();
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 0) {
                            $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
                            $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
                                $message = $dataH->no_document . ' Discount Udara melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $dataD->discount_udara = null;
                            $dataD->total_discount_udara = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 0) {
                            $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
                            $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
                                $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $dataD->discount_emisi = null;
                            $dataD->total_discount_emisi = 0;
                        }
                    } else {
                        $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
                        $dataD->discount_non_air = null;
                        $dataD->total_discount_non_air = '0.00';

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) == 0) {
                            $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
                            $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
                                $message = $dataH->no_document . ' Discount Udara melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $harga_total += $harga_udara;
                            $dataD->discount_udara = null;
                            $dataD->total_discount_udara = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) == 0) {
                            $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
                            $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
                            if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
                                $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
                                Notification::where('id', 19)
                                    ->title('Peringatan.')
                                    ->message($message)
                                    ->url('/quote-request')
                                    ->send();
                            }
                        } else {
                            $harga_total += $harga_emisi;
                            $dataD->discount_emisi = null;
                            $dataD->total_discount_emisi = 0;
                        }
                    }

                    // ========================================================END DISKON ANALISA========================================================================
                    // ====================================================DISKON TRANSPORTASI========================================================================================
                    $harga_total += $harga_pangan;
                    $transport_ = 0;
                    $perdiem_ = 0;
                    $jam_ = 0;

                    if ($isPeriodeDiskonExist) {
                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0) {
                            $dataD->discount_transport = $data_diskon->discount_data[$indexDataDiskon]->discount_transport;
                            $dataD->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            // Harga Total
                            // $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $transport_ = $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                        } else {
                            $dataD->discount_transport = null;
                            $dataD->total_discount_transport = 0;
                            $transport_ = $transport;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem) > 0) {
                            $dataD->discount_perdiem = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem;
                            $dataD->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            // $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $perdiem_ = $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                        } else {
                            $dataD->discount_perdiem = null;
                            $dataD->total_discount_perdiem = 0;
                            $perdiem_ = $perdiem;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam) > 0) {
                            $dataD->discount_perdiem_24jam = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam;
                            $dataD->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            // $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $jam_ = $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                        } else {
                            $dataD->discount_perdiem_24jam = null;
                            $dataD->total_discount_perdiem_24jam = 0;
                            $jam_ = $jam;
                        }
                    }

                    $harga_transport += ($transport_ + $perdiem_ + $jam_);
                    // ==================================================END DISKON TRANSPORTASI======================================================================================
                    // =======================================================DISKON GABUNGAN=========================================================================================
                    if ($isPeriodeDiskonExist) {
                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_gabungan) > 0) {
                            $dataD->discount_gabungan = $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan;
                            $dataD->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                            $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                            $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
                        } else {
                            $dataD->discount_gabungan = null;
                            $dataD->total_discount_gabungan = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_consultant) > 0) {
                            $dataD->discount_consultant = $data_diskon->discount_data[$indexDataDiskon]->discount_consultant;
                            $dataD->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                            $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                            $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
                        } else {
                            $dataD->discount_consultant = null;
                            $dataD->total_discount_consultant = 0;
                        }

                        // BIAYA LAIN
                        // $biaya_lain = 0;
                        if (isset($data_diskon->discount_data[$indexDataDiskon]->biaya_lains) && !empty($data_diskon->discount_data[$indexDataDiskon]->biaya_lains)) {
                            $data_lain = array_values(array_filter(array_map(function ($disc) use (&$biaya_lain) {
                                if ($disc->harga == 0)
                                    return null;
                                $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga));
                                return (object) [
                                    'deskripsi' => $disc->deskripsi,
                                    'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
                                ];
                            }, $data_diskon->discount_data[$indexDataDiskon]->biaya_lains)));

                            // $grand_total += $biaya_lain;
                            // $harga_total += $biaya_lain;
                            $dataD->biaya_lain = count($data_lain) > 0 ? json_encode($data_lain) : null;
                            $dataD->total_biaya_lain = $biaya_lain;
                        } else {
                            $dataD->biaya_lain = null;
                            $dataD->total_biaya_lain = 0;
                        }

                        // ====================================================END BIAYA LAIN=======================================================================================


                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_group) > 0) {
                            $totalTransport = $harga_transport;
                            if (isset($payload->data_diskon->diluar_pajak)) {

                                if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
                                    $totalTransport -= $transport_;
                                }

                                if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
                                    $totalTransport -= $perdiem_;
                                }

                                if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
                                    $totalTransport -= $jam_;
                                }
                            }
                            // if($per == '2025-02') dd($harga_total + $totalTransport);
                            $dataD->discount_group = $data_diskon->discount_data[$indexDataDiskon]->discount_group;
                            $diskon_group = ((($harga_total + $totalTransport) - $biaya_lain) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_group));
                            $dataD->total_discount_group = $diskon_group;
                            $total_diskon += $diskon_group;
                            $harga_total = $harga_total - $diskon_group;
                        } else {
                            $dataD->discount_group = null;
                            $dataD->total_discount_group = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen) > 0) {
                            $dataD->cash_discount_persen = $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen;
                            $dataD->total_cash_discount_persen = (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                            $total_diskon += (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                            $harga_total = $harga_total - (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
                        } else {
                            $dataD->cash_discount_persen = null;
                            $dataD->total_cash_discount_persen = 0;
                        }

                        if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount) > 0) {
                            $harga_total = $harga_total - floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                            $dataD->cash_discount = floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                            $total_diskon += floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
                        } else {

                            $dataD->cash_discount = floatval(0);
                        }


                        // CUSTOM DISKON
                        if (isset($data_diskon->discount_data[$indexDataDiskon]->custom_discounts) && !empty($data_diskon->discount_data[$indexDataDiskon]->custom_discounts)) {
                            $custom_disc = array_values(array_filter(array_map(function ($disc) {
                                if ($disc->discount == 0)
                                    return null; // Tidak mengembalikan apa-apa jika discount = 0
                                return (object) [
                                    'deskripsi' => $disc->deskripsi,
                                    'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
                                ];
                            }, $data_diskon->discount_data[$indexDataDiskon]->custom_discounts)));

                            $harga_disc = 0;
                            foreach ($data_diskon->discount_data[$indexDataDiskon]->custom_discounts as $disc) {
                                $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
                            }

                            $total_diskon += $harga_disc;
                            $harga_total -= $harga_disc;
                            $dataD->custom_discount = count($custom_disc) > 0 ? json_encode($custom_disc) : null;
                            $dataD->total_custom_discount = $harga_disc;
                        } else {
                            $dataD->custom_discount = null;
                            $dataD->total_custom_discount = 0;
                        }
                        // ====================================================END CUSTOM DISKON=======================================================================================
                    }

                    //============= BIAYA PREPARASI
                    // dd($desc_preparasi);
                    $dataD->biaya_preparasi = json_encode($desc_preparasi);
                    $dataD->total_biaya_preparasi = $harga_preparasi;
                    $grand_total += $harga_preparasi;
                    $harga_total += $harga_preparasi;
                    // dd($grand_total);

                    //jika Transportasi di luar pajak
                    $biaya_akhir = 0;
                    $biaya_diluar_pajak = 0;
                    $txt = [];

                    if (isset($payload->data_diskon->diluar_pajak)) {
                        if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
                            $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
                            // $harga_total += $transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport);
                            $biaya_akhir += $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
                            $biaya_diluar_pajak += $transport;
                        } else {
                            $grand_total += $transport;
                            $harga_total += $transport_;
                        }

                        if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
                            $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
                            // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem);
                            $biaya_akhir += $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
                            $biaya_diluar_pajak += $perdiem;
                        } else {
                            $grand_total += $perdiem;
                            $harga_total += $perdiem_;
                        }

                        if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
                            $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
                            // $harga_total += $jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam);
                            $biaya_akhir += $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
                            $biaya_diluar_pajak += $jam;
                        } else {
                            $grand_total += $jam;
                            $harga_total += $jam_;
                        }

                        if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
                            $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
                            $biaya_akhir += $biaya_lain;
                            $biaya_diluar_pajak += $biaya_lain;
                        } else {
                            $grand_total += $biaya_lain;
                            $harga_total += $biaya_lain;
                        }
                    }

                    //Grand total sebelum kena diskon
                    $dataD->grand_total = $grand_total;
                    $dataD->total_dpp = $harga_total;

                    if (floatval($data_diskon->ppn) >= 0) {
                        $dataD->ppn = $data_diskon->ppn;
                        $dataD->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
                        $piutang = $harga_total + ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
                    }

                    if (floatval($data_diskon->pph) >= 0) {
                        $dataD->pph = $data_diskon->pph;
                        $dataD->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
                        $piutang = $piutang - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
                    }

                    $diluar_pajak = ['select' => $txt, 'body' => []];

                    if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
                        foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

                            $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                            $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
                        }
                        $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
                    }

                    //biaya di luar pajak
                    $dataD->biaya_di_luar_pajak = json_encode($diluar_pajak);
                    $dataD->total_biaya_di_luar_pajak = $biaya_diluar_pajak;

                    $dataD->piutang = $piutang;
                    $dataD->total_discount = $total_diskon;
                    $biaya_akhir += $piutang;

                    $dataD->biaya_akhir = $biaya_akhir;

                    //==========================END BIAYA DI LUAR PAJAK======================================
                    $dataD->save();
                }

                // ----------------- Start Delete If Periode Not Exist In Current Quotation ---------------------- //
                $deleted = QuotationKontrakD::whereNotIn('periode_kontrak', $period)->where('id_request_quotation_kontrak_h', $dataH->id)->delete();
                // ----------------- End Delete If Periode Not Exist In Current Quotation   --------------------- //

                // SUM DATA DETAIL FOR TOTAL HEADER
                $Dd = DB::select(" SELECT SUM(harga_air) as harga_air, SUM(harga_udara) as harga_udara,
                                            SUM(harga_emisi) as harga_emisi,
                                            SUM(harga_padatan) as harga_padatan,
                                            SUM(harga_swab_test) as harga_swab_test,
                                            SUM(harga_tanah) as harga_tanah,
                                            SUM(transportasi) as transportasi,
                                            SUM(perdiem_jumlah_orang) as perdiem_jumlah_orang,
                                            SUM(perdiem_jumlah_hari) as perdiem_jumlah_hari,
                                            SUM(jumlah_orang_24jam) as jumlah_orang_24jam,
                                            SUM(jumlah_hari_24jam) as jumlah_hari_24jam,
                                            SUM(harga_transportasi) as harga_transportasi,
                                            SUM(harga_transportasi_total) as harga_transportasi_total,
                                            SUM(harga_personil) as harga_personil,
                                            SUM(harga_perdiem_personil_total) as harga_perdiem_personil_total,
                                            SUM(harga_24jam_personil) as harga_24jam_personil,
                                            SUM(harga_24jam_personil_total) as harga_24jam_personil_total,
                                            SUM(discount_air) as discount_air,
                                            SUM(total_discount_air) as total_discount_air,
                                            SUM(discount_non_air) as discount_non_air,
                                            SUM(total_discount_non_air) as total_discount_non_air,
                                            SUM(discount_udara) as discount_udara,
                                            SUM(total_discount_udara) as total_discount_udara,
                                            SUM(discount_emisi) as discount_emisi,
                                            SUM(total_discount_emisi) as total_discount_emisi,
                                            SUM(discount_gabungan) as discount_gabungan,
                                            SUM(total_discount_gabungan) as total_discount_gabungan,
                                            SUM(cash_discount_persen) as cash_discount_persen,
                                            SUM(total_cash_discount_persen) as total_cash_discount_persen,
                                            SUM(discount_consultant) as discount_consultant,
                                            SUM(discount_group) as discount_group,
                                            SUM(total_discount_group) as total_discount_group,
                                            SUM(total_discount_consultant) as total_discount_consultant,
                                            SUM(cash_discount) as cash_discount,
                                            SUM(total_custom_discount) as total_custom_discount,
                                            SUM(discount_transport) as discount_transport,
                                            SUM(total_discount_transport) as total_discount_transport,
                                            SUM(discount_perdiem) as discount_perdiem,
                                            SUM(total_discount_perdiem) as total_discount_perdiem,
                                            SUM(discount_perdiem_24jam) as discount_perdiem_24jam,
                                            SUM(total_discount_perdiem_24jam) as total_discount_perdiem_24jam,
                                            SUM(ppn) as ppn,
                                            SUM(total_ppn) as total_ppn,
                                            SUM(total_pph) as total_pph,
                                            SUM(pph) as pph,
                                            SUM(biaya_lain) as biaya_lain,
                                            SUM(total_biaya_lain) as total_biaya_lain,
                                            SUM(total_biaya_preparasi) as total_biaya_preparasi,
                                            SUM(biaya_di_luar_pajak) as biaya_di_luar_pajak,
                                            SUM(total_biaya_di_luar_pajak) as total_biaya_di_luar_pajak,
                                            SUM(grand_total) as grand_total,
                                            SUM(total_discount) as total_discount,
                                            SUM(total_dpp) as total_dpp,
                                            SUM(piutang) as piutang,
                                            SUM(biaya_akhir) as biaya_akhir FROM request_quotation_kontrak_D WHERE id_request_quotation_kontrak_h = '$dataH->id' GROUP BY id_request_quotation_kontrak_h ");
                // UPDATE HEADER DATA
                // dd($Dd);
                $editH = QuotationKontrakH::where('id', $dataH->id)
                    ->first();
                $editH->syarat_ketentuan = json_encode($payload->syarat_ketentuan);
                // dd($syarat);
                if (isset($payload->keterangan_tambahan) && $payload->keterangan_tambahan != null)
                    $editH->keterangan_tambahan = json_encode($payload->keterangan_tambahan);
                if ($tgl == null || !isset($tgl)) {
                    $tgl = date('Y-m-d', strtotime("+30 days", strtotime(DATE('Y-m-d'))));
                }
                // dd($Dd);
                $editH->expired = $tgl;
                $editH->total_harga_air = $Dd[0]->harga_air;
                $editH->total_harga_udara = $Dd[0]->harga_udara;
                $editH->total_harga_emisi = $Dd[0]->harga_emisi;
                $editH->total_harga_padatan = $Dd[0]->harga_padatan;
                $editH->total_harga_swab_test = $Dd[0]->harga_swab_test;
                $editH->total_harga_tanah = $Dd[0]->harga_tanah;
                $editH->transportasi = $Dd[0]->transportasi;
                $editH->perdiem_jumlah_orang = $Dd[0]->perdiem_jumlah_orang;
                $editH->perdiem_jumlah_hari = $Dd[0]->perdiem_jumlah_hari;
                $editH->jumlah_orang_24jam = $Dd[0]->jumlah_orang_24jam;
                $editH->jumlah_hari_24jam = $Dd[0]->jumlah_hari_24jam;
                if (!is_null($Dd[0]->harga_transportasi))
                    $editH->harga_transportasi = $Dd[0]->harga_transportasi;
                if (!is_null($Dd[0]->harga_transportasi_total))
                    $editH->harga_transportasi_total = $Dd[0]->harga_transportasi_total;
                if (!is_null($Dd[0]->harga_personil))
                    $editH->harga_personil = $Dd[0]->harga_personil;
                if (!is_null($Dd[0]->harga_perdiem_personil_total))
                    $editH->harga_perdiem_personil_total = $Dd[0]->harga_perdiem_personil_total;

                if (!is_null($Dd[0]->harga_24jam_personil))
                    $editH->harga_24jam_personil = $Dd[0]->harga_24jam_personil;
                if (!is_null($Dd[0]->harga_24jam_personil_total))
                    $editH->harga_24jam_personil_total = $Dd[0]->harga_24jam_personil_total;

                $editH->total_discount_air = $Dd[0]->total_discount_air;

                $editH->total_discount_non_air = $Dd[0]->total_discount_non_air;

                $editH->total_discount_udara = $Dd[0]->total_discount_udara;

                $editH->total_discount_emisi = $Dd[0]->total_discount_emisi;

                $editH->total_discount_gabungan = $Dd[0]->total_discount_gabungan;

                $editH->total_cash_discount_persen = $Dd[0]->total_cash_discount_persen;
                $editH->total_discount_group = $Dd[0]->total_discount_group;
                $editH->total_discount_consultant = $Dd[0]->total_discount_consultant;
                if (!is_null($Dd[0]->cash_discount))
                    $editH->total_cash_discount = round($Dd[0]->cash_discount);

                $editH->total_discount_transport = $Dd[0]->total_discount_transport;

                $editH->total_discount_perdiem = $Dd[0]->total_discount_perdiem;

                $editH->total_discount_perdiem_24jam = $Dd[0]->total_discount_perdiem_24jam;

                $editH->total_custom_discount = $Dd[0]->total_custom_discount;

                $editH->total_ppn = $Dd[0]->total_ppn;
                $editH->total_pph = $Dd[0]->total_pph;

                $editH->total_biaya_lain = $Dd[0]->total_biaya_lain;
                $editH->total_biaya_preparasi = $Dd[0]->total_biaya_preparasi;
                $editH->biaya_diluar_pajak = json_encode($diluar_pajak);
                $editH->total_biaya_di_luar_pajak = $Dd[0]->total_biaya_di_luar_pajak;
                $editH->grand_total = $Dd[0]->grand_total;
                $editH->total_discount = $Dd[0]->total_discount;
                $editH->total_dpp = $Dd[0]->total_dpp;
                $editH->piutang = $Dd[0]->piutang;
                $editH->biaya_akhir = $Dd[0]->biaya_akhir;
                $editH->updated_by = $this->karyawan;
                $editH->updated_at = date('Y-m-d H:i:s');
                $editH->save();

                if ($data_lama != null) {
                    if ($data_lama->status_sp == 'true') { //merubah jadwal dalam arti menon aktifkan SP
                        SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
                        Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => false, 'canceled_by' => 'system']);

                        // SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
                        // Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => true]);

                        $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $dataH->no_document dan data yang sudah terjadwal akan di tarik otomatis oleh system dan menunggu request baru dari sales";
                        // Helpers::sendTelegramAtasan($message, $cek->add_by);
                        // Helpers::sendTelegramAtasan($message, '187');
                    } else if ($data_lama->status_sp == 'false') {
                        //tidak merubah jadwal dalam arti update no doc dalam sp
                        DB::beginTransaction();
                        try {
                            // ========== START Perbaikan SP dan Jadwal By: 565 ==========
                            // step 1. Menghitung periode kontrak pada dokumen baru
                            $Arrperiod = [];
                            foreach ($payload->data_wilayah->wilayah_data as $key => $wilayah_data) {
                                if ($wilayah_data->status_sampling !== 'SD') {
                                    foreach ($wilayah_data->periode as $key => $valuePeriode) {
                                        array_push($Arrperiod, $valuePeriode);
                                    }
                                }
                            }
                            $Arrperiod = array_values(array_unique($Arrperiod));

                            $detailLama = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get()->pluck('periode_kontrak')->unique()->toArray();

                            // dd($Arrperiod, $detailLama);
                            $perubahan_periode = $this->different($dataOld->id, $dataH->id, $detailLama, $Arrperiod, $dataH->no_document);

                            if ($perubahan_periode) {
                                foreach ($perubahan_periode as $key => $value) {
                                    SamplingPlan::where('no_quotation', $dataOld->no_document)
                                        ->where('periode_kontrak', $value['before'])
                                        ->where('is_active', true)
                                        ->update(['periode_kontrak' => $value['after']]);

                                    Jadwal::where('no_quotation', $dataOld->no_document)
                                        ->where('periode', $value['before'])
                                        ->update(['periode' => $value['after']]);
                                }
                            }
                            // step 2. Menyesuaikan sampling plan dan jadwal dengan dokumen baru
                            SamplingPlan::where('no_quotation', $dataOld->no_document)
                                ->update([
                                    'no_quotation' => $dataH->no_document,
                                    'quotation_id' => $dataH->id
                                ]);
                            Jadwal::where('no_quotation', $dataOld->no_document)
                                ->update([
                                    'no_quotation' => $dataH->no_document,
                                    'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($dataH->nama_perusahaan)))
                                ]);

                            // step 3. Menonaktifkan sampling plan yang tidak ada di dokumen baru
                            SamplingPlan::where('no_quotation', $dataH->no_document)
                                ->whereNotIn('periode_kontrak', $Arrperiod)
                                ->where('is_active', true)
                                ->update(['is_active' => false, 'status' => false, 'status_jadwal' => 'cancel']);

                            // step 4. Mengambil id sampling plan yang non-aktif
                            $resultJadwalNonAktif = SamplingPlan::select('id', 'periode_kontrak')
                                ->where('no_quotation', $no_document)
                                ->whereNotIn('periode_kontrak', $Arrperiod)
                                ->where('is_active', false)
                                ->get();

                            $periodJadwalId = [];
                            foreach ($resultJadwalNonAktif as $key => $value) {
                                array_push($periodJadwalId, $value->id);
                            }

                            // step 5. Menonaktifkan jadwal yang tidak relevan dengan dokumen baru
                            Jadwal::where('no_quotation', $no_document)
                                ->whereIn('id_sampling', $periodJadwalId)
                                ->where('is_active', true)
                                ->update(['is_active' => false, 'canceled_by' => 'system']);
                            // ========== END Perbaikan SP dan Jadwal By: 565 ==========

                            DB::commit();
                        } catch (\Exception $ex) {
                            DB::rollback();
                            $templateMessage = "Error : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method writequotKontrakApi";
                            // Helpers::sendTo(843302196, $templateMessage);
                        }

                        $message = "Terjadi perubahan quotation $data_lama->no_qt menjadi $no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
                    }


                    if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                        // $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
                        // $no_qt_lama = $cek_order->no_document;
                        // $no_qt_baru = $dataH->no_document;
                        // $id_order = $data_lama->id_order;

                        // $parse = new GeneratePraSampling;
                        // $parse->type('QTC');
                        // $parse->where('no_qt_lama', $no_qt_lama);
                        // $parse->where('no_qt_baru', $no_qt_baru);
                        // $parse->where('id_order', $id_order);
                        // $parse->save();
                        // dd($data_lama);
                    } else {
                        // $parse = new GeneratePraSampling;
                        // $parse->type('QTC');
                        // $parse->where('no_qt_baru', $dataH->no_document);
                        // $parse->where('generate', 'new');
                        // $parse->save();
                    }

                    if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                        $invoices = Invoice::where('no_quotation', $dataOld->no_document)
                            ->where('is_active', true)
                            ->get();

                        $invoiceNumbersTobeChecked = [];
                        foreach ($invoices as $invoice) {
                            if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
                                $invoice->is_generate = false;
                                $invoice->is_emailed = false;

                                $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
                            }
                            $invoice->no_quotation = $no_document;
                            $invoice->save();

                            // $render = new RenderInvoice();
                            // $render->renderInvoice($invoice->no_invoice);
                        }

                        $invoiceNumbersStr = implode(', ', $invoiceNumbersTobeChecked);

                        $message = count($invoiceNumbersTobeChecked) > 0
                            ? "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order. Oleh karena itu nomor invoice $invoiceNumbersStr akan dikembalikan ke menu generate invoice untuk dilakukan pengecekan."
                            : "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order.";

                        Notification::where('id_department', 5)->title('Revisi Penawaran')->message($message)->url('/generate-invoice')->send();

                        DB::table('persiapan_sampel_header')
                            ->where('no_quotation', $dataOld->no_document)
                            ->where('is_active', true)
                            ->update([
                                'no_quotation' => $dataH->no_document,
                            ]);

                        $konfirmasi = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)
                            ->where('is_active', true)->get();
                        // UPDATE KONFIRMASI ORDER ===================
                        foreach ($konfirmasi as $k) {
                            $k->no_quotation = $dataH->no_document;
                            $k->id_quotation = $dataH->id;
                            $k->save();
                        }
                    }
                } else {
                    // $parse = new GeneratePraSampling;
                    // $parse->type('QTC');
                    // $parse->where('no_qt_baru', $dataH->no_document);
                    // $parse->where('generate', 'new');
                    // $parse->save();
                }
                // dd($dataH, $dataD);
                // $dataOld->document_status = 'Non Aktif';
                // $dataOld->is_active = false;
                // $dataOld->save();

                // $orderConfirmation = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)->where('is_active', true)->first();
                // if ($orderConfirmation)
                //     $orderConfirmation->update(['no_quotation' => $no_document]);
                // ===========================================
                // dd('=====================');

                JobTask::insert([
                    'job' => 'RenderPdfPenawaran',
                    'status' => 'processing',
                    'no_document' => $dataH->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
                // dd('=====================');

                // $fixingPenamaanTitik = $this->changeDataPendukungSamplingKontrak([$dataH->no_document]);
                // $cek = QuotationKontrakD::where('id_request_quotation_kontrak_H', $dataH->id)->pluck('data_pendukung_sampling');
                // foreach ($cek as $item) {
                //     dump(json_decode($item));
                // }
                // dd('==========================================================================');

                // if ($fixingPenamaanTitik) {
                DB::commit();
                // } else {
                //     throw new \Exception('Gagal Fixing Penamaan Titik');
                // }

                $job = new RenderPdfPenawaran($dataH->id, 'kontrak');
                $this->dispatch($job);

                $array_id_user = GetAtasan::where('id', $dataH->sales_id)->get()->pluck('id')->toArray();

                Notification::whereIn('id', $array_id_user)
                    ->title('Penawaran telah di revisi')
                    ->message('Penawaran dengan nomor ' . $dataOld->no_document . ' telah di revisi menjadi ' . $dataH->no_document . '.')
                    ->url('/quote-request')
                    ->send();

                return response()->json([
                    'message' => "Request Quotation number $dataH->no_document has been successfully revised"
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                // dd($e);
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                ], 401);
            }
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage() . ' ' . $th->getLine(),
                'line' => $th->getLine(),
            ], 401);
        }
    }

    // private function revisiKontrak($payload)
    // {
    //     // Implementasi untuk revisi kontrak
    //     try {
    //         $informasi_pelanggan = $payload->informasi_pelanggan;
    //         $data_pendukung = $payload->data_pendukung;
    //         $data_wilayah = $payload->data_wilayah;
    //         $syarat_ketentuan = $payload->syarat_ketentuan;
    //         $data_diskon = $payload->data_diskon;

    //         // dd($informasi_pelanggan);
    //         if (!isset($payload->informasi_pelanggan->sales_id) || $payload->informasi_pelanggan->sales_id == '') {
    //             return response()->json([
    //                 'message' => 'Sales penanggung jawab tidak boleh kosong',
    //             ], 403);
    //         }
    //         if (isset($payload->keterangan_tambahan))
    //             $keterangan_tambahan = $payload->keterangan_tambahan;
    //         DB::BeginTransaction();
    //         try {

    //             $dataOld = QuotationKontrakH::where('is_active', true)
    //                 ->where('no_document', $informasi_pelanggan->no_document)
    //                 ->first();

    //             if (isset($payload->informasi_pelanggan->new_no_document) && $payload->informasi_pelanggan->new_no_document != null) {

    //                 $no_quotation = $dataOld->no_quotation;
    //                 $no_document = $payload->informasi_pelanggan->new_no_document;

    //                 $dataOld->updated_by = $this->karyawan;
    //                 $dataOld->updated_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $dataOld->document_status = 'Non Aktif';
    //                 $dataOld->is_active = false;
    //                 $dataOld->is_emailed = true;
    //                 $dataOld->is_approved = true;
    //                 $dataOld->save();

    //                 ($dataOld->data_lama != null) ? $data_lama = json_decode($dataOld->data_lama) : $data_lama = null;

    //                 $cek_master_customer = MasterPelanggan::where('id_pelanggan', $informasi_pelanggan->pelanggan_ID)->where('is_active', true)->first();
    //                 if ($cek_master_customer != null) {
    //                     // Update kontak pelanggan
    //                     if ($informasi_pelanggan->no_tlp_perusahaan != '') {
    //                         $kontak = KontakPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->first();
    //                         if ($kontak != null) {
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         } else {
    //                             $kontak = new KontakPelanggan;
    //                             $kontak->pelanggan_id = $cek_master_customer->id;
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         }
    //                     }

    //                     // Update alamat pelanggan
    //                     if ($informasi_pelanggan->alamat_kantor != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'kantor')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'kantor';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->alamat_sampling != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'sampling')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'sampling';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_order != '') {
    //                         $picorder = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'order')->first();
    //                         if ($picorder != null) {
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         } else {
    //                             $picorder = new PicPelanggan;
    //                             $picorder->pelanggan_id = $cek_master_customer->id;
    //                             $picorder->type_pic = 'order';
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_sampling != '') {
    //                         $picsampling = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'sampling')->first();
    //                         if ($picsampling != null) {
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->jabatan_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         } else {
    //                             $picsampling = new PicPelanggan;
    //                             $picsampling->pelanggan_id = $cek_master_customer->id;
    //                             $picsampling->type_pic = 'sampling';
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->nama_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 return response()->json([
    //                     'message' => 'Ada masalah pada data silahkan hubungi tim IT.!'
    //                 ], 401);
    //             }

    //             $dataH = new QuotationKontrakH;

    //             //dataH customer order     -------------------------------------------------------> save ke master customer parrent
    //             $dataH->no_quotation = $no_quotation; //penentian nomor Quotation
    //             $dataH->no_document = $no_document;
    //             $dataH->pelanggan_ID = $dataOld->pelanggan_ID;
    //             $dataH->id_cabang = $this->idcabang;

    //             // $dataH->nama_perusahaan = $informasi_pelanggan->nama_perusahaan;
    //             // if (isset($informasi_pelanggan->konsultan) && $informasi_pelanggan->konsultan != '')
    //             // $dataH->konsultan = $informasi_pelanggan->konsultan;

    //             $dataH->nama_perusahaan = $dataOld->nama_perusahaan;
    //             $dataH->konsultan = $dataOld->konsultan;

    //             $dataH->tanggal_penawaran = $informasi_pelanggan->tgl_penawaran;

    //             if (isset($informasi_pelanggan->alamat_kantor) && $informasi_pelanggan->alamat_kantor != '')
    //                 $dataH->alamat_kantor = $informasi_pelanggan->alamat_kantor;
    //             $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //             $dataH->nama_pic_order = ucwords($informasi_pelanggan->nama_pic_order);
    //             $dataH->jabatan_pic_order = $informasi_pelanggan->jabatan_pic_order;
    //             $dataH->no_pic_order = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //             $dataH->email_pic_order = $informasi_pelanggan->email_pic_order;
    //             $dataH->email_cc = (!empty($informasi_pelanggan->email_cc) && sizeof($informasi_pelanggan->email_cc) !== 0) ? json_encode($informasi_pelanggan->email_cc) : null;
    //             $dataH->alamat_sampling = $informasi_pelanggan->alamat_sampling;
    //             // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_sampling);
    //             $dataH->nama_pic_sampling = ucwords($informasi_pelanggan->nama_pic_sampling);
    //             $dataH->jabatan_pic_sampling = $informasi_pelanggan->jabatan_pic_sampling;
    //             $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //             $dataH->email_pic_sampling = $informasi_pelanggan->email_pic_sampling;

    //             $dataH->data_pendukung_diskon = json_encode($data_diskon);
    //             $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
    //             // dd($payload);
    //             $dataH->ppn = $data_diskon->ppn;
    //             $dataH->pph = $data_diskon->pph;
    //             // Periode Kontrak
    //             $dataH->periode_kontrak_awal = $data_pendukung[0]->periodeAwal;
    //             $dataH->periode_kontrak_akhir = $data_pendukung[0]->periodeAkhir;

    //             $dataH->status_wilayah = $data_wilayah->status_Wilayah;
    //             $dataH->wilayah = $data_wilayah->wilayah;

    //             // $dataH->is_generated = $dataOld->is_generated;
    //             // $dataH->is_emailed = $dataOld->is_emailed;

    //             $uniqueStatusSampling = array_unique(array_map(function ($wilayah) {
    //                 return $wilayah->status_sampling;
    //             }, $data_wilayah->wilayah_data));

    //             $dataH->status_sampling = count($uniqueStatusSampling) === 1 ? $uniqueStatusSampling[0] : null;
    //             // $dataH->add_by = $this->userid;
    //             // $dataH->add_at = DATE('Y-m-d H:i:s');
    //             $data_transport_h = [];
    //             $data_pendukung_h = [];
    //             $data_s = [];
    //             $period = [];

    //             $data_pendukung_lain = [];
    //             $total_biaya_lain = 0;
    //             // BIAYA LAIN
    //             if (isset($data_diskon->biaya_lain) && !empty($data_diskon->biaya_lain)) {
    //                 $biaya_lains = 0;
    //                 $data_pendukung_lain = array_map(function ($disc) use (&$biaya_lains) {
    //                     $biaya_lains += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
    //                         'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
    //                     ];
    //                 }, $data_diskon->biaya_lain);
    //                 $total_biaya_lain = $biaya_lains;
    //                 $dataH->biaya_lain = json_encode($data_pendukung_lain);
    //                 $dataH->total_biaya_lain = $total_biaya_lain;
    //             } else {
    //                 $dataH->biaya_lain = null;
    //                 $dataH->total_biaya_lain = 0;
    //             }
    //             // END BIAYA LAIN
    //             //CUSTOM DISCOUNT
    //             $custom_disc = [];
    //             if (isset($data_diskon->custom_discount) && !empty($data_diskon->custom_discount)) {
    //                 $custom_disc = array_map(function ($disc) {
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                     ];
    //                 }, $data_diskon->custom_discount);
    //                 $dataH->custom_discount = json_encode($custom_disc);
    //             } else {
    //                 $dataH->custom_discount = null;
    //             }
    //             // END CUSTOM DISCOUNT

    //             foreach ($payload->data_pendukung as $i => $item) {
    //                 $param = $item->parameter;
    //                 // dd($item);
    //                 $exp = explode("-", $item->kategori_1);
    //                 $kategori = $exp[0];
    //                 $vol = 0;

    //                 $parameter = [];
    //                 foreach ($param as $par) {
    //                     $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
    //                     array_push($parameter, $cek_par->nama_lab);
    //                 }

    //                 $harga_db = [];
    //                 $volume_db = [];
    //                 foreach ($parameter as $param_) {
    //                     $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                         ->where('nama_parameter', $param_)
    //                         ->orderBy('id', 'ASC')
    //                         ->get();


    //                     $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                         return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
    //                     }) ?? $ambil_data->first();

    //                     $harga_db[] = $cek_harga_parameter->harga ?? 0;
    //                     $volume_db[] = $cek_harga_parameter->volume ?? 0;
    //                     // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                     //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
    //                     // }) ?? $ambil_data->first();

    //                     // // fix bug
    //                     // if ($cek_harga_parameter) {
    //                     //     $harga_db[] = $cek_harga_parameter->harga;
    //                     //     $volume_db[] = $cek_harga_parameter->volume;
    //                     // } else {
    //                     //     $harga_db[] = 0;
    //                     //     $volume_db[] = 0;
    //                     // }
    //                 }

    //                 $harga_pertitik = (object) [
    //                     'volume' => array_sum($volume_db),
    //                     'total_harga' => array_sum($harga_db)
    //                 ];

    //                 if ($harga_pertitik->volume != null) {
    //                     $vol += floatval($harga_pertitik->volume);
    //                 }

    //                 $titik = $item->jumlah_titik;

    //                 $temp_prearasi = [];
    //                 if ($item->biaya_preparasi != null || $item->biaya_preparasi != "") {
    //                     foreach ($item->biaya_preparasi as $pre) {
    //                         if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                             $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                         // HARGA PREPARASI UNUSED
    //                         // if($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") $harga_preparasi += floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan));
    //                     }
    //                 }
    //                 $biaya_preparasi = $temp_prearasi;

    //                 $data_sampling[$i] = [
    //                     'kategori_1' => $item->kategori_1,
    //                     'kategori_2' => $item->kategori_2,
    //                     'penamaan_titik' => $item->penamaan_titik,
    //                     'parameter' => $param,
    //                     'jumlah_titik' => $titik,
    //                     'total_parameter' => count($param),
    //                     'harga_satuan' => $harga_pertitik->total_harga,
    //                     'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
    //                     'volume' => $vol,
    //                     'periode' => $item->periode,
    //                     'biaya_preparasi' => $biaya_preparasi
    //                 ];

    //                 isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

    //                 foreach ($item->periode as $key => $v) {
    //                     array_push($period, $v);
    //                 }

    //                 array_push($data_pendukung_h, $data_sampling[$i]);
    //             }

    //             // dd($data_pendukung_h);
    //             $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);
    //             // dd($data_pendukung_h);
    //             $period_pendukung = [];

    //             for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {

    //                 $data_lain = $data_pendukung_lain;

    //                 $kalkulasi = 0;
    //                 $harga_transportasi = 0;
    //                 $harga_perdiem = 0;
    //                 $harga_perdiem24 = 0;

    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi))
    //                     $harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem))
    //                     $harga_perdiem = $data_wilayah->wilayah_data[$c]->harga_perdiem;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem24))
    //                     $harga_perdiem24 = $data_wilayah->wilayah_data[$c]->harga_perdiem24;

    //                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem))
    //                     $kalkulasi = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;
    //                 $data_lain_arr = [];
    //                 foreach ($data_lain as $dl) {
    //                     array_push($data_lain_arr, (array) $dl);
    //                 }
    //                 // dd($data_wilayah->wilayah_data[$c]);
    //                 array_push($data_transport_h, (object) ['status_sampling' => $data_wilayah->wilayah_data[$c]->status_sampling, 'jumlah_transportasi' => $data_wilayah->wilayah_data[$c]->transportasi, 'jumlah_orang_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang, 'jumlah_hari_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari, 'jumlah_orang_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam, 'jumlah_hari_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam, 'biaya_lain' => $data_lain_arr, 'harga_transportasi' => $harga_transportasi, 'harga_perdiem' => $harga_perdiem, 'harga_perdiem24' => $harga_perdiem24, 'periode' => $data_wilayah->wilayah_data[$c]->periode, 'kalkulasi_by_sistem' => $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem]);

    //                 foreach ($data_wilayah->wilayah_data[$c]->periode as $key => $v) {
    //                     array_push($period_pendukung, $v);
    //                 }
    //             }

    //             // dd($data_transport_h);

    //             $dataH->data_pendukung_lain = json_encode($data_transport_h);
    //             // $status_sampling = array_map(function ($wilayah) {
    //             //     return $wilayah->status_sampling;
    //             // }, $data_wilayah->wilayah_data);
    //             // if (count($status_sampling) == 1)
    //             //     $dataH->status_sampling = implode(',', $status_sampling);
    //             isset($syarat_ketentuan) ? $dataH->syarat_ketentuan = json_encode($syarat_ketentuan) : $dataH->syarat_ketentuan = null;
    //             isset($keterangan_tambahan) ? $dataH->keterangan_tambahan = json_encode($keterangan_tambahan) : $dataH->keterangan_tambahan = null;
    //             isset($data_diskon->diluar_pajak) ? $dataH->diluar_pajak = json_encode($data_diskon->diluar_pajak) : $dataH->diluar_pajak = null;

    //             ($data_lama != null) ? $dataH->data_lama = json_encode($data_lama) : $dataH->data_lama = null;
    //             $dataH->created_by = $dataOld->created_by;
    //             $dataH->created_at = $dataOld->created_at;
    //             $dataH->save();

    //             // END HEADER DATA
    //             // START DETAIL DATA
    //             $data_detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)
    //                 ->select('id')
    //                 ->get();
    //             // dd($data_detail);

    //             // Period Data Pendukung
    //             $period_pendukung = array_values(array_unique($period_pendukung));

    //             $period = [];
    //             foreach ($data_pendukung as $key => $v) {
    //                 foreach ($v->periode as $a) {
    //                     array_push($period, $a);
    //                 }
    //             }

    //             $period = array_values(array_unique($period));

    //             $diff1 = array_values(array_diff($period, $period_pendukung));

    //             if (count($diff1) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff1 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Wilayah Tidak Ada Di Periode Data Pendukung..! '], 500);
    //             }

    //             $diff2 = array_values(array_diff($period_pendukung, $period));

    //             if (count($diff2) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff2 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Pendukung Tidak Ada Di Periode Data Wilayah..! '], 500);
    //             }

    //             $num = count($period);
    //             $detail = count($data_detail);
    //             if ($num > 12) {
    //                 DB::rollBack();
    //                 return response()->json(['message' => 'Periode Kontrak Lebih Dari 12 Bulan'], 201);
    //             }
    //             $id_det = [];
    //             $tgl = null;
    //             // =========================================================================
    //             // STEP 1: Bongkar & Petakan Nomor Titik dari Kontrak Lama (dataOld)
    //             // =========================================================================
    //             $titikGroupMapping = [];
    //             $lastTitikNumber = 0;
    //             $periodeOld = [];
    //             // Ambil semua detail dari kontrak LAMA
    //             $detailsFromOldContract = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();

    //             foreach ($detailsFromOldContract as $detail) {
    //                 // Decode data pendukung sampling dari JSON
    //                 $dataPendukungSampling = json_decode($detail->data_pendukung_sampling, true);
    //                 // Iterasi setiap data sampling di dalamnya
    //                 if (is_array($dataPendukungSampling)) {
    //                     foreach ($dataPendukungSampling as $samplingData) {
    //                         // Ambil data sampling yang relevan
    //                         $dataSamplingList = $samplingData['data_sampling'] ?? [];
    //                         if (!is_array($dataSamplingList)) {
    //                             $dataSamplingList = json_decode($dataSamplingList, true);
    //                         }
    //                         foreach ($dataSamplingList as $item) {
    //                             $periodeOld[] = $samplingData['periode_kontrak'];

    //                             // Buat unique key berdasarkan kategori, ini patokan kita!
    //                             $groupKey = $samplingData['periode_kontrak'] . ';' . $item['kategori_1'] . ';' . $item['kategori_2'] . ';' . json_encode($item['regulasi']) . ';' . json_encode($item['parameter']);
    //                             if (!isset($titikGroupMapping[$groupKey])) {
    //                                 $titikGroupMapping[$groupKey] = [];
    //                             }

    //                             // Petakan NAMA titik ke NOMOR titik
    //                             if (isset($item['penamaan_titik']) && is_array($item['penamaan_titik'])) {
    //                                 foreach ($item['penamaan_titik'] as $titikObject) {
    //                                     $titikObject = (array) $titikObject;
    //                                     $nomor = key($titikObject);
    //                                     $nama = current($titikObject);

    //                                     // Mapping: 'Outlet' => '001'
    //                                     $titikGroupMapping[$groupKey][$nomor] = $nama;

    //                                     // Cari nomor titik tertinggi untuk counter
    //                                     if (intval($nomor) > $lastTitikNumber) {
    //                                         $lastTitikNumber = intval($nomor);
    //                                     }
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //             // dump($titikGroupMapping);

    //             // Counter untuk titik BARU akan dimulai dari nomor tertinggi + 1
    //             $globalTitikCounter = $lastTitikNumber + 1;
    //             $allDetailQuotOld = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();
    //             // foreach ($allDetailQuotOld as $detail) {
    //             //     dump(json_decode($detail->data_pendukung_sampling));
    //             // }
    //             $oldPeriod = $allDetailQuotOld->pluck('periode_kontrak')->toArray();
    //             $alreadyOrdered = false;
    //             if ($dataOld->data_lama !== null) {
    //                 $dataLamaDecoded = json_decode($dataOld->data_lama, true);
    //                 if (isset($dataLamaDecoded['id_order'])) {
    //                     $alreadyOrdered = true;
    //                 }
    //             }

    //             $biggestNumberOfSampel = 0;

    //             if ($alreadyOrdered) {
    //                 foreach ($allDetailQuotOld as $detail) {
    //                     $oldData = json_decode($detail->data_pendukung_sampling, true);
    //                     $first = reset($oldData)['data_sampling'];
    //                     foreach ($first as $item) {
    //                         foreach ($item['penamaan_titik'] as $titik) {
    //                             $key = array_key_first($titik);
    //                             $number = (int) $key;
    //                             if ($number > $biggestNumberOfSampel) {
    //                                 $biggestNumberOfSampel = $number;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //             // dd($biggestNumberOfSampel);
    //             $diffPeriod = array_diff($period, $oldPeriod);
    //             $diffOldPeriod = array_diff($oldPeriod, $period);
    //             foreach ($period as $k => $per) {
    //                 // dd($per);
    //                 if (!isset($data_detail[$k]->id)) {
    //                     $id_detail = '';
    //                 } else {
    //                     $id_detail = $data_detail[$k]->id;
    //                     if ($num < $detail) {
    //                         array_push($id_det, $id_detail);
    //                     }
    //                 }

    //                 $cek = QuotationKontrakD::where('id', $id_detail)->first();

    //                 if (!is_null($cek)) {
    //                     $dataD = QuotationKontrakD::where('id', $data_detail[$k]->id)->first();
    //                 } else {
    //                     $dataD = new QuotationKontrakD;
    //                     $dataD->id_request_quotation_kontrak_h = $dataH->id;
    //                 }

    //                 // dd($period);

    //                 $data_sampling = [];
    //                 $datas = [];
    //                 $harga_total = 0;
    //                 $harga_air = 0;
    //                 $harga_udara = 0;
    //                 $harga_emisi = 0;
    //                 $harga_padatan = 0;
    //                 $harga_swab_test = 0;
    //                 $harga_tanah = 0;
    //                 $harga_pangan = 0;
    //                 $grand_total = 0;
    //                 $total_diskon = 0;
    //                 $desc_preparasi = [];
    //                 $harga_preparasi = 0;

    //                 $j = $k + 1;
    //                 $n = 0;

    //                 foreach ($data_pendukung as $m => $xyz) {
    //                     if (in_array($per, $xyz->periode)) {
    //                         $param = [];
    //                         $regulasi = '';
    //                         if ($xyz->parameter != null)
    //                             $param = $xyz->parameter;
    //                         if ($xyz->regulasi != null)
    //                             $regulasi = $xyz->regulasi;

    //                         $exp = explode("-", $xyz->kategori_1);
    //                         $kategori = $exp[0];
    //                         $vol = 0;

    //                         // GET PARAMETER NAME FOR CEK HARGA KONTRAK
    //                         $parameter = [];
    //                         $id_param = [];
    //                         foreach ($xyz->parameter as $va) {
    //                             $cek_par = DB::table('parameter')
    //                                 ->where('id', explode(';', $va)[0])->first();
    //                             array_push($parameter, $cek_par->nama_lab);
    //                             array_push($id_param, $cek_par->id);
    //                         }

    //                         $harga_db = [];
    //                         $volume_db = [];
    //                         foreach ($parameter as $ix => $param_) {

    //                             $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                                 ->where('nama_parameter', $param_)
    //                                 ->orderBy('id', 'ASC')
    //                                 ->get();
    //                             if (count($ambil_data) > 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     // $zx->tgl_order $request->tgl_order
    //                                     if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }

    //                                     if ((count($ambil_data) - 1) == $xc) {
    //                                         $zx = $ambil_data[0];
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }
    //                                 }
    //                             } else if (count($ambil_data) == 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     array_push($harga_db, $zx->harga);
    //                                     array_push($volume_db, $zx->volume);
    //                                     break;
    //                                 }
    //                             } else {
    //                                 array_push($harga_db, 0);
    //                                 array_push($volume_db, 0);
    //                             }
    //                         }

    //                         $vol_db = array_sum($volume_db);
    //                         $har_db = array_sum($harga_db);

    //                         $harga_pertitik = (object) [
    //                             'volume' => $vol_db,
    //                             'total_harga' => $har_db
    //                         ];

    //                         if ($harga_pertitik->volume != null)
    //                             $vol += floatval($harga_pertitik->volume);
    //                         if ($xyz->jumlah_titik == '') {
    //                             $reqtitik = 0;
    //                         } else {
    //                             $reqtitik = $xyz->jumlah_titik;
    //                         }

    //                         //============= BIAYA PREPARASI ==================
    //                         if ($xyz->biaya_preparasi != null || $xyz->biaya_preparasi != "") {
    //                             foreach ($xyz->biaya_preparasi as $pre) {
    //                                 if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                                     $desc_preparasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                                 if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
    //                                     $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
    //                             }
    //                         }
    //                         // =========================================================================
    //                         // STEP 2: Terapkan Nomor Titik (Lama atau Baru)
    //                         // =========================================================================
    //                         $penamaan_titik_fixed = [];

    //                         $fullGroupKey = $per . ';' . $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);
    //                         $fallbackGroupKey = $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);

    //                         $pengurangan_periode_kontrak = array_values(array_diff($periodeOld, $xyz->selectedPeriode));
    //                         $penambahan_periode_kontrak = array_values(array_diff($xyz->selectedPeriode, $periodeOld));

    //                         $key_penambahan = array_search($per, $penambahan_periode_kontrak);
    //                         $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

    //                         // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak, $key_penambahan, $periode_pengganti);
    //                         $oldNumberMappingForGroup = [];
    //                         if (isset($titikGroupMapping[$fullGroupKey])) {
    //                             $oldNumberMappingForGroup = $titikGroupMapping[$fullGroupKey];
    //                         } else {
    //                             /* Base By Penamaan Titik
    //                             if (in_array($per, $periodeOld)) {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($per) {
    //                                     return strpos($item, $per) !== false;
    //                                 });
    //                             } else {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($periode_pengganti) {
    //                                     return strpos($item, $periode_pengganti) !== false;
    //                                 });
    //                             }
    //                             // dump($tempKeyGroup);

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 $titikOld = $titikGroupMapping[$key];
    //                                 $foundNamaTitik = array_intersect($titikOld, $xyz->penamaan_titik);
    //                                 if (count($foundNamaTitik) > 0) {
    //                                     $foundKey = $key;
    //                                     break;
    //                                 }
    //                             }*/

    //                             $keyPeriod = in_array($per, $periodeOld) ? $per : $periode_pengganti;
    //                             $tempKeyGroup = array_filter(array_keys($titikGroupMapping), fn($key) => str_contains($key, $keyPeriod));

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 if (count(array_intersect($titikGroupMapping[$key], $xyz->penamaan_titik)) > 0) {
    //                                     $foundKey = $key;
    //                                     // break;
    //                                 }
    //                             }

    //                             $oldNumberMappingForGroup = isset($titikGroupMapping[$foundKey]) ? $titikGroupMapping[$foundKey] : [];

    //                             /* Only By Period
    //                             dd($titikGroupMapping[$foundKey], $xyz->penamaan_titik);
    //                             $existingPeriods = array_map(function ($key) {
    //                                 return explode(';', $key)[0];
    //                             }, array_keys($titikGroupMapping));
    //                             if (!in_array($per, $existingPeriods)) {
    //                                 foreach (array_keys($titikGroupMapping) as $key) {
    //                                     $temp = explode(';', $key);
    //                                     $period = array_shift($temp);
    //                                     $partialKey = implode(';', $temp);

    //                                     if ($partialKey === $fallbackGroupKey) {
    //                                         $oldNumberMappingForGroup = $titikGroupMapping[$key];
    //                                         break;
    //                                     }
    //                                 }
    //                             }*/
    //                         }
    //                         // dd($oldNumberMappingForGroup);
    //                         // dump($oldNumberMappingForGroup, $titikGroupMapping);
    //                         foreach ($xyz->penamaan_titik as $pt) {
    //                             // dump($pt);
    //                             $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
    //                             if (in_array($namaTitik, $oldNumberMappingForGroup) && $namaTitik != "") {
    //                                 // dump($oldNumberMappingForGroup);
    //                                 $nomor = array_search($namaTitik, $oldNumberMappingForGroup);
    //                                 $penamaan_titik_fixed[] = (object) [$nomor => $namaTitik];
    //                             } else {
    //                                 $nomorLama = null;

    //                                 foreach ($titikGroupMapping as $key => $map) {
    //                                     if (strpos($key, $fallbackGroupKey) !== false && in_array($namaTitik, $map)) {
    //                                         $nomorLama = array_search($namaTitik, $map);
    //                                         if (!array_key_exists($nomorLama, $penamaan_titik_fixed)) {
    //                                             $nomorLama = null;
    //                                         }
    //                                         break;
    //                                     }
    //                                 }
    //                                 // dump($nomorLama);

    //                                 if ($nomorLama) {
    //                                     $penamaan_titik_fixed[] = (object) [$nomorLama => $namaTitik];

    //                                     // Daftarin ke current group juga biar ke-cache
    //                                     $titikGroupMapping[$fullGroupKey][$nomorLama] = $namaTitik;
    //                                 } else {
    //                                     $nomorBaru = sprintf('%03d', $globalTitikCounter);
    //                                     $penamaan_titik_fixed[] = (object) [$nomorBaru => $namaTitik];

    //                                     $titikGroupMapping[$fullGroupKey][$nomorBaru] = $namaTitik;
    //                                     $globalTitikCounter++;
    //                                 }
    //                             }
    //                         }

    //                         // dump($penamaan_titik_fixed);

    //                         $data_sampling[$n++] = [
    //                             'kategori_1' => $xyz->kategori_1,
    //                             'kategori_2' => $xyz->kategori_2,
    //                             'regulasi' => $regulasi,
    //                             'parameter' => $param,
    //                             'jumlah_titik' => $xyz->jumlah_titik,
    //                             'penamaan_titik' => $penamaan_titik_fixed,
    //                             'total_parameter' => count($param),
    //                             'harga_satuan' => $harga_pertitik->total_harga,
    //                             'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
    //                             'volume' => $vol,
    //                             'biaya_preparasi' => $desc_preparasi
    //                         ];


    //                         // dump($per);
    //                         // dump($penamaan_titik_fixed);

    //                         // kalkulasi harga parameter sesuai titik
    //                         switch ($kategori) {
    //                             case '1':
    //                                 $harga_air += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '4':
    //                                 $harga_udara += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '5':
    //                                 $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '6':
    //                                 $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '7':
    //                                 $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '8':
    //                                 $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '9':
    //                                 $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                         }
    //                     }
    //                 }


    //                 foreach ($data_sampling as $index => $newItem) {
    //                     $checkOldQt = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->where('periode_kontrak', $per)->first();
    //                     $first = [];
    //                     // if($per == '2026-06' || $per == '2026-07'){
    //                     //     dump("sebelum");
    //                     //     dump($data_sampling);
    //                     // }
    //                     $isMutated = false;
    //                     if ($checkOldQt) {
    //                         // dump('ada old data sesuai periode', $per);
    //                         $oldData = json_decode($checkOldQt->data_pendukung_sampling, true);
    //                         $first = reset($oldData)['data_sampling'];
    //                     } else {
    //                         if ($alreadyOrdered) {
    //                             $checkOldQtRemaining = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)
    //                                 ->whereIn('periode_kontrak', $diffOldPeriod)
    //                                 ->get();
    //                             // dump('masuk else periode', $per);

    //                             if ($checkOldQtRemaining->count() > 0) {
    //                                 // dump('ada data diff periode', $per);

    //                                 $foundFromOldRemaining = false;
    //                                 $matchedOldPenamaan = null;
    //                                 foreach ($checkOldQtRemaining as $oldDetail) {
    //                                     $oldData = json_decode($oldDetail->data_pendukung_sampling, true);
    //                                     $oldSamplingList = reset($oldData)['data_sampling'] ?? [];
    //                                     foreach ($oldSamplingList as $oldSampling) {
    //                                         if (
    //                                             ($oldSampling['kategori_1'] ?? null) === ($newItem['kategori_1'] ?? null) &&
    //                                             ($oldSampling['kategori_2'] ?? null) === ($newItem['kategori_2'] ?? null)
    //                                         ) {
    //                                             // Match found
    //                                             $matchedOldPenamaan = $oldSampling['penamaan_titik'] ?? [];
    //                                             $foundFromOldRemaining = $oldDetail->periode_kontrak;
    //                                             // dump('Pengecekan match lama');
    //                                             // dump($matchedOldPenamaan, $foundFromOldRemaining);
    //                                             break 2;
    //                                         }
    //                                     }
    //                                 }

    //                                 if ($matchedOldPenamaan !== null) {
    //                                     // Langsung assign titik dari match lama
    //                                     $data_sampling[$index]['penamaan_titik'] = $matchedOldPenamaan;

    //                                     $isMutated = true;
    //                                     // Buang periode yang udah kepake dari diffOldPeriod
    //                                     $diffOldPeriod = array_filter($diffOldPeriod, function ($periode) use ($foundFromOldRemaining) {
    //                                         return $periode !== $foundFromOldRemaining;
    //                                     });

    //                                     // Skip ke iterasi berikutnya (ga pake logic bawah)
    //                                     continue;
    //                                 }
    //                             }
    //                         }
    //                     }

    //                     if (!$isMutated) {
    //                         // dump('tidak mutated');
    //                         $diffRegulasi = false;
    //                         $diffKategori = false;
    //                         $diffSubKategori = false;
    //                         $diffParameter = false;
    //                         $oldItem = $first[$index] ?? [];

    //                         // Cek kategori_1
    //                         if ((isset($oldItem['kategori_1']) && $newItem['kategori_1'] !== $oldItem['kategori_1'] ?? null)) {
    //                             $diffKategori = true;
    //                         }

    //                         // Cek kategori_2
    //                         if ((isset($oldItem['kategori_2']) && $newItem['kategori_2'] !== $oldItem['kategori_2'] ?? null)) {
    //                             $diffSubKategori = true;
    //                         }

    //                         // Cek regulasi
    //                         if ((isset($oldItem['regulasi']) && json_encode($newItem['regulasi']) !== json_encode($oldItem['regulasi'] ?? []))) {
    //                             $diffRegulasi = true;
    //                         }

    //                         // Cek parameter (urutan juga dibandingkan)
    //                         if ((isset($oldItem['parameter']) && json_encode($newItem['parameter']) !== json_encode($oldItem['parameter'] ?? []))) {
    //                             $diffParameter = true;
    //                         }

    //                         $penamaanBaru = array_map(function ($obj) {
    //                             return (array) $obj;
    //                         }, $newItem['penamaan_titik']);

    //                         $penamaanLama = $oldItem['penamaan_titik'] ?? [];

    //                         if (!$alreadyOrdered) {

    //                             // dump('tidak pernah Order');
    //                             $penamaanFix = [];

    //                             foreach ($penamaanBaru as $titikBaru) {
    //                                 $valBaru = array_values($titikBaru)[0];

    //                                 $biggestNumberOfSampel++;
    //                                 $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                                 $penamaanFix[] = [$newKey => $valBaru];
    //                             }

    //                             $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                         } else {
    //                             // dump('sudah pernah Order');
    //                             if ($diffKategori) {
    //                                 $penamaanFix = [];

    //                                 foreach ($penamaanBaru as $titikBaru) {
    //                                     $valBaru = array_values($titikBaru)[0];

    //                                     // Selalu generate nomor baru
    //                                     $biggestNumberOfSampel++;
    //                                     $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                                     $penamaanFix[] = [$newKey => $valBaru];
    //                                 }

    //                                 $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                             } else {
    //                                 $penamaanFix = [];
    //                                 $used = [];

    //                                 // Loop data baru
    //                                 foreach ($penamaanBaru as $titikBaru) {
    //                                     $valBaru = array_values($titikBaru)[0];
    //                                     $found = false;

    //                                     // Coba cari val yang sama di penamaan lama
    //                                     foreach ($penamaanLama as $titikLama) {
    //                                         $keyLama = array_key_first($titikLama);
    //                                         $valLama = array_values($titikLama)[0];

    //                                         if ((string) $valBaru === (string) $valLama && !in_array($keyLama, $used)) {
    //                                             $penamaanFix[] = [$keyLama => $valBaru];
    //                                             $used[] = $keyLama;
    //                                             $found = true;
    //                                             break;
    //                                         }
    //                                     }

    //                                     // Kalau tidak ditemukan di data lama, pakai nomor baru
    //                                     if (!$found) {
    //                                         $biggestNumberOfSampel++;
    //                                         $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                                         $penamaanFix[] = [$newKey => $valBaru];
    //                                         $used[] = $newKey;
    //                                     }
    //                                 }

    //                                 // Set hasil akhirnya
    //                                 $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                             }
    //                         }
    //                     }
    //                 }
    //                 // dump($data_sampling);
    //                 // if($per == '2026-06' || $per == '2026-07'){
    //                 //         dump('setelah');
    //                 //         dump($data_sampling);
    //                 //     }

    //                 $datas[$j] = [
    //                     'periode_kontrak' => $per,
    //                     'data_sampling' => array_values($data_sampling)
    //                     // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
    //                 ];

    //                 $dataD->periode_kontrak = $per;
    //                 $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

    //                 $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
    //                 // end data sampling
    //                 $dataD->harga_air = $harga_air;
    //                 $dataD->harga_udara = $harga_udara;
    //                 $dataD->harga_emisi = $harga_emisi;
    //                 $dataD->harga_padatan = $harga_padatan;
    //                 $dataD->harga_swab_test = $harga_swab_test;
    //                 $dataD->harga_tanah = $harga_tanah;

    //                 // kalkulasi harga
    //                 $expOp = explode("-", $data_wilayah->wilayah);
    //                 $id_wilayah = $expOp[0];
    //                 $cekOperasional = HargaTransportasi::where('is_active', true)->where('id', $id_wilayah)->first();

    //                 // START FOR
    //                 $disc_transport = 0;
    //                 $disc_perdiem = 0;
    //                 $disc_perdiem_24 = 0;

    //                 $harga_transport = 0;
    //                 $jam = 0;
    //                 $transport = 0;
    //                 $perdiem = 0;

    //                 $data_lain = $data_pendukung_lain;
    //                 $biaya_lain = 0;

    //                 for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {
    //                     if (in_array($per, $data_wilayah->wilayah_data[$c]->periode)) {

    //                         // Menjumlahkan total % discount transport kedalam variable
    //                         if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD')
    //                             $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi;
    //                         // dd($data_wilayah->status_Wilayah);
    //                         if ($data_wilayah->status_Wilayah == 'DALAM KOTA') {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }

    //                                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem)) {
    //                                     $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true' ? $dataD->kalkulasi_by_sistem = 'on' : $dataD->kalkulasi_by_sistem = 'off';
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {
    //                                     $dataD->harga_transportasi = $cekOperasional->transportasi;
    //                                     $dataD->harga_transportasi_total = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);

    //                                     $dataD->harga_personil = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang);
    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);
    //                                     $perdiem = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '')
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     // dd($data_wilayah->wilayah_data);
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_personil);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;
    //                         } else {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;

    //                             // dd($data_wilayah->wilayah_data);
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
    //                                 //hitung harga tiket perjalanan
    //                                 $dataD->kalkulasi_by_sistem = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {

    //                                     $harga_tiket = $cekOperasional->tiket * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                     $harga_transportasi_darat = $cekOperasional->transportasi;
    //                                     $harga_penginapan = $cekOperasional->penginapan;
    //                                     $dataD->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
    //                                     $dataD->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;

    //                                     $dataD->harga_personil = $cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;

    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;
    //                                     $perdiem = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi_total);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }
    //                         }

    //                         $dataD->status_sampling = $data_wilayah->wilayah_data[$c]->status_sampling;
    //                     }
    //                 }

    //                 // =======================================================================DATA DISKON===========================================================================
    //                 // ==================================================DISKON ANALISA=============================================================================================
    //                 $isPeriodeDiskonExist = false;
    //                 $periodeNotExist = '';
    //                 $indexDataDiskon = 0;
    //                 if (count($data_diskon->discount_data) > 0) {
    //                     foreach ($data_diskon->discount_data as $d => $discount) {
    //                         if (in_array($per, $discount->periode)) {
    //                             $isPeriodeDiskonExist = true;
    //                             $indexDataDiskon = $d;
    //                             break;
    //                         }
    //                         if (!$isPeriodeDiskonExist && $d == count($data_diskon->discount_data) - 1) {
    //                             $periodeNotExist = $per;
    //                         }
    //                     }
    //                 }

    //                 if (!$isPeriodeDiskonExist) {
    //                     return response()->json(['message' => 'Periode ' . $periodeNotExist . ' tidak ditemukan pada group diskon', 'status' => '500'], 403);
    //                 }
    //                 // $indexDataDiskon == 1 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_transport) : '';
    //                 // dd($isPeriodeDiskonExist);

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_air > 0) {
    //                     // $indexDataDiskon == 0 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_air) : '';
    //                     // dd($data_diskon->discount_data[$indexDataDiskon]->discount_non_air);
    //                     $dataD->discount_air = $data_diskon->discount_data[$indexDataDiskon]->discount_air;
    //                     $dataD->total_discount_air = ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $harga_total += $harga_air - ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $total_diskon += ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));
    //                     if (floatval(\str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air)) > 10) {
    //                         $message = $dataH->no_document . ' Discount Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }
    //                 } else {
    //                     $harga_total += $harga_air;
    //                     $dataD->discount_air = null;
    //                     $dataD->total_discount_air = 0;
    //                 }

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_non_air > 0) {
    //                     $dataD->discount_non_air = $data_diskon->discount_data[$indexDataDiskon]->discount_non_air;
    //                     $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $total_diskon += ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 10) {
    //                         $message = $dataH->no_document . ' Discount Non-Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 } else {
    //                     $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->discount_non_air = null;
    //                     $dataD->total_discount_non_air = '0.00';

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) == 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_udara;
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) == 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_emisi;
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 }

    //                 // ========================================================END DISKON ANALISA========================================================================
    //                 // ====================================================DISKON TRANSPORTASI========================================================================================
    //                 $harga_total += $harga_pangan;
    //                 $transport_ = 0;
    //                 $perdiem_ = 0;
    //                 $jam_ = 0;

    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0) {
    //                         $dataD->discount_transport = $data_diskon->discount_data[$indexDataDiskon]->discount_transport;
    //                         $dataD->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         // Harga Total
    //                         // $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $transport_ = $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                     } else {
    //                         $dataD->discount_transport = null;
    //                         $dataD->total_discount_transport = 0;
    //                         $transport_ = $transport;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem) > 0) {
    //                         $dataD->discount_perdiem = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem;
    //                         $dataD->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         // $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $perdiem_ = $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                     } else {
    //                         $dataD->discount_perdiem = null;
    //                         $dataD->total_discount_perdiem = 0;
    //                         $perdiem_ = $perdiem;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam) > 0) {
    //                         $dataD->discount_perdiem_24jam = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam;
    //                         $dataD->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         // $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $jam_ = $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                     } else {
    //                         $dataD->discount_perdiem_24jam = null;
    //                         $dataD->total_discount_perdiem_24jam = 0;
    //                         $jam_ = $jam;
    //                     }
    //                 }

    //                 $harga_transport += ($transport_ + $perdiem_ + $jam_);
    //                 // ==================================================END DISKON TRANSPORTASI======================================================================================
    //                 // =======================================================DISKON GABUNGAN=========================================================================================
    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_gabungan) > 0) {
    //                         $dataD->discount_gabungan = $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan;
    //                         $dataD->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                     } else {
    //                         $dataD->discount_gabungan = null;
    //                         $dataD->total_discount_gabungan = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_consultant) > 0) {
    //                         $dataD->discount_consultant = $data_diskon->discount_data[$indexDataDiskon]->discount_consultant;
    //                         $dataD->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                     } else {
    //                         $dataD->discount_consultant = null;
    //                         $dataD->total_discount_consultant = 0;
    //                     }

    //                     // BIAYA LAIN
    //                     // $biaya_lain = 0;
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->biaya_lains) && !empty($data_diskon->discount_data[$indexDataDiskon]->biaya_lains)) {
    //                         $data_lain = array_values(array_filter(array_map(function ($disc) use (&$biaya_lain) {
    //                             if ($disc->harga == 0)
    //                                 return null;
    //                             $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga));
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->biaya_lains)));

    //                         // $grand_total += $biaya_lain;
    //                         // $harga_total += $biaya_lain;
    //                         $dataD->biaya_lain = count($data_lain) > 0 ? json_encode($data_lain) : null;
    //                         $dataD->total_biaya_lain = $biaya_lain;
    //                     } else {
    //                         $dataD->biaya_lain = null;
    //                         $dataD->total_biaya_lain = 0;
    //                     }

    //                     // ====================================================END BIAYA LAIN=======================================================================================


    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_group) > 0) {
    //                         $totalTransport = $harga_transport;
    //                         if (isset($payload->data_diskon->diluar_pajak)) {

    //                             if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
    //                                 $totalTransport -= $transport_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
    //                                 $totalTransport -= $perdiem_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
    //                                 $totalTransport -= $jam_;
    //                             }
    //                         }
    //                         // if($per == '2025-02') dd($harga_total + $totalTransport);
    //                         $dataD->discount_group = $data_diskon->discount_data[$indexDataDiskon]->discount_group;
    //                         $diskon_group = ((($harga_total + $totalTransport) - $biaya_lain) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_group));
    //                         $dataD->total_discount_group = $diskon_group;
    //                         $total_diskon += $diskon_group;
    //                         $harga_total = $harga_total - $diskon_group;
    //                     } else {
    //                         $dataD->discount_group = null;
    //                         $dataD->total_discount_group = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen) > 0) {
    //                         $dataD->cash_discount_persen = $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen;
    //                         $dataD->total_cash_discount_persen = (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $total_diskon += (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $harga_total = $harga_total - (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                     } else {
    //                         $dataD->cash_discount_persen = null;
    //                         $dataD->total_cash_discount_persen = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount) > 0) {
    //                         $harga_total = $harga_total - floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $dataD->cash_discount = floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $total_diskon += floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                     } else {

    //                         $dataD->cash_discount = floatval(0);
    //                     }


    //                     // CUSTOM DISKON
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->custom_discounts) && !empty($data_diskon->discount_data[$indexDataDiskon]->custom_discounts)) {
    //                         $custom_disc = array_values(array_filter(array_map(function ($disc) {
    //                             if ($disc->discount == 0)
    //                                 return null; // Tidak mengembalikan apa-apa jika discount = 0
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->custom_discounts)));

    //                         $harga_disc = 0;
    //                         foreach ($data_diskon->discount_data[$indexDataDiskon]->custom_discounts as $disc) {
    //                             $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
    //                         }

    //                         $total_diskon += $harga_disc;
    //                         $harga_total -= $harga_disc;
    //                         $dataD->custom_discount = count($custom_disc) > 0 ? json_encode($custom_disc) : null;
    //                         $dataD->total_custom_discount = $harga_disc;
    //                     } else {
    //                         $dataD->custom_discount = null;
    //                         $dataD->total_custom_discount = 0;
    //                     }
    //                     // ====================================================END CUSTOM DISKON=======================================================================================
    //                 }

    //                 //============= BIAYA PREPARASI
    //                 // dd($desc_preparasi);
    //                 $dataD->biaya_preparasi = json_encode($desc_preparasi);
    //                 $dataD->total_biaya_preparasi = $harga_preparasi;
    //                 $grand_total += $harga_preparasi;
    //                 $harga_total += $harga_preparasi;
    //                 // dd($grand_total);

    //                 //jika Transportasi di luar pajak
    //                 $biaya_akhir = 0;
    //                 $biaya_diluar_pajak = 0;
    //                 $txt = [];

    //                 if (isset($payload->data_diskon->diluar_pajak)) {
    //                     if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
    //                         // $harga_total += $transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport);
    //                         $biaya_akhir += $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $biaya_diluar_pajak += $transport;
    //                     } else {
    //                         $grand_total += $transport;
    //                         $harga_total += $transport_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
    //                         // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem);
    //                         $biaya_akhir += $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $biaya_diluar_pajak += $perdiem;
    //                     } else {
    //                         $grand_total += $perdiem;
    //                         $harga_total += $perdiem_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
    //                         // $harga_total += $jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam);
    //                         $biaya_akhir += $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $biaya_diluar_pajak += $jam;
    //                     } else {
    //                         $grand_total += $jam;
    //                         $harga_total += $jam_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
    //                         $biaya_akhir += $biaya_lain;
    //                         $biaya_diluar_pajak += $biaya_lain;
    //                     } else {
    //                         $grand_total += $biaya_lain;
    //                         $harga_total += $biaya_lain;
    //                     }
    //                 }

    //                 //Grand total sebelum kena diskon
    //                 $dataD->grand_total = $grand_total;
    //                 $dataD->total_dpp = $harga_total;

    //                 if (floatval($data_diskon->ppn) >= 0) {
    //                     $dataD->ppn = $data_diskon->ppn;
    //                     $dataD->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                     $piutang = $harga_total + ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                 }

    //                 if (floatval($data_diskon->pph) >= 0) {
    //                     $dataD->pph = $data_diskon->pph;
    //                     $dataD->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                     $piutang = $piutang - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                 }

    //                 $diluar_pajak = ['select' => $txt, 'body' => []];

    //                 if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
    //                     foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

    //                         $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                         $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                     }
    //                     $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
    //                 }

    //                 //biaya di luar pajak
    //                 $dataD->biaya_di_luar_pajak = json_encode($diluar_pajak);
    //                 $dataD->total_biaya_di_luar_pajak = $biaya_diluar_pajak;

    //                 $dataD->piutang = $piutang;
    //                 $dataD->total_discount = $total_diskon;
    //                 $biaya_akhir += $piutang;

    //                 $dataD->biaya_akhir = $biaya_akhir;

    //                 //==========================END BIAYA DI LUAR PAJAK======================================
    //                 // if($k == 11) dd($dataD);
    //                 $dataD->save();
    //                 // dump(json_decode($dataD->data_pendukung_sampling));
    //                 // dd($dataH, $dataD);
    //                 // END FOR
    //                 if ($k == ($num - 1)) {
    //                     // SUM DATA DETAIL FOR TOTAL HEADER
    //                     $Dd = DB::select(" SELECT SUM(harga_air) as harga_air, SUM(harga_udara) as harga_udara,
    //                                                 SUM(harga_emisi) as harga_emisi,
    //                                                 SUM(harga_padatan) as harga_padatan,
    //                                                 SUM(harga_swab_test) as harga_swab_test,
    //                                                 SUM(harga_tanah) as harga_tanah,
    //                                                 SUM(transportasi) as transportasi,
    //                                                 SUM(perdiem_jumlah_orang) as perdiem_jumlah_orang,
    //                                                 SUM(perdiem_jumlah_hari) as perdiem_jumlah_hari,
    //                                                 SUM(jumlah_orang_24jam) as jumlah_orang_24jam,
    //                                                 SUM(jumlah_hari_24jam) as jumlah_hari_24jam,
    //                                                 SUM(harga_transportasi) as harga_transportasi,
    //                                                 SUM(harga_transportasi_total) as harga_transportasi_total,
    //                                                 SUM(harga_personil) as harga_personil,
    //                                                 SUM(harga_perdiem_personil_total) as harga_perdiem_personil_total,
    //                                                 SUM(harga_24jam_personil) as harga_24jam_personil,
    //                                                 SUM(harga_24jam_personil_total) as harga_24jam_personil_total,
    //                                                 SUM(discount_air) as discount_air,
    //                                                 SUM(total_discount_air) as total_discount_air,
    //                                                 SUM(discount_non_air) as discount_non_air,
    //                                                 SUM(total_discount_non_air) as total_discount_non_air,
    //                                                 SUM(discount_udara) as discount_udara,
    //                                                 SUM(total_discount_udara) as total_discount_udara,
    //                                                 SUM(discount_emisi) as discount_emisi,
    //                                                 SUM(total_discount_emisi) as total_discount_emisi,
    //                                                 SUM(discount_gabungan) as discount_gabungan,
    //                                                 SUM(total_discount_gabungan) as total_discount_gabungan,
    //                                                 SUM(cash_discount_persen) as cash_discount_persen,
    //                                                 SUM(total_cash_discount_persen) as total_cash_discount_persen,
    //                                                 SUM(discount_consultant) as discount_consultant,
    //                                                 SUM(discount_group) as discount_group,
    //                                                 SUM(total_discount_group) as total_discount_group,
    //                                                 SUM(total_discount_consultant) as total_discount_consultant,
    //                                                 SUM(cash_discount) as cash_discount,
    //                                                 SUM(total_custom_discount) as total_custom_discount,
    //                                                 SUM(discount_transport) as discount_transport,
    //                                                 SUM(total_discount_transport) as total_discount_transport,
    //                                                 SUM(discount_perdiem) as discount_perdiem,
    //                                                 SUM(total_discount_perdiem) as total_discount_perdiem,
    //                                                 SUM(discount_perdiem_24jam) as discount_perdiem_24jam,
    //                                                 SUM(total_discount_perdiem_24jam) as total_discount_perdiem_24jam,
    //                                                 SUM(ppn) as ppn,
    //                                                 SUM(total_ppn) as total_ppn,
    //                                                 SUM(total_pph) as total_pph,
    //                                                 SUM(pph) as pph,
    //                                                 SUM(biaya_lain) as biaya_lain,
    //                                                 SUM(total_biaya_lain) as total_biaya_lain,
    //                                                 SUM(total_biaya_preparasi) as total_biaya_preparasi,
    //                                                 SUM(biaya_di_luar_pajak) as biaya_di_luar_pajak,
    //                                                 SUM(total_biaya_di_luar_pajak) as total_biaya_di_luar_pajak,
    //                                                 SUM(grand_total) as grand_total,
    //                                                 SUM(total_discount) as total_discount,
    //                                                 SUM(total_dpp) as total_dpp,
    //                                                 SUM(piutang) as piutang,
    //                                                 SUM(biaya_akhir) as biaya_akhir FROM request_quotation_kontrak_D WHERE id_request_quotation_kontrak_h = '$dataH->id' GROUP BY id_request_quotation_kontrak_h ");
    //                     // UPDATE HEADER DATA
    //                     // dd($Dd);
    //                     $editH = QuotationKontrakH::where('id', $dataH->id)
    //                         ->first();
    //                     $editH->syarat_ketentuan = json_encode($payload->syarat_ketentuan);
    //                     // dd($syarat);
    //                     if (isset($payload->keterangan_tambahan) && $payload->keterangan_tambahan != null)
    //                         $editH->keterangan_tambahan = json_encode($payload->keterangan_tambahan);
    //                     if ($tgl == null || !isset($tgl)) {
    //                         $tgl = date('Y-m-d', strtotime("+30 days", strtotime(DATE('Y-m-d'))));
    //                     }
    //                     // dd($Dd);
    //                     $editH->expired = $tgl;
    //                     $editH->total_harga_air = $Dd[0]->harga_air;
    //                     $editH->total_harga_udara = $Dd[0]->harga_udara;
    //                     $editH->total_harga_emisi = $Dd[0]->harga_emisi;
    //                     $editH->total_harga_padatan = $Dd[0]->harga_padatan;
    //                     $editH->total_harga_swab_test = $Dd[0]->harga_swab_test;
    //                     $editH->total_harga_tanah = $Dd[0]->harga_tanah;
    //                     $editH->transportasi = $Dd[0]->transportasi;
    //                     $editH->perdiem_jumlah_orang = $Dd[0]->perdiem_jumlah_orang;
    //                     $editH->perdiem_jumlah_hari = $Dd[0]->perdiem_jumlah_hari;
    //                     $editH->jumlah_orang_24jam = $Dd[0]->jumlah_orang_24jam;
    //                     $editH->jumlah_hari_24jam = $Dd[0]->jumlah_hari_24jam;
    //                     if (!is_null($Dd[0]->harga_transportasi))
    //                         $editH->harga_transportasi = $Dd[0]->harga_transportasi;
    //                     if (!is_null($Dd[0]->harga_transportasi_total))
    //                         $editH->harga_transportasi_total = $Dd[0]->harga_transportasi_total;
    //                     if (!is_null($Dd[0]->harga_personil))
    //                         $editH->harga_personil = $Dd[0]->harga_personil;
    //                     if (!is_null($Dd[0]->harga_perdiem_personil_total))
    //                         $editH->harga_perdiem_personil_total = $Dd[0]->harga_perdiem_personil_total;

    //                     if (!is_null($Dd[0]->harga_24jam_personil))
    //                         $editH->harga_24jam_personil = $Dd[0]->harga_24jam_personil;
    //                     if (!is_null($Dd[0]->harga_24jam_personil_total))
    //                         $editH->harga_24jam_personil_total = $Dd[0]->harga_24jam_personil_total;

    //                     $editH->total_discount_air = $Dd[0]->total_discount_air;

    //                     $editH->total_discount_non_air = $Dd[0]->total_discount_non_air;

    //                     $editH->total_discount_udara = $Dd[0]->total_discount_udara;

    //                     $editH->total_discount_emisi = $Dd[0]->total_discount_emisi;

    //                     $editH->total_discount_gabungan = $Dd[0]->total_discount_gabungan;

    //                     $editH->total_cash_discount_persen = $Dd[0]->total_cash_discount_persen;
    //                     $editH->total_discount_group = $Dd[0]->total_discount_group;
    //                     $editH->total_discount_consultant = $Dd[0]->total_discount_consultant;
    //                     if (!is_null($Dd[0]->cash_discount))
    //                         $editH->total_cash_discount = round($Dd[0]->cash_discount);

    //                     $editH->total_discount_transport = $Dd[0]->total_discount_transport;

    //                     $editH->total_discount_perdiem = $Dd[0]->total_discount_perdiem;

    //                     $editH->total_discount_perdiem_24jam = $Dd[0]->total_discount_perdiem_24jam;

    //                     $editH->total_custom_discount = $Dd[0]->total_custom_discount;

    //                     $editH->total_ppn = $Dd[0]->total_ppn;
    //                     $editH->total_pph = $Dd[0]->total_pph;

    //                     $editH->total_biaya_lain = $Dd[0]->total_biaya_lain;
    //                     $editH->total_biaya_preparasi = $Dd[0]->total_biaya_preparasi;
    //                     $editH->biaya_diluar_pajak = json_encode($diluar_pajak);
    //                     $editH->total_biaya_di_luar_pajak = $Dd[0]->total_biaya_di_luar_pajak;
    //                     $editH->grand_total = $Dd[0]->grand_total;
    //                     $editH->total_discount = $Dd[0]->total_discount;
    //                     $editH->total_dpp = $Dd[0]->total_dpp;
    //                     $editH->piutang = $Dd[0]->piutang;
    //                     $editH->biaya_akhir = $Dd[0]->biaya_akhir;
    //                     $editH->updated_by = $this->karyawan;
    //                     $editH->updated_at = date('Y-m-d H:i:s');
    //                     $editH->save();
    //                 }
    //             }

    //             // dd('stop');
    //             // DELETE DETAIL RECORD IF PERIODE NOT SAME
    //             // dump($id_det, $dataH->id, $num, $detail);
    //             if ($num < count($data_detail)) {
    //                 $deleted = QuotationKontrakD::whereNotIn('id', $id_det)->where('id_request_quotation_kontrak_h', $dataH->id)->delete();
    //             }

    //             if ($data_lama != null) {
    //                 if ($data_lama->status_sp == 'true') { //merubah jadwal dalam arti menon aktifkan SP
    //                     SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => false, 'canceled_by' => 'system']);

    //                     // SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     // Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => true]);

    //                     $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $dataH->no_document dan data yang sudah terjadwal akan di tarik otomatis oleh system dan menunggu request baru dari sales";
    //                     // Helpers::sendTelegramAtasan($message, $cek->add_by);
    //                     // Helpers::sendTelegramAtasan($message, '187');
    //                 } else if ($data_lama->status_sp == 'false') {
    //                     //tidak merubah jadwal dalam arti update no doc dalam sp
    //                     DB::beginTransaction();
    //                     try {
    //                         // ========== START Perbaikan SP dan Jadwal By: 565 ==========
    //                         // step 1. Menghitung periode kontrak pada dokumen baru
    //                         $Arrperiod = [];
    //                         foreach ($payload->data_wilayah->wilayah_data as $key => $wilayah_data) {
    //                             if ($wilayah_data->status_sampling !== 'SD') {
    //                                 foreach ($wilayah_data->periode as $key => $valuePeriode) {
    //                                     array_push($Arrperiod, $valuePeriode);
    //                                 }
    //                             }
    //                         }
    //                         $Arrperiod = array_values(array_unique($Arrperiod));

    //                         $detailLama = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get()->pluck('periode_kontrak')->unique()->toArray();

    //                         // dd($Arrperiod, $detailLama);
    //                         $perubahan_periode = $this->different($dataOld->id, $dataH->id, $detailLama, $Arrperiod, $dataH->no_document);

    //                         if ($perubahan_periode) {
    //                             foreach ($perubahan_periode as $key => $value) {
    //                                 SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode_kontrak', $value['before'])
    //                                     ->where('is_active', true)
    //                                     ->update(['periode_kontrak' => $value['after']]);

    //                                 Jadwal::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode', $value['before'])
    //                                     ->update(['periode' => $value['after']]);
    //                             }
    //                         }
    //                         // step 2. Menyesuaikan sampling plan dan jadwal dengan dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'quotation_id' => $dataH->id
    //                             ]);
    //                         Jadwal::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($dataH->nama_perusahaan)))
    //                             ]);

    //                         // step 3. Menonaktifkan sampling plan yang tidak ada di dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataH->no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'status' => false, 'status_jadwal' => 'cancel']);

    //                         // step 4. Mengambil id sampling plan yang non-aktif
    //                         $resultJadwalNonAktif = SamplingPlan::select('id', 'periode_kontrak')
    //                             ->where('no_quotation', $no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', false)
    //                             ->get();

    //                         $periodJadwalId = [];
    //                         foreach ($resultJadwalNonAktif as $key => $value) {
    //                             array_push($periodJadwalId, $value->id);
    //                         }

    //                         // step 5. Menonaktifkan jadwal yang tidak relevan dengan dokumen baru
    //                         Jadwal::where('no_quotation', $no_document)
    //                             ->whereIn('id_sampling', $periodJadwalId)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'canceled_by' => 'system']);
    //                         // ========== END Perbaikan SP dan Jadwal By: 565 ==========

    //                         DB::commit();
    //                     } catch (\Exception $ex) {
    //                         DB::rollback();
    //                         $templateMessage = "Error : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method writequotKontrakApi";
    //                         // Helpers::sendTo(843302196, $templateMessage);
    //                     }

    //                     $message = "Terjadi perubahan quotation $data_lama->no_qt menjadi $no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
    //                 }


    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     // $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
    //                     // $no_qt_lama = $cek_order->no_document;
    //                     // $no_qt_baru = $dataH->no_document;
    //                     // $id_order = $data_lama->id_order;

    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_lama', $no_qt_lama);
    //                     // $parse->where('no_qt_baru', $no_qt_baru);
    //                     // $parse->where('id_order', $id_order);
    //                     // $parse->save();
    //                     // dd($data_lama);
    //                 } else {
    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_baru', $dataH->no_document);
    //                     // $parse->where('generate', 'new');
    //                     // $parse->save();
    //                 }

    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     $invoices = Invoice::where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->get();

    //                     $invoiceNumbersTobeChecked = [];
    //                     foreach ($invoices as $invoice) {
    //                         if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
    //                             $invoice->is_generate = false;
    //                             $invoice->is_emailed = false;

    //                             $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
    //                         }
    //                         $invoice->no_quotation = $no_document;
    //                         $invoice->save();

    //                         // $render = new RenderInvoice();
    //                         // $render->renderInvoice($invoice->no_invoice);
    //                     }

    //                     $invoiceNumbersStr = implode(', ', $invoiceNumbersTobeChecked);

    //                     $message = count($invoiceNumbersTobeChecked) > 0
    //                         ? "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order. Oleh karena itu nomor invoice $invoiceNumbersStr akan dikembalikan ke menu generate invoice untuk dilakukan pengecekan."
    //                         : "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order.";

    //                     Notification::where('id_department', 5)->title('Revisi Penawaran')->message($message)->url('/generate-invoice')->send();

    //                     DB::table('persiapan_sampel_header')
    //                         ->where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->update([
    //                             'no_quotation' => $dataH->no_document,
    //                         ]);
    //                 }
    //             } else {
    //                 // $parse = new GeneratePraSampling;
    //                 // $parse->type('QTC');
    //                 // $parse->where('no_qt_baru', $dataH->no_document);
    //                 // $parse->where('generate', 'new');
    //                 // $parse->save();
    //             }
    //             // dd($dataH, $dataD);
    //             // $dataOld->document_status = 'Non Aktif';
    //             // $dataOld->is_active = false;
    //             // $dataOld->save();

    //             // $orderConfirmation = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)->where('is_active', true)->first();
    //             // if ($orderConfirmation)
    //             //     $orderConfirmation->update(['no_quotation' => $no_document]);
    //             // ===========================================

    //             $konfirmasi = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)
    //                 ->where('is_active', true)->get();
    //             // UPDATE KONFIRMASI ORDER ===================
    //             foreach ($konfirmasi as $k) {
    //                 $k->no_quotation = $dataH->no_document;
    //                 $k->id_quotation = $dataH->id;
    //                 $k->save();
    //             }

    //             JobTask::insert([
    //                 'job' => 'RenderPdfPenawaran',
    //                 'status' => 'processing',
    //                 'no_document' => $dataH->no_document,
    //                 'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
    //             ]);
    //             // dd('=====================');

    //             // $fixingPenamaanTitik = $this->changeDataPendukungSamplingKontrak([$dataH->no_document]);
    //             // $cek = QuotationKontrakD::where('id_request_quotation_kontrak_H', $dataH->id)->pluck('data_pendukung_sampling');
    //             // foreach ($cek as $item) {
    //             //     dump(json_decode($item));
    //             // }
    //             // dd('==========================================================================');

    //             // if ($fixingPenamaanTitik) {
    //             DB::commit();
    //             // } else {
    //             //     throw new \Exception('Gagal Fixing Penamaan Titik');
    //             // }

    //             $job = new RenderPdfPenawaran($dataH->id, 'kontrak');
    //             $this->dispatch($job);

    //             $array_id_user = GetAtasan::where('id', $dataH->sales_id)->get()->pluck('id')->toArray();

    //             Notification::whereIn('id', $array_id_user)
    //                 ->title('Penawaran telah di revisi')
    //                 ->message('Penawaran dengan nomor ' . $dataOld->no_document . ' telah di revisi menjadi ' . $dataH->no_document . '.')
    //                 ->url('/quote-request')
    //                 ->send();

    //             return response()->json([
    //                 'message' => "Request Quotation number $dataH->no_document has been successfully revised"
    //             ], 200);
    //         } catch (\Exception $e) {
    //             DB::rollback();
    //             // dd($e);
    //             return response()->json([
    //                 'message' => $e->getMessage(),
    //                 'line' => $e->getLine(),
    //             ], 401);
    //         }
    //     } catch (\Exception $th) {
    //         DB::rollback();
    //         return response()->json([
    //             'message' => $th->getMessage() . ' ' . $th->getLine(),
    //             'line' => $th->getLine(),
    //         ], 401);
    //     }
    // }


    // LastRevisiKontrakProduction
    // private function revisiKontrak($payload)
    // {
    //     // Implementasi untuk revisi kontrak
    //     try {
    //         $informasi_pelanggan = $payload->informasi_pelanggan;
    //         $data_pendukung = $payload->data_pendukung;
    //         $data_wilayah = $payload->data_wilayah;
    //         $syarat_ketentuan = $payload->syarat_ketentuan;
    //         $data_diskon = $payload->data_diskon;

    //         // dd($informasi_pelanggan);
    //         if (!isset($payload->informasi_pelanggan->sales_id) || $payload->informasi_pelanggan->sales_id == '') {
    //             return response()->json([
    //                 'message' => 'Sales penanggung jawab tidak boleh kosong',
    //             ], 403);
    //         }
    //         if (isset($payload->keterangan_tambahan))
    //             $keterangan_tambahan = $payload->keterangan_tambahan;
    //         DB::BeginTransaction();
    //         try {

    //             $dataOld = QuotationKontrakH::where('is_active', true)
    //                 ->where('no_document', $informasi_pelanggan->no_document)
    //                 ->first();

    //             if (isset($payload->informasi_pelanggan->new_no_document) && $payload->informasi_pelanggan->new_no_document != null) {

    //                 $no_quotation = $dataOld->no_quotation;
    //                 $no_document = $payload->informasi_pelanggan->new_no_document;

    //                 $dataOld->updated_by = $this->karyawan;
    //                 $dataOld->updated_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $dataOld->document_status = 'Non Aktif';
    //                 $dataOld->is_active = false;
    //                 $dataOld->is_emailed = true;
    //                 $dataOld->is_approved = true;
    //                 $dataOld->save();

    //                 ($dataOld->data_lama != null) ? $data_lama = json_decode($dataOld->data_lama) : $data_lama = null;

    //                 $cek_master_customer = MasterPelanggan::where('id_pelanggan', $informasi_pelanggan->pelanggan_ID)->where('is_active', true)->first();
    //                 if ($cek_master_customer != null) {
    //                     // Update kontak pelanggan
    //                     if ($informasi_pelanggan->no_tlp_perusahaan != '') {
    //                         $kontak = KontakPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->first();
    //                         if ($kontak != null) {
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         } else {
    //                             $kontak = new KontakPelanggan;
    //                             $kontak->pelanggan_id = $cek_master_customer->id;
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         }
    //                     }

    //                     // Update alamat pelanggan
    //                     if ($informasi_pelanggan->alamat_kantor != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'kantor')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'kantor';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->alamat_sampling != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'sampling')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'sampling';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_order != '') {
    //                         $picorder = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'order')->first();
    //                         if ($picorder != null) {
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         } else {
    //                             $picorder = new PicPelanggan;
    //                             $picorder->pelanggan_id = $cek_master_customer->id;
    //                             $picorder->type_pic = 'order';
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_sampling != '') {
    //                         $picsampling = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'sampling')->first();
    //                         if ($picsampling != null) {
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->jabatan_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         } else {
    //                             $picsampling = new PicPelanggan;
    //                             $picsampling->pelanggan_id = $cek_master_customer->id;
    //                             $picsampling->type_pic = 'sampling';
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->nama_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 return response()->json([
    //                     'message' => 'Ada masalah pada data silahkan hubungi tim IT.!'
    //                 ], 401);
    //             }

    //             $dataH = new QuotationKontrakH;

    //             //dataH customer order     -------------------------------------------------------> save ke master customer parrent
    //             $dataH->no_quotation = $no_quotation; //penentian nomor Quotation
    //             $dataH->no_document = $no_document;
    //             $dataH->pelanggan_ID = $dataOld->pelanggan_ID;
    //             $dataH->id_cabang = $this->idcabang;

    //             // $dataH->nama_perusahaan = $informasi_pelanggan->nama_perusahaan;
    //             // if (isset($informasi_pelanggan->konsultan) && $informasi_pelanggan->konsultan != '')
    //             // $dataH->konsultan = $informasi_pelanggan->konsultan;

    //             $dataH->nama_perusahaan = $dataOld->nama_perusahaan;
    //             $dataH->konsultan = $dataOld->konsultan;

    //             $dataH->tanggal_penawaran = $informasi_pelanggan->tgl_penawaran;

    //             if (isset($informasi_pelanggan->alamat_kantor) && $informasi_pelanggan->alamat_kantor != '')
    //                 $dataH->alamat_kantor = $informasi_pelanggan->alamat_kantor;
    //             $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //             $dataH->nama_pic_order = ucwords($informasi_pelanggan->nama_pic_order);
    //             $dataH->jabatan_pic_order = $informasi_pelanggan->jabatan_pic_order;
    //             $dataH->no_pic_order = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //             $dataH->email_pic_order = $informasi_pelanggan->email_pic_order;
    //             $dataH->email_cc = (!empty($informasi_pelanggan->email_cc) && sizeof($informasi_pelanggan->email_cc) !== 0) ? json_encode($informasi_pelanggan->email_cc) : null;
    //             $dataH->alamat_sampling = $informasi_pelanggan->alamat_sampling;
    //             // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_sampling);
    //             $dataH->nama_pic_sampling = ucwords($informasi_pelanggan->nama_pic_sampling);
    //             $dataH->jabatan_pic_sampling = $informasi_pelanggan->jabatan_pic_sampling;
    //             $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //             $dataH->email_pic_sampling = $informasi_pelanggan->email_pic_sampling;

    //             $dataH->data_pendukung_diskon = json_encode($data_diskon);
    //             $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
    //             // dd($payload);
    //             $dataH->ppn = $data_diskon->ppn;
    //             $dataH->pph = $data_diskon->pph;
    //             // Periode Kontrak
    //             $dataH->periode_kontrak_awal = $data_pendukung[0]->periodeAwal;
    //             $dataH->periode_kontrak_akhir = $data_pendukung[0]->periodeAkhir;

    //             $dataH->status_wilayah = $data_wilayah->status_Wilayah;
    //             $dataH->wilayah = $data_wilayah->wilayah;

    //             // $dataH->is_generated = $dataOld->is_generated;
    //             // $dataH->is_emailed = $dataOld->is_emailed;

    //             $uniqueStatusSampling = array_unique(array_map(function ($wilayah) {
    //                 return $wilayah->status_sampling;
    //             }, $data_wilayah->wilayah_data));

    //             $dataH->status_sampling = count($uniqueStatusSampling) === 1 ? $uniqueStatusSampling[0] : null;
    //             // $dataH->add_by = $this->userid;
    //             // $dataH->add_at = DATE('Y-m-d H:i:s');
    //             $data_transport_h = [];
    //             $data_pendukung_h = [];
    //             $data_s = [];
    //             $period = [];

    //             $data_pendukung_lain = [];
    //             $total_biaya_lain = 0;
    //             // BIAYA LAIN
    //             if (isset($data_diskon->biaya_lain) && !empty($data_diskon->biaya_lain)) {
    //                 $biaya_lains = 0;
    //                 $data_pendukung_lain = array_map(function ($disc) use (&$biaya_lains) {
    //                     $biaya_lains += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
    //                         'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
    //                     ];
    //                 }, $data_diskon->biaya_lain);
    //                 $total_biaya_lain = $biaya_lains;
    //                 $dataH->biaya_lain = json_encode($data_pendukung_lain);
    //                 $dataH->total_biaya_lain = $total_biaya_lain;
    //             } else {
    //                 $dataH->biaya_lain = null;
    //                 $dataH->total_biaya_lain = 0;
    //             }
    //             // END BIAYA LAIN
    //             //CUSTOM DISCOUNT
    //             $custom_disc = [];
    //             if (isset($data_diskon->custom_discount) && !empty($data_diskon->custom_discount)) {
    //                 $custom_disc = array_map(function ($disc) {
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                     ];
    //                 }, $data_diskon->custom_discount);
    //                 $dataH->custom_discount = json_encode($custom_disc);
    //             } else {
    //                 $dataH->custom_discount = null;
    //             }
    //             // END CUSTOM DISCOUNT

    //             foreach ($payload->data_pendukung as $i => $item) {
    //                 $param = $item->parameter;
    //                 // dd($item);
    //                 $exp = explode("-", $item->kategori_1);
    //                 $kategori = $exp[0];
    //                 $vol = 0;

    //                 $parameter = [];
    //                 foreach ($param as $par) {
    //                     $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
    //                     array_push($parameter, $cek_par->nama_lab);
    //                 }

    //                 $harga_db = [];
    //                 $volume_db = [];
    //                 foreach ($parameter as $param_) {
    //                     $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                         ->where('nama_parameter', $param_)
    //                         ->orderBy('id', 'ASC')
    //                         ->get();


    //                     $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                         return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
    //                     }) ?? $ambil_data->first();

    //                     $harga_db[] = $cek_harga_parameter->harga ?? 0;
    //                     $volume_db[] = $cek_harga_parameter->volume ?? 0;
    //                     // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                     //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
    //                     // }) ?? $ambil_data->first();

    //                     // // fix bug
    //                     // if ($cek_harga_parameter) {
    //                     //     $harga_db[] = $cek_harga_parameter->harga;
    //                     //     $volume_db[] = $cek_harga_parameter->volume;
    //                     // } else {
    //                     //     $harga_db[] = 0;
    //                     //     $volume_db[] = 0;
    //                     // }
    //                 }

    //                 $harga_pertitik = (object) [
    //                     'volume' => array_sum($volume_db),
    //                     'total_harga' => array_sum($harga_db)
    //                 ];

    //                 if ($harga_pertitik->volume != null) {
    //                     $vol += floatval($harga_pertitik->volume);
    //                 }

    //                 $titik = $item->jumlah_titik;

    //                 $temp_prearasi = [];
    //                 if ($item->biaya_preparasi != null || $item->biaya_preparasi != "") {
    //                     foreach ($item->biaya_preparasi as $pre) {
    //                         if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                             $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                         // HARGA PREPARASI UNUSED
    //                         // if($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") $harga_preparasi += floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan));
    //                     }
    //                 }
    //                 $biaya_preparasi = $temp_prearasi;

    //                 $data_sampling[$i] = [
    //                     'kategori_1' => $item->kategori_1,
    //                     'kategori_2' => $item->kategori_2,
    //                     'penamaan_titik' => $item->penamaan_titik,
    //                     'parameter' => $param,
    //                     'jumlah_titik' => $titik,
    //                     'total_parameter' => count($param),
    //                     'harga_satuan' => $harga_pertitik->total_harga,
    //                     'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
    //                     'volume' => $vol,
    //                     'periode' => $item->periode,
    //                     'biaya_preparasi' => $biaya_preparasi
    //                 ];

    //                 isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

    //                 foreach ($item->periode as $key => $v) {
    //                     array_push($period, $v);
    //                 }

    //                 array_push($data_pendukung_h, $data_sampling[$i]);
    //             }

    //             // dd($data_pendukung_h);
    //             $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);
    //             // dd($data_pendukung_h);
    //             $period_pendukung = [];

    //             for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {

    //                 $data_lain = $data_pendukung_lain;

    //                 $kalkulasi = 0;
    //                 $harga_transportasi = 0;
    //                 $harga_perdiem = 0;
    //                 $harga_perdiem24 = 0;

    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi))
    //                     $harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem))
    //                     $harga_perdiem = $data_wilayah->wilayah_data[$c]->harga_perdiem;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem24))
    //                     $harga_perdiem24 = $data_wilayah->wilayah_data[$c]->harga_perdiem24;

    //                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem))
    //                     $kalkulasi = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;
    //                 $data_lain_arr = [];
    //                 foreach ($data_lain as $dl) {
    //                     array_push($data_lain_arr, (array) $dl);
    //                 }
    //                 // dd($data_wilayah->wilayah_data[$c]);
    //                 array_push($data_transport_h, (object) ['status_sampling' => $data_wilayah->wilayah_data[$c]->status_sampling, 'jumlah_transportasi' => $data_wilayah->wilayah_data[$c]->transportasi, 'jumlah_orang_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang, 'jumlah_hari_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari, 'jumlah_orang_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam, 'jumlah_hari_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam, 'biaya_lain' => $data_lain_arr, 'harga_transportasi' => $harga_transportasi, 'harga_perdiem' => $harga_perdiem, 'harga_perdiem24' => $harga_perdiem24, 'periode' => $data_wilayah->wilayah_data[$c]->periode, 'kalkulasi_by_sistem' => $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem]);

    //                 foreach ($data_wilayah->wilayah_data[$c]->periode as $key => $v) {
    //                     array_push($period_pendukung, $v);
    //                 }
    //             }

    //             // dd($data_transport_h);

    //             $dataH->data_pendukung_lain = json_encode($data_transport_h);
    //             // $status_sampling = array_map(function ($wilayah) {
    //             //     return $wilayah->status_sampling;
    //             // }, $data_wilayah->wilayah_data);
    //             // if (count($status_sampling) == 1)
    //             //     $dataH->status_sampling = implode(',', $status_sampling);
    //             isset($syarat_ketentuan) ? $dataH->syarat_ketentuan = json_encode($syarat_ketentuan) : $dataH->syarat_ketentuan = null;
    //             isset($keterangan_tambahan) ? $dataH->keterangan_tambahan = json_encode($keterangan_tambahan) : $dataH->keterangan_tambahan = null;
    //             isset($data_diskon->diluar_pajak) ? $dataH->diluar_pajak = json_encode($data_diskon->diluar_pajak) : $dataH->diluar_pajak = null;

    //             ($data_lama != null) ? $dataH->data_lama = json_encode($data_lama) : $dataH->data_lama = null;
    //             $dataH->created_by = $dataOld->created_by;
    //             $dataH->created_at = $dataOld->created_at;
    //             $dataH->save();

    //             // END HEADER DATA
    //             // START DETAIL DATA
    //             $data_detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)
    //                 ->select('id')
    //                 ->get();
    //             // dd($data_detail);

    //             // Period Data Pendukung
    //             $period_pendukung = array_values(array_unique($period_pendukung));

    //             $period = [];
    //             foreach ($data_pendukung as $key => $v) {
    //                 foreach ($v->periode as $a) {
    //                     array_push($period, $a);
    //                 }
    //             }

    //             $period = array_values(array_unique($period));

    //             $diff1 = array_values(array_diff($period, $period_pendukung));

    //             if (count($diff1) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff1 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Wilayah Tidak Ada Di Periode Data Pendukung..! '], 500);
    //             }

    //             $diff2 = array_values(array_diff($period_pendukung, $period));

    //             if (count($diff2) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff2 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Pendukung Tidak Ada Di Periode Data Wilayah..! '], 500);
    //             }

    //             $num = count($period);
    //             $detail = count($data_detail);
    //             if ($num > 12) {
    //                 DB::rollBack();
    //                 return response()->json(['message' => 'Periode Kontrak Lebih Dari 12 Bulan'], 201);
    //             }
    //             $id_det = [];
    //             $tgl = null;
    //             // =========================================================================
    //             // STEP 1: Bongkar & Petakan Nomor Titik dari Kontrak Lama (dataOld)
    //             // =========================================================================
    //             $titikGroupMapping = [];
    //             $lastTitikNumber = 0;
    //             $periodeOld = [];
    //             // Ambil semua detail dari kontrak LAMA
    //             $detailsFromOldContract = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();

    //             foreach ($detailsFromOldContract as $detail) {
    //                 // Decode data pendukung sampling dari JSON
    //                 $dataPendukungSampling = json_decode($detail->data_pendukung_sampling, true);
    //                 // Iterasi setiap data sampling di dalamnya
    //                 if (is_array($dataPendukungSampling)) {
    //                     foreach ($dataPendukungSampling as $samplingData) {
    //                         // Ambil data sampling yang relevan
    //                         $dataSamplingList = $samplingData['data_sampling'] ?? [];
    //                         if (!is_array($dataSamplingList)) {
    //                             $dataSamplingList = json_decode($dataSamplingList, true);
    //                         }
    //                         foreach ($dataSamplingList as $item) {
    //                             $periodeOld[] = $samplingData['periode_kontrak'];

    //                             // Buat unique key berdasarkan kategori, ini patokan kita!
    //                             $groupKey = $samplingData['periode_kontrak'] . ';' . $item['kategori_1'] . ';' . $item['kategori_2'] . ';' . json_encode($item['regulasi']) . ';' . json_encode($item['parameter']);
    //                             if (!isset($titikGroupMapping[$groupKey])) {
    //                                 $titikGroupMapping[$groupKey] = [];
    //                             }

    //                             // Petakan NAMA titik ke NOMOR titik
    //                             if (isset($item['penamaan_titik']) && is_array($item['penamaan_titik'])) {
    //                                 foreach ($item['penamaan_titik'] as $titikObject) {
    //                                     $titikObject = (array) $titikObject;
    //                                     $nomor = key($titikObject);
    //                                     $nama = current($titikObject);

    //                                     // Mapping: 'Outlet' => '001'
    //                                     $titikGroupMapping[$groupKey][$nomor] = $nama;

    //                                     // Cari nomor titik tertinggi untuk counter
    //                                     if (intval($nomor) > $lastTitikNumber) {
    //                                         $lastTitikNumber = intval($nomor);
    //                                     }
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             }
    //             // dump($titikGroupMapping);

    //             // Counter untuk titik BARU akan dimulai dari nomor tertinggi + 1
    //             $globalTitikCounter = $lastTitikNumber + 1;
    //             $allDetailQuotOld = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();

    //             $biggestNumberOfSampel = 0;

    //             foreach ($allDetailQuotOld as $detail) {
    //                 $oldData = json_decode($detail->data_pendukung_sampling, true);
    //                 $first = reset($oldData)['data_sampling'];
    //                 foreach ($first as $item) {
    //                     foreach ($item['penamaan_titik'] as $titik) {
    //                         $key = array_key_first($titik); // ambil key-nya, misal "001", "004"
    //                         $number = (int) $key; // ubah ke integer, misal "004" => 4
    //                         if ($number > $biggestNumberOfSampel) {
    //                             $biggestNumberOfSampel = $number;
    //                         }
    //                     }
    //                 }
    //             }
    //             // dd($biggestNumberOfSampel);

    //             foreach ($period as $k => $per) {
    //                 // dd($per);
    //                 if (!isset($data_detail[$k]->id)) {
    //                     $id_detail = '';
    //                 } else {
    //                     $id_detail = $data_detail[$k]->id;
    //                     if ($num < $detail) {
    //                         array_push($id_det, $id_detail);
    //                     }
    //                 }

    //                 $cek = QuotationKontrakD::where('id', $id_detail)->first();

    //                 if (!is_null($cek)) {
    //                     $dataD = QuotationKontrakD::where('id', $data_detail[$k]->id)->first();
    //                 } else {
    //                     $dataD = new QuotationKontrakD;
    //                     $dataD->id_request_quotation_kontrak_h = $dataH->id;
    //                 }

    //                 // dd($period);

    //                 $data_sampling = [];
    //                 $datas = [];
    //                 $harga_total = 0;
    //                 $harga_air = 0;
    //                 $harga_udara = 0;
    //                 $harga_emisi = 0;
    //                 $harga_padatan = 0;
    //                 $harga_swab_test = 0;
    //                 $harga_tanah = 0;
    //                 $harga_pangan = 0;
    //                 $grand_total = 0;
    //                 $total_diskon = 0;
    //                 $desc_preparasi = [];
    //                 $harga_preparasi = 0;

    //                 $j = $k + 1;
    //                 $n = 0;

    //                 foreach ($data_pendukung as $m => $xyz) {
    //                     if (in_array($per, $xyz->periode)) {
    //                         $param = [];
    //                         $regulasi = '';
    //                         if ($xyz->parameter != null)
    //                             $param = $xyz->parameter;
    //                         if ($xyz->regulasi != null)
    //                             $regulasi = $xyz->regulasi;

    //                         $exp = explode("-", $xyz->kategori_1);
    //                         $kategori = $exp[0];
    //                         $vol = 0;

    //                         // GET PARAMETER NAME FOR CEK HARGA KONTRAK
    //                         $parameter = [];
    //                         $id_param = [];
    //                         foreach ($xyz->parameter as $va) {
    //                             $cek_par = DB::table('parameter')
    //                                 ->where('id', explode(';', $va)[0])->first();
    //                             array_push($parameter, $cek_par->nama_lab);
    //                             array_push($id_param, $cek_par->id);
    //                         }

    //                         $harga_db = [];
    //                         $volume_db = [];
    //                         foreach ($parameter as $ix => $param_) {

    //                             $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                                 ->where('nama_parameter', $param_)
    //                                 ->orderBy('id', 'ASC')
    //                                 ->get();
    //                             if (count($ambil_data) > 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     // $zx->tgl_order $request->tgl_order
    //                                     if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }

    //                                     if ((count($ambil_data) - 1) == $xc) {
    //                                         $zx = $ambil_data[0];
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }
    //                                 }
    //                             } else if (count($ambil_data) == 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     array_push($harga_db, $zx->harga);
    //                                     array_push($volume_db, $zx->volume);
    //                                     break;
    //                                 }
    //                             } else {
    //                                 array_push($harga_db, 0);
    //                                 array_push($volume_db, 0);
    //                             }
    //                         }

    //                         $vol_db = array_sum($volume_db);
    //                         $har_db = array_sum($harga_db);

    //                         $harga_pertitik = (object) [
    //                             'volume' => $vol_db,
    //                             'total_harga' => $har_db
    //                         ];

    //                         if ($harga_pertitik->volume != null)
    //                             $vol += floatval($harga_pertitik->volume);
    //                         if ($xyz->jumlah_titik == '') {
    //                             $reqtitik = 0;
    //                         } else {
    //                             $reqtitik = $xyz->jumlah_titik;
    //                         }

    //                         //============= BIAYA PREPARASI ==================
    //                         if ($xyz->biaya_preparasi != null || $xyz->biaya_preparasi != "") {
    //                             foreach ($xyz->biaya_preparasi as $pre) {
    //                                 if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                                     $desc_preparasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                                 if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
    //                                     $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
    //                             }
    //                         }
    //                         // =========================================================================
    //                         // STEP 2: Terapkan Nomor Titik (Lama atau Baru)
    //                         // =========================================================================
    //                         $penamaan_titik_fixed = [];

    //                         $fullGroupKey = $per . ';' . $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);
    //                         $fallbackGroupKey = $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);

    //                         $pengurangan_periode_kontrak = array_values(array_diff($periodeOld, $xyz->selectedPeriode));
    //                         $penambahan_periode_kontrak = array_values(array_diff($xyz->selectedPeriode, $periodeOld));

    //                         $key_penambahan = array_search($per, $penambahan_periode_kontrak);
    //                         $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

    //                         // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak, $key_penambahan, $periode_pengganti);
    //                         $oldNumberMappingForGroup = [];
    //                         if (isset($titikGroupMapping[$fullGroupKey])) {
    //                             $oldNumberMappingForGroup = $titikGroupMapping[$fullGroupKey];
    //                         } else {
    //                             /* Base By Penamaan Titik
    //                             if (in_array($per, $periodeOld)) {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($per) {
    //                                     return strpos($item, $per) !== false;
    //                                 });
    //                             } else {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($periode_pengganti) {
    //                                     return strpos($item, $periode_pengganti) !== false;
    //                                 });
    //                             }
    //                             // dump($tempKeyGroup);

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 $titikOld = $titikGroupMapping[$key];
    //                                 $foundNamaTitik = array_intersect($titikOld, $xyz->penamaan_titik);
    //                                 if (count($foundNamaTitik) > 0) {
    //                                     $foundKey = $key;
    //                                     break;
    //                                 }
    //                             }*/

    //                             $keyPeriod = in_array($per, $periodeOld) ? $per : $periode_pengganti;
    //                             $tempKeyGroup = array_filter(array_keys($titikGroupMapping), fn($key) => str_contains($key, $keyPeriod));

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 if (count(array_intersect($titikGroupMapping[$key], $xyz->penamaan_titik)) > 0) {
    //                                     $foundKey = $key;
    //                                     // break;
    //                                 }
    //                             }

    //                             $oldNumberMappingForGroup = isset($titikGroupMapping[$foundKey]) ? $titikGroupMapping[$foundKey] : [];

    //                             /* Only By Period
    //                             dd($titikGroupMapping[$foundKey], $xyz->penamaan_titik);
    //                             $existingPeriods = array_map(function ($key) {
    //                                 return explode(';', $key)[0];
    //                             }, array_keys($titikGroupMapping));
    //                             if (!in_array($per, $existingPeriods)) {
    //                                 foreach (array_keys($titikGroupMapping) as $key) {
    //                                     $temp = explode(';', $key);
    //                                     $period = array_shift($temp);
    //                                     $partialKey = implode(';', $temp);

    //                                     if ($partialKey === $fallbackGroupKey) {
    //                                         $oldNumberMappingForGroup = $titikGroupMapping[$key];
    //                                         break;
    //                                     }
    //                                 }
    //                             }*/
    //                         }
    //                         // dd($oldNumberMappingForGroup);
    //                         // dump($oldNumberMappingForGroup, $titikGroupMapping);
    //                         foreach ($xyz->penamaan_titik as $pt) {
    //                             // dump($pt);
    //                             $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
    //                             if (in_array($namaTitik, $oldNumberMappingForGroup) && $namaTitik != "") {
    //                                 // dump($oldNumberMappingForGroup);
    //                                 $nomor = array_search($namaTitik, $oldNumberMappingForGroup);
    //                                 $penamaan_titik_fixed[] = (object) [$nomor => $namaTitik];
    //                             } else {
    //                                 $nomorLama = null;

    //                                 foreach ($titikGroupMapping as $key => $map) {
    //                                     if (strpos($key, $fallbackGroupKey) !== false && in_array($namaTitik, $map)) {
    //                                         $nomorLama = array_search($namaTitik, $map);
    //                                         if (!array_key_exists($nomorLama, $penamaan_titik_fixed)) {
    //                                             $nomorLama = null;
    //                                         }
    //                                         break;
    //                                     }
    //                                 }
    //                                 // dump($nomorLama);

    //                                 if ($nomorLama) {
    //                                     $penamaan_titik_fixed[] = (object) [$nomorLama => $namaTitik];

    //                                     // Daftarin ke current group juga biar ke-cache
    //                                     $titikGroupMapping[$fullGroupKey][$nomorLama] = $namaTitik;
    //                                 } else {
    //                                     $nomorBaru = sprintf('%03d', $globalTitikCounter);
    //                                     $penamaan_titik_fixed[] = (object) [$nomorBaru => $namaTitik];

    //                                     $titikGroupMapping[$fullGroupKey][$nomorBaru] = $namaTitik;
    //                                     $globalTitikCounter++;
    //                                 }
    //                             }
    //                         }

    //                         // dump($penamaan_titik_fixed);

    //                         $data_sampling[$n++] = [
    //                             'kategori_1' => $xyz->kategori_1,
    //                             'kategori_2' => $xyz->kategori_2,
    //                             'regulasi' => $regulasi,
    //                             'parameter' => $param,
    //                             'jumlah_titik' => $xyz->jumlah_titik,
    //                             'penamaan_titik' => $penamaan_titik_fixed,
    //                             'total_parameter' => count($param),
    //                             'harga_satuan' => $harga_pertitik->total_harga,
    //                             'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
    //                             'volume' => $vol,
    //                             'biaya_preparasi' => $desc_preparasi
    //                         ];


    //                         // dump($per);
    //                         // dump($penamaan_titik_fixed);

    //                         // kalkulasi harga parameter sesuai titik
    //                         switch ($kategori) {
    //                             case '1':
    //                                 $harga_air += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '4':
    //                                 $harga_udara += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '5':
    //                                 $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '6':
    //                                 $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '7':
    //                                 $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '8':
    //                                 $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '9':
    //                                 $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                         }
    //                     }
    //                 }
    //                 // dump($data_sampling);
    //                 $checkOldQt = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->where('periode_kontrak', $per)->first();


    //                 // dd(json_decode($checkOldQt->data_pendukung_sampling), $data_sampling);
    //                 $oldData = json_decode($checkOldQt->data_pendukung_sampling, true);
    //                 $first = reset($oldData)['data_sampling'];
    //                 // $biggestNumberOfSampel = 0;

    //                 // foreach ($first as $item) {
    //                 //     foreach ($item['penamaan_titik'] as $titik) {
    //                 //         $key = array_key_first($titik); // ambil key-nya, misal "001", "004"
    //                 //         $number = (int) $key; // ubah ke integer, misal "004" => 4
    //                 //         if ($number > $biggestNumberOfSampel) {
    //                 //             $biggestNumberOfSampel = $number;
    //                 //         }
    //                 //     }
    //                 // }

    //                 // dd($first, $data_sampling);

    //                 foreach ($data_sampling as $index => $newItem) {

    //                     $diffRegulasi = false;
    //                     $diffKategori = false;
    //                     $diffSubKategori = false;
    //                     $diffParameter = false;
    //                     $oldItem = $first[$index] ?? null;
    //                     // dump($newItem, $oldItem, $per);
    //                     // Cek kategori_1

    //                     // if ($oldItem === null) {
    //                     //     dd($per);
    //                     // }

    //                     if ($oldItem === null) {
    //                         $penamaanBaru = array_map(function ($obj) {
    //                             return (array) $obj;
    //                         }, $newItem['penamaan_titik']);

    //                         $penamaanFix = [];

    //                         foreach ($penamaanBaru as $titikBaru) {
    //                             $valBaru = array_values($titikBaru)[0];

    //                             $biggestNumberOfSampel++;
    //                             $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                             $penamaanFix[] = [$newKey => $valBaru];
    //                         }

    //                         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                         continue;
    //                     }

    //                     if ($newItem['kategori_1'] !== $oldItem['kategori_1']) {
    //                         $diffKategori = true;
    //                     }

    //                     // Cek kategori_2
    //                     if ($newItem['kategori_2'] !== $oldItem['kategori_2']) {
    //                         $diffSubKategori = true;
    //                     }

    //                     // Cek regulasi
    //                     if (json_encode($newItem['regulasi']) !== json_encode($oldItem['regulasi'])) {
    //                         $diffRegulasi = true;
    //                     }

    //                     // Cek parameter (urutan juga dibandingkan)
    //                     if (json_encode($newItem['parameter']) !== json_encode($oldItem['parameter'])) {
    //                         $diffParameter = true;
    //                     }

    //                     // $flags = [
    //                     //     $diffKategori,
    //                     //     $diffSubKategori,
    //                     //     $diffRegulasi,
    //                     //     $diffParameter,
    //                     // ];

    //                     // $jumlahTrue = count(array_filter($flags));

    //                     $penamaanBaru = array_map(function ($obj) {
    //                         return (array) $obj;
    //                     }, $newItem['penamaan_titik']);

    //                     $penamaanLama = $oldItem['penamaan_titik'];

    //                     if ($diffKategori) {
    //                         $penamaanFix = [];

    //                         foreach ($penamaanBaru as $titikBaru) {
    //                             $valBaru = array_values($titikBaru)[0];

    //                             // Selalu generate nomor baru
    //                             $biggestNumberOfSampel++;
    //                             $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                             $penamaanFix[] = [$newKey => $valBaru];
    //                         }

    //                         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                     } else {
    //                         $penamaanFix = [];
    //                         $used = [];

    //                         // Loop data baru
    //                         foreach ($penamaanBaru as $titikBaru) {
    //                             $valBaru = array_values($titikBaru)[0];
    //                             $found = false;

    //                             // Coba cari val yang sama di penamaan lama
    //                             foreach ($penamaanLama as $titikLama) {
    //                                 $keyLama = array_key_first($titikLama);
    //                                 $valLama = array_values($titikLama)[0];

    //                                 if ((string)$valBaru === (string)$valLama && !in_array($keyLama, $used)) {
    //                                     $penamaanFix[] = [$keyLama => $valBaru];
    //                                     $used[] = $keyLama;
    //                                     $found = true;
    //                                     break;
    //                                 }
    //                             }

    //                             // Kalau tidak ditemukan di data lama, pakai nomor baru
    //                             if (!$found) {
    //                                 $biggestNumberOfSampel++;
    //                                 $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                                 $penamaanFix[] = [$newKey => $valBaru];
    //                                 $used[] = $newKey;
    //                             }
    //                         }

    //                         // Set hasil akhirnya
    //                         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                     }
    //                 }

    //                 // $deletedItems = [];

    //                 // foreach ($first as $index => $oldItem) {
    //                 //     $newItem = $data_sampling[$index] ?? null;

    //                 //     // 🔻 1. Handle pengurangan (item lama tidak ada di data baru)
    //                 //     if ($newItem === null) {
    //                 //         $deletedItems[] = $oldItem;
    //                 //         continue;
    //                 //     }

    //                 //     // 🔄 2. Handle perubahan & penambahan
    //                 //     $diffRegulasi = false;
    //                 //     $diffKategori = false;
    //                 //     $diffSubKategori = false;
    //                 //     $diffParameter = false;

    //                 //     if ($oldItem === null) {
    //                 //         // Jika oldItem tidak ada, berarti data baru (penambahan)
    //                 //         $penamaanBaru = array_map(fn($obj) => (array) $newItem['penamaan_titik'], $newItem['penamaan_titik']);
    //                 //         $penamaanFix = [];

    //                 //         foreach ($penamaanBaru as $titikBaru) {
    //                 //             $valBaru = array_values($titikBaru)[0];
    //                 //             $biggestNumberOfSampel++;
    //                 //             $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                 //             $penamaanFix[] = [$newKey => $valBaru];
    //                 //         }

    //                 //         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                 //         continue;
    //                 //     }

    //                 //     // 🔍 3. Cek perubahan
    //                 //     if ($newItem['kategori_1'] !== $oldItem['kategori_1']) $diffKategori = true;
    //                 //     if ($newItem['kategori_2'] !== $oldItem['kategori_2']) $diffSubKategori = true;
    //                 //     if (json_encode($newItem['regulasi']) !== json_encode($oldItem['regulasi'])) $diffRegulasi = true;
    //                 //     if (json_encode($newItem['parameter']) !== json_encode($oldItem['parameter'])) $diffParameter = true;

    //                 //     // 🔀 4. Penamaan titik
    //                 //     $penamaanBaru = array_map(fn($obj) => (array) $obj, $newItem['penamaan_titik']);
    //                 //     $penamaanLama = $oldItem['penamaan_titik'];

    //                 //     if ($diffKategori) {
    //                 //         $penamaanFix = [];

    //                 //         foreach ($penamaanBaru as $titikBaru) {
    //                 //             $valBaru = array_values($titikBaru)[0];
    //                 //             $biggestNumberOfSampel++;
    //                 //             $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                 //             $penamaanFix[] = [$newKey => $valBaru];
    //                 //         }

    //                 //         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                 //     } else {
    //                 //         $penamaanFix = [];
    //                 //         $used = [];

    //                 //         foreach ($penamaanBaru as $titikBaru) {
    //                 //             $valBaru = array_values($titikBaru)[0];
    //                 //             $found = false;

    //                 //             foreach ($penamaanLama as $titikLama) {
    //                 //                 $keyLama = array_key_first($titikLama);
    //                 //                 $valLama = array_values($titikLama)[0];

    //                 //                 if ((string)$valBaru === (string)$valLama && !in_array($keyLama, $used)) {
    //                 //                     $penamaanFix[] = [$keyLama => $valBaru];
    //                 //                     $used[] = $keyLama;
    //                 //                     $found = true;
    //                 //                     break;
    //                 //                 }
    //                 //             }

    //                 //             if (!$found) {
    //                 //                 $biggestNumberOfSampel++;
    //                 //                 $newKey = str_pad($biggestNumberOfSampel, 3, '0', STR_PAD_LEFT);
    //                 //                 $penamaanFix[] = [$newKey => $valBaru];
    //                 //                 $used[] = $newKey;
    //                 //             }
    //                 //         }

    //                 //         $data_sampling[$index]['penamaan_titik'] = $penamaanFix;
    //                 //     }
    //                 // }

    //                 // 🔘 Jika kamu mau log hasil pengurangan
    //                 // if (!empty($deletedItems)) {
    //                 //     // Misalnya:
    //                 //     Log::build([
    //                 //         'driver' => 'single',
    //                 //         'path' => storage_path('logs/perubahan_qt.log'),
    //                 //     ])->info('Deleted Items:', $deletedItems);
    //                 // }

    //                 // dd($data_sampling);
    //                 $datas[$j] = [
    //                     'periode_kontrak' => $per,
    //                     'data_sampling' => array_values($data_sampling)
    //                     // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
    //                 ];

    //                 $dataD->periode_kontrak = $per;
    //                 $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

    //                 $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
    //                 // end data sampling
    //                 $dataD->harga_air = $harga_air;
    //                 $dataD->harga_udara = $harga_udara;
    //                 $dataD->harga_emisi = $harga_emisi;
    //                 $dataD->harga_padatan = $harga_padatan;
    //                 $dataD->harga_swab_test = $harga_swab_test;
    //                 $dataD->harga_tanah = $harga_tanah;

    //                 // kalkulasi harga
    //                 $expOp = explode("-", $data_wilayah->wilayah);
    //                 $id_wilayah = $expOp[0];
    //                 $cekOperasional = HargaTransportasi::where('is_active', true)->where('id', $id_wilayah)->first();

    //                 // START FOR
    //                 $disc_transport = 0;
    //                 $disc_perdiem = 0;
    //                 $disc_perdiem_24 = 0;

    //                 $harga_transport = 0;
    //                 $jam = 0;
    //                 $transport = 0;
    //                 $perdiem = 0;

    //                 $data_lain = $data_pendukung_lain;
    //                 $biaya_lain = 0;

    //                 for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {
    //                     if (in_array($per, $data_wilayah->wilayah_data[$c]->periode)) {

    //                         // Menjumlahkan total % discount transport kedalam variable
    //                         if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD')
    //                             $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi;
    //                         // dd($data_wilayah->status_Wilayah);
    //                         if ($data_wilayah->status_Wilayah == 'DALAM KOTA') {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }

    //                                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem)) {
    //                                     $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true' ? $dataD->kalkulasi_by_sistem = 'on' : $dataD->kalkulasi_by_sistem = 'off';
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {
    //                                     $dataD->harga_transportasi = $cekOperasional->transportasi;
    //                                     $dataD->harga_transportasi_total = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);

    //                                     $dataD->harga_personil = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang);
    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);
    //                                     $perdiem = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '')
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     // dd($data_wilayah->wilayah_data);
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_personil);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;
    //                         } else {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;

    //                             // dd($data_wilayah->wilayah_data);
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
    //                                 //hitung harga tiket perjalanan
    //                                 $dataD->kalkulasi_by_sistem = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {

    //                                     $harga_tiket = $cekOperasional->tiket * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                     $harga_transportasi_darat = $cekOperasional->transportasi;
    //                                     $harga_penginapan = $cekOperasional->penginapan;
    //                                     $dataD->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
    //                                     $dataD->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;

    //                                     $dataD->harga_personil = $cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;

    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;
    //                                     $perdiem = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi_total);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }
    //                         }

    //                         $dataD->status_sampling = $data_wilayah->wilayah_data[$c]->status_sampling;
    //                     }
    //                 }

    //                 // =======================================================================DATA DISKON===========================================================================
    //                 // ==================================================DISKON ANALISA=============================================================================================
    //                 $isPeriodeDiskonExist = false;
    //                 $periodeNotExist = '';
    //                 $indexDataDiskon = 0;
    //                 if (count($data_diskon->discount_data) > 0) {
    //                     foreach ($data_diskon->discount_data as $d => $discount) {
    //                         if (in_array($per, $discount->periode)) {
    //                             $isPeriodeDiskonExist = true;
    //                             $indexDataDiskon = $d;
    //                             break;
    //                         }
    //                         if (!$isPeriodeDiskonExist && $d == count($data_diskon->discount_data) - 1) {
    //                             $periodeNotExist = $per;
    //                         }
    //                     }
    //                 }

    //                 if (!$isPeriodeDiskonExist) {
    //                     return response()->json(['message' => 'Periode ' . $periodeNotExist . ' tidak ditemukan pada group diskon', 'status' => '500'], 403);
    //                 }
    //                 // $indexDataDiskon == 1 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_transport) : '';
    //                 // dd($isPeriodeDiskonExist);

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_air > 0) {
    //                     // $indexDataDiskon == 0 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_air) : '';
    //                     // dd($data_diskon->discount_data[$indexDataDiskon]->discount_non_air);
    //                     $dataD->discount_air = $data_diskon->discount_data[$indexDataDiskon]->discount_air;
    //                     $dataD->total_discount_air = ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $harga_total += $harga_air - ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $total_diskon += ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));
    //                     if (floatval(\str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air)) > 10) {
    //                         $message = $dataH->no_document . ' Discount Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }
    //                 } else {
    //                     $harga_total += $harga_air;
    //                     $dataD->discount_air = null;
    //                     $dataD->total_discount_air = 0;
    //                 }

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_non_air > 0) {
    //                     $dataD->discount_non_air = $data_diskon->discount_data[$indexDataDiskon]->discount_non_air;
    //                     $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $total_diskon += ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 10) {
    //                         $message = $dataH->no_document . ' Discount Non-Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 } else {
    //                     $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->discount_non_air = null;
    //                     $dataD->total_discount_non_air = '0.00';

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) == 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_udara;
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) == 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_emisi;
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 }

    //                 // ========================================================END DISKON ANALISA========================================================================
    //                 // ====================================================DISKON TRANSPORTASI========================================================================================
    //                 $harga_total += $harga_pangan;
    //                 $transport_ = 0;
    //                 $perdiem_ = 0;
    //                 $jam_ = 0;

    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0) {
    //                         $dataD->discount_transport = $data_diskon->discount_data[$indexDataDiskon]->discount_transport;
    //                         $dataD->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         // Harga Total
    //                         // $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $transport_ = $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                     } else {
    //                         $dataD->discount_transport = null;
    //                         $dataD->total_discount_transport = 0;
    //                         $transport_ = $transport;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem) > 0) {
    //                         $dataD->discount_perdiem = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem;
    //                         $dataD->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         // $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $perdiem_ = $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                     } else {
    //                         $dataD->discount_perdiem = null;
    //                         $dataD->total_discount_perdiem = 0;
    //                         $perdiem_ = $perdiem;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam) > 0) {
    //                         $dataD->discount_perdiem_24jam = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam;
    //                         $dataD->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         // $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $jam_ = $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                     } else {
    //                         $dataD->discount_perdiem_24jam = null;
    //                         $dataD->total_discount_perdiem_24jam = 0;
    //                         $jam_ = $jam;
    //                     }
    //                 }

    //                 $harga_transport += ($transport_ + $perdiem_ + $jam_);
    //                 // ==================================================END DISKON TRANSPORTASI======================================================================================
    //                 // =======================================================DISKON GABUNGAN=========================================================================================
    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_gabungan) > 0) {
    //                         $dataD->discount_gabungan = $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan;
    //                         $dataD->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                     } else {
    //                         $dataD->discount_gabungan = null;
    //                         $dataD->total_discount_gabungan = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_consultant) > 0) {
    //                         $dataD->discount_consultant = $data_diskon->discount_data[$indexDataDiskon]->discount_consultant;
    //                         $dataD->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                     } else {
    //                         $dataD->discount_consultant = null;
    //                         $dataD->total_discount_consultant = 0;
    //                     }

    //                     // BIAYA LAIN
    //                     // $biaya_lain = 0;
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->biaya_lains) && !empty($data_diskon->discount_data[$indexDataDiskon]->biaya_lains)) {
    //                         $data_lain = array_values(array_filter(array_map(function ($disc) use (&$biaya_lain) {
    //                             if ($disc->harga == 0)
    //                                 return null;
    //                             $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga));
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->biaya_lains)));

    //                         // $grand_total += $biaya_lain;
    //                         // $harga_total += $biaya_lain;
    //                         $dataD->biaya_lain = count($data_lain) > 0 ? json_encode($data_lain) : null;
    //                         $dataD->total_biaya_lain = $biaya_lain;
    //                     } else {
    //                         $dataD->biaya_lain = null;
    //                         $dataD->total_biaya_lain = 0;
    //                     }

    //                     // ====================================================END BIAYA LAIN=======================================================================================


    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_group) > 0) {
    //                         $totalTransport = $harga_transport;
    //                         if (isset($payload->data_diskon->diluar_pajak)) {

    //                             if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
    //                                 $totalTransport -= $transport_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
    //                                 $totalTransport -= $perdiem_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
    //                                 $totalTransport -= $jam_;
    //                             }
    //                         }
    //                         // if($per == '2025-02') dd($harga_total + $totalTransport);
    //                         $dataD->discount_group = $data_diskon->discount_data[$indexDataDiskon]->discount_group;
    //                         $diskon_group = ((($harga_total + $totalTransport) - $biaya_lain) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_group));
    //                         $dataD->total_discount_group = $diskon_group;
    //                         $total_diskon += $diskon_group;
    //                         $harga_total = $harga_total - $diskon_group;
    //                     } else {
    //                         $dataD->discount_group = null;
    //                         $dataD->total_discount_group = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen) > 0) {
    //                         $dataD->cash_discount_persen = $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen;
    //                         $dataD->total_cash_discount_persen = (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $total_diskon += (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $harga_total = $harga_total - (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                     } else {
    //                         $dataD->cash_discount_persen = null;
    //                         $dataD->total_cash_discount_persen = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount) > 0) {
    //                         $harga_total = $harga_total - floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $dataD->cash_discount = floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $total_diskon += floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                     } else {

    //                         $dataD->cash_discount = floatval(0);
    //                     }


    //                     // CUSTOM DISKON
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->custom_discounts) && !empty($data_diskon->discount_data[$indexDataDiskon]->custom_discounts)) {
    //                         $custom_disc = array_values(array_filter(array_map(function ($disc) {
    //                             if ($disc->discount == 0)
    //                                 return null; // Tidak mengembalikan apa-apa jika discount = 0
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->custom_discounts)));

    //                         $harga_disc = 0;
    //                         foreach ($data_diskon->discount_data[$indexDataDiskon]->custom_discounts as $disc) {
    //                             $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
    //                         }

    //                         $total_diskon += $harga_disc;
    //                         $harga_total -= $harga_disc;
    //                         $dataD->custom_discount = count($custom_disc) > 0 ? json_encode($custom_disc) : null;
    //                         $dataD->total_custom_discount = $harga_disc;
    //                     } else {
    //                         $dataD->custom_discount = null;
    //                         $dataD->total_custom_discount = 0;
    //                     }
    //                     // ====================================================END CUSTOM DISKON=======================================================================================
    //                 }

    //                 //============= BIAYA PREPARASI
    //                 // dd($desc_preparasi);
    //                 $dataD->biaya_preparasi = json_encode($desc_preparasi);
    //                 $dataD->total_biaya_preparasi = $harga_preparasi;
    //                 $grand_total += $harga_preparasi;
    //                 $harga_total += $harga_preparasi;
    //                 // dd($grand_total);

    //                 //jika Transportasi di luar pajak
    //                 $biaya_akhir = 0;
    //                 $biaya_diluar_pajak = 0;
    //                 $txt = [];

    //                 if (isset($payload->data_diskon->diluar_pajak)) {
    //                     if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
    //                         // $harga_total += $transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport);
    //                         $biaya_akhir += $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $biaya_diluar_pajak += $transport;
    //                     } else {
    //                         $grand_total += $transport;
    //                         $harga_total += $transport_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
    //                         // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem);
    //                         $biaya_akhir += $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $biaya_diluar_pajak += $perdiem;
    //                     } else {
    //                         $grand_total += $perdiem;
    //                         $harga_total += $perdiem_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
    //                         // $harga_total += $jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam);
    //                         $biaya_akhir += $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $biaya_diluar_pajak += $jam;
    //                     } else {
    //                         $grand_total += $jam;
    //                         $harga_total += $jam_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
    //                         $biaya_akhir += $biaya_lain;
    //                         $biaya_diluar_pajak += $biaya_lain;
    //                     } else {
    //                         $grand_total += $biaya_lain;
    //                         $harga_total += $biaya_lain;
    //                     }
    //                 }

    //                 //Grand total sebelum kena diskon
    //                 $dataD->grand_total = $grand_total;
    //                 $dataD->total_dpp = $harga_total;

    //                 if (floatval($data_diskon->ppn) >= 0) {
    //                     $dataD->ppn = $data_diskon->ppn;
    //                     $dataD->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                     $piutang = $harga_total + ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                 }

    //                 if (floatval($data_diskon->pph) >= 0) {
    //                     $dataD->pph = $data_diskon->pph;
    //                     $dataD->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                     $piutang = $piutang - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                 }

    //                 $diluar_pajak = ['select' => $txt, 'body' => []];

    //                 if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
    //                     foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

    //                         $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                         $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                     }
    //                     $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
    //                 }

    //                 //biaya di luar pajak
    //                 $dataD->biaya_di_luar_pajak = json_encode($diluar_pajak);
    //                 $dataD->total_biaya_di_luar_pajak = $biaya_diluar_pajak;

    //                 $dataD->piutang = $piutang;
    //                 $dataD->total_discount = $total_diskon;
    //                 $biaya_akhir += $piutang;

    //                 $dataD->biaya_akhir = $biaya_akhir;

    //                 //==========================END BIAYA DI LUAR PAJAK======================================
    //                 // if($k == 11) dd($dataD);
    //                 $dataD->save();
    //                 // dump(json_decode($dataD->data_pendukung_sampling));
    //                 // dd($dataH, $dataD);
    //                 // END FOR
    //                 if ($k == ($num - 1)) {
    //                     // SUM DATA DETAIL FOR TOTAL HEADER
    //                     $Dd = DB::select(" SELECT SUM(harga_air) as harga_air, SUM(harga_udara) as harga_udara,
    //                                                 SUM(harga_emisi) as harga_emisi,
    //                                                 SUM(harga_padatan) as harga_padatan,
    //                                                 SUM(harga_swab_test) as harga_swab_test,
    //                                                 SUM(harga_tanah) as harga_tanah,
    //                                                 SUM(transportasi) as transportasi,
    //                                                 SUM(perdiem_jumlah_orang) as perdiem_jumlah_orang,
    //                                                 SUM(perdiem_jumlah_hari) as perdiem_jumlah_hari,
    //                                                 SUM(jumlah_orang_24jam) as jumlah_orang_24jam,
    //                                                 SUM(jumlah_hari_24jam) as jumlah_hari_24jam,
    //                                                 SUM(harga_transportasi) as harga_transportasi,
    //                                                 SUM(harga_transportasi_total) as harga_transportasi_total,
    //                                                 SUM(harga_personil) as harga_personil,
    //                                                 SUM(harga_perdiem_personil_total) as harga_perdiem_personil_total,
    //                                                 SUM(harga_24jam_personil) as harga_24jam_personil,
    //                                                 SUM(harga_24jam_personil_total) as harga_24jam_personil_total,
    //                                                 SUM(discount_air) as discount_air,
    //                                                 SUM(total_discount_air) as total_discount_air,
    //                                                 SUM(discount_non_air) as discount_non_air,
    //                                                 SUM(total_discount_non_air) as total_discount_non_air,
    //                                                 SUM(discount_udara) as discount_udara,
    //                                                 SUM(total_discount_udara) as total_discount_udara,
    //                                                 SUM(discount_emisi) as discount_emisi,
    //                                                 SUM(total_discount_emisi) as total_discount_emisi,
    //                                                 SUM(discount_gabungan) as discount_gabungan,
    //                                                 SUM(total_discount_gabungan) as total_discount_gabungan,
    //                                                 SUM(cash_discount_persen) as cash_discount_persen,
    //                                                 SUM(total_cash_discount_persen) as total_cash_discount_persen,
    //                                                 SUM(discount_consultant) as discount_consultant,
    //                                                 SUM(discount_group) as discount_group,
    //                                                 SUM(total_discount_group) as total_discount_group,
    //                                                 SUM(total_discount_consultant) as total_discount_consultant,
    //                                                 SUM(cash_discount) as cash_discount,
    //                                                 SUM(total_custom_discount) as total_custom_discount,
    //                                                 SUM(discount_transport) as discount_transport,
    //                                                 SUM(total_discount_transport) as total_discount_transport,
    //                                                 SUM(discount_perdiem) as discount_perdiem,
    //                                                 SUM(total_discount_perdiem) as total_discount_perdiem,
    //                                                 SUM(discount_perdiem_24jam) as discount_perdiem_24jam,
    //                                                 SUM(total_discount_perdiem_24jam) as total_discount_perdiem_24jam,
    //                                                 SUM(ppn) as ppn,
    //                                                 SUM(total_ppn) as total_ppn,
    //                                                 SUM(total_pph) as total_pph,
    //                                                 SUM(pph) as pph,
    //                                                 SUM(biaya_lain) as biaya_lain,
    //                                                 SUM(total_biaya_lain) as total_biaya_lain,
    //                                                 SUM(total_biaya_preparasi) as total_biaya_preparasi,
    //                                                 SUM(biaya_di_luar_pajak) as biaya_di_luar_pajak,
    //                                                 SUM(total_biaya_di_luar_pajak) as total_biaya_di_luar_pajak,
    //                                                 SUM(grand_total) as grand_total,
    //                                                 SUM(total_discount) as total_discount,
    //                                                 SUM(total_dpp) as total_dpp,
    //                                                 SUM(piutang) as piutang,
    //                                                 SUM(biaya_akhir) as biaya_akhir FROM request_quotation_kontrak_D WHERE id_request_quotation_kontrak_h = '$dataH->id' GROUP BY id_request_quotation_kontrak_h ");
    //                     // UPDATE HEADER DATA
    //                     // dd($Dd);
    //                     $editH = QuotationKontrakH::where('id', $dataH->id)
    //                         ->first();
    //                     $editH->syarat_ketentuan = json_encode($payload->syarat_ketentuan);
    //                     // dd($syarat);
    //                     if (isset($payload->keterangan_tambahan) && $payload->keterangan_tambahan != null)
    //                         $editH->keterangan_tambahan = json_encode($payload->keterangan_tambahan);
    //                     if ($tgl == null || !isset($tgl)) {
    //                         $tgl = date('Y-m-d', strtotime("+30 days", strtotime(DATE('Y-m-d'))));
    //                     }
    //                     // dd($Dd);
    //                     $editH->expired = $tgl;
    //                     $editH->total_harga_air = $Dd[0]->harga_air;
    //                     $editH->total_harga_udara = $Dd[0]->harga_udara;
    //                     $editH->total_harga_emisi = $Dd[0]->harga_emisi;
    //                     $editH->total_harga_padatan = $Dd[0]->harga_padatan;
    //                     $editH->total_harga_swab_test = $Dd[0]->harga_swab_test;
    //                     $editH->total_harga_tanah = $Dd[0]->harga_tanah;
    //                     $editH->transportasi = $Dd[0]->transportasi;
    //                     $editH->perdiem_jumlah_orang = $Dd[0]->perdiem_jumlah_orang;
    //                     $editH->perdiem_jumlah_hari = $Dd[0]->perdiem_jumlah_hari;
    //                     $editH->jumlah_orang_24jam = $Dd[0]->jumlah_orang_24jam;
    //                     $editH->jumlah_hari_24jam = $Dd[0]->jumlah_hari_24jam;
    //                     if (!is_null($Dd[0]->harga_transportasi))
    //                         $editH->harga_transportasi = $Dd[0]->harga_transportasi;
    //                     if (!is_null($Dd[0]->harga_transportasi_total))
    //                         $editH->harga_transportasi_total = $Dd[0]->harga_transportasi_total;
    //                     if (!is_null($Dd[0]->harga_personil))
    //                         $editH->harga_personil = $Dd[0]->harga_personil;
    //                     if (!is_null($Dd[0]->harga_perdiem_personil_total))
    //                         $editH->harga_perdiem_personil_total = $Dd[0]->harga_perdiem_personil_total;

    //                     if (!is_null($Dd[0]->harga_24jam_personil))
    //                         $editH->harga_24jam_personil = $Dd[0]->harga_24jam_personil;
    //                     if (!is_null($Dd[0]->harga_24jam_personil_total))
    //                         $editH->harga_24jam_personil_total = $Dd[0]->harga_24jam_personil_total;

    //                     $editH->total_discount_air = $Dd[0]->total_discount_air;

    //                     $editH->total_discount_non_air = $Dd[0]->total_discount_non_air;

    //                     $editH->total_discount_udara = $Dd[0]->total_discount_udara;

    //                     $editH->total_discount_emisi = $Dd[0]->total_discount_emisi;

    //                     $editH->total_discount_gabungan = $Dd[0]->total_discount_gabungan;

    //                     $editH->total_cash_discount_persen = $Dd[0]->total_cash_discount_persen;
    //                     $editH->total_discount_group = $Dd[0]->total_discount_group;
    //                     $editH->total_discount_consultant = $Dd[0]->total_discount_consultant;
    //                     if (!is_null($Dd[0]->cash_discount))
    //                         $editH->total_cash_discount = round($Dd[0]->cash_discount);

    //                     $editH->total_discount_transport = $Dd[0]->total_discount_transport;

    //                     $editH->total_discount_perdiem = $Dd[0]->total_discount_perdiem;

    //                     $editH->total_discount_perdiem_24jam = $Dd[0]->total_discount_perdiem_24jam;

    //                     $editH->total_custom_discount = $Dd[0]->total_custom_discount;

    //                     $editH->total_ppn = $Dd[0]->total_ppn;
    //                     $editH->total_pph = $Dd[0]->total_pph;

    //                     $editH->total_biaya_lain = $Dd[0]->total_biaya_lain;
    //                     $editH->total_biaya_preparasi = $Dd[0]->total_biaya_preparasi;
    //                     $editH->biaya_diluar_pajak = json_encode($diluar_pajak);
    //                     $editH->total_biaya_di_luar_pajak = $Dd[0]->total_biaya_di_luar_pajak;
    //                     $editH->grand_total = $Dd[0]->grand_total;
    //                     $editH->total_discount = $Dd[0]->total_discount;
    //                     $editH->total_dpp = $Dd[0]->total_dpp;
    //                     $editH->piutang = $Dd[0]->piutang;
    //                     $editH->biaya_akhir = $Dd[0]->biaya_akhir;
    //                     $editH->updated_by = $this->karyawan;
    //                     $editH->updated_at = date('Y-m-d H:i:s');
    //                     $editH->save();
    //                 }
    //             }

    //             // dd('stop');
    //             // DELETE DETAIL RECORD IF PERIODE NOT SAME
    //             // dump($id_det, $dataH->id, $num, $detail);
    //             if ($num < count($data_detail)) {
    //                 $deleted = QuotationKontrakD::whereNotIn('id', $id_det)->where('id_request_quotation_kontrak_h', $dataH->id)->delete();
    //             }

    //             if ($data_lama != null) {
    //                 if ($data_lama->status_sp == 'true') { //merubah jadwal dalam arti menon aktifkan SP
    //                     SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => false, 'canceled_by' => 'system']);

    //                     // SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     // Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => true]);

    //                     $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $dataH->no_document dan data yang sudah terjadwal akan di tarik otomatis oleh system dan menunggu request baru dari sales";
    //                     // Helpers::sendTelegramAtasan($message, $cek->add_by);
    //                     // Helpers::sendTelegramAtasan($message, '187');
    //                 } else if ($data_lama->status_sp == 'false') {
    //                     //tidak merubah jadwal dalam arti update no doc dalam sp
    //                     DB::beginTransaction();
    //                     try {
    //                         // ========== START Perbaikan SP dan Jadwal By: 565 ==========
    //                         // step 1. Menghitung periode kontrak pada dokumen baru
    //                         $Arrperiod = [];
    //                         foreach ($payload->data_wilayah->wilayah_data as $key => $wilayah_data) {
    //                             if ($wilayah_data->status_sampling !== 'SD') {
    //                                 foreach ($wilayah_data->periode as $key => $valuePeriode) {
    //                                     array_push($Arrperiod, $valuePeriode);
    //                                 }
    //                             }
    //                         }
    //                         $Arrperiod = array_values(array_unique($Arrperiod));

    //                         $detailLama = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get()->pluck('periode_kontrak')->unique()->toArray();

    //                         // dd($Arrperiod, $detailLama);
    //                         $perubahan_periode = $this->different($dataOld->id, $dataH->id, $detailLama, $Arrperiod, $dataH->no_document);

    //                         if ($perubahan_periode) {
    //                             foreach ($perubahan_periode as $key => $value) {
    //                                 SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode_kontrak', $value['before'])
    //                                     ->where('is_active', true)
    //                                     ->update(['periode_kontrak' => $value['after']]);

    //                                 Jadwal::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode', $value['before'])
    //                                     ->update(['periode' => $value['after']]);
    //                             }
    //                         }
    //                         // step 2. Menyesuaikan sampling plan dan jadwal dengan dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'quotation_id' => $dataH->id
    //                             ]);
    //                         Jadwal::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($dataH->nama_perusahaan)))
    //                             ]);

    //                         // step 3. Menonaktifkan sampling plan yang tidak ada di dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataH->no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'status' => false, 'status_jadwal' => 'cancel']);

    //                         // step 4. Mengambil id sampling plan yang non-aktif
    //                         $resultJadwalNonAktif = SamplingPlan::select('id', 'periode_kontrak')
    //                             ->where('no_quotation', $no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', false)
    //                             ->get();

    //                         $periodJadwalId = [];
    //                         foreach ($resultJadwalNonAktif as $key => $value) {
    //                             array_push($periodJadwalId, $value->id);
    //                         }

    //                         // step 5. Menonaktifkan jadwal yang tidak relevan dengan dokumen baru
    //                         Jadwal::where('no_quotation', $no_document)
    //                             ->whereIn('id_sampling', $periodJadwalId)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'canceled_by' => 'system']);
    //                         // ========== END Perbaikan SP dan Jadwal By: 565 ==========

    //                         DB::commit();
    //                     } catch (\Exception $ex) {
    //                         DB::rollback();
    //                         $templateMessage = "Error : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method writequotKontrakApi";
    //                         // Helpers::sendTo(843302196, $templateMessage);
    //                     }

    //                     $message = "Terjadi perubahan quotation $data_lama->no_qt menjadi $no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
    //                 }


    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     // $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
    //                     // $no_qt_lama = $cek_order->no_document;
    //                     // $no_qt_baru = $dataH->no_document;
    //                     // $id_order = $data_lama->id_order;

    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_lama', $no_qt_lama);
    //                     // $parse->where('no_qt_baru', $no_qt_baru);
    //                     // $parse->where('id_order', $id_order);
    //                     // $parse->save();
    //                     // dd($data_lama);
    //                 } else {
    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_baru', $dataH->no_document);
    //                     // $parse->where('generate', 'new');
    //                     // $parse->save();
    //                 }

    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     $invoices = Invoice::where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->get();

    //                     $invoiceNumbersTobeChecked = [];
    //                     foreach ($invoices as $invoice) {
    //                         if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
    //                             $invoice->is_generate = false;
    //                             $invoice->is_emailed = false;

    //                             $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
    //                         }
    //                         $invoice->no_quotation = $no_document;
    //                         $invoice->save();

    //                         // $render = new RenderInvoice();
    //                         // $render->renderInvoice($invoice->no_invoice);
    //                     }

    //                     $invoiceNumbersStr = implode(', ', $invoiceNumbersTobeChecked);

    //                     $message = count($invoiceNumbersTobeChecked) > 0
    //                         ? "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order. Oleh karena itu nomor invoice $invoiceNumbersStr akan dikembalikan ke menu generate invoice untuk dilakukan pengecekan."
    //                         : "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order.";

    //                     Notification::where('id_department', 5)->title('Revisi Penawaran')->message($message)->url('/generate-invoice')->send();

    //                     DB::table('persiapan_sampel_header')
    //                         ->where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->update([
    //                             'no_quotation' => $dataH->no_document,
    //                         ]);

    //                     $konfirmasi = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)->get();
    //                     // UPDATE KONFIRMASI ORDER ===================
    //                     foreach ($konfirmasi as $k) {
    //                         $k->no_quotation = $dataH->no_document;
    //                         $k->id_quotation = $dataH->id;
    //                         $k->save();
    //                     }
    //                 }
    //             } else {
    //                 // $parse = new GeneratePraSampling;
    //                 // $parse->type('QTC');
    //                 // $parse->where('no_qt_baru', $dataH->no_document);
    //                 // $parse->where('generate', 'new');
    //                 // $parse->save();
    //             }
    //             // dd($dataH, $dataD);
    //             // $dataOld->document_status = 'Non Aktif';
    //             // $dataOld->is_active = false;
    //             // $dataOld->save();

    //             // $orderConfirmation = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)->where('is_active', true)->first();
    //             // if ($orderConfirmation)
    //             //     $orderConfirmation->update(['no_quotation' => $no_document]);
    //             // ===========================================

    //             JobTask::insert([
    //                 'job' => 'RenderPdfPenawaran',
    //                 'status' => 'processing',
    //                 'no_document' => $dataH->no_document,
    //                 'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
    //             ]);
    //             // dd('=====================');

    //             $fixingPenamaanTitik = $this->changeDataPendukungSamplingKontrak([$dataH->no_document]);
    //             // $cek = QuotationKontrakD::where('id_request_quotation_kontrak_H', $dataH->id)->pluck('data_pendukung_sampling');
    //             // foreach ($cek as $item) {
    //             //     dump(json_decode($item));
    //             // }
    //             // dd('==========================================================================');

    //             if ($fixingPenamaanTitik) {
    //                 DB::commit();
    //             } else {
    //                 throw new \Exception('Gagal Fixing Penamaan Titik');
    //             }

    //             $job = new RenderPdfPenawaran($dataH->id, 'kontrak');
    //             $this->dispatch($job);

    //             $array_id_user = GetAtasan::where('id', $dataH->sales_id)->get()->pluck('id')->toArray();

    //             Notification::whereIn('id', $array_id_user)
    //                 ->title('Penawaran telah di revisi')
    //                 ->message('Penawaran dengan nomor ' . $dataOld->no_document . ' telah di revisi menjadi ' . $dataH->no_document . '.')
    //                 ->url('/quote-request')
    //                 ->send();

    //             return response()->json([
    //                 'message' => "Request Quotation number $dataH->no_document has been successfully revised"
    //             ], 200);
    //         } catch (\Exception $e) {
    //             DB::rollback();
    //             // dd($e);
    //             return response()->json([
    //                 'message' => $e->getMessage(),
    //                 'line' => $e->getLine(),
    //             ], 401);
    //         }
    //     } catch (\Exception $th) {
    //         DB::rollback();
    //         return response()->json([
    //             'message' => $th->getMessage() . ' ' . $th->getLine(),
    //             'line' => $th->getLine(),
    //         ], 401);
    //     }
    // }

    // private function revisiKontrak($payload)
    // {
    //     // Implementasi untuk revisi kontrak
    //     try {
    //         $informasi_pelanggan = $payload->informasi_pelanggan;
    //         $data_pendukung = $payload->data_pendukung;
    //         $data_wilayah = $payload->data_wilayah;
    //         $syarat_ketentuan = $payload->syarat_ketentuan;
    //         $data_diskon = $payload->data_diskon;

    //         // dd($informasi_pelanggan);
    //         if (!isset($payload->informasi_pelanggan->sales_id) || $payload->informasi_pelanggan->sales_id == '') {
    //             return response()->json([
    //                 'message' => 'Sales penanggung jawab tidak boleh kosong',
    //             ], 403);
    //         }
    //         if (isset($payload->keterangan_tambahan))
    //             $keterangan_tambahan = $payload->keterangan_tambahan;
    //         DB::BeginTransaction();
    //         try {

    //             $dataOld = QuotationKontrakH::where('is_active', true)
    //                 ->where('no_document', $informasi_pelanggan->no_document)
    //                 ->first();

    //             if (isset($payload->informasi_pelanggan->new_no_document) && $payload->informasi_pelanggan->new_no_document != null) {

    //                 $no_quotation = $dataOld->no_quotation;
    //                 $no_document = $payload->informasi_pelanggan->new_no_document;

    //                 $dataOld->updated_by = $this->karyawan;
    //                 $dataOld->updated_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $dataOld->document_status = 'Non Aktif';
    //                 $dataOld->is_active = false;
    //                 $dataOld->is_emailed = true;
    //                 $dataOld->is_approved = true;
    //                 $dataOld->save();

    //                 ($dataOld->data_lama != null) ? $data_lama = json_decode($dataOld->data_lama) : $data_lama = null;

    //                 $cek_master_customer = MasterPelanggan::where('id_pelanggan', $informasi_pelanggan->pelanggan_ID)->where('is_active', true)->first();
    //                 if ($cek_master_customer != null) {
    //                     // Update kontak pelanggan
    //                     if ($informasi_pelanggan->no_tlp_perusahaan != '') {
    //                         $kontak = KontakPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->first();
    //                         if ($kontak != null) {
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         } else {
    //                             $kontak = new KontakPelanggan;
    //                             $kontak->pelanggan_id = $cek_master_customer->id;
    //                             $kontak->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //                             if ($informasi_pelanggan->email_pic_order != '')
    //                                 $kontak->email_perusahaan = $informasi_pelanggan->email_pic_order;
    //                             $kontak->save();
    //                         }
    //                     }

    //                     // Update alamat pelanggan
    //                     if ($informasi_pelanggan->alamat_kantor != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'kantor')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'kantor';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_kantor;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->alamat_sampling != '') {
    //                         $alamat = AlamatPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_alamat', 'sampling')->first();
    //                         if ($alamat != null) {
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         } else {
    //                             $alamat = new AlamatPelanggan;
    //                             $alamat->pelanggan_id = $cek_master_customer->id;
    //                             $alamat->type_alamat = 'sampling';
    //                             $alamat->alamat = $informasi_pelanggan->alamat_sampling;
    //                             $alamat->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_order != '') {
    //                         $picorder = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'order')->first();
    //                         if ($picorder != null) {
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         } else {
    //                             $picorder = new PicPelanggan;
    //                             $picorder->pelanggan_id = $cek_master_customer->id;
    //                             $picorder->type_pic = 'order';
    //                             $picorder->nama_pic = $informasi_pelanggan->nama_pic_order;
    //                             if ($informasi_pelanggan->jabatan_pic_order != '')
    //                                 $picorder->jabatan_pic = $informasi_pelanggan->jabatan_pic_order;
    //                             $picorder->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //                             $picorder->email_pic = $informasi_pelanggan->nama_pic_order;
    //                             $picorder->save();
    //                         }
    //                     }

    //                     if ($informasi_pelanggan->nama_pic_sampling != '') {
    //                         $picsampling = PicPelanggan::where('pelanggan_id', $cek_master_customer->id)->where('is_active', true)->where('type_pic', 'sampling')->first();
    //                         if ($picsampling != null) {
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->jabatan_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         } else {
    //                             $picsampling = new PicPelanggan;
    //                             $picsampling->pelanggan_id = $cek_master_customer->id;
    //                             $picsampling->type_pic = 'sampling';
    //                             $picsampling->nama_pic = $informasi_pelanggan->nama_pic_sampling;
    //                             if ($informasi_pelanggan->nama_pic_sampling != '')
    //                                 $picsampling->jabatan_pic = $informasi_pelanggan->jabatan_pic_sampling;
    //                             $picsampling->no_tlp_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             $picsampling->wa_pic = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //                             if ($informasi_pelanggan->email_pic_sampling != '')
    //                                 $picsampling->email_pic = $informasi_pelanggan->email_pic_sampling;
    //                             $picsampling->save();
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 return response()->json([
    //                     'message' => 'Ada masalah pada data silahkan hubungi tim IT.!'
    //                 ], 401);
    //             }

    //             $dataH = new QuotationKontrakH;

    //             //dataH customer order     -------------------------------------------------------> save ke master customer parrent
    //             $dataH->no_quotation = $no_quotation; //penentian nomor Quotation
    //             $dataH->no_document = $no_document;
    //             $dataH->pelanggan_ID = $dataOld->pelanggan_ID;
    //             $dataH->id_cabang = $this->idcabang;

    //             // $dataH->nama_perusahaan = $informasi_pelanggan->nama_perusahaan;
    //             // if (isset($informasi_pelanggan->konsultan) && $informasi_pelanggan->konsultan != '')
    //             // $dataH->konsultan = $informasi_pelanggan->konsultan;

    //             $dataH->nama_perusahaan = $dataOld->nama_perusahaan;
    //             $dataH->konsultan = $dataOld->konsultan;

    //             $dataH->tanggal_penawaran = $informasi_pelanggan->tgl_penawaran;

    //             if (isset($informasi_pelanggan->alamat_kantor) && $informasi_pelanggan->alamat_kantor != '')
    //                 $dataH->alamat_kantor = $informasi_pelanggan->alamat_kantor;
    //             $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_perusahaan);
    //             $dataH->nama_pic_order = ucwords($informasi_pelanggan->nama_pic_order);
    //             $dataH->jabatan_pic_order = $informasi_pelanggan->jabatan_pic_order;
    //             $dataH->no_pic_order = \str_replace(["-", "_"], "", $informasi_pelanggan->no_pic_order);
    //             $dataH->email_pic_order = $informasi_pelanggan->email_pic_order;
    //             $dataH->email_cc = (!empty($informasi_pelanggan->email_cc) && sizeof($informasi_pelanggan->email_cc) !== 0) ? json_encode($informasi_pelanggan->email_cc) : null;
    //             $dataH->alamat_sampling = $informasi_pelanggan->alamat_sampling;
    //             // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $informasi_pelanggan->no_tlp_sampling);
    //             $dataH->nama_pic_sampling = ucwords($informasi_pelanggan->nama_pic_sampling);
    //             $dataH->jabatan_pic_sampling = $informasi_pelanggan->jabatan_pic_sampling;
    //             $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $informasi_pelanggan->no_tlp_pic_sampling);
    //             $dataH->email_pic_sampling = $informasi_pelanggan->email_pic_sampling;

    //             $dataH->data_pendukung_diskon = json_encode($data_diskon);
    //             $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
    //             // dd($payload);
    //             $dataH->ppn = $data_diskon->ppn;
    //             $dataH->pph = $data_diskon->pph;
    //             // Periode Kontrak
    //             $dataH->periode_kontrak_awal = $data_pendukung[0]->periodeAwal;
    //             $dataH->periode_kontrak_akhir = $data_pendukung[0]->periodeAkhir;

    //             $dataH->status_wilayah = $data_wilayah->status_Wilayah;
    //             $dataH->wilayah = $data_wilayah->wilayah;

    //             // $dataH->is_generated = $dataOld->is_generated;
    //             // $dataH->is_emailed = $dataOld->is_emailed;

    //             $uniqueStatusSampling = array_unique(array_map(function ($wilayah) {
    //                 return $wilayah->status_sampling;
    //             }, $data_wilayah->wilayah_data));

    //             $dataH->status_sampling = count($uniqueStatusSampling) === 1 ? $uniqueStatusSampling[0] : null;
    //             // $dataH->add_by = $this->userid;
    //             // $dataH->add_at = DATE('Y-m-d H:i:s');
    //             $data_transport_h = [];
    //             $data_pendukung_h = [];
    //             $data_s = [];
    //             $period = [];

    //             $data_pendukung_lain = [];
    //             $total_biaya_lain = 0;
    //             // BIAYA LAIN
    //             if (isset($data_diskon->biaya_lain) && !empty($data_diskon->biaya_lain)) {
    //                 $biaya_lains = 0;
    //                 $data_pendukung_lain = array_map(function ($disc) use (&$biaya_lains) {
    //                     $biaya_lains += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya));
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga)),
    //                         'total_biaya' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->total_biaya))
    //                     ];
    //                 }, $data_diskon->biaya_lain);
    //                 $total_biaya_lain = $biaya_lains;
    //                 $dataH->biaya_lain = json_encode($data_pendukung_lain);
    //                 $dataH->total_biaya_lain = $total_biaya_lain;
    //             } else {
    //                 $dataH->biaya_lain = null;
    //                 $dataH->total_biaya_lain = 0;
    //             }
    //             // END BIAYA LAIN
    //             //CUSTOM DISCOUNT
    //             $custom_disc = [];
    //             if (isset($data_diskon->custom_discount) && !empty($data_diskon->custom_discount)) {
    //                 $custom_disc = array_map(function ($disc) {
    //                     return (object) [
    //                         'deskripsi' => $disc->deskripsi,
    //                         'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                     ];
    //                 }, $data_diskon->custom_discount);
    //                 $dataH->custom_discount = json_encode($custom_disc);
    //             } else {
    //                 $dataH->custom_discount = null;
    //             }
    //             // END CUSTOM DISCOUNT

    //             foreach ($payload->data_pendukung as $i => $item) {
    //                 $param = $item->parameter;
    //                 // dd($item);
    //                 $exp = explode("-", $item->kategori_1);
    //                 $kategori = $exp[0];
    //                 $vol = 0;

    //                 $parameter = [];
    //                 foreach ($param as $par) {
    //                     $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
    //                     array_push($parameter, $cek_par->nama_lab);
    //                 }

    //                 $harga_db = [];
    //                 $volume_db = [];
    //                 foreach ($parameter as $param_) {
    //                     $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                         ->where('nama_parameter', $param_)
    //                         ->orderBy('id', 'ASC')
    //                         ->get();


    //                     $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                         return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
    //                     }) ?? $ambil_data->first();

    //                     $harga_db[] = $cek_harga_parameter->harga ?? 0;
    //                     $volume_db[] = $cek_harga_parameter->volume ?? 0;
    //                     // $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
    //                     //     return explode(' ', $item->created_at)[0] <= $payload->informasi_pelanggan->tgl_penawaran;
    //                     // }) ?? $ambil_data->first();

    //                     // // fix bug
    //                     // if ($cek_harga_parameter) {
    //                     //     $harga_db[] = $cek_harga_parameter->harga;
    //                     //     $volume_db[] = $cek_harga_parameter->volume;
    //                     // } else {
    //                     //     $harga_db[] = 0;
    //                     //     $volume_db[] = 0;
    //                     // }
    //                 }

    //                 $harga_pertitik = (object) [
    //                     'volume' => array_sum($volume_db),
    //                     'total_harga' => array_sum($harga_db)
    //                 ];

    //                 if ($harga_pertitik->volume != null) {
    //                     $vol += floatval($harga_pertitik->volume);
    //                 }

    //                 $titik = $item->jumlah_titik;

    //                 $temp_prearasi = [];
    //                 if ($item->biaya_preparasi != null || $item->biaya_preparasi != "") {
    //                     foreach ($item->biaya_preparasi as $pre) {
    //                         if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                             $temp_prearasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                         // HARGA PREPARASI UNUSED
    //                         // if($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") $harga_preparasi += floatval(\str_replace(['Rp. ', ','], '', $pre->biaya_preparasi_padatan));
    //                     }
    //                 }
    //                 $biaya_preparasi = $temp_prearasi;

    //                 $data_sampling[$i] = [
    //                     'kategori_1' => $item->kategori_1,
    //                     'kategori_2' => $item->kategori_2,
    //                     'penamaan_titik' => $item->penamaan_titik,
    //                     'parameter' => $param,
    //                     'jumlah_titik' => $titik,
    //                     'total_parameter' => count($param),
    //                     'harga_satuan' => $harga_pertitik->total_harga,
    //                     'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
    //                     'volume' => $vol,
    //                     'periode' => $item->periode,
    //                     'biaya_preparasi' => $biaya_preparasi
    //                 ];

    //                 isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

    //                 foreach ($item->periode as $key => $v) {
    //                     array_push($period, $v);
    //                 }

    //                 array_push($data_pendukung_h, $data_sampling[$i]);
    //             }

    //             // dd($data_pendukung_h);
    //             $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);
    //             // dd($data_pendukung_h);
    //             $period_pendukung = [];

    //             for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {

    //                 $data_lain = $data_pendukung_lain;

    //                 $kalkulasi = 0;
    //                 $harga_transportasi = 0;
    //                 $harga_perdiem = 0;
    //                 $harga_perdiem24 = 0;

    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi))
    //                     $harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem))
    //                     $harga_perdiem = $data_wilayah->wilayah_data[$c]->harga_perdiem;
    //                 if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem24))
    //                     $harga_perdiem24 = $data_wilayah->wilayah_data[$c]->harga_perdiem24;

    //                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem))
    //                     $kalkulasi = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;
    //                 $data_lain_arr = [];
    //                 foreach ($data_lain as $dl) {
    //                     array_push($data_lain_arr, (array) $dl);
    //                 }
    //                 // dd($data_wilayah->wilayah_data[$c]);
    //                 array_push($data_transport_h, (object) ['status_sampling' => $data_wilayah->wilayah_data[$c]->status_sampling, 'jumlah_transportasi' => $data_wilayah->wilayah_data[$c]->transportasi, 'jumlah_orang_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang, 'jumlah_hari_perdiem' => $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari, 'jumlah_orang_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam, 'jumlah_hari_24jam' => $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam, 'biaya_lain' => $data_lain_arr, 'harga_transportasi' => $harga_transportasi, 'harga_perdiem' => $harga_perdiem, 'harga_perdiem24' => $harga_perdiem24, 'periode' => $data_wilayah->wilayah_data[$c]->periode, 'kalkulasi_by_sistem' => $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem]);

    //                 foreach ($data_wilayah->wilayah_data[$c]->periode as $key => $v) {
    //                     array_push($period_pendukung, $v);
    //                 }
    //             }

    //             // dd($data_transport_h);

    //             $dataH->data_pendukung_lain = json_encode($data_transport_h);
    //             // $status_sampling = array_map(function ($wilayah) {
    //             //     return $wilayah->status_sampling;
    //             // }, $data_wilayah->wilayah_data);
    //             // if (count($status_sampling) == 1)
    //             //     $dataH->status_sampling = implode(',', $status_sampling);
    //             isset($syarat_ketentuan) ? $dataH->syarat_ketentuan = json_encode($syarat_ketentuan) : $dataH->syarat_ketentuan = null;
    //             isset($keterangan_tambahan) ? $dataH->keterangan_tambahan = json_encode($keterangan_tambahan) : $dataH->keterangan_tambahan = null;
    //             isset($data_diskon->diluar_pajak) ? $dataH->diluar_pajak = json_encode($data_diskon->diluar_pajak) : $dataH->diluar_pajak = null;

    //             ($data_lama != null) ? $dataH->data_lama = json_encode($data_lama) : $dataH->data_lama = null;
    //             $dataH->created_by = $dataOld->created_by;
    //             $dataH->created_at = $dataOld->created_at;
    //             $dataH->save();

    //             // END HEADER DATA
    //             // START DETAIL DATA
    //             $data_detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataH->id)
    //                 ->select('id')
    //                 ->get();
    //             // dd($data_detail);

    //             // Period Data Pendukung
    //             $period_pendukung = array_values(array_unique($period_pendukung));

    //             $period = [];
    //             foreach ($data_pendukung as $key => $v) {
    //                 foreach ($v->periode as $a) {
    //                     array_push($period, $a);
    //                 }
    //             }

    //             $period = array_values(array_unique($period));

    //             $diff1 = array_values(array_diff($period, $period_pendukung));

    //             if (count($diff1) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff1 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Wilayah Tidak Ada Di Periode Data Pendukung..! '], 500);
    //             }

    //             $diff2 = array_values(array_diff($period_pendukung, $period));

    //             if (count($diff2) != 0) {
    //                 DB::rollBack();
    //                 $periode = [];
    //                 foreach ($diff2 as $k => $val) {
    //                     array_push($periode, self::tanggal_indonesia($val, 'period'));
    //                 }

    //                 return response()->json(['message' => 'Periode : ' . implode(",", $periode) . ' Pada Data Pendukung Tidak Ada Di Periode Data Wilayah..! '], 500);
    //             }

    //             $num = count($period);
    //             $detail = count($data_detail);
    //             if ($num > 12) {
    //                 DB::rollBack();
    //                 return response()->json(['message' => 'Periode Kontrak Lebih Dari 12 Bulan'], 201);
    //             }
    //             $id_det = [];
    //             $tgl = null;

    //             // =========================================================================
    //             // STEP 1: Bongkar & Petakan Nomor Titik dari Kontrak Lama (dataOld)
    //             // =========================================================================
    //             $titikGroupMapping = [];
    //             $lastTitikNumber = 0;
    //             $periodeOld = [];
    //             // Ambil semua detail dari kontrak LAMA
    //             $detailsFromOldContract = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get();

    //             foreach ($detailsFromOldContract as $detail) {
    //                 // Decode data pendukung sampling dari JSON
    //                 $dataPendukungSampling = json_decode($detail->data_pendukung_sampling, true);
    //                 // Iterasi setiap data sampling di dalamnya
    //                 if (is_array($dataPendukungSampling)) {
    //                     foreach ($dataPendukungSampling as $samplingData) {
    //                         // Ambil data sampling yang relevan
    //                         $dataSamplingList = $samplingData['data_sampling'] ?? [];
    //                         foreach ($dataSamplingList as $item) {
    //                             $periodeOld[] = $samplingData['periode_kontrak'];

    //                             // Buat unique key berdasarkan kategori, ini patokan kita!
    //                             $groupKey = $samplingData['periode_kontrak'] . ';' . $item['kategori_1'] . ';' . $item['kategori_2'] . ';' . json_encode($item['regulasi']) . ';' . json_encode($item['parameter']);
    //                             if (!isset($titikGroupMapping[$groupKey])) {
    //                                 $titikGroupMapping[$groupKey] = [];
    //                             }

    //                             // Petakan NAMA titik ke NOMOR titik
    //                             if (isset($item['penamaan_titik']) && is_array($item['penamaan_titik'])) {
    //                                 foreach ($item['penamaan_titik'] as $titikObject) {
    //                                     $titikObject = (array) $titikObject;
    //                                     $nomor = key($titikObject);
    //                                     $nama = current($titikObject);

    //                                     // Mapping: 'Outlet' => '001'
    //                                     $titikGroupMapping[$groupKey][$nomor] = $nama;

    //                                     // Cari nomor titik tertinggi untuk counter
    //                                     if (intval($nomor) > $lastTitikNumber) {
    //                                         $lastTitikNumber = intval($nomor);
    //                                     }
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             // Counter untuk titik BARU akan dimulai dari nomor tertinggi + 1
    //             $globalTitikCounter = $lastTitikNumber + 1;
    //             foreach ($period as $k => $per) {
    //                 // dd($per);
    //                 if (!isset($data_detail[$k]->id)) {
    //                     $id_detail = '';
    //                 } else {
    //                     $id_detail = $data_detail[$k]->id;
    //                     if ($num < $detail) {
    //                         array_push($id_det, $id_detail);
    //                     }
    //                 }

    //                 $cek = QuotationKontrakD::where('id', $id_detail)->first();

    //                 if (!is_null($cek)) {
    //                     $dataD = QuotationKontrakD::where('id', $data_detail[$k]->id)->first();
    //                 } else {
    //                     $dataD = new QuotationKontrakD;
    //                     $dataD->id_request_quotation_kontrak_h = $dataH->id;
    //                 }

    //                 // dd($period);

    //                 $data_sampling = [];
    //                 $datas = [];
    //                 $harga_total = 0;
    //                 $harga_air = 0;
    //                 $harga_udara = 0;
    //                 $harga_emisi = 0;
    //                 $harga_padatan = 0;
    //                 $harga_swab_test = 0;
    //                 $harga_tanah = 0;
    //                 $harga_pangan = 0;
    //                 $grand_total = 0;
    //                 $total_diskon = 0;
    //                 $desc_preparasi = [];
    //                 $harga_preparasi = 0;

    //                 $j = $k + 1;
    //                 $n = 0;

    //                 foreach ($data_pendukung as $m => $xyz) {
    //                     if (in_array($per, $xyz->periode)) {
    //                         $param = [];
    //                         $regulasi = '';
    //                         if ($xyz->parameter != null)
    //                             $param = $xyz->parameter;
    //                         if ($xyz->regulasi != null)
    //                             $regulasi = $xyz->regulasi;

    //                         $exp = explode("-", $xyz->kategori_1);
    //                         $kategori = $exp[0];
    //                         $vol = 0;

    //                         // GET PARAMETER NAME FOR CEK HARGA KONTRAK
    //                         $parameter = [];
    //                         $id_param = [];
    //                         foreach ($xyz->parameter as $va) {
    //                             $cek_par = DB::table('parameter')
    //                                 ->where('id', explode(';', $va)[0])->first();
    //                             array_push($parameter, $cek_par->nama_lab);
    //                             array_push($id_param, $cek_par->id);
    //                         }

    //                         $harga_db = [];
    //                         $volume_db = [];
    //                         foreach ($parameter as $ix => $param_) {

    //                             $ambil_data = HargaParameter::where('id_kategori', $kategori)
    //                                 ->where('nama_parameter', $param_)
    //                                 ->orderBy('id', 'ASC')
    //                                 ->get();
    //                             if (count($ambil_data) > 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     // $zx->tgl_order $request->tgl_order
    //                                     if (\explode(' ', $zx->created_at)[0] > $informasi_pelanggan->tgl_penawaran) {
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }

    //                                     if ((count($ambil_data) - 1) == $xc) {
    //                                         $zx = $ambil_data[0];
    //                                         array_push($harga_db, $zx->harga);
    //                                         array_push($volume_db, $zx->volume);
    //                                         break;
    //                                     }
    //                                 }
    //                             } else if (count($ambil_data) == 1) {
    //                                 foreach ($ambil_data as $xc => $zx) {
    //                                     array_push($harga_db, $zx->harga);
    //                                     array_push($volume_db, $zx->volume);
    //                                     break;
    //                                 }
    //                             } else {
    //                                 array_push($harga_db, 0);
    //                                 array_push($volume_db, 0);
    //                             }
    //                         }

    //                         $vol_db = array_sum($volume_db);
    //                         $har_db = array_sum($harga_db);

    //                         $harga_pertitik = (object) [
    //                             'volume' => $vol_db,
    //                             'total_harga' => $har_db
    //                         ];

    //                         if ($harga_pertitik->volume != null)
    //                             $vol += floatval($harga_pertitik->volume);
    //                         if ($xyz->jumlah_titik == '') {
    //                             $reqtitik = 0;
    //                         } else {
    //                             $reqtitik = $xyz->jumlah_titik;
    //                         }

    //                         //============= BIAYA PREPARASI ==================
    //                         if ($xyz->biaya_preparasi != null || $xyz->biaya_preparasi != "") {
    //                             foreach ($xyz->biaya_preparasi as $pre) {
    //                                 if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null)
    //                                     $desc_preparasi[] = ['Deskripsi' => $pre->desc_preparasi, 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))];
    //                                 if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "")
    //                                     $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
    //                             }
    //                         }

    //                         // =========================================================================
    //                         // STEP 2: Terapkan Nomor Titik (Lama atau Baru)
    //                         // =========================================================================
    //                         $penamaan_titik_fixed = [];

    //                         $fullGroupKey = $per . ';' . $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);
    //                         $fallbackGroupKey = $xyz->kategori_1 . ';' . $xyz->kategori_2 . ';' . json_encode($xyz->regulasi) . ';' . json_encode($xyz->parameter);

    //                         $pengurangan_periode_kontrak = array_values(array_diff($periodeOld, $xyz->selectedPeriode));
    //                         $penambahan_periode_kontrak = array_values(array_diff($xyz->selectedPeriode, $periodeOld));

    //                         $key_penambahan = array_search($per, $penambahan_periode_kontrak);
    //                         $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

    //                         // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak, $key_penambahan, $periode_pengganti);
    //                         $oldNumberMappingForGroup = [];
    //                         if (isset($titikGroupMapping[$fullGroupKey])) {
    //                             $oldNumberMappingForGroup = $titikGroupMapping[$fullGroupKey];
    //                         } else {
    //                             /* Base By Penamaan Titik
    //                             if (in_array($per, $periodeOld)) {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($per) {
    //                                     return strpos($item, $per) !== false;
    //                                 });
    //                             } else {
    //                                 $tempKeyGroup = array_filter(array_keys($titikGroupMapping), function ($item) use ($periode_pengganti) {
    //                                     return strpos($item, $periode_pengganti) !== false;
    //                                 });
    //                             }
    //                             // dump($tempKeyGroup);

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 $titikOld = $titikGroupMapping[$key];
    //                                 $foundNamaTitik = array_intersect($titikOld, $xyz->penamaan_titik);
    //                                 if (count($foundNamaTitik) > 0) {
    //                                     $foundKey = $key;
    //                                     break;
    //                                 }
    //                             }*/

    //                             $keyPeriod = in_array($per, $periodeOld) ? $per : $periode_pengganti;
    //                             $tempKeyGroup = array_filter(array_keys($titikGroupMapping), fn($key) => str_contains($key, $keyPeriod));

    //                             $foundKey = null;
    //                             foreach ($tempKeyGroup as $key) {
    //                                 if (count(array_intersect($titikGroupMapping[$key], $xyz->penamaan_titik)) > 0) {
    //                                     $foundKey = $key;
    //                                     // break;
    //                                 }
    //                             }

    //                             $oldNumberMappingForGroup = isset($titikGroupMapping[$foundKey]) ? $titikGroupMapping[$foundKey] : [];

    //                             /* Only By Period
    //                             dd($titikGroupMapping[$foundKey], $xyz->penamaan_titik);
    //                             $existingPeriods = array_map(function ($key) {
    //                                 return explode(';', $key)[0];
    //                             }, array_keys($titikGroupMapping));
    //                             if (!in_array($per, $existingPeriods)) {
    //                                 foreach (array_keys($titikGroupMapping) as $key) {
    //                                     $temp = explode(';', $key);
    //                                     $period = array_shift($temp);
    //                                     $partialKey = implode(';', $temp);

    //                                     if ($partialKey === $fallbackGroupKey) {
    //                                         $oldNumberMappingForGroup = $titikGroupMapping[$key];
    //                                         break;
    //                                     }
    //                                 }
    //                             }*/
    //                         }
    //                         // dd($oldNumberMappingForGroup);
    //                         // dump($oldNumberMappingForGroup, $titikGroupMapping);
    //                         foreach ($xyz->penamaan_titik as $pt) {
    //                             $namaTitik = is_object($pt) ? current(get_object_vars($pt)) : $pt;
    //                             if (in_array($namaTitik, $oldNumberMappingForGroup)) {
    //                                 // dump($oldNumberMappingForGroup);
    //                                 $nomor = array_search($namaTitik, $oldNumberMappingForGroup);
    //                                 $penamaan_titik_fixed[] = (object) [$nomor => $namaTitik];
    //                             } else {
    //                                 $nomorLama = null;

    //                                 foreach ($titikGroupMapping as $key => $map) {
    //                                     if (strpos($key, $fallbackGroupKey) !== false && in_array($namaTitik, $map)) {
    //                                         $nomorLama = array_search($namaTitik, $map);
    //                                         if (!array_key_exists($nomorLama, $penamaan_titik_fixed)) {
    //                                             $nomorLama = null;
    //                                         }
    //                                         break;
    //                                     }
    //                                 }

    //                                 if ($nomorLama) {
    //                                     $penamaan_titik_fixed[] = (object) [$nomorLama => $namaTitik];

    //                                     // Daftarin ke current group juga biar ke-cache
    //                                     $titikGroupMapping[$fullGroupKey][$nomorLama] = $namaTitik;
    //                                 } else {
    //                                     $nomorBaru = sprintf('%03d', $globalTitikCounter);
    //                                     $penamaan_titik_fixed[] = (object) [$nomorBaru => $namaTitik];

    //                                     $titikGroupMapping[$fullGroupKey][$nomorBaru] = $namaTitik;
    //                                     $globalTitikCounter++;
    //                                 }
    //                             }
    //                         }

    //                         $data_sampling[$n++] = [
    //                             'kategori_1' => $xyz->kategori_1,
    //                             'kategori_2' => $xyz->kategori_2,
    //                             'regulasi' => $regulasi,
    //                             'parameter' => $param,
    //                             'jumlah_titik' => $xyz->jumlah_titik,
    //                             'penamaan_titik' => $penamaan_titik_fixed,
    //                             'total_parameter' => count($param),
    //                             'harga_satuan' => $harga_pertitik->total_harga,
    //                             'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
    //                             'volume' => $vol,
    //                             'biaya_preparasi' => $desc_preparasi
    //                         ];
    //                         // dump($per);
    //                         // dump($penamaan_titik_fixed);

    //                         // kalkulasi harga parameter sesuai titik
    //                         switch ($kategori) {
    //                             case '1':
    //                                 $harga_air += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '4':
    //                                 $harga_udara += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '5':
    //                                 $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '6':
    //                                 $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '7':
    //                                 $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '8':
    //                                 $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                             case '9':
    //                                 $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $xyz->jumlah_titik;
    //                                 break;
    //                         }
    //                     }
    //                 }
    //                 // dd($desc_preparasi);

    //                 $datas[$j] = [
    //                     'periode_kontrak' => $per,
    //                     'data_sampling' => array_values($data_sampling)
    //                     // 'data_sampling' => json_encode(array_values($data_sampling), JSON_UNESCAPED_UNICODE)
    //                 ];
    //                 $dataD->periode_kontrak = $per;
    //                 $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

    //                 $dataD->data_pendukung_sampling = json_encode($datas, JSON_UNESCAPED_UNICODE);
    //                 // end data sampling
    //                 $dataD->harga_air = $harga_air;
    //                 $dataD->harga_udara = $harga_udara;
    //                 $dataD->harga_emisi = $harga_emisi;
    //                 $dataD->harga_padatan = $harga_padatan;
    //                 $dataD->harga_swab_test = $harga_swab_test;
    //                 $dataD->harga_tanah = $harga_tanah;

    //                 // kalkulasi harga
    //                 $expOp = explode("-", $data_wilayah->wilayah);
    //                 $id_wilayah = $expOp[0];
    //                 $cekOperasional = HargaTransportasi::where('is_active', true)->where('id', $id_wilayah)->first();

    //                 // START FOR
    //                 $disc_transport = 0;
    //                 $disc_perdiem = 0;
    //                 $disc_perdiem_24 = 0;

    //                 $harga_transport = 0;
    //                 $jam = 0;
    //                 $transport = 0;
    //                 $perdiem = 0;

    //                 $data_lain = $data_pendukung_lain;
    //                 $biaya_lain = 0;

    //                 for ($c = 0; $c < count($data_wilayah->wilayah_data); $c++) {
    //                     if (in_array($per, $data_wilayah->wilayah_data[$c]->periode)) {

    //                         // Menjumlahkan total % discount transport kedalam variable
    //                         if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD')
    //                             $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi;
    //                         // dd($data_wilayah->status_Wilayah);
    //                         if ($data_wilayah->status_Wilayah == 'DALAM KOTA') {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }

    //                                 if (isset($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem)) {
    //                                     $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true' ? $dataD->kalkulasi_by_sistem = 'on' : $dataD->kalkulasi_by_sistem = 'off';
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {
    //                                     $dataD->harga_transportasi = $cekOperasional->transportasi;
    //                                     $dataD->harga_transportasi_total = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);

    //                                     $dataD->harga_personil = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang);
    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($cekOperasional->transportasi * (int) $data_wilayah->wilayah_data[$c]->transportasi);
    //                                     $perdiem = ($cekOperasional->per_orang * (int) $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '')
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     // dd($data_wilayah->wilayah_data);
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_personil);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;
    //                         } else {
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {

    //                                 $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                 $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                     $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_orang_24jam = null;
    //                                 }

    //                                 if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '') {
    //                                     $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                 } else {
    //                                     $dataD->jumlah_hari_24jam = 0;
    //                                 }
    //                             }

    //                             $harga_tiket = 0;
    //                             $harga_transportasi_darat = 0;
    //                             $harga_penginapan = 0;

    //                             // dd($data_wilayah->wilayah_data);
    //                             if ($data_wilayah->wilayah_data[$c]->status_sampling != 'SD') {
    //                                 //hitung harga tiket perjalanan
    //                                 $dataD->kalkulasi_by_sistem = $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem;

    //                                 if ($data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'on' || $data_wilayah->wilayah_data[$c]->kalkulasi_by_sistem == 'true') {

    //                                     $harga_tiket = $cekOperasional->tiket * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;
    //                                     $harga_transportasi_darat = $cekOperasional->transportasi;
    //                                     $harga_penginapan = $cekOperasional->penginapan;
    //                                     $dataD->harga_transportasi = $harga_tiket + $harga_transportasi_darat + $harga_penginapan;
    //                                     $dataD->harga_transportasi_total = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;

    //                                     $dataD->harga_personil = $cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang;

    //                                     $dataD->harga_perdiem_personil_total = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil = $cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam;
    //                                     }

    //                                     if ($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam != '' && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam != '') {
    //                                         $dataD->harga_24jam_personil_total = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                         $jam = ($cekOperasional->{'24jam'} * (int) $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) * $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam;
    //                                     }

    //                                     $transport = ($harga_tiket + $harga_transportasi_darat + $harga_penginapan) * $data_wilayah->wilayah_data[$c]->transportasi;
    //                                     $perdiem = ($cekOperasional->per_orang * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) * $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari;
    //                                 } else {
    //                                     // IF NOT CALCULATE BY SYSTEM
    //                                     // JUMLAH TRANSPORTASI
    //                                     isset($data_wilayah->wilayah_data[$c]->transportasi) && $data_wilayah->wilayah_data[$c]->transportasi !== '' ? $dataD->transportasi = $data_wilayah->wilayah_data[$c]->transportasi : $dataD->transportasi = null;
    //                                     // JUMLAH ORANG PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang !== '' ? $dataD->perdiem_jumlah_orang = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_orang : $dataD->perdiem_jumlah_orang = null;
    //                                     // JUMLAH HARI PERDIEM
    //                                     isset($data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari) && $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari !== '' ? $dataD->perdiem_jumlah_hari = $data_wilayah->wilayah_data[$c]->perdiem_jumlah_hari : $dataD->perdiem_jumlah_hari = null;
    //                                     // JUMLAH ORANG 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_orang_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam !== '' ? $dataD->jumlah_orang_24jam = $data_wilayah->wilayah_data[$c]->jumlah_orang_24jam : $dataD->jumlah_orang_24jam = null;
    //                                     // JUMLAH HARI 24 JAM
    //                                     isset($data_wilayah->wilayah_data[$c]->jumlah_hari_24jam) && $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam !== '' ? $dataD->jumlah_hari_24jam = $data_wilayah->wilayah_data[$c]->jumlah_hari_24jam : $dataD->jumlah_hari_24jam = null;
    //                                     // HARGA SATUAN TRANSPORTASI
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi !== '')
    //                                         $dataD->harga_transportasi = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     // HARGA TRANSPORTASI TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi_total) && $data_wilayah->wilayah_data[$c]->harga_transportasi_total !== '')
    //                                         $dataD->harga_transportasi_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_transportasi_total);
    //                                     // HARGA SATUAN PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil !== '' ? $dataD->harga_personil = $data_wilayah->wilayah_data[$c]->harga_personil : $dataD->harga_personil = 0;
    //                                     // HARGA PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total) && $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total !== '')
    //                                         $dataD->harga_perdiem_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_perdiem_personil_total);
    //                                     // HARGA 24 JAM PERSONIL
    //                                     isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil !== '' ? $dataD->harga_24jam_personil = $data_wilayah->wilayah_data[$c]->harga_24jam_personil : $dataD->harga_24jam_personil = 0;
    //                                     // HARGA 24 JAM PERSONIL TOTAL (CALCULATE ON CLIENT)
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil_total) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total !== '')
    //                                         $dataD->harga_24jam_personil_total = (int) str_replace('.', '', $data_wilayah->wilayah_data[$c]->harga_24jam_personil_total);

    //                                     // PERDIEM, JAM, TRANSPORT
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_transportasi) && $data_wilayah->wilayah_data[$c]->harga_transportasi != '')
    //                                         $transport = $data_wilayah->wilayah_data[$c]->harga_transportasi;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_personil) && $data_wilayah->wilayah_data[$c]->harga_personil != '')
    //                                         $perdiem = $data_wilayah->wilayah_data[$c]->harga_personil;
    //                                     if (isset($data_wilayah->wilayah_data[$c]->harga_24jam_personil) && $data_wilayah->wilayah_data[$c]->harga_24jam_personil != '')
    //                                         $jam = $data_wilayah->wilayah_data[$c]->harga_24jam_personil;
    //                                 }
    //                             } else {
    //                                 $dataD->transportasi = null;
    //                                 $dataD->perdiem_jumlah_orang = null;
    //                                 $dataD->perdiem_jumlah_hari = null;
    //                                 $dataD->jumlah_orang_24jam = null;
    //                                 $dataD->jumlah_hari_24jam = null;

    //                                 $dataD->harga_transportasi = 0;
    //                                 $dataD->harga_transportasi_total = null;

    //                                 $dataD->harga_personil = 0;
    //                                 $dataD->harga_perdiem_personil_total = null;

    //                                 $dataD->harga_24jam_personil = 0;
    //                                 $dataD->harga_24jam_personil_total = null;
    //                             }
    //                         }

    //                         $dataD->status_sampling = $data_wilayah->wilayah_data[$c]->status_sampling;
    //                     }
    //                 }

    //                 // =======================================================================DATA DISKON===========================================================================
    //                 // ==================================================DISKON ANALISA=============================================================================================
    //                 $isPeriodeDiskonExist = false;
    //                 $periodeNotExist = '';
    //                 $indexDataDiskon = 0;
    //                 if (count($data_diskon->discount_data) > 0) {
    //                     foreach ($data_diskon->discount_data as $d => $discount) {
    //                         if (in_array($per, $discount->periode)) {
    //                             $isPeriodeDiskonExist = true;
    //                             $indexDataDiskon = $d;
    //                             break;
    //                         }
    //                         if (!$isPeriodeDiskonExist && $d == count($data_diskon->discount_data) - 1) {
    //                             $periodeNotExist = $per;
    //                         }
    //                     }
    //                 }

    //                 if (!$isPeriodeDiskonExist) {
    //                     return response()->json(['message' => 'Periode ' . $periodeNotExist . ' tidak ditemukan pada group diskon', 'status' => '500'], 403);
    //                 }
    //                 // $indexDataDiskon == 1 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_transport) : '';
    //                 // dd($isPeriodeDiskonExist);

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_air > 0) {
    //                     // $indexDataDiskon == 0 ? dd($data_diskon->discount_data[$indexDataDiskon]->discount_air) : '';
    //                     // dd($data_diskon->discount_data[$indexDataDiskon]->discount_non_air);
    //                     $dataD->discount_air = $data_diskon->discount_data[$indexDataDiskon]->discount_air;
    //                     $dataD->total_discount_air = ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $harga_total += $harga_air - ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));

    //                     $total_diskon += ($harga_air / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air));
    //                     if (floatval(\str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_air)) > 10) {
    //                         $message = $dataH->no_document . ' Discount Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }
    //                 } else {
    //                     $harga_total += $harga_air;
    //                     $dataD->discount_air = null;
    //                     $dataD->total_discount_air = 0;
    //                 }

    //                 if ($isPeriodeDiskonExist && $data_diskon->discount_data[$indexDataDiskon]->discount_non_air > 0) {
    //                     $dataD->discount_non_air = $data_diskon->discount_data[$indexDataDiskon]->discount_non_air;
    //                     $jumlah = floatval($harga_udara) + floatval($harga_emisi) + floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->total_discount_non_air = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $disc_ = ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $harga_total += $jumlah - ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     $total_diskon += ($jumlah / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_non_air));
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 10) {
    //                         $message = $dataH->no_document . ' Discount Non-Air melebihi 10%';
    //                         Notification::where('id', 19)
    //                             ->title('Peringatan.')
    //                             ->message($message)
    //                             ->url('/quote-request')
    //                             ->send();
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total -= ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total -= ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 } else {
    //                     $harga_total += floatval($harga_padatan) + floatval($harga_swab_test) + floatval($harga_tanah);
    //                     $dataD->discount_non_air = null;
    //                     $dataD->total_discount_non_air = '0.00';

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) == 0) {
    //                         $dataD->discount_udara = $data_diskon->discount_data[$indexDataDiskon]->discount_udara;
    //                         $dataD->total_discount_udara = ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $total_diskon += ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         $harga_total += $harga_udara - ($harga_udara / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_udara));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_udara) > 10) {
    //                             $message = $dataH->no_document . ' Discount Udara melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_udara;
    //                         $dataD->discount_udara = null;
    //                         $dataD->total_discount_udara = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_non_air) == 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) == 0) {
    //                         $dataD->discount_emisi = $data_diskon->discount_data[$indexDataDiskon]->discount_emisi;
    //                         $dataD->total_discount_emisi = ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $total_diskon += ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         $harga_total += $harga_emisi - ($harga_emisi / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_emisi));
    //                         if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_emisi) > 10) {
    //                             $message = $dataH->no_document . ' Discount Emisi melebihi 10%';
    //                             Notification::where('id', 19)
    //                                 ->title('Peringatan.')
    //                                 ->message($message)
    //                                 ->url('/quote-request')
    //                                 ->send();
    //                         }
    //                     } else {
    //                         $harga_total += $harga_emisi;
    //                         $dataD->discount_emisi = null;
    //                         $dataD->total_discount_emisi = 0;
    //                     }
    //                 }

    //                 // ========================================================END DISKON ANALISA========================================================================
    //                 // ====================================================DISKON TRANSPORTASI========================================================================================
    //                 $harga_total += $harga_pangan;
    //                 $transport_ = 0;
    //                 $perdiem_ = 0;
    //                 $jam_ = 0;

    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0 && floatval($data_diskon->discount_data[$indexDataDiskon]->discount_transport) > 0) {
    //                         $dataD->discount_transport = $data_diskon->discount_data[$indexDataDiskon]->discount_transport;
    //                         $dataD->total_discount_transport = ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $total_diskon += ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         // Harga Total
    //                         // $harga_total -= ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $transport_ = $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                     } else {
    //                         $dataD->discount_transport = null;
    //                         $dataD->total_discount_transport = 0;
    //                         $transport_ = $transport;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem) > 0) {
    //                         $dataD->discount_perdiem = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem;
    //                         $dataD->total_discount_perdiem = ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $total_diskon += ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         // $harga_total -= ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $perdiem_ = $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                     } else {
    //                         $dataD->discount_perdiem = null;
    //                         $dataD->total_discount_perdiem = 0;
    //                         $perdiem_ = $perdiem;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam) > 0) {
    //                         $dataD->discount_perdiem_24jam = $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam;
    //                         $dataD->total_discount_perdiem_24jam = ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $total_diskon += ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         // $harga_total -= ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $jam_ = $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                     } else {
    //                         $dataD->discount_perdiem_24jam = null;
    //                         $dataD->total_discount_perdiem_24jam = 0;
    //                         $jam_ = $jam;
    //                     }
    //                 }

    //                 $harga_transport += ($transport_ + $perdiem_ + $jam_);
    //                 // ==================================================END DISKON TRANSPORTASI======================================================================================
    //                 // =======================================================DISKON GABUNGAN=========================================================================================
    //                 if ($isPeriodeDiskonExist) {
    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_gabungan) > 0) {
    //                         $dataD->discount_gabungan = $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan;
    //                         $dataD->total_discount_gabungan = (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $total_diskon += (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                         $harga_total = $harga_total - (($harga_total + $harga_transport) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_gabungan));
    //                     } else {
    //                         $dataD->discount_gabungan = null;
    //                         $dataD->total_discount_gabungan = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_consultant) > 0) {
    //                         $dataD->discount_consultant = $data_diskon->discount_data[$indexDataDiskon]->discount_consultant;
    //                         $dataD->total_discount_consultant = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $total_diskon += ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                         $harga_total = $harga_total - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_consultant));
    //                     } else {
    //                         $dataD->discount_consultant = null;
    //                         $dataD->total_discount_consultant = 0;
    //                     }

    //                     // BIAYA LAIN
    //                     // $biaya_lain = 0;
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->biaya_lains) && !empty($data_diskon->discount_data[$indexDataDiskon]->biaya_lains)) {
    //                         $data_lain = array_values(array_filter(array_map(function ($disc) use (&$biaya_lain) {
    //                             if ($disc->harga == 0)
    //                                 return null;
    //                             $biaya_lain += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga));
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'harga' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->harga))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->biaya_lains)));

    //                         // $grand_total += $biaya_lain;
    //                         // $harga_total += $biaya_lain;
    //                         $dataD->biaya_lain = count($data_lain) > 0 ? json_encode($data_lain) : null;
    //                         $dataD->total_biaya_lain = $biaya_lain;
    //                     } else {
    //                         $dataD->biaya_lain = null;
    //                         $dataD->total_biaya_lain = 0;
    //                     }

    //                     // ====================================================END BIAYA LAIN=======================================================================================


    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->discount_group) > 0) {
    //                         $totalTransport = $harga_transport;
    //                         if (isset($payload->data_diskon->diluar_pajak)) {

    //                             if ($payload->data_diskon->diluar_pajak->transportasi == 'true' || $payload->data_diskon->diluar_pajak->transportasi == true) {
    //                                 $totalTransport -= $transport_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem == 'true' || $payload->data_diskon->diluar_pajak->perdiem == true) {
    //                                 $totalTransport -= $perdiem_;
    //                             }

    //                             if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true' || $payload->data_diskon->diluar_pajak->perdiem24jam == true) {
    //                                 $totalTransport -= $jam_;
    //                             }
    //                         }
    //                         // if($per == '2025-02') dd($harga_total + $totalTransport);
    //                         $dataD->discount_group = $data_diskon->discount_data[$indexDataDiskon]->discount_group;
    //                         $diskon_group = ((($harga_total + $totalTransport) - $biaya_lain) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_group));
    //                         $dataD->total_discount_group = $diskon_group;
    //                         $total_diskon += $diskon_group;
    //                         $harga_total = $harga_total - $diskon_group;
    //                     } else {
    //                         $dataD->discount_group = null;
    //                         $dataD->total_discount_group = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen) > 0) {
    //                         $dataD->cash_discount_persen = $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen;
    //                         $dataD->total_cash_discount_persen = (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $total_diskon += (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                         $harga_total = $harga_total - (($harga_total) / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount_persen));
    //                     } else {
    //                         $dataD->cash_discount_persen = null;
    //                         $dataD->total_cash_discount_persen = 0;
    //                     }

    //                     if (floatval($data_diskon->discount_data[$indexDataDiskon]->cash_discount) > 0) {
    //                         $harga_total = $harga_total - floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $dataD->cash_discount = floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                         $total_diskon += floatval(\str_replace(["Rp. ", ","], "", $data_diskon->discount_data[$indexDataDiskon]->cash_discount));
    //                     } else {

    //                         $dataD->cash_discount = floatval(0);
    //                     }


    //                     // CUSTOM DISKON
    //                     if (isset($data_diskon->discount_data[$indexDataDiskon]->custom_discounts) && !empty($data_diskon->discount_data[$indexDataDiskon]->custom_discounts)) {
    //                         $custom_disc = array_values(array_filter(array_map(function ($disc) {
    //                             if ($disc->discount == 0)
    //                                 return null; // Tidak mengembalikan apa-apa jika discount = 0
    //                             return (object) [
    //                                 'deskripsi' => $disc->deskripsi,
    //                                 'discount' => floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount))
    //                             ];
    //                         }, $data_diskon->discount_data[$indexDataDiskon]->custom_discounts)));

    //                         $harga_disc = 0;
    //                         foreach ($data_diskon->discount_data[$indexDataDiskon]->custom_discounts as $disc) {
    //                             $harga_disc += floatval(str_replace(['Rp. ', ',', '.'], '', $disc->discount));
    //                         }

    //                         $total_diskon += $harga_disc;
    //                         $harga_total -= $harga_disc;
    //                         $dataD->custom_discount = count($custom_disc) > 0 ? json_encode($custom_disc) : null;
    //                         $dataD->total_custom_discount = $harga_disc;
    //                     } else {
    //                         $dataD->custom_discount = null;
    //                         $dataD->total_custom_discount = 0;
    //                     }
    //                     // ====================================================END CUSTOM DISKON=======================================================================================
    //                 }

    //                 //============= BIAYA PREPARASI
    //                 // dd($desc_preparasi);
    //                 $dataD->biaya_preparasi = json_encode($desc_preparasi);
    //                 $dataD->total_biaya_preparasi = $harga_preparasi;
    //                 $grand_total += $harga_preparasi;
    //                 $harga_total += $harga_preparasi;
    //                 // dd($grand_total);

    //                 //jika Transportasi di luar pajak
    //                 $biaya_akhir = 0;
    //                 $biaya_diluar_pajak = 0;
    //                 $txt = [];

    //                 if (isset($payload->data_diskon->diluar_pajak)) {
    //                     if ($payload->data_diskon->diluar_pajak->transportasi == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Transportasi", "harga" => $transport];
    //                         // $harga_total += $transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport);
    //                         $biaya_akhir += $transport - ($transport / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_transport));
    //                         $biaya_diluar_pajak += $transport;
    //                     } else {
    //                         $grand_total += $transport;
    //                         $harga_total += $transport_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem", "harga" => $perdiem];
    //                         // $harga_total += $perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem);
    //                         $biaya_akhir += $perdiem - ($perdiem / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem));
    //                         $biaya_diluar_pajak += $perdiem;
    //                     } else {
    //                         $grand_total += $perdiem;
    //                         $harga_total += $perdiem_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->perdiem24jam == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Perdiem (24 jam)", "harga" => $jam];
    //                         // $harga_total += $jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam);
    //                         $biaya_akhir += $jam - ($jam / 100 * (int) \str_replace("%", "", $data_diskon->discount_data[$indexDataDiskon]->discount_perdiem_24jam));
    //                         $biaya_diluar_pajak += $jam;
    //                     } else {
    //                         $grand_total += $jam;
    //                         $harga_total += $jam_;
    //                     }

    //                     if ($payload->data_diskon->diluar_pajak->biayalain == 'true') {
    //                         $txt[] = ["deskripsi" => "Biaya Lain", "harga" => $biaya_lain];
    //                         $biaya_akhir += $biaya_lain;
    //                         $biaya_diluar_pajak += $biaya_lain;
    //                     } else {
    //                         $grand_total += $biaya_lain;
    //                         $harga_total += $biaya_lain;
    //                     }
    //                 }

    //                 //Grand total sebelum kena diskon
    //                 $dataD->grand_total = $grand_total;
    //                 $dataD->total_dpp = $harga_total;

    //                 if (floatval($data_diskon->ppn) >= 0) {
    //                     $dataD->ppn = $data_diskon->ppn;
    //                     $dataD->total_ppn = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                     $piutang = $harga_total + ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->ppn));
    //                 }

    //                 if (floatval($data_diskon->pph) >= 0) {
    //                     $dataD->pph = $data_diskon->pph;
    //                     $dataD->total_pph = ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                     $piutang = $piutang - ($harga_total / 100 * (int) \str_replace("%", "", $data_diskon->pph));
    //                 }

    //                 $diluar_pajak = ['select' => $txt, 'body' => []];

    //                 if (isset($payload->data_diskon->biaya_di_luar_pajak->body) && !empty($payload->data_diskon->biaya_di_luar_pajak->body)) {
    //                     foreach ($payload->data_diskon->biaya_di_luar_pajak->body as $item) {

    //                         $biaya_diluar_pajak += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                         $biaya_akhir += floatval(str_replace(['Rp. ', ',', '.'], '', $item->harga));
    //                     }
    //                     $diluar_pajak['body'] = $payload->data_diskon->biaya_di_luar_pajak->body;
    //                 }

    //                 //biaya di luar pajak
    //                 $dataD->biaya_di_luar_pajak = json_encode($diluar_pajak);
    //                 $dataD->total_biaya_di_luar_pajak = $biaya_diluar_pajak;

    //                 $dataD->piutang = $piutang;
    //                 $biaya_akhir += $piutang;

    //                 $dataD->biaya_akhir = $biaya_akhir;

    //                 //==========================END BIAYA DI LUAR PAJAK======================================
    //                 // if($k == 11) dd($dataD);
    //                 $dataD->save();
    //                 // dump(json_decode($dataD->data_pendukung_sampling));
    //                 // dd($dataH, $dataD);
    //                 // END FOR
    //                 if ($k == ($num - 1)) {
    //                     // SUM DATA DETAIL FOR TOTAL HEADER
    //                     $Dd = DB::select(" SELECT SUM(harga_air) as harga_air, SUM(harga_udara) as harga_udara,
    //                                                 SUM(harga_emisi) as harga_emisi,
    //                                                 SUM(harga_padatan) as harga_padatan,
    //                                                 SUM(harga_swab_test) as harga_swab_test,
    //                                                 SUM(harga_tanah) as harga_tanah,
    //                                                 SUM(transportasi) as transportasi,
    //                                                 SUM(perdiem_jumlah_orang) as perdiem_jumlah_orang,
    //                                                 SUM(perdiem_jumlah_hari) as perdiem_jumlah_hari,
    //                                                 SUM(jumlah_orang_24jam) as jumlah_orang_24jam,
    //                                                 SUM(jumlah_hari_24jam) as jumlah_hari_24jam,
    //                                                 SUM(harga_transportasi) as harga_transportasi,
    //                                                 SUM(harga_transportasi_total) as harga_transportasi_total,
    //                                                 SUM(harga_personil) as harga_personil,
    //                                                 SUM(harga_perdiem_personil_total) as harga_perdiem_personil_total,
    //                                                 SUM(harga_24jam_personil) as harga_24jam_personil,
    //                                                 SUM(harga_24jam_personil_total) as harga_24jam_personil_total,
    //                                                 SUM(discount_air) as discount_air,
    //                                                 SUM(total_discount_air) as total_discount_air,
    //                                                 SUM(discount_non_air) as discount_non_air,
    //                                                 SUM(total_discount_non_air) as total_discount_non_air,
    //                                                 SUM(discount_udara) as discount_udara,
    //                                                 SUM(total_discount_udara) as total_discount_udara,
    //                                                 SUM(discount_emisi) as discount_emisi,
    //                                                 SUM(total_discount_emisi) as total_discount_emisi,
    //                                                 SUM(discount_gabungan) as discount_gabungan,
    //                                                 SUM(total_discount_gabungan) as total_discount_gabungan,
    //                                                 SUM(cash_discount_persen) as cash_discount_persen,
    //                                                 SUM(total_cash_discount_persen) as total_cash_discount_persen,
    //                                                 SUM(discount_consultant) as discount_consultant,
    //                                                 SUM(discount_group) as discount_group,
    //                                                 SUM(total_discount_group) as total_discount_group,
    //                                                 SUM(total_discount_consultant) as total_discount_consultant,
    //                                                 SUM(cash_discount) as cash_discount,
    //                                                 SUM(total_custom_discount) as total_custom_discount,
    //                                                 SUM(discount_transport) as discount_transport,
    //                                                 SUM(total_discount_transport) as total_discount_transport,
    //                                                 SUM(discount_perdiem) as discount_perdiem,
    //                                                 SUM(total_discount_perdiem) as total_discount_perdiem,
    //                                                 SUM(discount_perdiem_24jam) as discount_perdiem_24jam,
    //                                                 SUM(total_discount_perdiem_24jam) as total_discount_perdiem_24jam,
    //                                                 SUM(ppn) as ppn,
    //                                                 SUM(total_ppn) as total_ppn,
    //                                                 SUM(total_pph) as total_pph,
    //                                                 SUM(pph) as pph,
    //                                                 SUM(biaya_lain) as biaya_lain,
    //                                                 SUM(total_biaya_lain) as total_biaya_lain,
    //                                                 SUM(total_biaya_preparasi) as total_biaya_preparasi,
    //                                                 SUM(biaya_di_luar_pajak) as biaya_di_luar_pajak,
    //                                                 SUM(total_biaya_di_luar_pajak) as total_biaya_di_luar_pajak,
    //                                                 SUM(grand_total) as grand_total,
    //                                                 SUM(total_discount) as total_discount,
    //                                                 SUM(total_dpp) as total_dpp,
    //                                                 SUM(piutang) as piutang,
    //                                                 SUM(biaya_akhir) as biaya_akhir FROM request_quotation_kontrak_D WHERE id_request_quotation_kontrak_h = '$dataH->id' GROUP BY id_request_quotation_kontrak_h ");
    //                     // UPDATE HEADER DATA
    //                     // dd($Dd);
    //                     $editH = QuotationKontrakH::where('id', $dataH->id)
    //                         ->first();
    //                     $editH->syarat_ketentuan = json_encode($payload->syarat_ketentuan);
    //                     // dd($syarat);
    //                     if (isset($payload->keterangan_tambahan) && $payload->keterangan_tambahan != null)
    //                         $editH->keterangan_tambahan = json_encode($payload->keterangan_tambahan);
    //                     if ($tgl == null || !isset($tgl)) {
    //                         $tgl = date('Y-m-d', strtotime("+30 days", strtotime(DATE('Y-m-d'))));
    //                     }
    //                     // dd($Dd);
    //                     $editH->expired = $tgl;
    //                     $editH->total_harga_air = $Dd[0]->harga_air;
    //                     $editH->total_harga_udara = $Dd[0]->harga_udara;
    //                     $editH->total_harga_emisi = $Dd[0]->harga_emisi;
    //                     $editH->total_harga_padatan = $Dd[0]->harga_padatan;
    //                     $editH->total_harga_swab_test = $Dd[0]->harga_swab_test;
    //                     $editH->total_harga_tanah = $Dd[0]->harga_tanah;
    //                     $editH->transportasi = $Dd[0]->transportasi;
    //                     $editH->perdiem_jumlah_orang = $Dd[0]->perdiem_jumlah_orang;
    //                     $editH->perdiem_jumlah_hari = $Dd[0]->perdiem_jumlah_hari;
    //                     $editH->jumlah_orang_24jam = $Dd[0]->jumlah_orang_24jam;
    //                     $editH->jumlah_hari_24jam = $Dd[0]->jumlah_hari_24jam;
    //                     if (!is_null($Dd[0]->harga_transportasi))
    //                         $editH->harga_transportasi = $Dd[0]->harga_transportasi;
    //                     if (!is_null($Dd[0]->harga_transportasi_total))
    //                         $editH->harga_transportasi_total = $Dd[0]->harga_transportasi_total;
    //                     if (!is_null($Dd[0]->harga_personil))
    //                         $editH->harga_personil = $Dd[0]->harga_personil;
    //                     if (!is_null($Dd[0]->harga_perdiem_personil_total))
    //                         $editH->harga_perdiem_personil_total = $Dd[0]->harga_perdiem_personil_total;

    //                     if (!is_null($Dd[0]->harga_24jam_personil))
    //                         $editH->harga_24jam_personil = $Dd[0]->harga_24jam_personil;
    //                     if (!is_null($Dd[0]->harga_24jam_personil_total))
    //                         $editH->harga_24jam_personil_total = $Dd[0]->harga_24jam_personil_total;

    //                     $editH->total_discount_air = $Dd[0]->total_discount_air;

    //                     $editH->total_discount_non_air = $Dd[0]->total_discount_non_air;

    //                     $editH->total_discount_udara = $Dd[0]->total_discount_udara;

    //                     $editH->total_discount_emisi = $Dd[0]->total_discount_emisi;

    //                     $editH->total_discount_gabungan = $Dd[0]->total_discount_gabungan;

    //                     $editH->total_cash_discount_persen = $Dd[0]->total_cash_discount_persen;
    //                     $editH->total_discount_group = $Dd[0]->total_discount_group;
    //                     $editH->total_discount_consultant = $Dd[0]->total_discount_consultant;
    //                     if (!is_null($Dd[0]->cash_discount))
    //                         $editH->total_cash_discount = round($Dd[0]->cash_discount);

    //                     $editH->total_discount_transport = $Dd[0]->total_discount_transport;

    //                     $editH->total_discount_perdiem = $Dd[0]->total_discount_perdiem;

    //                     $editH->total_discount_perdiem_24jam = $Dd[0]->total_discount_perdiem_24jam;

    //                     $editH->total_custom_discount = $Dd[0]->total_custom_discount;

    //                     $editH->total_ppn = $Dd[0]->total_ppn;
    //                     $editH->total_pph = $Dd[0]->total_pph;

    //                     $editH->total_biaya_lain = $Dd[0]->total_biaya_lain;
    //                     $editH->total_biaya_preparasi = $Dd[0]->total_biaya_preparasi;
    //                     $editH->biaya_diluar_pajak = json_encode($diluar_pajak);
    //                     $editH->total_biaya_di_luar_pajak = $Dd[0]->total_biaya_di_luar_pajak;
    //                     $editH->grand_total = $Dd[0]->grand_total;
    //                     $editH->total_discount = $Dd[0]->total_discount;
    //                     $editH->total_dpp = $Dd[0]->total_dpp;
    //                     $editH->piutang = $Dd[0]->piutang;
    //                     $editH->biaya_akhir = $Dd[0]->biaya_akhir;
    //                     $editH->updated_by = $this->karyawan;
    //                     $editH->updated_at = date('Y-m-d H:i:s');
    //                     $editH->save();
    //                 }
    //             }

    //             // dd('stop');
    //             // DELETE DETAIL RECORD IF PERIODE NOT SAME
    //             // dump($id_det, $dataH->id, $num, $detail);
    //             if ($num < count($data_detail)) {
    //                 $deleted = QuotationKontrakD::whereNotIn('id', $id_det)->where('id_request_quotation_kontrak_h', $dataH->id)->delete();
    //             }

    //             if ($data_lama != null) {
    //                 if ($data_lama->status_sp == 'true') { //merubah jadwal dalam arti menon aktifkan SP
    //                     SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => false, 'canceled_by' => 'system']);

    //                     // SamplingPlan::where('no_quotation', $dataOld->no_document)->update(['is_active' => false]);
    //                     // Jadwal::where('no_quotation', $dataOld->no_document)->update(['is_active' => true]);

    //                     $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $dataH->no_document dan data yang sudah terjadwal akan di tarik otomatis oleh system dan menunggu request baru dari sales";
    //                     // Helpers::sendTelegramAtasan($message, $cek->add_by);
    //                     // Helpers::sendTelegramAtasan($message, '187');
    //                 } else if ($data_lama->status_sp == 'false') {
    //                     //tidak merubah jadwal dalam arti update no doc dalam sp
    //                     DB::beginTransaction();
    //                     try {
    //                         // ========== START Perbaikan SP dan Jadwal By: 565 ==========
    //                         // step 1. Menghitung periode kontrak pada dokumen baru
    //                         $Arrperiod = [];
    //                         foreach ($payload->data_wilayah->wilayah_data as $key => $wilayah_data) {
    //                             if ($wilayah_data->status_sampling !== 'SD') {
    //                                 foreach ($wilayah_data->periode as $key => $valuePeriode) {
    //                                     array_push($Arrperiod, $valuePeriode);
    //                                 }
    //                             }
    //                         }
    //                         $Arrperiod = array_values(array_unique($Arrperiod));

    //                         $detailLama = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataOld->id)->get()->pluck('periode_kontrak')->unique()->toArray();

    //                         // dd($Arrperiod, $detailLama);
    //                         $perubahan_periode = $this->different($dataOld->id, $dataH->id, $detailLama, $Arrperiod, $dataH->no_document);

    //                         if ($perubahan_periode) {
    //                             foreach ($perubahan_periode as $key => $value) {
    //                                 SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode_kontrak', $value['before'])
    //                                     ->where('is_active', true)
    //                                     ->update(['periode_kontrak' => $value['after']]);

    //                                 Jadwal::where('no_quotation', $dataOld->no_document)
    //                                     ->where('periode', $value['before'])
    //                                     ->update(['periode' => $value['after']]);
    //                             }
    //                         }
    //                         // step 2. Menyesuaikan sampling plan dan jadwal dengan dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'quotation_id' => $dataH->id
    //                             ]);
    //                         Jadwal::where('no_quotation', $dataOld->no_document)
    //                             ->update([
    //                                 'no_quotation' => $dataH->no_document,
    //                                 'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($dataH->nama_perusahaan)))
    //                             ]);

    //                         // step 3. Menonaktifkan sampling plan yang tidak ada di dokumen baru
    //                         SamplingPlan::where('no_quotation', $dataH->no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'status' => false, 'status_jadwal' => 'cancel']);

    //                         // step 4. Mengambil id sampling plan yang non-aktif
    //                         $resultJadwalNonAktif = SamplingPlan::select('id', 'periode_kontrak')
    //                             ->where('no_quotation', $no_document)
    //                             ->whereNotIn('periode_kontrak', $Arrperiod)
    //                             ->where('is_active', false)
    //                             ->get();

    //                         $periodJadwalId = [];
    //                         foreach ($resultJadwalNonAktif as $key => $value) {
    //                             array_push($periodJadwalId, $value->id);
    //                         }

    //                         // step 5. Menonaktifkan jadwal yang tidak relevan dengan dokumen baru
    //                         Jadwal::where('no_quotation', $no_document)
    //                             ->whereIn('id_sampling', $periodJadwalId)
    //                             ->where('is_active', true)
    //                             ->update(['is_active' => false, 'canceled_by' => 'system']);
    //                         // ========== END Perbaikan SP dan Jadwal By: 565 ==========

    //                         DB::commit();
    //                     } catch (\Exception $ex) {
    //                         DB::rollback();
    //                         $templateMessage = "Error : " . $ex->getMessage() . "\nLine : " . $ex->getLine() . "\nFile : " . $ex->getFile() . "\n pada method writequotKontrakApi";
    //                         // Helpers::sendTo(843302196, $templateMessage);
    //                     }

    //                     $message = "Terjadi perubahan quotation $data_lama->no_qt menjadi $no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
    //                 }


    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     // $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
    //                     // $no_qt_lama = $cek_order->no_document;
    //                     // $no_qt_baru = $dataH->no_document;
    //                     // $id_order = $data_lama->id_order;

    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_lama', $no_qt_lama);
    //                     // $parse->where('no_qt_baru', $no_qt_baru);
    //                     // $parse->where('id_order', $id_order);
    //                     // $parse->save();
    //                     // dd($data_lama);
    //                 } else {
    //                     // $parse = new GeneratePraSampling;
    //                     // $parse->type('QTC');
    //                     // $parse->where('no_qt_baru', $dataH->no_document);
    //                     // $parse->where('generate', 'new');
    //                     // $parse->save();
    //                 }

    //                 if (isset($data_lama->id_order) && $data_lama->id_order != null) {
    //                     $invoices = Invoice::where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->get();

    //                     $invoiceNumbersTobeChecked = [];
    //                     foreach ($invoices as $invoice) {
    //                         if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
    //                             $invoice->is_generate = false;
    //                             $invoice->is_emailed = false;

    //                             $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
    //                         }
    //                         $invoice->no_quotation = $no_document;
    //                         $invoice->save();

    //                         // $render = new RenderInvoice();
    //                         // $render->renderInvoice($invoice->no_invoice);
    //                     }

    //                     $invoiceNumbersStr = implode(', ', $invoiceNumbersTobeChecked);

    //                     $message = count($invoiceNumbersTobeChecked) > 0
    //                         ? "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order. Oleh karena itu nomor invoice $invoiceNumbersStr akan dikembalikan ke menu generate invoice untuk dilakukan pengecekan."
    //                         : "Telah terjadi revisi pada nomor quote $dataOld->no_document menjadi $no_document dengan nomor order $data_lama->no_order.";

    //                     Notification::where('id_department', 5)->title('Revisi Penawaran')->message($message)->url('/generate-invoice')->send();

    //                     DB::table('persiapan_sampel_header')
    //                         ->where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)
    //                         ->update([
    //                             'no_quotation' => $dataH->no_document,
    //                         ]);

    //                     $konfirmasi = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)
    //                         ->where('is_active', true)->get();
    //                     // UPDATE KONFIRMASI ORDER ===================
    //                     foreach ($konfirmasi as $k) {
    //                         $k->no_quotation = $dataH->no_document;
    //                         $k->id_quotation = $dataH->id;
    //                         $k->save();
    //                     }
    //                 }
    //             } else {
    //                 // $parse = new GeneratePraSampling;
    //                 // $parse->type('QTC');
    //                 // $parse->where('no_qt_baru', $dataH->no_document);
    //                 // $parse->where('generate', 'new');
    //                 // $parse->save();
    //             }
    //             // dd($dataH, $dataD);
    //             // $dataOld->document_status = 'Non Aktif';
    //             // $dataOld->is_active = false;
    //             // $dataOld->save();

    //             // $orderConfirmation = KelengkapanKonfirmasiQs::where('no_quotation', $dataOld->no_document)->where('is_active', true)->first();
    //             // if ($orderConfirmation)
    //             //     $orderConfirmation->update(['no_quotation' => $no_document]);
    //             // ===========================================

    //             JobTask::insert([
    //                 'job' => 'RenderPdfPenawaran',
    //                 'status' => 'processing',
    //                 'no_document' => $dataH->no_document,
    //                 'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
    //             ]);
    //             // dd('=====================');

    //             DB::commit();

    //             $job = new RenderPdfPenawaran($dataH->id, 'kontrak');
    //             $this->dispatch($job);

    //             $array_id_user = GetAtasan::where('id', $dataH->sales_id)->get()->pluck('id')->toArray();

    //             Notification::whereIn('id', $array_id_user)
    //                 ->title('Penawaran telah di revisi')
    //                 ->message('Penawaran dengan nomor ' . $dataOld->no_document . ' telah di revisi menjadi ' . $dataH->no_document . '.')
    //                 ->url('/quote-request')
    //                 ->send();

    //             return response()->json([
    //                 'message' => "Request Quotation number $dataH->no_document has been successfully revised"
    //             ], 200);
    //         } catch (\Exception $e) {
    //             DB::rollback();
    //             // dd($e);
    //             return response()->json([
    //                 'message' => $e->getMessage(),
    //                 'line' => $e->getLine(),
    //             ], 401);
    //         }
    //     } catch (\Exception $th) {
    //         DB::rollback();
    //         return response()->json([
    //             'message' => $th->getMessage() . ' ' . $th->getLine(),
    //             'line' => $th->getLine(),
    //         ], 401);
    //     }
    // }

    protected function saveToken($data)
    {

        $key = $data['add'] . str_replace('.', '', microtime(true));
        $gen = MD5($key);
        $gen_tahun = self::encrypt(DATE('Y-m-d'));
        $token = self::encrypt($gen . '|' . $gen_tahun);

        if ($data['status_quot'] == 'kontrak') {
            $table = 'request_quotation_kontrak_H';
        } else if ($data['status_quot'] == 'non_kontrak') {
            $table = 'request_quotation';
        }

        $data_body = [
            'token' => $token,
            'key' => $gen,
            'id_quotation' => $data['id'],
            'quotation_status' => $data['status_quot'],
            'expired' => $data['expired'],
            // 'password' => $cek->nama_pic_order[4].DATE('dym', strtotime($cek->add_at)),
            'created_at' => DATE('Y-m-d'),
            'created_by' => $data['userid'],
            // 'fileName' => json_encode($data_file) ,
            // 'fileName_pdf' => $fileName,
            'type' => null
        ];


        $insert = DB::table('generate_link_quotation')
            ->insertGetId($data_body);

        DB::table($table)->where('id', $data['id'])->update([
            'id_token' => $insert,
            'is_generate' => 1,
            'generate_at' => DATE('Y-m-d H:i:s'),
            'generate_by' => $data['userid'],
            'flag_status' => 'draft'
        ]);

        DB::table('job_task')->insert([
            'job' => 'GeneratePdfDocument',
            'status' => 'processing',
            'no_document' => $data['no_document'],
            'timestamp' => DATE('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public function tanggal_indonesia($tanggal, $mode = '')
    {

        $bulan = array(
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );

        $var = explode('-', $tanggal);
        if ($mode == 'period') {

            // dd($tanggal);
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        }
    }

    /* Proses service update by afryan in 2025-02-04
    public function renderPdfQuotation(Request $request)
    {
        $job = new RenderPdfPenawaran($request->id, $request->tipe_penawaran);
        $this->dispatch($job);
    }

    public function renderPDF(Request $request)
    {
        if ($request->id == '' || $request->filename == '') {
            return response()->json([
                'message' => 'ID Quotation dan Filename tidak terbaca'
            ], 404);
        }
        $file = $request->filename ?? '';
        $path = public_path('quotation/' . $file);
        $isFileExist = true;
        if (!file_exists($path)) { // Menggunakan fungsi bawaan PHP
            $isFileExist = false;
        }

        if (!$isFileExist) {
            if ($request->mode == 'kontrak') {
                $filename = RenderKontrak::renderDataQuotation($request->id);
            } else {
                $filename = RenderNonKontrak::renderHeader($request->id);
            }
        }

        if ($request->mode == 'kontrak') {
            $quotation = QuotationKontrakH::where('id', $request->id)->first();
        } else {
            $quotation = QuotationNonKontrak::where('id', $request->id)->first();
        }

        return response()->json($quotation->filename, 200);
    }*/

    /* prod public function renderPDF(Request $request)
    {
        if ($request->mode == 'kontrak') {
            $quotation = QuotationKontrakH::where('id', $request->id)->first();
        } else {
            $quotation = QuotationNonKontrak::where('id', $request->id)->first();
        }

        if ($request->mode == 'kontrak') {
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotation->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($request->id, 'kontrak');
            $this->dispatch($job);
        } else {
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotation->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($request->id, 'non kontrak');
            $this->dispatch($job);
        }

        return response()->json($quotation->filename, 200);
    } */
    public function renderPDF(Request $request)
    {
        if ($request->mode == 'kontrak') {
            $quotation = QuotationKontrakH::where('id', $request->id)->first();
        } else {
            $quotation = QuotationNonKontrak::where('id', $request->id)->first();
        }

        if ($request->mode == 'kontrak') {
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotation->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($request->id, 'kontrak');
            $this->dispatch($job);
        } else {
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $quotation->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($request->id, 'non kontrak');
            $this->dispatch($job);
        }

        return response()->json($quotation->filename, 200);
    }

    public function renderPdfQuotation(Request $request)
    {
        // dump($request->all());
        // $job = new RenderPdfPenawaran($request->id, $request->tipe_penawaran);
        // $this->dispatch($job);
        // dd($request->all());
        try {
            if ($request->mode == 'copy') {
                if ($request->tipe_penawaran == 'kontrak') {
                    $render = new RenderKontrakCopy();
                    $render->renderDataQuotation($request->id, 'id');
                } else {
                    $render = new RenderNonKontrakCopy();
                    $render->renderHeader($request->id, 'id');
                }
            } else {
                if ($request->tipe_penawaran == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->first();
                    JobTask::insert([
                        'job' => 'RenderPdfPenawaran',
                        'status' => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'kontrak');
                    $this->dispatch($job);
                } else {
                    $data = QuotationNonKontrak::where('id', $request->id)->first();
                    JobTask::insert([
                        'job' => 'RenderPdfPenawaran',
                        'status' => 'processing',
                        'no_document' => $data->no_document,
                        'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                    $job = new RenderPdfPenawaran($request->id, 'non kontrak');
                    $this->dispatch($job);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    public function getTemplatePenawaran()
    {
        $template = DB::table('template_penawaran')->where('is_active', true)->latest()->get();
        return response()->json($template, 200);
    }

    public function getTemplatePenawaranRequest(Request $request)
    {
        $template = DB::table('template_penawaran')->where('id', $request->id)->where('is_active', true)->first();
        return response()->json($template, 200);
    }

    public function updateColumn(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->mode = 'non_kontrak') {
                $data = QuotationNonKontrak::find($request->id);
                $data->update([$request->column => $request->value]);
            } else {
                $data = QuotationKontrakH::find($request->id);
                $data->update([$request->column => $request->value]);
            }

            DB::commit();
            return response()->json(['message' => 'Data berhasil diubah']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function different($id_lama, $id_baru, $array_periode_lama, $array_periode_baru, $no_document)
    {
        try {
            $pengurangan_periode_kontrak = array_values(array_diff($array_periode_lama, $array_periode_baru));
            $penambahan_periode_kontrak = array_values(array_diff($array_periode_baru, $array_periode_lama));
            // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak);
            $qt_lama = QuotationKontrakD::where('id_request_quotation_kontrak_h', $id_lama)
                ->get();
            $qt_baru = QuotationKontrakD::where('id_request_quotation_kontrak_h', $id_baru)
                ->get();

            $perubahan_periode = [];
            // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak);
            if ($pengurangan_periode_kontrak != null) {
                foreach ($qt_lama as $z => $xx) {
                    if (in_array($xx->periode_kontrak, $pengurangan_periode_kontrak)) { // cari periode

                        /**
                         * Apabila periode pengurangan isinya sama dengan periode penambahan
                         * maka data yang ada di periode pengurangan akan dimasukan ke perubahan periode yang baru
                         */
                        $key_pengurangan = array_search($xx->periode_kontrak, $pengurangan_periode_kontrak);

                        $periode_pengganti = $penambahan_periode_kontrak[$key_pengurangan] ?? null;

                        $data_pendukung_sampling_periode_lama = array_values(array_map(function ($item) {
                            return (array) $item['data_sampling'];
                        }, json_decode($qt_lama->where('periode_kontrak', $xx->periode_kontrak)->first()->data_pendukung_sampling, true)))[0];
                        if ($periode_pengganti != null) {
                            $data_pendukung_sampling_periode_baru = array_values(array_map(function ($item) {
                                return (array) $item['data_sampling'];
                            }, json_decode($qt_baru->where('periode_kontrak', $periode_pengganti)->first()->data_pendukung_sampling, true)))[0];
                        } else {
                            $data_pendukung_sampling_periode_baru = [];
                        }

                        // dd($data_pendukung_sampling_periode_lama, $data_pendukung_sampling_periode_baru);
                        if ($data_pendukung_sampling_periode_lama == $data_pendukung_sampling_periode_baru) {
                            /**
                             * Apabila data pendukung samplingnya sama
                             * maka data yang ada di periode pengurangan akan dimasukan ke perubahan periode yang baru
                             */
                            $perubahan_periode[] = [
                                'before' => $xx->periode_kontrak,
                                'after' => $periode_pengganti
                            ];

                            $insert = DB::table('perubahan_periode_quotation')->updateOrInsert(
                                ['no_quotation' => $no_document], // kondisi pencarian
                                [
                                    'periode_awal' => $xx->periode_kontrak,
                                    'periode_revisi' => $periode_pengganti
                                ]
                            );
                            // unset($penambahan_periode_kontrak[$key_pengurangan]);
                        }
                    }
                }
            }

            return $perubahan_periode;
        } catch (\Exception $e) {
            dd($e);
            return [];
        }
    }

    private function changeDataPendukungSamplingKontrak($no_document)
    {
        // dd($request->all());
        try {
            $dataList = QuotationKontrakH::with('orderDetail', 'quotationKontrakD')
                ->whereNotIn('flag_status', ['void', 'rejected'])
                ->where('is_active', true);

            if ($no_document) {
                $dataList = $dataList->whereIn('no_document', $no_document);
            }

            $dataList = $dataList->orderByDesc('id')
                ->get();

            $processedCount = 0;
            $errorCount = 0;

            foreach ($dataList as $data) {
                DB::beginTransaction();
                try {
                    // Cek basic data
                    if (!$data->data_pendukung_sampling || !$data->quotationKontrakD) {
                        DB::rollBack();
                        $errorCount++;
                        continue;
                    }

                    $pendukung_sampling_header = json_decode($data->data_pendukung_sampling, true);
                    if (!$pendukung_sampling_header) {
                        DB::rollBack();
                        $errorCount++;
                        continue;
                    }

                    $detailList = $data->quotationKontrakD;
                    $hasOrderDetail = $data->orderDetail && $data->orderDetail->isNotEmpty();

                    // Order Detail
                    $sortedOrderDetail = null;
                    if ($hasOrderDetail) {
                        $sortedOrderDetail = $data->orderDetail->map(function ($item) {
                            // Sort regulasi
                            if ($item->regulasi) {
                                $regulasi = json_decode($item->regulasi, true);
                                if (is_array($regulasi)) {
                                    $regulasi = array_values(array_filter($regulasi));
                                    sort($regulasi);
                                    $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                }
                            }

                            // Sort parameter
                            if ($item->parameter) {
                                $parameter = json_decode($item->parameter, true);
                                if (is_array($parameter)) {
                                    sort($parameter);
                                    $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                }
                            }

                            return $item;
                        });
                    } else if (!empty($data->data_lama) && !$hasOrderDetail) {
                        $dataLama = json_decode($data->data_lama, true);
                        if ($dataLama && isset($dataLama['no_order'])) {
                            $orderLama = OrderDetail::where('no_order', $dataLama['no_order'])->get();
                            if ($orderLama->isNotEmpty()) {
                                $sortedOrderDetail = $orderLama->map(function ($item) {
                                    // Sort regulasi
                                    if ($item->regulasi) {
                                        $regulasi = json_decode($item->regulasi, true);
                                        if (is_array($regulasi)) {
                                            $regulasi = array_values(array_filter($regulasi));
                                            sort($regulasi);
                                            $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                        }
                                    }

                                    // Sort parameter
                                    if ($item->parameter) {
                                        $parameter = json_decode($item->parameter, true);
                                        if (is_array($parameter)) {
                                            sort($parameter);
                                            $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                        }
                                    }

                                    return $item;
                                });
                            }
                        }
                    }
                    // dd($detailList);
                    // Proses quotation kontrak detail
                    $lastSampel = 0;
                    foreach ($detailList as $detail) {
                        if (!$detail->data_pendukung_sampling)
                            continue;

                        $pendukung_sampling_detail = json_decode($detail->data_pendukung_sampling, true);
                        if (!$pendukung_sampling_detail)
                            continue;

                        $key = array_key_first($pendukung_sampling_detail);
                        if (!$key || !isset($pendukung_sampling_detail[$key]['data_sampling']))
                            continue;

                        $periode = $pendukung_sampling_detail[$key]['periode_kontrak'];
                        $dataSampling = &$pendukung_sampling_detail[$key]['data_sampling'];

                        if ($sortedOrderDetail && $sortedOrderDetail->isNotEmpty()) {
                            $highestSampel = 0;
                            foreach ($sortedOrderDetail as $order) {
                                if ($order->no_sampel) {
                                    $parts = explode('/', $order->no_sampel);
                                    if (count($parts) >= 2 && is_numeric($parts[1])) {
                                        $highestSampel = max($highestSampel, (int) $parts[1]);
                                    }
                                }
                            }
                            $lastSampel = max($lastSampel, $highestSampel);

                            $substract = [];
                            foreach ($dataSampling as &$detailSampling) {
                                if (
                                    !isset(
                                        $detailSampling['kategori_1'],
                                        $detailSampling['kategori_2'],
                                        $detailSampling['parameter'],
                                        $detailSampling['jumlah_titik']
                                    )
                                ) {
                                    continue;
                                }

                                // Sort regulasi dan parameter
                                $regulasi = $detailSampling['regulasi'] ?? [];
                                $parameter = $detailSampling['parameter'] ?? [];

                                if (is_array($regulasi)) {
                                    $regulasi = array_values(array_filter($regulasi));
                                    sort($regulasi);
                                } else {
                                    $regulasi = [$regulasi];
                                }

                                if (is_array($parameter)) {
                                    $parameter = array_values($parameter);
                                    sort($parameter);
                                } else {
                                    $parameter = [$parameter];
                                }

                                $regulasiJson = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                $parameterJson = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));

                                // Filter order detail sesuai criteria
                                $order_detail = $sortedOrderDetail
                                    ->whereNotIn('no_sampel', $substract)
                                    ->where('periode', $periode)
                                    ->where('is_active', true)
                                    // ->where('regulasi', $regulasiJson)
                                    ->where('kategori_2', $detailSampling['kategori_1'])
                                    ->where('kategori_3', $detailSampling['kategori_2'])
                                    // ->where('parameter', $parameterJson)
                                    ->values();

                                // Filter regulasi
                                // dump($regulasi);
                                $order_detail = $order_detail->filter(function ($item) use ($regulasi) {
                                    $item_regulasi = json_decode($item->regulasi, true) ?? [];
                                    return count(array_intersect($item_regulasi, $regulasi)) > 0;
                                });

                                // Filter parameter
                                $order_detail = $order_detail->filter(function ($item) use ($parameter) {
                                    $item_parameter = json_decode($item->parameter, true) ?? [];
                                    return count(array_intersect($item_parameter, $parameter)) > 0;
                                });

                                // Reset key index
                                $order_detail = $order_detail->values();

                                $penamaan_titik = [];
                                $jumlah_titik = (int) $detailSampling['jumlah_titik'];
                                $defaultNaming = is_string($detailSampling['penamaan_titik']) ? $detailSampling['penamaan_titik'] : "";

                                // Penamaan titik sebanyak jumlah titik detail
                                for ($i = 0; $i < $jumlah_titik; $i++) {
                                    $order = $order_detail[$i] ?? null;
                                    if ($order && $order->no_sampel) {
                                        $parts = explode('/', $order->no_sampel);
                                        if (count($parts) >= 2 && is_numeric($parts[1])) {
                                            $no_sample = str_pad((int) $parts[1], 3, '0', STR_PAD_LEFT);
                                            $keterangan = $order->keterangan_1 ?: $defaultNaming;
                                            $penamaan_titik[] = (object) [$no_sample => $keterangan];
                                            $substract[] = $order->no_sampel;
                                            $lastSampel = max($lastSampel, (int) $parts[1]);
                                        }
                                    } else {
                                        $lastSampel++;
                                        $no_sample = str_pad($lastSampel, 3, '0', STR_PAD_LEFT);
                                        $penamaan_titik[] = (object) [$no_sample => $defaultNaming];
                                    }
                                }

                                $detailSampling['penamaan_titik'] = $penamaan_titik;
                            }
                        } else {
                            // Proses tanpa order detail
                            foreach ($dataSampling as &$detailSampling) {
                                if (!isset($detailSampling['jumlah_titik']))
                                    continue;

                                $jumlah_titik = (int) $detailSampling['jumlah_titik'];
                                $defaultNaming = is_string($detailSampling['penamaan_titik']) ? $detailSampling['penamaan_titik'] : "";

                                // Penamaan titik sebanyak jumlah titik detail
                                $penamaan_titik = [];
                                for ($j = 0; $j < $jumlah_titik; $j++) {
                                    $lastSampel++;
                                    $no_sample = str_pad($lastSampel, 3, '0', STR_PAD_LEFT);
                                    $penamaan_titik[] = (object) [$no_sample => $defaultNaming];
                                }
                                $detailSampling['penamaan_titik'] = $penamaan_titik;
                            }
                        }
                        // dump($penamaan_titik);
                        // Update data detail
                        $detail->data_pendukung_sampling = json_encode($pendukung_sampling_detail, JSON_UNESCAPED_UNICODE);
                        // dump($detail->data_pendukung_sampling);
                        $detail->save();
                    }

                    // Proses Header
                    foreach ($pendukung_sampling_header as &$header) {
                        if (!isset($header['periode'], $header['jumlah_titik']))
                            continue;

                        $penamaan_sampling_all = [];

                        foreach ($header['periode'] as $periode) {
                            $kontrakDetail = $detailList->where('periode_kontrak', $periode)->first();
                            if (!$kontrakDetail)
                                continue;

                            $data_sampling_detail = json_decode($kontrakDetail->data_pendukung_sampling, true);
                            if (!$data_sampling_detail)
                                continue;

                            $data_sampling_detail = reset($data_sampling_detail)['data_sampling'] ?? [];

                            // Pencarian matched record dengan early break
                            foreach ($data_sampling_detail as $item) {
                                if (
                                    !isset($item['kategori_1'], $item['kategori_2']) ||
                                    !isset($header['kategori_1'], $header['kategori_2'])
                                ) {
                                    continue;
                                }

                                // Normalisasi array
                                $itemRegulasi = $item['regulasi'] ?? [];
                                $itemParameter = $item['parameter'] ?? [];
                                $headerRegulasi = $header['regulasi'] ?? [];
                                $headerParameter = $header['parameter'] ?? [];

                                if (is_array($itemRegulasi)) {
                                    $itemRegulasi = array_values(array_filter($itemRegulasi));
                                    sort($itemRegulasi);
                                }
                                if (is_array($itemParameter))
                                    sort($itemParameter);
                                if (is_array($headerRegulasi)) {
                                    $headerRegulasi = array_values(array_filter($headerRegulasi));
                                    sort($headerRegulasi);
                                }
                                if (is_array($headerParameter))
                                    sort($headerParameter);

                                if (
                                    $item['kategori_1'] === $header['kategori_1'] &&
                                    $item['kategori_2'] === $header['kategori_2'] &&
                                    $itemRegulasi === $headerRegulasi &&
                                    $itemParameter === $headerParameter
                                ) {

                                    $penamaan_sampling_all[] = $item['penamaan_titik'];
                                    break; // Early break
                                }
                            }
                        }

                        // Filter penamaan titik
                        $penamaan_sampling_all = array_filter($penamaan_sampling_all, function ($group) {
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
                            $penamaan_sampling = array_fill(0, $header['jumlah_titik'], "");
                        }

                        $header['penamaan_titik'] = $penamaan_sampling;
                    }

                    // Update data utama
                    $data->data_pendukung_sampling = json_encode($pendukung_sampling_header, JSON_UNESCAPED_UNICODE);
                    $data->save();
                    // dd('stop');
                    $processedCount++;
                    DB::commit();
                } catch (Throwable $e) {
                    dd($e);
                    DB::rollBack();
                    $errorCount++;
                    Log::error('Error processing document: ' . $data->no_document, [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                    continue;
                }
            }

            return true;

            return response()->json([
                'message' => 'Process completed',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => $dataList->count()
            ], 200);
        } catch (Exception $e) {
            return false;
            Log::error('Critical error in changeDataPendukungSamplingKontrak: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
