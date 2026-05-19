<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\RenderPdfPenawaran;

use App\Services\RenderNonKontrak;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use App\Services\GenerateQrDocument;
use App\Services\GenerateToken;
use App\Services\SendEmail;

use App\Models\QuotationNonKontrak;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\ParameterSar;
use App\Models\MasterRegulasi;
use App\Models\Parameter;
use App\Models\HargaTransportasi;
use App\Models\HargaParameter;
use App\Models\JobTask;
use App\Models\MasterPelanggan;
use App\Models\KelengkapanKonfirmasiQs;
use App\Models\{KontakPelanggan, AlamatPelanggan, PicPelanggan};

class QuotationSarController extends Controller
{
    public function index(Request $request)
    {
        $data = QuotationNonKontrak::with('pelanggan',
                    'sales')->where('is_active', true)->where('status_sampling', 'SAR')->where('id_cabang', $request->id_cabang)->whereYear('tanggal_penawaran', $request->periode)->where(function ($q) {
                        $q->whereNull('flag_status')
                        ->orWhereNotIn('flag_status', ['ordered', 'void']);
                    });
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
        return Datatables::of($data)->make(true);
    }

    public function getCabang(Request $request)
    {
        $cabang = MasterCabang::where('is_active', true)->get();
        return response()->json($cabang);
    }

    public function getRegulasi(Request $request)
    {
        $query = MasterRegulasi::with('bakumutu')->where('is_active', true);

        if ($request->has('term') && $request->term !== null && $request->term !== "") {
            $searchTerm = $request->term;
            $query = $query->where(function ($q) use ($searchTerm) {
                $q->where('peraturan', 'like', "%{$searchTerm}%")
                  ->orWhere('deskripsi', 'like', "%{$searchTerm}%");
            });
        }

        $page = $request->get('page', 1);
        $perPage = 20;
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $results->count() === $perPage
            ]
        ]);
    }

    public function getParameter(Request $request)
    {
        $regulasi = ParameterSar::with('hargaParameter')->select('id_parameter as id', 'nama_lab', 'nama_regulasi', 'nilai_rujukan')->where('is_active', true)->get();
        return response()->json($regulasi);
    }

    public function getKategori(Request $request)
    {
        return response()->json([
            'id' => "00",
            'nama_kategori' => "Quick Test Parameter"
        ]);
    }

    public function writeQuotationSar (Request $request)
    {
        $payload = json_decode(json_encode($request->all(), JSON_OBJECT_AS_ARRAY));

        switch ($payload->informasi_pelanggan->mode) {
            case 'create':
                return $this->create($payload);
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

    private function create($payload) {
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

            $no_ = 1;  // Set default nomor urut menjadi 1

            if ($cek != null) {
                // Pisahkan komponen no_document untuk mengambil tahun dan nomor urut terakhir
                $parts = explode('/', $cek->no_document);

                if (count($parts) > 3) {  // Pastikan formatnya sesuai
                    $tahun_cek_full = $parts[2];  // Tahun dan bulan dokumen terakhir
                    list($tahun_cek_docLast, $bulan_cek_docLast) = explode('-', $tahun_cek_full);

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
                    //per kategori
                    $param = $item->parameter;
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;
                    $is_paket = isset($item->is_paket_analisa) ? $item->is_paket_analisa : false;
                    
                    
                    $parameter = [];
                    $kategori_analisa = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                        array_push($kategori_analisa, trim($cek_par->id_kategori));
                    }

                    $kategori_analisa_unique = array_unique($kategori_analisa);

                    $hargaAnalisaTotal = 0;
                    $hargaPertitikTotal = 0;
                    foreach ($kategori_analisa_unique as $kategori_) {
                        $harga_db = [];
                        $volume_db = [];
                        foreach ($parameter as $param_) {
                            $ambil_data = HargaParameter::where('id_kategori', $kategori_)
                            ->where('nama_parameter', $param_)
                            ->orderBy('id', 'ASC')
                            ->get();
                            $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                            }) ?? $ambil_data->first();
                            $harga_db[] = $cek_harga_parameter->harga ?? 0;
                            $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        }
                        $harga_pertitik = (object) [
                            'volume' => array_sum($volume_db),
                            'total_harga' => array_sum($harga_db)
                        ];

                        if ($harga_pertitik->volume != null) {
                            $vol += floatval($harga_pertitik->volume);
                        }
                        $titik = $item->jumlah_titik;

                        $hargaPaket = 0;
                        $hargaSatuan = 0;
                        $kelipatan = 0;

                        if($is_paket){
                            $dataPaket = TemplatePaketAnalisa::where('id', $item->paket_id)->first();
                            $dataPaketAnalisa = json_decode($dataPaket->data_pendukung_sampling, true);
                            foreach ($dataPaketAnalisa as $paket) {
                                if(
                                    $paket['regulasi'] == $item->regulasi &&
                                    $paket['parameter'] == $param && 
                                    $paket['kategori_1'] == $item->kategori_1
                                ) {
                                    $pengali = ($titik / (int)$paket['jumlah_titik']);
                                    $harga_sementara = (int)$paket['harga_paket'] * $pengali;
                                    $hargaPaket += $harga_sementara;
                                    $hargaSatuan = $paket['harga_paket'];
                                    $kelipatan = (int)$paket['jumlah_titik'];
                                } else {
                                    continue;
                                }
                            }
                        } 

                        $hargaAnalisa = $is_paket ? $hargaPaket : (floatval($harga_pertitik->total_harga) * (int) $titik);
                        $hargaPerTitik = $is_paket ? $hargaSatuan : $harga_pertitik->total_harga;
                        $hargaAnalisaTotal += $hargaAnalisa;
                        $hargaPertitikTotal += $hargaPerTitik;
                        $temp_preparasi = [];
                        if (isset($item->biaya_preparasi) && $item->biaya_preparasi != null) {
                            foreach ($item->biaya_preparasi as $pre) {
                                if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null) {
                                    $temp_preparasi[] = [
                                        'Deskripsi' => $pre->desc_preparasi,
                                        'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))
                                    ];
                                }
                                if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") {
                                    $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                                }
                            }
                        }

                        $data_sampling[$i] = [
                            'kategori_1' => $item->kategori_1,
                            // 'kategori_2' => $item->kategori_2,
                            'regulasi' => isset($item->regulasi) ? $item->regulasi : '',
                            'penamaan_titik' => isset($item->penamaan_titik) ? $item->penamaan_titik : [],
                            'parameter' => $param,
                            'jumlah_titik' => $titik,
                            'total_parameter' => count($param),
                            'harga_satuan' => $hargaPertitikTotal,
                            'harga_total' => $hargaAnalisaTotal,
                            'volume' => $vol,
                            'biaya_preparasi' => $temp_preparasi,
                        ];
                        
                        if ($is_paket) {
                            $data_sampling[$i]['is_paket_analisa'] = $is_paket;
                            $data_sampling[$i]['paket_id'] = $item->paket_id;
                            $data_sampling[$i]['paket'] = $item->paket;
                            $data_sampling[$i]['kelipatan_dasar'] = $kelipatan;
                        }
                        switch ($kategori_) {
                            case '1':
                                $harga_air += $hargaAnalisa;
                                break;
                            case '4':
                                $harga_udara += $hargaAnalisa;
                                break;
                            case '5':
                                $harga_emisi += $hargaAnalisa;
                                break;
                            case '6':
                                $harga_padatan += $hargaAnalisa;
                                break;
                            case '7':
                                $harga_swab_test += $hargaAnalisa;
                                break;
                            case '8':
                                $harga_tanah += $hargaAnalisa;
                                break;
                            case '9':
                                $harga_pangan += $hargaAnalisa;
                                break;
                        }
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

            // $data->grand_total = $grand_total;
            // $data->sales_id = $sales_id;
            $expOp = explode("-", $payload->data_wilayah->wilayah);
            $nama_wilayah = implode("-", array_slice($expOp, 1));

            $ambil_data_transport = HargaTransportasi::where('wilayah', $nama_wilayah)
            ->orderBy('id', 'ASC')
            ->get();

            $cekOperasional = $ambil_data_transport->first(function ($item) use ($payload) {
                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
            }) ?? $ambil_data_transport->first();

            $data->status_wilayah = $payload->data_wilayah->status_wilayah;
            $data->wilayah = $payload->data_wilayah->wilayah;
            $data->transportasi = !in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR']) ? $payload->data_wilayah->transportasi : null;
            $data->kalkulasi_by_sistem = $payload->data_wilayah->kalkulasi_by_sistem;

            $harga_transport = 0;
            $jam = 0;
            $transport = 0;
            $perdiem = 0;

            if ($payload->data_wilayah->status_wilayah == 'DALAM KOTA') {
                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
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

                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == 'on') {
                        $data->kalkulasi_by_sistem = $payload->data_wilayah->kalkulasi_by_sistem;
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

            // ==================== DISKON DENGAN KODE PROMO ===================== //
            if (!empty($payload->data_diskon->kode_promo_discount) && floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount)) > 0) {
                $biaya_pengujian = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
                $discount_promo = floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount));
                $total_discount_promo = $biaya_pengujian / 100 *  $discount_promo;

                $data->kode_promo = $payload->data_diskon->kode_promo_discount;
                $data->discount_promo = json_encode((object)[
                    'deskripsi_promo_discount' => $payload->data_diskon->deskripsi_promo_discount,
                    'jumlah_promo_discount' => $payload->data_diskon->jumlah_promo_discount
                ]);
                $data->total_discount_promo = floatval($total_discount_promo);
                $total_diskon += $total_discount_promo;
                $harga_total -= floatval($total_discount_promo);
            } else {
                // $harga_total += 0;
                // $data->discount_air = null;
                $data->total_discount_promo = 0;
                $data->discount_promo = null;
                $data->kode_promo = null;
            }
            // ==================== END DISKON DENGAN KODE PROMO ======================= //

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
            $data->sales_id = $payload->informasi_pelanggan->sales_id;
            $data->is_approved = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = DATE('Y-m-d H:i:s');
            $data->flag_status = 'draft';
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->expired = date('Y-m-d', strtotime('+30 days'));
            $data->save();

            $message = "Penawaran dengan nomor $data->no_document berhasil di update.";
            $data_lama = ($data->data_lama != null) ? json_decode($data->data_lama) : null;

            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $data->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $job = new RenderPdfPenawaran($data->id, 'quotation-sar');
            $this->dispatch($job);

            $array_id_user = GetAtasan::where('id', $data->sales_id)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $array_id_user)
                ->title('Penawaran telah diperbarui')
                ->message('Penawaran dengan nomor ' . $data->no_document . ' telah diperbarui.')
                ->url('/quote-request')
                ->send();

            DB::commit();

            return response()->json([
                'message' => "Penawaran dengan nomor $data->no_document berhasil di update."
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => "Gagal memperbarui penawaran. Error: " . $e->getMessage()
            ], 401);
        }
    }

    private function updateNonKontrak($payload)
    {
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
            $data->is_generate_data_lab = $payload->data_wilayah->is_generate_data_lab;
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
            $data->use_kuota = $payload->data_diskon->use_kuota;

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

            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    //per kategori
                    $param = $item->parameter;
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;
                    $is_paket = isset($item->is_paket_analisa) ? $item->is_paket_analisa : false;


                    $parameter = [];
                    $kategori_analisa = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                        array_push($kategori_analisa, $cek_par->id_kategori);
                    }
                    $kategori_analisa = array_unique($kategori_analisa);
                    $harga_pertitik_total = 0;
                    $harga_pertitik_satuan =0;
                    foreach($kategori_analisa as $kategori_){
                        $harga_db = [];
                        $volume_db = [];
                        foreach ($parameter as $param_) {
                            $ambil_data = HargaParameter::where('id_kategori', $kategori_)
                                ->where('nama_parameter', $param_)
                                ->orderBy('id', 'ASC')
                                ->get();

                            $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                            }) ?? $ambil_data->first();

                            $harga_db[] = $cek_harga_parameter->harga ?? 0;
                            $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        }

                        $harga_pertitik = (object) [
                            'volume' => array_sum($volume_db),
                            'total_harga' => array_sum($harga_db)
                        ];

                        $titik = $item->jumlah_titik;

                        if (isset($harga_pertitik->volume) && $harga_pertitik->volume != null) {
                            $vol += floatval($harga_pertitik->volume);
                        }

                        $hargaPaket = 0;
                        $hargaSatuan = 0;
                        $kelipatan = 0;

                        if($is_paket){
                            $dataPaket = TemplatePaketAnalisa::where('id', $item->paket_id)->first();
                            $dataPaketAnalisa = json_decode($dataPaket->data_pendukung_sampling, true);
                            foreach ($dataPaketAnalisa as $paket) {
                                if(
                                    $paket['regulasi'] == $item->regulasi &&
                                    $paket['parameter'] == $param &&
                                    $paket['kategori_1'] == $item->kategori_1
                                ) {
                                    $pengali = ($titik / (int)$paket['jumlah_titik']);
                                    $harga_sementara = (int)$paket['harga_paket'] * $pengali;
                                    $hargaPaket += $harga_sementara;
                                    $hargaSatuan = $paket['harga_paket'];
                                    $kelipatan = (int)$paket['jumlah_titik'];
                                } else {
                                    continue;
                                }
                            }
                        }

                        $hargaAnalisa = $is_paket ? $hargaPaket : (floatval($harga_pertitik->total_harga) * (int) $titik);
                        $hargaPerTitik = $is_paket ? $hargaSatuan : $harga_pertitik->total_harga;
                        $harga_pertitik_total += $hargaAnalisa;
                        $harga_pertitik_satuan += $hargaPerTitik;
                        $temp_preparasi = [];
                        if (isset($item->biaya_preparasi) && $item->biaya_preparasi != null) {
                            foreach ($item->biaya_preparasi as $pre) {
                                if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null) {
                                    $temp_preparasi[] = [
                                        'Deskripsi' => $pre->desc_preparasi,
                                        'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))
                                    ];
                                }
                                if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") {
                                    $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                                }
                            }
                        }
                        
                        $data_sampling[$i] = [
                            'kategori_1' => $item->kategori_1,
                            // 'kategori_2' => $item->kategori_2,
                            'regulasi' => isset($item->regulasi) ? $item->regulasi : '',
                            'penamaan_titik' => isset($item->penamaan_titik) ? $item->penamaan_titik : [],
                            'parameter' => $param,
                            'jumlah_titik' => $titik,
                            'total_parameter' => count($param),
                            'harga_satuan' => $harga_pertitik_satuan,
                            'harga_total' => $harga_pertitik_total,
                            'volume' => $vol,
                            'biaya_preparasi' => $temp_preparasi,
                        ];

                        if ($is_paket) {
                            $data_sampling[$i]['is_paket_analisa'] = $is_paket;
                            $data_sampling[$i]['paket_id'] = $item->paket_id;
                            $data_sampling[$i]['paket'] = $item->paket;
                            $data_sampling[$i]['kelipatan_dasar'] = $kelipatan;
                        }

                        switch ($kategori_) {
                            case '1':
                                $harga_air += $hargaAnalisa;
                                break;
                            case '4':
                                $harga_udara += $hargaAnalisa;
                                break;
                            case '5':
                                $harga_emisi += $hargaAnalisa;
                                break;
                            case '6':
                                $harga_padatan += $hargaAnalisa;
                                break;
                            case '7':
                                $harga_swab_test += $hargaAnalisa;
                                break;
                            case '8':
                                $harga_tanah += $hargaAnalisa;
                                break;
                            case '9':
                                $harga_pangan += $hargaAnalisa;
                                break;
                        }
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

            $expOp = explode("-", $payload->data_wilayah->wilayah);
            $nama_wilayah = implode("-", array_slice($expOp, 1));

            $ambil_data_transport = HargaTransportasi::where('wilayah', $nama_wilayah)
            ->orderBy('id', 'ASC')
            ->get();

            $cekOperasional = $ambil_data_transport->first(function ($item) use ($payload) {
                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
            }) ?? $ambil_data_transport->first();

            $data->status_wilayah = $payload->data_wilayah->status_wilayah;
            $data->wilayah = $payload->data_wilayah->wilayah;
            $data->transportasi = !in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR']) ? $payload->data_wilayah->transportasi : null;
            $data->kalkulasi_by_sistem = $payload->data_wilayah->kalkulasi_by_sistem;

            $harga_transport = 0;
            $jam = 0;
            $transport = 0;
            $perdiem = 0;

            if ($payload->data_wilayah->status_wilayah == 'DALAM KOTA') {
                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
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

                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
                    $data->perdiem_jumlah_orang = $payload->data_wilayah->perdiem_jumlah_orang;
                    $data->perdiem_jumlah_hari = $payload->data_wilayah->perdiem_jumlah_hari;

                    $data->jumlah_orang_24jam = $payload->data_wilayah->jumlah_orang_24jam;
                    $data->jumlah_hari_24jam = $payload->data_wilayah->jumlah_hari_24jam;

                    if (isset($payload->data_wilayah->kalkulasi_by_sistem) && $payload->data_wilayah->kalkulasi_by_sistem == 'on') {
                        $data->kalkulasi_by_sistem = $payload->data_wilayah->kalkulasi_by_sistem;
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

            // ==================== DISKON DENGAN KODE PROMO ===================== //
            if (!empty($payload->data_diskon->kode_promo_discount) && floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount)) > 0) {
                $biaya_pengujian = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
                $discount_promo = floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount));
                $total_discount_promo = $biaya_pengujian / 100 *  $discount_promo;

                $data->kode_promo = $payload->data_diskon->kode_promo_discount;
                $data->discount_promo = json_encode((object)[
                    'deskripsi_promo_discount' => $payload->data_diskon->deskripsi_promo_discount,
                    'jumlah_promo_discount' => $payload->data_diskon->jumlah_promo_discount
                ]);
                $data->total_discount_promo = floatval($total_discount_promo);
                $total_diskon += $total_discount_promo;
                $harga_total -= floatval($total_discount_promo);
            } else {
                // $harga_total += 0;
                // $data->discount_air = null;
                $data->total_discount_promo = 0;
                $data->discount_promo = null;
                $data->kode_promo = null;
            }
            // ==================== END DISKON DENGAN KODE PROMO ======================= //

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
            $data->is_approved = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = DATE('Y-m-d H:i:s');
            $data->flag_status = 'draft';
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
                }

                if($data_lama->status_sp == 'false') {
                    if (in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
                        SamplingPlan::where('no_quotation', $data->no_document)
                            ->update([
                                'quotation_id' => $data->id,
                                'status_jadwal' => 'SD',
                                'is_active' => false
                            ]);

                        Jadwal::where('no_quotation', $data->no_document)
                            ->update([
                                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($data->nama_perusahaan))),
                                'is_active' => false,
                                'canceled_by' => 'system'
                            ]);
                    } else {
                        $jobChangeJadwal = new ChangeJadwalJob([], 'update', $data->no_document, 'non_kontrak');
                        $this->dispatch($jobChangeJadwal);
                    }
                }
            }

            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'processing',
                'no_document' => $data->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            $job = new RenderPdfPenawaran($data->id, 'quotation-sar');
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
            if (
                str_contains($e->getMessage(), 'Connection timed out') ||
                str_contains($e->getMessage(), 'MySQL server has gone away') ||
                str_contains($e->getMessage(), 'Lock wait timeout exceeded')
            ) {
                Notification::whereIn('id_department', [7])->title('Database time out Exceeded')->message('Saat akan Update Non Kontrak atau di Controller Request Quotation bermasalah.!')->url('/monitor-database')->send();
                return response()->json([
                    'message' => 'Terdapat antrian transaksi pada fitur ini, mohon untuk mencoba kembali beberapa saat lagi.!',
                    'status' => 401
                ], 401);
            } else {
                return response()->json([
                    'message' => 'Update Non Kontrak Failed: ' . $e->getMessage(),
                    'status' => 401
                ], 401);
            }
        }
    }

    private function revisiNonKontrak($payload)
    {
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
            $data->is_generate_data_lab = $payload->data_wilayah->is_generate_data_lab;
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
            $data->use_kuota = $payload->data_diskon->use_kuota;

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

            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    //per kategori
                    $param = $item->parameter;
                    $exp = explode("-", $item->kategori_1);
                    $kategori = $exp[0];
                    $vol = 0;
                    $is_paket = isset($item->is_paket_analisa) ? $item->is_paket_analisa : false;


                    $parameter = [];
                    $kategori_analisa = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                        array_push($kategori_analisa, $cek_par->id_kategori);
                    }
                    $kategori_analisa = array_unique($kategori_analisa);
                    $harga_pertitik_total = 0;
                    $harga_pertitik_satuan =0;
                    foreach($kategori_analisa as $kategori_){
                        $harga_db = [];
                        $volume_db = [];
                        foreach ($parameter as $param_) {
                            $ambil_data = HargaParameter::where('id_kategori', $kategori_)
                                ->where('nama_parameter', $param_)
                                ->orderBy('id', 'ASC')
                                ->get();

                            $cek_harga_parameter = $ambil_data->first(function ($item) use ($payload) {
                                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
                            }) ?? $ambil_data->first();

                            $harga_db[] = $cek_harga_parameter->harga ?? 0;
                            $volume_db[] = $cek_harga_parameter->volume ?? 0;
                        }

                        $harga_pertitik = (object) [
                            'volume' => array_sum($volume_db),
                            'total_harga' => array_sum($harga_db)
                        ];

                        $titik = $item->jumlah_titik;

                        if (isset($harga_pertitik->volume) && $harga_pertitik->volume != null) {
                            $vol += floatval($harga_pertitik->volume);
                        }

                        $hargaPaket = 0;
                        $hargaSatuan = 0;
                        $kelipatan = 0;

                        if($is_paket){
                            $dataPaket = TemplatePaketAnalisa::where('id', $item->paket_id)->first();
                            $dataPaketAnalisa = json_decode($dataPaket->data_pendukung_sampling, true);
                            foreach ($dataPaketAnalisa as $paket) {
                                if(
                                    $paket['regulasi'] == $item->regulasi &&
                                    $paket['parameter'] == $param &&
                                    $paket['kategori_1'] == $item->kategori_1
                                ) {
                                    $pengali = ($titik / (int)$paket['jumlah_titik']);
                                    $harga_sementara = (int)$paket['harga_paket'] * $pengali;
                                    $hargaPaket += $harga_sementara;
                                    $hargaSatuan = $paket['harga_paket'];
                                    $kelipatan = (int)$paket['jumlah_titik'];
                                } else {
                                    continue;
                                }
                            }
                        }

                        $hargaAnalisa = $is_paket ? $hargaPaket : (floatval($harga_pertitik->total_harga) * (int) $titik);
                        $hargaPerTitik = $is_paket ? $hargaSatuan : $harga_pertitik->total_harga;
                        $harga_pertitik_total += $hargaAnalisa;
                        $harga_pertitik_satuan += $hargaPerTitik;
                        $temp_preparasi = [];
                        if (isset($item->biaya_preparasi) && $item->biaya_preparasi != null) {
                            foreach ($item->biaya_preparasi as $pre) {
                                if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null) {
                                    $temp_preparasi[] = [
                                        'Deskripsi' => $pre->desc_preparasi,
                                        'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))
                                    ];
                                }
                                if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") {
                                    $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                                }
                            }
                        }
                        
                        $data_sampling[$i] = [
                            'kategori_1' => $item->kategori_1,
                            // 'kategori_2' => $item->kategori_2,
                            'regulasi' => isset($item->regulasi) ? $item->regulasi : '',
                            'penamaan_titik' => isset($item->penamaan_titik) ? $item->penamaan_titik : [],
                            'parameter' => $param,
                            'jumlah_titik' => $titik,
                            'total_parameter' => count($param),
                            'harga_satuan' => $harga_pertitik_satuan,
                            'harga_total' => $harga_pertitik_total,
                            'volume' => $vol,
                            'biaya_preparasi' => $temp_preparasi,
                        ];

                        if ($is_paket) {
                            $data_sampling[$i]['is_paket_analisa'] = $is_paket;
                            $data_sampling[$i]['paket_id'] = $item->paket_id;
                            $data_sampling[$i]['paket'] = $item->paket;
                            $data_sampling[$i]['kelipatan_dasar'] = $kelipatan;
                        }

                        switch ($kategori_) {
                            case '1':
                                $harga_air += $hargaAnalisa;
                                break;
                            case '4':
                                $harga_udara += $hargaAnalisa;
                                break;
                            case '5':
                                $harga_emisi += $hargaAnalisa;
                                break;
                            case '6':
                                $harga_padatan += $hargaAnalisa;
                                break;
                            case '7':
                                $harga_swab_test += $hargaAnalisa;
                                break;
                            case '8':
                                $harga_tanah += $hargaAnalisa;
                                break;
                            case '9':
                                $harga_pangan += $hargaAnalisa;
                                break;
                        }
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
            // $id_wilayah = $expOp[0];

            // $cekOperasional = HargaTransportasi::where('is_active', true)
            //     ->where('id', $id_wilayah)
            //     ->first();
            $nama_wilayah = implode("-", array_slice($expOp, 1));

            $ambil_data_transport = HargaTransportasi::where('wilayah', $nama_wilayah)
                ->orderBy('id', 'ASC')
                ->get();

            $cekOperasional = $ambil_data_transport->first(function ($item) use ($payload) {
                return explode(' ', $item->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran;
            }) ?? $ambil_data_transport->first();

            $data->status_wilayah = $payload->data_wilayah->status_wilayah;
            $data->wilayah = $payload->data_wilayah->wilayah;
            $data->transportasi = !in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR']) ? $payload->data_wilayah->transportasi : null;

            $harga_transport = 0;
            $jam = 0;
            $transport = 0;
            $perdiem = 0;

            if ($payload->data_wilayah->status_wilayah == 'DALAM KOTA') {
                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
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

                if (!in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
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

            // ==================== DISKON DENGAN KODE PROMO ===================== //
            if (!empty($payload->data_diskon->kode_promo_discount) && floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount)) > 0) {
                $biaya_pengujian = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
                $discount_promo = floatval(str_replace('%', '', $payload->data_diskon->jumlah_promo_discount));
                $total_discount_promo = $biaya_pengujian / 100 *  $discount_promo;

                $data->kode_promo = $payload->data_diskon->kode_promo_discount;
                $data->discount_promo = json_encode((object)[
                    'deskripsi_promo_discount' => $payload->data_diskon->deskripsi_promo_discount,
                    'jumlah_promo_discount' => $payload->data_diskon->jumlah_promo_discount
                ]);
                $data->total_discount_promo = floatval($total_discount_promo);
                $total_diskon += $total_discount_promo;
                $harga_total -= floatval($total_discount_promo);
            } else {
                // $harga_total += 0;
                // $data->discount_air = null;
                $data->total_discount_promo = 0;
                $data->discount_promo = null;
                $data->kode_promo = null;
            }
            // ==================== END DISKON DENGAN KODE PROMO ======================= //

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
                    if (in_array($payload->data_wilayah->status_sampling, ['SD', 'SAR'])) {
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

                    } else {
                        $no_qt = [
                            'new' => $data->no_document,
                            'old' => $dataOld->no_document,
                        ];

                        $jobChangeJadwal = new ChangeJadwalJob([], 'revisi', $no_qt, 'non_kontrak');
                        $this->dispatch($jobChangeJadwal);
                    }

                    $message = "Terjadi perubahan quotation $dataOld->no_document menjadi $data->no_document dan silahkan di cek di bagian menu sampling plan dengan No QT $data->no_document apakah sudah sesuai atau belum untuk jumlah kategorinya demi ke-efisiensi penjadwalan sampler";
                }

                if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                    $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
                    $no_qt_lama = $cek_order->no_document;
                    $no_qt_baru = $data->no_document;
                    $id_order = $data_lama->id_order;
                }
            }

            if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                // Update QR Psikologi Data
                $qr_psikologi = QrPsikologi::where('id_quotation', $data_lama->id_order)
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(2)
                    ->get();

                foreach ($qr_psikologi as $psikologi) {

                    // Decode kolom data JSON ke array
                    $json = json_decode($psikologi->data, true);

                    if (!is_array($json)) {
                        continue; // skip jika JSON tidak valid
                    }

                    // update no document
                    $json['no_document'] = $data->no_document;

                    // encode kembali ke JSON
                    $psikologi->data = json_encode($json);

                    $psikologi->save();
                }

                $invoices = Invoice::where('no_quotation', $dataOld->no_document)
                    // ->whereNull('nilai_pelunasan')
                    ->where('is_active', true)
                    ->get();

                $invoiceNumbersTobeChecked = [];
                foreach ($invoices as $invoice) {
                    // if (($invoice->nilai_pelunasan ?? 0) < $invoice->nilai_tagihan) {
                    if($invoice->nilai_pelunasan == null) {
                        $invoice->is_generate = false;
                        $invoice->is_emailed = false;

                        $invoiceNumbersTobeChecked[] = $invoice->no_invoice;
                    }
                    $invoice->no_quotation = $data->no_document;
                    $invoice->save();
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


                // update link lhp
                DB::table('link_lhp')
                    ->where('no_quotation', $dataOld->no_document)
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

            $job = new RenderPdfPenawaran($data->id, 'quotation-sar');
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
            if (
                str_contains($e->getMessage(), 'Connection timed out') ||
                str_contains($e->getMessage(), 'MySQL server has gone away') ||
                str_contains($e->getMessage(), 'Lock wait timeout exceeded')
            ) {
                Notification::whereIn('id_department', [7])->title('Database time out Exceeded')->message('Saat akan Revisi Non Kontrak atau di Controller Request Quotation bermasalah.!')->url('/monitor-database')->send();
                return response()->json([
                    'message' => 'Terdapat antrian transaksi pada fitur ini, mohon untuk mencoba kembali beberapa saat lagi.!',
                    'status' => 401
                ], 401);
            } else {
                return response()->json([
                    'message' => 'Revisi Non Kontrak Failed: ' . $e->getMessage(),
                    'status' => 401
                ], 401);
            }
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function generateLink (Request $request) {
        $quotationNonKontrak = QuotationNonKontrak::where('id', $request->id)->first();

        // GENERATE QR
        (new GenerateQrDocument())->insert('quotation_non_kontrak', $quotationNonKontrak, $this->karyawan);

        // GENERATE DOCUMENT
        JobTask::insert([
            'job' => 'RenderPdfPenawaran',
            'status' => 'processing',
            'no_document' => $quotationNonKontrak->no_document,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $job = new RenderPdfPenawaran($quotationNonKontrak->id, 'non kontrak');
        $this->dispatch($job);

        // GENERATE LINK & TOKEN
        $generateToken = new GenerateToken();
        $token = $generateToken->save('non_kontrak', $quotationNonKontrak, $this->karyawan, 'quotation');
        
        $quotationNonKontrak->is_generated = true;
        $quotationNonKontrak->generated_by = $this->karyawan;
        $quotationNonKontrak->generated_at = Carbon::now()->format('Y-m-d H:i:s');
        $quotationNonKontrak->id_token = $token->id;
        $quotationNonKontrak->expired = $token->expired;

        $quotationNonKontrak->save();

        return response()->json(["message" => "Data Quotation has been generated"], 200);
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $status_sampling = [];
            $nonPengujian = false;
            $data = QuotationNonKontrak::with('sales')->where('id', $request->id)->first();
            $data->flag_status = 'emailed';
            $data->is_emailed = true;
            $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->emailed_by = $this->karyawan;

            if (empty(json_decode($data->data_pendukung_sampling, true))) {
                $nonPengujian = true;
            }

            array_push($status_sampling, $data->status_sampling);

            // $emails = GetAtasan::where('id', $data->sales_id)->get()->pluck('email');
            // Jika $request->cc adalah array dengan satu elemen kosong, ubah menjadi array kosong
            if (is_array($request->cc) && count($request->cc) === 1 && $request->cc[0] === "") {
                $request->cc = [];
            }
            
            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->fromAdmsales()
                ->send();

            if ($email) {
                if ($data->data_lama !== null && $data->data_lama !== 'null') {
                    $data_lama = json_decode($data->data_lama);
                    if ($data_lama->status_sp == 'false') {
                        $cek_sp = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', 1)->where('is_approved', 1)->exists();
                        if ($cek_sp) {
                            $data->flag_status = 'sp';
                            $data->is_ready_order = 1;
                        }
                    }
                }

                $status_sampling = array_unique($status_sampling);
                if (count($status_sampling) == 1) {
                    if (in_array('SD', $status_sampling) || in_array('SAR', $status_sampling)) {
                        $data->flag_status = 'sp';
                        $data->is_ready_order = 1;
                    } else if ($nonPengujian) {
                        $data->flag_status = 'sp';
                        $data->is_ready_order = 1;
                    }
                }

                if($data->is_generate_data_lab == 0){
                    $data->flag_status = 'sp';
                    $data->is_ready_order = 1;
                    // $data->is_konfirmasi_order = 1;
                }

                $data->save();
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function rejectData(Request $request)
    {
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

                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->keterangan_reject = $request->keterangan;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success rejected.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot rejected data.!',
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
}