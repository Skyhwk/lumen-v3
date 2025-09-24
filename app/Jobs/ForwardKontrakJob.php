<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Parameter;
use App\Models\HargaParameter;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\RequestQr;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Services\GetBawahan;

class ForwardKontrakJob extends Job
{
    protected $data;
    protected $idcabang;
    protected $karyawan;
    protected $user_id;


    public function __construct($data, $idcabang, $karyawan, $user_id)
    {
        $this->data = $data;
        $this->idcabang = $idcabang;
        $this->karyawan = $karyawan;
        $this->user_id = $user_id;
    }

    public function handle()
    {
        $payload = $this->data;
        DB::beginTransaction();
        try {
            $tahun_chek = date('y', strtotime($payload->informasi_pelanggan['tgl_penawaran']));  // 2 digit tahun (misal: 25)
            $bulan_chek = date('m', strtotime($payload->informasi_pelanggan['tgl_penawaran']));  // 2 digit bulan (misal: 01)
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

            // Implementasi untuk create kontrak
            // Insert Data Quotation Kontrak Header
            $dataH = new QuotationKontrakH;
            $dataH->no_quotation = $no_quotation;  //penentian nomor Quotation
            $dataH->no_document = $no_document;
            $dataH->pelanggan_ID = $payload->informasi_pelanggan['pelanggan_ID'];
            $dataH->id_cabang = $this->idcabang;

            //dataH customer order     -------------------------------------------------------> save ke master customer parrent
            $dataH->nama_perusahaan = strtoupper(htmlspecialchars_decode($payload->informasi_pelanggan['nama_perusahaan']));
            $dataH->tanggal_penawaran = strtoupper($payload->informasi_pelanggan['tgl_penawaran']);
            if ($payload->informasi_pelanggan['konsultan'] != '')
                $dataH->konsultan = strtoupper(trim(htmlspecialchars_decode($payload->informasi_pelanggan['konsultan'])));
            if ($payload->informasi_pelanggan['alamat_kantor'] != '')
                $dataH->alamat_kantor = $payload->informasi_pelanggan['alamat_kantor'];
            $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']);
            $dataH->nama_pic_order = ucwords($payload->informasi_pelanggan['nama_pic_order']);
            $dataH->jabatan_pic_order = $payload->informasi_pelanggan['jabatan_pic_order'];
            $dataH->no_pic_order = \str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']);
            $dataH->email_pic_order = $payload->informasi_pelanggan['email_pic_order'];
            $dataH->email_cc = isset($payload->informasi_pelanggan['email_cc']) ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
            $dataH->status_sampling = $payload->informasi_pelanggan['status_sampling'];
            $dataH->alamat_sampling = $payload->informasi_pelanggan['alamat_sampling'];
            // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']);
            $dataH->nama_pic_sampling = ucwords($payload->informasi_pelanggan['nama_pic_sampling']);
            $dataH->jabatan_pic_sampling = $payload->informasi_pelanggan['jabatan_pic_sampling'];
            $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']);
            $dataH->email_pic_sampling = $payload->informasi_pelanggan['email_pic_sampling'];
            //end lokasi sampling customer
            // $dataH->status_wilayah = $payload->status_wilayah;
            // $dataH->wilayah = $payload->wilayah;
            $dataH->periode_kontrak_awal = $payload->data_pendukung[0]['periodeAwal'];
            $dataH->periode_kontrak_akhir = $payload->data_pendukung[0]['periodeAkhir'];
            $dataH->sales_id = $payload->informasi_pelanggan['sales_id'];
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

                if ($data_pendukungH['parameter'] != null)
                    $param = $data_pendukungH['parameter'];
                if (isset($data_pendukungH['regulasi']))
                    $regulasi = $data_pendukungH['regulasi'];
                if ($data_pendukungH['periode'] != null)
                    $periode = $data_pendukungH['periode'];

                $exp = explode("-", $data_pendukungH['kategori_1']);
                $kategori = $exp[0];
                $vol = 0;

                // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                $parameter = [];
                foreach ($data_pendukungH['parameter'] as $va) {
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
                if ($data_pendukungH['jumlah_titik'] == '') {
                    $reqtitik = 0;
                } else {
                    $reqtitik = $data_pendukungH['jumlah_titik'];
                }

                $temp_prearasi = [];
                if ($data_pendukungH['biaya_preparasi'] != null || $data_pendukungH['biaya_preparasi'] != "") {
                    foreach ($data_pendukungH['biaya_preparasi'] as $pre) {
                        if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
                            $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ','], '', $pre['biaya_preparasi_padatan']))];
                    }
                }
                $biaya_preparasi = $temp_prearasi;

                array_push($data_pendukung_h, (object) [
                    'kategori_1' => $data_pendukungH['kategori_1'],
                    'kategori_2' => $data_pendukungH['kategori_2'],
                    'regulasi' => $regulasi,
                    'parameter' => $param,
                    'jumlah_titik' => $data_pendukungH['jumlah_titik'],
                    'penamaan_titik' => isset($data_pendukungH['penamaan_titik']) ? $data_pendukungH['penamaan_titik'] : "",
                    'total_parameter' => count($param),
                    'harga_satuan' => $harga_pertitik->total_harga,
                    'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
                    'volume' => $vol,
                    'periode' => $periode,
                    'biaya_preparasi' => $biaya_preparasi
                ]);

                foreach ($data_pendukungH['periode'] as $key => $v) {
                    array_push($period, $v);
                }
            }

            $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h));

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
                    if (in_array($per, $data_pendukungD['periode'])) {
                        $param = [];
                        $regulasi = '';
                        if ($data_pendukungD['parameter'] != null)
                            $param = $data_pendukungD['parameter'];
                        if (isset($data_pendukungD['regulasi']))
                            $regulasi = $data_pendukungD['regulasi'];

                        $exp = explode("-", $data_pendukungD['kategori_1']);
                        $kategori = $exp[0];
                        $vol = 0;

                        // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                        $parameter = [];
                        foreach ($data_pendukungD['parameter'] as $va) {
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
                        if ($data_pendukungD['jumlah_titik'] == '') {
                            $reqtitik = 0;
                        } else {
                            $reqtitik = $data_pendukungD['jumlah_titik'];
                        }

                        //============= BIAYA PREPARASI ==================
                        $temp_prearasi = [];
                        if ($data_pendukungD['biaya_preparasi'] != null || $data_pendukungD['biaya_preparasi'] != "") {
                            foreach ($data_pendukungD['biaya_preparasi'] as $pre) {
                                if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
                                    $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre['biaya_preparasi_padatan']))];
                                if ($pre['biaya_preparasi_padatan'] != null || $pre['biaya_preparasi_padatan'] != "")
                                    $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre['biaya_preparasi_padatan']));
                            }
                        }
                        $biaya_preparasi = $temp_prearasi;

                        // dd($biaya_preparasi);

                        // PENENTUAN NOMOR PENAMAAN TITIK
                        $penamaan_titik_fixed = [];
                        if ($data_pendukungD['penamaan_titik'] != null) {
                            foreach ($data_pendukungD['penamaan_titik'] as $pt) {
                                $penamaan_titik_fixed[] = [sprintf('%03d', $globalTitikCounter) => trim($pt)];
                                $globalTitikCounter++;
                            }
                        }

                        $data_sampling[$n++] = [
                            'kategori_1' => $data_pendukungD['kategori_1'],
                            'kategori_2' => $data_pendukungD['kategori_2'],
                            'regulasi' => $regulasi,
                            'parameter' => $param,
                            'jumlah_titik' => $data_pendukungD['jumlah_titik'],
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
                    'data_sampling' => json_encode(array_values($data_sampling))
                ];

                $dataD->periode_kontrak = $per;
                $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;
                $dataD->data_pendukung_sampling = json_encode($datas);
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

            $data_request = RequestQr::where('id', $payload->informasi_pelanggan['id'])->first();
            // dd($data_request);
            $data_request->is_active = 0;
            $data_request->save();

            if($this->karyawan == $data_request->created_by){ // JIka yang membuat request qr itu sendiri maka kirim ke atasan juga
                $message = 'Request QR telah diexport ke request quotation';

                $getAtasan = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();

                Notification::whereIn('id', $getAtasan)
                    ->title('Ticket Programming Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            }else { // JIka yang membuat quotation itu bukan yang membuat request qr maka kirim ke yang membuat request qr
                $message = 'Request QR telah diexport ke request quotation';
                Notification::where('nama_lengkap', $dataH->created_by)
                    ->title('Request QR telah diexport ke request quotation')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            }

            DB::commit();

            Log::channel('quotation')->info('ForwardKontrakJob: Penawaran berhasil dibuat dengan nomor dokumen ' . $dataH->no_document);
        } catch (\Exception $e) {
            DB::rollback();
            Log::channel('quotation')->info('ForwardKontrakJob: Terjadi kesalahan saat membuat penawaran: ' . $e->getMessage());
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }
}
