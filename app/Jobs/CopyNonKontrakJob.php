<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\QuotationNonKontrak;
use \Carbon\Carbon;
use App\Helpers\RenderPdfHelpers;

class CopyNonKontrakJob extends Job
{
    protected $idcabang;
    protected $karyawan;
    protected $id;


    public function __construct($idcabang, $karyawan, $id)
    {
        $this->idcabang = $idcabang;
        $this->karyawan = $karyawan;
        $this->id       = $id;
    }

    public function handle()
    {
        DB::beginTransaction();

        try {
            $tahun_chek = DATE('y');  // 2 digit tahun (misal: 25)
            $bulan_chek = DATE('m');  // 2 digit bulan (misal: 01)
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
            
            $query = QuotationNonKontrak::where('id', $this->id)->firstOrFail();

            $newQuery = $query->replicate();
            $newQuery->no_quotation = $no_quotation;
            $newQuery->no_document = $no_document;
            $newQuery->konsultan = $query->konsultan;
            $newQuery->flag_status = null;
            $newQuery->tanggal_penawaran = Carbon::now()->format('Y-m-d');
            $newQuery->created_by = $this->karyawan;
            $newQuery->created_at = Carbon::now();
            $newQuery->updated_by = null;
            $newQuery->updated_at = null;
            $newQuery->data_lama = null;
            $newQuery->keterangan_reject = null;
            $newQuery->is_approved = 0;
            $newQuery->approved_by = null;
            $newQuery->approved_at = null;
            $newQuery->is_emailed = 0;
            $newQuery->is_ready_order = 0;
            $newQuery->emailed_at = null;
            $newQuery->emailed_by = null;
            $newQuery->is_generated = 0;
            $newQuery->generated_at = null;
            $newQuery->generated_by = null;
            $newQuery->id_token = null;
            $newQuery->save();

            // RenderPdfHelpers::run($newQuery->id, 'non_kontrak', $newQuery->no_document);
            
            DB::commit();
            Log::channel('quotation')->info('CreateCopyNonKontrakJob:  Penawaran berhasil dibuat dengan nomor dokumen ' . $no_document);
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