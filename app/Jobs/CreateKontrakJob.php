<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\Parameter;
use App\Models\HargaParameter;

class CreateKontrakJob extends Job
{
    protected $data;
    protected $idcabang;
    protected $karyawan;
    protected $sales_id;


    public function __construct($data, $idcabang, $karyawan, $sales_id)
    {
        $this->data = $data;
        $this->idcabang = $idcabang;
        $this->karyawan = $karyawan;
        $this->sales_id = $sales_id;
    }

    private function groupDataSampling(array $data)
    {
        $grouped = [];
    
        foreach ($data as $periodeItem) {
            $periode = $periodeItem->periode_kontrak ?? null;
            if (!$periode || empty($periodeItem->data_sampling)) continue;
    
            foreach ($periodeItem->data_sampling as $sampling) {
                // Buat key unik untuk mengelompokkan
                $key = md5(json_encode([
                    'kategori_1'      => $sampling->kategori_1,
                    'kategori_2'      => $sampling->kategori_2,
                    'parameter'       => $sampling->parameter,
                    'jumlah_titik'    => $sampling->jumlah_titik,
                    'total_parameter' => $sampling->total_parameter,
                    'regulasi'        => $sampling->regulasi,
                ]));
    
                // Hapus properti yang tidak diperlukan
                unset($sampling->harga_satuan, $sampling->harga_total, $sampling->volume);
    
                if (!isset($grouped[$key])) {
                    $grouped[$key] = (object)[
                        'kategori_1'      => $sampling->kategori_1,
                        'kategori_2'      => $sampling->kategori_2,
                        'penamaan_titik'  => [], // default kosong
                        'parameter'       => $sampling->parameter,
                        'jumlah_titik'    => $sampling->jumlah_titik,
                        'total_parameter' => $sampling->total_parameter,
                        'periode_kontrak' => [$periode],
                        'biaya_preparasi' => [], // default kosong
                        'regulasi'        => $sampling->regulasi,
                    ];
                } else {
                    // Tambahkan periode baru jika belum ada
                    if (!in_array($periode, $grouped[$key]->periode_kontrak)) {
                        $grouped[$key]->periode_kontrak[] = $periode;
                    }
                }
            }
        }
    
        return array_values($grouped);
    }

    public function handle()
    { 
        $payload = $this->data;
        $sales_id = $this->sales_id;
        
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
            $no_document = 'ISL/QTC/' . $tahun_chek . '-' . $bulan_chek . '/' . $no_quotation;

            $data_pendukung = $payload->data_pendukung;
            $periodeAwal = \explode('-', $data_pendukung[0]->periode_kontrak)[1] . '-' . \explode('-', $data_pendukung[0]->periode_kontrak)[0];
            $periodeAkhir = \explode('-', $data_pendukung[count($data_pendukung) - 1]->periode_kontrak)[1] . '-' . \explode('-', $data_pendukung[count($data_pendukung) - 1]->periode_kontrak)[0];

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
            $dataH->nama_pic_sampling = ucwords($payload->informasi_pelanggan->nama_pic_sampling);
            $dataH->jabatan_pic_sampling = $payload->informasi_pelanggan->jabatan_pic_sampling;
            $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $payload->informasi_pelanggan->no_tlp_pic_sampling);
            $dataH->email_pic_sampling = $payload->informasi_pelanggan->email_pic_sampling;
            $dataH->periode_kontrak_awal = $periodeAwal;
            $dataH->periode_kontrak_akhir = $periodeAkhir;
            $dataH->sales_id = $payload->informasi_pelanggan->sales_id;
            $dataH->created_by = $this->karyawan;
            $dataH->created_at = DATE('Y-m-d H:i:s');
            $data_pendukung_h = [];
            $data_s = [];
            $period = [];

            $dataPendukungHeader = $this->groupDataSampling($data_pendukung);

            foreach ($dataPendukungHeader as $i => $item) {
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
                    'volume' => $vol,
                    'periode' => $item->periode_kontrak,
                    'biaya_preparasi' => []
                ];

                isset($item->regulasi) ? $data_sampling[$i]['regulasi'] = $item->regulasi : $data_sampling[$i]['regulasi'] = null;

                foreach ($item->periode_kontrak as $key => $v) {
                    array_push($period, $v);
                }

                array_push($data_pendukung_h, $data_sampling[$i]);
            }

            $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h), JSON_UNESCAPED_UNICODE);

            $dataH->save();
            
            foreach ($data_pendukung as $x => $pengujian){
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
                $harga_pangan = 0;
                $grand_total = 0;
                $total_diskon = 0;
                $desc_preparasi = [];
                $harga_preparasi = 0;

                $n = 0;

                // Perbaikan: data_sampling diupdate di dalam foreach, pastikan data yang di luar foreach sudah terupdate
                foreach ($pengujian->data_sampling as $i => $sampling) {
                    $id_kategori = \explode("-", $sampling->kategori_1)[0];
                    $kategori = \explode("-", $sampling->kategori_1)[1];
                    $regulasi = (empty($sampling->regulasi) || $sampling->regulasi == '' || (is_array($sampling->regulasi) && count($sampling->regulasi) == 1 && $sampling->regulasi[0] == '')) ? [] : $sampling->regulasi;
                    
                    $parameters = [];
                    $id_parameter = [];
                    foreach ($sampling->parameter as $item) {
                        $cek_par = DB::table('parameter')
                            ->where('id', explode(';', $item)[0])->first();
                        if ($cek_par) {
                            $parameters[] = $cek_par->nama_lab;
                            $id_parameter[] = $cek_par->id;
                        }
                    }
                    
                    $harga_parameter = [];
                    $volume_parameter = [];

                    foreach ($parameters as $parameter) {
                        $ambil_data = HargaParameter::where('id_kategori', $id_kategori)
                            ->where('nama_parameter', $parameter)
                            ->orderBy('id', 'ASC')
                            ->get();
                        
                        if (count($ambil_data) > 1) {
                            $found = false;
                            foreach ($ambil_data as $xc => $zx) {
                                if (\explode(' ', $zx->created_at)[0] > $payload->informasi_pelanggan->tgl_penawaran) {
                                    $harga_parameter[] = $zx->harga;
                                    $volume_parameter[] = $zx->volume;
                                    $found = true;
                                    break;
                                }
                                if ((count($ambil_data) - 1) == $xc && !$found) {
                                    $zx = $ambil_data[0];
                                    $harga_parameter[] = $zx->harga;
                                    $volume_parameter[] = $zx->volume;
                                    break;
                                }
                            }
                        } else if (count($ambil_data) == 1) {
                            foreach ($ambil_data as $zx) {
                                $harga_parameter[] = $zx->harga;
                                $volume_parameter[] = $zx->volume;
                                break;
                            }
                        } else {
                            $harga_parameter[] = 0;
                            $volume_parameter[] = 0;
                        }
                    }

                    $vol_db = array_sum($volume_parameter);
                    $har_db = array_sum($harga_parameter);

                    $harga_pertitik = (object) [
                        'volume' => $vol_db,
                        'total_harga' => $har_db
                    ];

                    // Update data_sampling agar jika digunakan di luar foreach sudah terupdate
                    $jumlah_titik = ($sampling->jumlah_titik === null || $sampling->jumlah_titik === '') ? 0 : $sampling->jumlah_titik;
                    
                    $pengujian->data_sampling[$i]->total_parameter = count($sampling->parameter);
                    $pengujian->data_sampling[$i]->regulasi = $regulasi;
                    $pengujian->data_sampling[$i]->harga_satuan = $har_db;
                    $pengujian->data_sampling[$i]->harga_total = ($har_db * $jumlah_titik);
                    $pengujian->data_sampling[$i]->volume = $vol_db;

                    if (isset($pengujian->data_sampling[$i]->biaya_preparasi)) {
                        unset($pengujian->data_sampling[$i]->biaya_preparasi);
                    }

                    //bagian untuk di parsing keluar ke variable lain
                    switch ($id_kategori) {
                        case '1':
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '4':
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '5':
                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '6':
                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '7':
                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '8':
                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                        case '9':
                            $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $jumlah_titik;
                            break;
                    }
                }

                $dataD->periode_kontrak = $pengujian->periode_kontrak;
                $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;

                // $dataD->data_pendukung_sampling = json_encode($pengujian->data_sampling, JSON_UNESCAPED_UNICODE);
                $data_sampling[$x] = [
                    'periode_kontrak' => $pengujian->periode_kontrak,
                    'data_sampling' => $pengujian->data_sampling
                ];
                
                $dataD->data_pendukung_sampling = json_encode($data_sampling, JSON_UNESCAPED_UNICODE);
                // end data sampling
                $dataD->harga_air = $harga_air;
                $dataD->harga_udara = $harga_udara;
                $dataD->harga_emisi = $harga_emisi;
                $dataD->harga_padatan = $harga_padatan;
                $dataD->harga_swab_test = $harga_swab_test;
                $dataD->harga_tanah = $harga_tanah;
                $dataD->harga_pangan = $harga_pangan;

                $dataD->biaya_preparasi = json_encode($pengujian->biaya_preparasi);
                $array_harga_preparasi = array_map(function ($pre) {
                    if (isset($pre->Harga)) {
                        return $pre->Harga;
                    } elseif (isset($pre->harga)) {
                        return $pre->harga;
                    } else {
                        return 0;
                    }
                }, $pengujian->biaya_preparasi);

                $harga_preparasi = array_sum($array_harga_preparasi);
                $dataD->total_biaya_preparasi = $harga_preparasi;

                $dataD->save();
            }

            DB::commit();

            Log::channel('quotation')->info('CreateKontrakJob: ' . $no_document . ' success created');
        } catch (\Exception $e) {
            DB::rollback();
            Log::channel('quotation')->error('CreateKontrakJob: ' . $e->getMessage() . ' - ' . $e->getFile() . ' - ' . $e->getLine());
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }
}