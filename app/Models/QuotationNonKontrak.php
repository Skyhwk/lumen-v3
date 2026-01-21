<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class QuotationNonKontrak extends Sector
{
    protected $table = 'request_quotation';
    protected $guarded = [];

    public $timestamps = false;

    public function qr_psikologi()
    {
        return $this->hasMany(QrPsikologi::class, 'id_quotation', 'id');
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang', 'id');
    }

    public function addby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'created_by', 'nama_lengkap');
    }

    public function updateby()
    {
        return $this->belongsTo(MasterKaryawan::class, 'updated_by', 'nama_lengkap');
    }

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id', 'id');
    }

    public function sampling()
    {
        return $this->hasMany(SamplingPlan::class, 'quotation_id', 'id')
            ->with('jadwal')
            ->whereNull('status_quotation')
            ->where('is_active', 1)
            ->select('id', 'filename', 'quotation_id', 'no_quotation', 'no_document', 'periode_kontrak', 'opsi_1', 'opsi_2', 'tambahan', 'keterangan_lain', 'is_sabtu', 'is_minggu', 'is_malam', 'created_at', 'created_by');
    }

    public function order()
    {
        return $this->hasOne(OrderHeader::class, 'no_document', 'no_document')
            ->where('is_active', 1)
            ->with('orderDetail');
    }

    public function documentCodingSampling()
    {
        return $this->belongsTo('App\Models\DocumentCodingSample', 'no_document', 'no_quotation');
    }

    public function link()
    {
        return $this->belongsTo('App\Models\GenerateLink', 'id', 'id_quotation')
            ->where('quotation_status', 'non_kontrak');
    }

    public function pelanggan()
    {
        return $this->hasOne(MasterPelanggan::class, 'id_pelanggan', 'pelanggan_ID');
    }

    public function salesWithAtasan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id');
    }
    public function konfirmasi()
    {
        return $this->belongsTo(KelengkapanKonfirmasiQs::class, 'no_document', 'no_quotation')->where('is_active', true);
    }

    public function orderD()
    {
        return $this->hasMany(OrderHeader::class, 'id_pelanggan', 'pelanggan_ID')
            ->with('orderDetail');
    }

    public function orderDetail()
    {
        return $this->hasMany(OrderDetail::class, 'no_quotation', 'no_document')->where('is_active', 1);
    }

    public static function getQuotationSummary($date)
    {
        return self::with('salesWithAtasan')
            ->leftJoin('order_header as oh', 'request_quotation.pelanggan_ID', '=', 'oh.id_pelanggan')
            ->selectRaw('
                request_quotation.sales_id, 
                COUNT(DISTINCT request_quotation.no_document) as total_request_quotation, 
                SUM(DISTINCT request_quotation.biaya_akhir) as total_biaya_akhir,
                COUNT(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation.no_document ELSE NULL END) as pelanggan_lama,
                COUNT(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation.no_document ELSE NULL END) as pelanggan_baru,
                SUM(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_lama,
                SUM(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_baru
            ')
            ->where('request_quotation.is_active', 1)
            // ->where('oh.is_active', 1)
            ->whereDate('request_quotation.created_at', $date)
            ->groupBy('request_quotation.sales_id');
    }

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_document', 'no_document');
    }
    
    public function orderHeaderWithInvoices()
    {
        return $this->orderHeader()
            ->with(['invoices' => function($query) {
                $query->where('is_active', true);
            }]);
    }

    public function alasanVoidQt()
    {
        return $this->hasOne(AlasanVoidQt::class, 'no_quotation', 'no_document');
    }

    public function link_lhp()
    {
        return $this->hasOne(LinkLhp::class, 'no_quotation', 'no_document');
    }

    public function dailyQsd()
    {
        return $this->hasMany(DailyQsd::class, 'no_quotation', 'no_document');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'no_quotation', 'no_document')->where('is_active', true);
    }
}
