<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationNonKontrak;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\Invoice;
use App\Services\GenerateQrDocument;
use App\Http\Controllers\Controller;
use App\Services\RenderInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class CreateInvoiceController extends Controller
{
    private static function generatePDF($noInvoice){
        try {
            $render = new RenderInvoice();
            $render->renderInvoice($noInvoice);
            return true; // Jika sukses
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function renderInvoice(Request $request){
        // dd($request->all());
        try {
            $render = new RenderInvoice();
            $render->renderInvoice($request->no_invoice);
            return true; // Jika sukses
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function forceGenerate(Request $request)
    {
        // dd('force generate');
        try {
            $render = new RenderInvoice();
            $render->renderInvoice($request->no_invoice);
            if($render){
                return response()->json(Invoice::where('no_invoice', $request->no_invoice)->first()->filename, 200); 
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function index(Request $request)
    {
        try {
            $table = $request->status == "kontrak" ? new QuotationKontrakH  : new QuotationNonKontrak;
            
            $data = $table
                ->from(DB::raw($table->getTable() . ' AS quot'))
                ->select(DB::raw('quot.nama_perusahaan, quot.konsultan, quot.filename, quot.no_document, quot.total_discount, quot.total_ppn, quot.biaya_akhir, order_header.no_order, order_header.tanggal_order AS tanggal_order, COALESCE(SUM(invoice.nilai_tagihan), 0) AS tertagih, COUNT(invoice.no_order) AS total_invoice'))
                ->leftJoin('order_header', 'quot.no_document', '=', 'order_header.no_document')
                ->leftJoin('invoice', function ($join) use ($request) {
                    $join->on('order_header.no_order', '=', 'invoice.no_order')
                        ->where('invoice.is_active', true);
                })
                ->where([
                    ['quot.is_active', true],
                    ['order_header.is_active', true],
                    ['quot.flag_status', 'ordered']
                ])
                ->groupBy('quot.nama_perusahaan', 'quot.konsultan', 'quot.filename', 'quot.no_document', 'quot.total_discount', 'quot.total_ppn', 'quot.biaya_akhir', 'order_header.no_order', 'order_header.tanggal_order', 'order_header.id')
                ->orderBy('order_header.id', 'DESC');
        
            // Debugging untuk melihat query yang dijalankan
            // dd($data->toSql());
        
            return Datatables::of($data)->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->whereRaw("LOWER(quot.nama_perusahaan) LIKE ?", ["%{$keyword}%"]);
            })
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->whereRaw("LOWER(quot.no_document) LIKE ?", ["%{$keyword}%"]);
            })
            ->filterColumn('total_invoice', function ($query, $keyword) {
                $query->whereRaw("LOWER(COUNT(invoice.no_order)) LIKE ?", ["%{$keyword}%"]);
            })
            ->filterColumn('konsultan', function ($query, $keyword) {
                $query->whereRaw("LOWER(quot.konsultan) LIKE ?", ["%{$keyword}%"]);
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->whereRaw("LOWER(order_header.no_order) LIKE ?", ["%{$keyword}%"]);
            })
            ->filterColumn('tanggal_order', function ($query, $keyword) {
                $query->whereRaw("LOWER(order_header.tanggal_order) LIKE ?", ["%{$keyword}%"]);
            })
            ->make(true);
        
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
        
    }

    public function insertData(Request $request)
    {   
        DB::beginTransaction();
        try {
            if($request->data){
                $splitData = array_map(function($item) {
                    return explode('_', $item);
                }, $request->data);
                
                foreach ($splitData as $item) {
                    $allDocument[] = $item[0]; 
                    $allOrders[] = $item[1]; 
                }
            }
            $cek_rekening = $request->rekening == 'ppn'? '4976688988' : '4978881988';
            if (count($allOrders) >= 1) {
                $invoiceYear = explode("-", $request->tgl_invoice)[0];
                
                if($request->faktur_pajak != ""){
                    $cekFaktur = Invoice::where('faktur_pajak', $request->faktur_pajak)
                    ->whereYear('tgl_invoice', $invoiceYear)
                    ->where('is_active', true)
                    ->first();
                    
                    if ($cekFaktur && $cek_rekening != '4978881988') {
                        return response()->json([
                            'message' => 'Faktur Pajak Sudah Ada',
                        ], 400);
                    }
                }

                // Buat nomor invoice
                $shortYear = substr($invoiceYear, -2);
                
                $lastInvoice = Invoice::where('rekening', $cek_rekening)
                ->whereYear('tgl_invoice', $invoiceYear)
                ->where('no_invoice', 'like', '%' . $shortYear . '%')
                ->orderBy('no_invoice', 'desc')
                ->value('no_invoice');
                
                $rekPpn = $request->rekening == 'ppn'? '4976688988' : '4978881988';
                $prefix = $request->rekening == 'ppn' ? 'INV' : 'IV';
                if ($invoiceYear == '2024') {
                    $defaultNo = $request->rekening == 'ppn' ? '06767' : '00531';
                } else {
                    $defaultNo = '00001';
                }
                $no = $lastInvoice ? str_pad(intval(substr($lastInvoice, -5)) + 1, 5, "0", STR_PAD_LEFT) : $defaultNo;
                $noInvoice = "ISL/{$prefix}/{$shortYear}{$no}";
                
                $expired = date('Y-m-d', strtotime($request->tgl_jatuh_tempo . ' + 2 years'));
                // dd($expired);
                    
                    $simpanHarga = 0;
                    foreach ($allOrders as $key => $item) {
                        $total_diskon = 0;
                        $getDetail = OrderHeader::where('id_pelanggan', $request->pelanggan)
                        ->where('no_order', $item)
                        ->where('is_active', true)
                        ->first();
                        // cek kontrak atau non kontrak
                        $noDoc = \explode("/", $getDetail->no_document);
                        $tertagih = 0;
                        $invoice_sebelumnya = Invoice::where('no_order', $item)->get();
                        if(count($invoice_sebelumnya) > 0){
                            foreach($invoice_sebelumnya as $inv){
                                $tertagih += $inv->nilai_tagihan;
                            }
                        }
                        if ($noDoc[1] == 'QTC') {
                            $periode = $request->periode_kontrak;
                            // jika "all" maka semua periode
                            if($request->periode_kontrak == "all"){
                                $total_diskon = QuotationKontrakH::where('no_document', $getDetail->no_document)
                                ->where('is_active', true)
                                ->first()->total_discount;
                            } else {
                                $total_diskon = QuotationKontrakD::join('request_quotation_kontrak_H', 'request_quotation_kontrak_D.id_request_quotation_kontrak_h', '=', 'request_quotation_kontrak_H.id')
                                ->where('request_quotation_kontrak_H.no_document', $getDetail->no_document)
                                ->where('request_quotation_kontrak_D.periode_kontrak', $request->periode_kontrak)
                                // ->where('request_quotation_kontrak_D.is_active', true)
                                ->first()->total_discount;
                            }
                        } else {
                            $periode = null;
                            $total_diskon = QuotationNonKontrak::where('no_document', $getDetail->no_document)
                            ->where('is_active', true)
                            ->first()->total_discount;
                        }
                        
                        $getNoInvoice = Invoice::select('no_invoice', 'nilai_tagihan')
                        ->where('no_order', $item)
                        ->where('is_active', true)
                        ->first();
                        
                        $orderSebelumnya = 0;
                        if ($getNoInvoice) {                            
                            $orderSebelumnya = Invoice::select('no_order')
                            ->where('no_invoice', $getNoInvoice->no_invoice)
                            ->where('is_active', true)
                            ->get();
                            
                            if (count($orderSebelumnya) != count($request->data)) {
                                return response()->json([
                                    'message' => 'Order Ini Sudah Pernah Dibuatkan Invoice, Jadi Harus Disamakan Dengan Invoice Sebelumnya',
                                ], 400);
                            }
                            
                            // cek nilai tagihan jika order lebih dari 1 hasilnya minus
                            $bagiHarga = preg_replace('/[Rp., ]/', '', $request->nilai_tagihan) / count($request->data);
                            $cekHarga = $getDetail->biaya_akhir - $getNoInvoice->nilai_tagihan - $bagiHarga;

                            if ($cekHarga < 0) {

                                $simpanHarga += abs($cekHarga);
                                $nilaiTagihan = $getDetail->biaya_akhir - $getNoInvoice->nilai_tagihan;
                            } else {
                                
                                $nilaiTagihan = $bagiHarga + $simpanHarga;
                            }
                            
                        } else {
                            
                            // cek nilai tagihan jika order lebih dari 1 hasilnya minus
                            $bagiHarga = preg_replace('/[Rp., ]/', '', $request->nilai_tagihan) / count($request->data);
                            $cekHarga = $getDetail->biaya_akhir - $bagiHarga;
                            
                            if ($cekHarga < 0) {
                                $simpanHarga += abs($cekHarga);
                                $nilaiTagihan = $getDetail->biaya_akhir;
                            } else {
                                $nilaiTagihan = $bagiHarga + $simpanHarga;
                            }
                        }           
                        $insert[] = [
                            'no_quotation' => $getDetail->no_document,
                            'periode' => $periode,
                            'no_order' => $item,
                            'nama_perusahaan' => $request->nama_perusahaan,
                            'pelanggan_id' => $request->pelanggan,
                            'no_invoice' => $noInvoice,
                            'faktur_pajak' => $request->faktur_pajak,
                            'no_faktur' => $request->no_faktur,
                            'no_spk' => $request->no_spk,
                            'no_po' => $request->no_po,
                            'tgl_jatuh_tempo' => $request->tgl_jatuh_tempo,
                            'keterangan_tambahan' => $request->keterangan_tambahan ? json_encode($request->keterangan_tambahan) : null,
                            'tgl_faktur' => DATE('Y-m-d H:i:s'),
                            'tgl_invoice' => $request->tgl_invoice,
                            'nilai_tagihan' => $nilaiTagihan,
                            'total_tagihan' => preg_replace('/[Rp., ]/', '', $request->total_tagihan) / count($request->data),
                            'rekening' => $cek_rekening,
                            'nama_pj' => $request->nama_pj,
                            'jabatan_pj' => $request->jabatan_pj,
                            'keterangan' => $request->keterangan,
                            'alamat_penagihan' => $request->alamat_penagihan,
                            'nama_pic' => $request->nama_pic,
                            'no_pic' => $request->no_pic,
                            'email_pic' => $request->email_pic,
                            'jabatan_pic' => $request->jabatan_pic,
                            'ppnbm' => $total_diskon,
                            'ppn' => $getDetail->total_ppn,
                            'piutang' => $getDetail->biaya_akhir - $tertagih,
                            'created_by' => $this->karyawan,
                            'created_at' => DATE('Y-m-d H:i:s'),
                            'is_emailed' => 0,
                            'is_generate' => 0,
                            'expired' => $expired,
                        ];
                    }
                    DB::table('invoice')->insert($insert);
                }

                // $filename = \str_replace("/", "_", $noInvoice);
                // $path = public_path() . "/qr_documents/" . $filename . '.svg';
                // $link = 'https://www.intilab.com/validation/';
                // $unique = 'isldc' . (int)floor(microtime(true) * 1000);

                // QrCode::size(200)->generate($link . $unique, $path);

                // $dataQr = [
                //     'type_document' => 'invoice',
                //     'kode_qr' => $unique,
                //     'file' => $filename,
                //     'data' => json_encode([
                //         'no_document' => $noInvoice,
                //         'nama_customer' => $getDetail->nama_perusahaan,
                //         'type_document' => 'invoice'
                //     ]),
                //     'created_at' => DATE('Y-m-d H:i:s'),
                //     'created_by' => $this->karyawan,
                // ];

                // DB::table('qr_documents')->insert($dataQr);
                self::generatePDF($noInvoice);
                // dd('masukkkk');
                DB::commit();
                return response()->json([
                    'message' => 'Data Has been Create',
                ], 201);

            } catch (\Exception $th) {
                DB::rollback();
                dd($th);
                return response()->json([
                    'message' => $th->getMessage(),
                ], 401);
            }
    }

    public function getDetails(Request $request)
    {
        try {
            if ($request->status == "kontrak") {
                $data = QuotationKontrakH::
                select('quot_h.no_document', 'quot_h.wilayah', 'quot_h.id', 'order.tanggal_order', 'quot_d.status_sampling', 'quot_h.nama_perusahaan', 'quot_h.konsultan', 'quot_h.id_cabang', 'quot_h.jabatan_pic_order', 'quot_h.jabatan_pic_sampling', 'quot_h.alamat_kantor', 'quot_h.no_tlp_perusahaan', 'quot_h.nama_pic_order', 'quot_h.email_pic_order', 'quot_h.nama_pic_sampling', 'quot_h.alamat_sampling', 'quot_h.no_tlp_pic_sampling', 'quot_h.email_pic_sampling', 'quot_d.data_pendukung_sampling', 'quot_d.transportasi', 'quot_d.harga_transportasi_total', 'quot_d.harga_transportasi', 'quot_d.jumlah_orang_24jam AS jam_jumlah_orang_24', 'quot_d.harga_24jam_personil_total', 'quot_d.perdiem_jumlah_orang', 'quot_d.harga_perdiem_personil_total', 'quot_d.biaya_lain', 'quot_d.biaya_preparasi AS biaya_preparasi_padatan', 'quot_d.grand_total', 'quot_d.discount_air', 'quot_d.total_discount_air', 'quot_d.discount_non_air', 'quot_d.total_discount_non_air', 'quot_d.discount_udara', 'quot_d.total_discount_udara', 'quot_d.discount_emisi', 'quot_d.total_discount_emisi', 'quot_d.discount_transport', 'quot_d.total_discount_transport', 'quot_d.discount_perdiem', 'quot_d.total_discount_perdiem', 'quot_d.discount_perdiem_24jam', 'quot_d.total_discount_perdiem_24jam', 'quot_d.discount_gabungan', 'quot_d.total_discount_gabungan', 'quot_d.discount_consultant', 'quot_d.total_discount_consultant', 'quot_d.discount_group', 'quot_d.total_discount_group', 'quot_d.cash_discount_persen', 'quot_d.total_cash_discount_persen', 'quot_d.cash_discount', 'quot_d.custom_discount', 'quot_h.syarat_ketentuan', 'quot_h.keterangan_tambahan', 'quot_d.total_dpp', 'quot_d.total_ppn', 'quot_d.total_pph', 'quot_d.pph', 'quot_d.biaya_di_luar_pajak', 'quot_d.piutang', 'quot_d.biaya_akhir', 'order.no_order', 'quot_h.is_approved', 'quot_h.approved_by', 'add.nama_lengkap AS created_by', 'add.no_telpon', 'update.nama_lengkap AS updated_by', 'quot_d.periode_kontrak', 'quot_h.data_pendukung_sampling AS pendukung_sampling', 'quot_h.transportasi AS transportasiH', 'quot_h.harga_transportasi_total AS harga_transportasi_totalH', 'quot_h.jumlah_orang_24jam AS jam_jumlah_orang_24H', 'quot_h.harga_24jam_personil_total AS harga_24jam_personil_totalH', 'quot_h.perdiem_jumlah_orang AS perdiem_jumlah_orangH', 'quot_h.harga_perdiem_personil_total AS harga_perdiem_personil_totalH', 'quot_h.total_biaya_lain AS total_biaya_lainH', 'quot_h.total_biaya_preparasi AS total_biaya_preparasiH', 'quot_h.grand_total AS grand_totalH', 'quot_h.discount_air AS discount_airH', 'quot_h.total_discount_air AS total_discount_airH', 'quot_h.discount_non_air AS discount_non_airH', 'quot_h.total_discount_non_air AS total-discount_non_airH', 'quot_h.discount_udara AS discount_udaraH', 'quot_h.total_discount_udara AS total_discount_udaraH', 'quot_h.discount_emisi AS discount_emisiH', 'quot_h.total_discount_emisi AS total_discount_emisiH', 'quot_h.discount_transport AS discount_transportH', 'quot_h.total_discount_transport AS total_discount_transportH', 'quot_h.discount_perdiem AS discount_perdiemH', 'quot_h.total_discount_perdiem AS total_discount_perdiemH', 'quot_h.discount_perdiem_24jam AS discount_perdiem_24jamH', 'quot_h.total_discount_perdiem_24jam AS total_discount_perdiem_24jamH', 'quot_h.discount_gabungan AS discount_gabunganH', 'quot_h.total_discount_gabungan AS total_discount_gabunganH', 'quot_h.discount_consultant AS discount_consultantH', 'quot_h.total_discount_consultant AS total_discount_consultantH', 'quot_h.discount_group AS discount_groupH', 'quot_h.total_discount_group AS total_discount_groupH', 'quot_h.cash_discount_persen AS cash_discount_persenH', 'quot_h.total_cash_discount_persen AS total_cash_discount_persenH', 'quot_h.cash_discount AS cash_discountH', 'quot_h.total_cash_discount AS total_cash_discountH', 'quot_h.total_dpp AS total_dppH', 'quot_h.total_ppn AS total_ppnH', 'quot_h.total_pph AS total_pphH', 'quot_h.biaya_akhir AS biaya_akhirH', 'quot_h.pph AS pphH', 'quot_h.ppn AS ppnH')
                ->from('request_quotation_kontrak_H as quot_h')     
                ->leftJoin('request_quotation_kontrak_D AS quot_d', 'quot_h.id', '=', 'quot_d.id_request_quotation_kontrak_h')
                ->leftJoin('order_header AS order', 'quot_h.no_document', '=', 'order.no_document')
                ->leftJoin('master_karyawan AS add', 'quot_h.created_by', '=', 'add.id')
                ->leftJoin('master_karyawan AS update', 'quot_h.updated_by', '=', 'update.id')
                ->where('quot_h.is_active', true)
                ->where('order.is_active', true)
                ->where('quot_h.flag_status', 'ordered')
                ->where('quot_h.no_document', $request->no_document)
                ->orderBy('quot_d.periode_kontrak', 'ASC')
                ->get();
                
                // dd('masuk');
            } else {
                
                $data = QuotationNonKontrak::select('quot.no_document', 'quot.wilayah', 'quot.id', 'order.tanggal_order', 'quot.status_sampling', 'quot.nama_perusahaan', 'quot.konsultan', 'quot.id_cabang', 'quot.jabatan_pic_order', 'quot.jabatan_pic_sampling', 'quot.alamat_kantor', 'quot.no_tlp_perusahaan', 'quot.nama_pic_order', 'quot.email_pic_order', 'quot.nama_pic_sampling', 'quot.alamat_sampling', 'quot.no_tlp_pic_sampling', 'quot.email_pic_sampling', 'quot.data_pendukung_sampling', 'quot.transportasi', 'quot.harga_transportasi_total', 'quot.harga_transportasi', 'quot.jumlah_orang_24jam AS jam_jumlah_orang_24', 'quot.harga_24jam_personil_total', 'quot.perdiem_jumlah_orang', 'quot.harga_perdiem_personil_total', 'quot.biaya_lain', 'quot.biaya_preparasi_padatan', 'quot.grand_total', 'quot.discount_air', 'quot.total_discount_air', 'quot.discount_non_air', 'quot.total_discount_non_air', 'quot.discount_udara', 'quot.total_discount_udara', 'quot.discount_emisi', 'quot.total_discount_emisi', 'quot.discount_transport', 'quot.total_discount_transport', 'quot.discount_perdiem', 'quot.total_discount_perdiem', 'quot.discount_perdiem_24jam', 'quot.total_discount_perdiem_24jam', 'quot.discount_gabungan', 'quot.total_discount_gabungan', 'quot.discount_consultant', 'quot.total_discount_consultant', 'quot.discount_group', 'quot.total_discount_group', 'quot.cash_discount_persen', 'quot.total_cash_discount_persen', 'quot.cash_discount', 'quot.custom_discount', 'quot.syarat_ketentuan', 'quot.keterangan_tambahan', 'quot.total_dpp', 'quot.total_ppn', 'quot.total_pph', 'quot.pph', 'quot.biaya_di_luar_pajak', 'quot.piutang', 'quot.biaya_akhir', 'order.no_order', 'quot.is_approved', 'quot.approved_by', 'add.nama_lengkap AS created_by', 'add.no_telpon', 'update.nama_lengkap AS updated_by')
                    ->from('request_quotation as quot')     
                    ->leftJoin('order_header AS order', 'quot.no_document', '=', 'order.no_document')
                    ->leftJoin('master_karyawan AS add', 'quot.created_by', '=', 'add.id')
                    ->leftJoin('master_karyawan AS update', 'quot.updated_by', '=', 'update.id')
                    ->where('quot.is_active', true)
                    ->where('order.is_active', true)
                    ->where('quot.flag_status', 'ordered')
                    ->where('quot.no_document', $request->no_document)
                    ->get();

            }

            return response()->json([
                'data' => $data,
            ], 200);

        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getDetailPelanggan(Request $request)
    {
        // dd($request->all());
        try {
            // $tabel = ($request->status == "kontrak") ? 'request_quotation_kontrak_H AS quot' : 'request_quotation AS quot';
            $table = $request->status == "kontrak" ? new QuotationKontrakH  : new QuotationNonKontrak;
            // dd($table);
            $data = $table->from(DB::raw($table->getTable() . ' AS quot'))
                ->select(DB::raw('
                    order_header.no_document, 
                    order_header.no_order, 
                    order_header.id_pelanggan, 
                    order_header.nama_perusahaan, 
                    order_header.konsultan, 
                    quot.alamat_kantor, 
                    quot.alamat_sampling, 
                    quot.keterangan_tambahan,
                    order_header.no_pic_order, 
                    order_header.nama_pic_order, 
                    order_header.jabatan_pic_order, 
                    order_header.no_pic_order, 
                    order_header.email_pic_order, 
                    order_header.biaya_akhir, 
                    COALESCE(SUM(invoice.nilai_tagihan), 0) AS tertagih, 
                    COALESCE(SUM(invoice.nilai_pelunasan), 0) AS total_pelunasan,
                    YEAR(order_header.tanggal_order) AS tahun_order
                '))
                ->leftJoin('order_header', 'quot.no_document', '=', 'order_header.no_document')
                ->leftJoin('invoice', function ($join) use ($request) {
                    $join->on('order_header.no_order', '=', 'invoice.no_order')
                        ->where('invoice.is_active', true);
                })
                ->where('order_header.is_active', true)
                ->where('order_header.id_pelanggan', $request->id_pelanggan)
                ->groupBy(
                    'order_header.no_document', 
                    'order_header.id_pelanggan', 
                    'order_header.nama_perusahaan', 
                    'order_header.konsultan', 
                    'order_header.biaya_akhir', 
                    'order_header.no_order', 
                    'quot.alamat_kantor',
                    'quot.keterangan_tambahan', 
                    'quot.alamat_sampling', 
                    'order_header.no_pic_order', 
                    'order_header.nama_pic_order', 
                    'order_header.jabatan_pic_order', 
                    'order_header.no_pic_order', 
                    'order_header.email_pic_order', 
                    DB::raw('YEAR(order_header.tanggal_order)')
                )
                // disini dicek: hanya ambil kalau biaya_akhir > total_pelunasan
                ->havingRaw('order_header.biaya_akhir > COALESCE(SUM(invoice.nilai_pelunasan), 0)')
                ->get();


            return response()->json([
                'data' => $data,
            ], 200);

        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getPelanggan(Request $request)
    {
        try {

            $data = OrderHeader::select('id_pelanggan', 'nama_perusahaan')
                ->where('is_active', true)
                ->where('nama_perusahaan', 'like', '%' . $request->term . '%')
                ->groupBy('id_pelanggan', 'nama_perusahaan')
                ->get();

            return response()->json([
                'data' => $data,
            ], 200);

        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getPeriode(Request $request)
    {
        try {
            // dd($request->all());

            $splitData = array_map(function($item) {
                return explode('_', $item);
            }, $request->data);
            
            foreach ($splitData as $item) {
                $allDocument[] = $item[0]; 
                $allOrders[] = $item[1]; 
            }
            
            if($request->no_invoice) {
                $invoice = Invoice::with('orderHeaderQuot')->where('no_invoice', $request->no_invoice)->where('is_active', true)->first();
                if($invoice->orderHeaderQuot == null) {
                    $quotation = $invoice->Quotation();
                    $status = '';
                    if($quotation) {
                        if($quotation->flag_status == "sp") {
                            $status = 'masih di tahap Sampling Plan';
                        } else if($quotation->flag_status == "draft") {
                            $status = 'masih di tahap Draft';
                        } else if($quotation->flag_status == "emailed") {
                            $status = 'masih di tahap Emailed';
                        } else {
                            $status = 'telah di Void';
                        }
                    } else {
                        $status = 'telah di Void';
                    }
                    return response()->json([
                        'message' => 'Quotation ' . $status . '. Silahkan konfirmasi ke divisi Sales.',
                    ], 400);
                }

                if($invoice->no_quotation != $allDocument[0]) {
                    return response()->json([
                        'message' => 'No Quotation Not Match',
                    ], 200);
                }
                $data = QuotationKontrakH::select(
                    'request_quotation_kontrak_H.no_document', 
                    'request_quotation_kontrak_D.periode_kontrak', 
                    DB::raw('CEIL(request_quotation_kontrak_D.biaya_akhir - COALESCE(SUM(invoice.nilai_tagihan), 0)) AS biaya_akhir')
                )
                ->leftJoin(
                    'request_quotation_kontrak_D', 
                    'request_quotation_kontrak_H.id', 
                    '=', 
                    'request_quotation_kontrak_D.id_request_quotation_kontrak_H'
                )
                ->leftJoin(
                    'invoice', 
                    function ($join) use ($request) {
                        $join->on('request_quotation_kontrak_H.no_document', '=', 'invoice.no_quotation')
                            ->on('request_quotation_kontrak_D.periode_kontrak', '=', 'invoice.periode')
                            ->where('invoice.is_active', true)
                            ->where('invoice.no_invoice', '!=', $request->no_invoice);
                    }
                )
                ->whereIn('request_quotation_kontrak_H.no_document', (array) $allDocument)
                ->groupBy('request_quotation_kontrak_H.no_document', 'request_quotation_kontrak_D.periode_kontrak', 'request_quotation_kontrak_D.biaya_akhir')
                ->get();

                $detailHarga = OrderHeader::leftJoin(DB::raw("
                    (
                        SELECT no_order, SUM(nilai_tagihan) AS total_tagihan
                        FROM invoice
                        WHERE is_active = true
                        AND no_invoice != '{$request->no_invoice}'
                        GROUP BY no_order
                    ) AS inv
                "), 'order_header.no_order', '=', 'inv.no_order')
                ->whereIn('order_header.no_order', $allOrders)
                ->where('order_header.is_active', true)
                ->select(DB::raw('SUM(order_header.biaya_akhir - COALESCE(inv.total_tagihan, 0)) AS total_tertagih'))
                ->value('total_tertagih');
            } else {
                $data = QuotationKontrakH::select(
                    'request_quotation_kontrak_H.no_document', 
                    'request_quotation_kontrak_D.periode_kontrak', 
                    DB::raw('CEIL(request_quotation_kontrak_D.biaya_akhir - COALESCE(SUM(invoice.nilai_tagihan), 0)) AS biaya_akhir')
                )
                ->leftJoin(
                    'request_quotation_kontrak_D', 
                    'request_quotation_kontrak_H.id', 
                    '=', 
                    'request_quotation_kontrak_D.id_request_quotation_kontrak_H'
                )
                ->leftJoin(
                    'invoice', 
                    function ($join) {
                        $join->on('request_quotation_kontrak_H.no_document', '=', 'invoice.no_quotation')
                            ->on('request_quotation_kontrak_D.periode_kontrak', '=', 'invoice.periode')
                            ->where('invoice.is_active', true);
                    }
                )
                ->whereIn('request_quotation_kontrak_H.no_document', (array) $allDocument)
                ->groupBy('request_quotation_kontrak_H.no_document', 'request_quotation_kontrak_D.periode_kontrak', 'request_quotation_kontrak_D.biaya_akhir')
                ->get();

                $detailHarga = OrderHeader::leftJoin(DB::raw("
                    (
                        SELECT no_order, SUM(nilai_tagihan) AS total_tagihan
                        FROM invoice
                        WHERE is_active = true
                        GROUP BY no_order
                    ) AS inv
                "), 'order_header.no_order', '=', 'inv.no_order')
                ->whereIn('order_header.no_order', $allOrders)
                ->where('order_header.is_active', true)
                ->select(DB::raw('SUM(order_header.biaya_akhir - COALESCE(inv.total_tagihan, 0)) AS total_tertagih'))
                ->value('total_tertagih');
            }
             
            $harga = QuotationKontrakH::select(DB::raw('biaya_akhir, COALESCE(SUM(invoice.nilai_tagihan), 0) AS tertagih'))
                ->leftJoin('invoice', function ($join){
                    $join->on('request_quotation_kontrak_H.no_document', '=', 'invoice.no_quotation')
                        ->where('invoice.is_active', true);
                })
                ->where('request_quotation_kontrak_H.no_document', $allDocument[0])
                ->groupBy('biaya_akhir')
                ->first();

            if($harga){
                $harga->biaya_akhir = CEIL($harga->biaya_akhir - CEIL($harga->tertagih));
            }

            // $detailHarga = OrderHeader::whereIn('no_document', $allDocument)->where('is_active', true)->sum('biaya_akhir');  

            $tlgSampling = OrderDetail::select('tanggal_sampling')
                ->where('no_order',$allOrders[0])
                ->orderBy('tanggal_sampling', 'ASC')
                ->first();

            $getNoInvoice = Invoice::select('no_invoice')
                ->where('no_order', $allOrders[0])
                ->where('is_active', true)
                ->first();

            $orderSebelumnya = null;

            if ($getNoInvoice) {

                $orderSebelumnya = DB::table('invoice')
                    ->select('no_order')
                    ->where('no_invoice', $getNoInvoice->no_invoice)
                    ->where('is_active', true)
                    ->get();

            }
            if ($harga) {
                $harga->biaya_akhir = CEIL($harga->biaya_akhir);
                $harga->tertagih = CEIL($harga->tertagih);
            }
            
            $detailHarga = CEIL($detailHarga);

            return response()->json([
                'data' => $data,
                'harga' => $harga,
                'detailHarga' => $detailHarga,
                'sampling' => $tlgSampling,
                'orderSebelumnya' => $orderSebelumnya,
            ], 200);

        } catch (\Throwable $th) {
            dd($th);
        }
    }

    // 05/02/2025 update perhitungan harga
    // public function getPeriode(Request $request)
    // {
    //     try {
    //         // dd($request->all());

    //         $splitData = array_map(function($item) {
    //             return explode('_', $item);
    //         }, $request->data);
            
    //         foreach ($splitData as $item) {
    //             $allDocument[] = $item[0]; 
    //             $allOrders[] = $item[1]; 
    //         }
    //         $data = QuotationKontrakH::select('request_quotation_kontrak_H.no_document', 'request_quotation_kontrak_D.periode_kontrak', DB::raw('CEIL(request_quotation_kontrak_D.biaya_akhir / 1000) * 1000 AS biaya_akhir'))
    //             ->leftJoin('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
    //             ->where('no_document', $allDocument)
    //             ->get();

    //             // 05-02-2025
    //         // $data = QuotationKontrakH::select('request_quotation_kontrak_H.no_document', 'request_quotation_kontrak_D.periode_kontrak', 'request_quotation_kontrak_D.biaya_akhir')
    //         //     ->leftJoin('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
    //         //     ->where('no_document', $allDocument)
    //         //     ->get();
    //             // dd($data);
                    
            
    //         $harga = QuotationKontrakH::select(DB::raw('biaya_akhir, COALESCE(SUM(invoice.nilai_tagihan), 0) AS tertagih'))
    //             ->leftJoin('invoice', function ($join){
    //                 $join->on('request_quotation_kontrak_H.no_document', '=', 'invoice.no_order')
    //                     ->where('invoice.is_active', true);
    //             })
    //             ->where('request_quotation_kontrak_H.no_document', $allDocument[0])
    //             ->groupBy('biaya_akhir')
    //             ->first();

    //         $detailHarga = OrderHeader::whereIn('no_document', $allDocument)->sum('biaya_akhir');

    //         $tlgSampling = OrderDetail::select('tanggal_sampling')
    //             ->where('no_order',$allOrders[0])
    //             ->orderBy('tanggal_sampling', 'ASC')
    //             ->first();

    //         $getNoInvoice = Invoice::select('no_invoice')
    //             ->where('no_order', $allOrders[0])
    //             ->where('is_active', true)
    //             ->first();

    //         $orderSebelumnya = null;

    //         if ($getNoInvoice) {

    //             $orderSebelumnya = DB::table('invoice')
    //                 ->select('no_order')
    //                 ->where('no_invoice', $getNoInvoice->no_invoice)
    //                 ->where('is_active', true)
    //                 ->get();

    //         }
    //         if ($harga) {
    //             $harga->biaya_akhir = ceil($harga->biaya_akhir / 1000) * 1000;
    //             $harga->tertagih = ceil($harga->tertagih / 1000) * 1000;
    //         }
            
    //         $detailHarga = ceil($detailHarga / 1000) * 1000;


    //         return response()->json([
    //             'data' => $data,
    //             'harga' => $harga,
    //             'detailHarga' => $detailHarga,
    //             'sampling' => $tlgSampling,
    //             'orderSebelumnya' => $orderSebelumnya,
    //         ], 200);

    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }
    
}