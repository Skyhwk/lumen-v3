<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use App\Models\Parameter;
use App\Models\HargaParameter;

class CreateNonKontrakJob extends Job
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

    public function handle()
    { 
        $payload = $this->data;
        $sales_id = $this->sales_id;
        
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
            
            Log::channel('quotation')->info('CreateNonKontrakJob:  Penawaran berhasil dibuat dengan nomor dokumen ' . $data->no_document);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('quotation')->info('CreateNonKontrakJob: Terjadi kesalahan saat membuat penawaran: ' . $e->getMessage());
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }
}