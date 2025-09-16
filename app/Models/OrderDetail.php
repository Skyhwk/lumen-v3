<?php

namespace App\Models;

use App\Models\Sector;

class OrderDetail extends Sector
{
    protected $table = "order_detail";
    public $timestamps = false;

    public function orderHeader()
    {
        return $this->hasOne(OrderHeader::class, 'id', 'id_order_header')->with(['sampling', 'persiapanSampel']);
    }

    public function sampelDiantar()
    {
        return $this->hasOne(SampelDiantar::class, 'no_order', 'no_order');
    }

    public function dataLapanganEmisiKendaraan()
    {
        return $this->belongsTo(DataLapanganEmisiKendaraan::class, 'no_sampel', 'no_sampel')->with('emisiOrder');
    }

    public function dataLapanganEmisiCerobong()
    {
        return $this->belongsTo(DataLapanganEmisiCerobong::class, 'no_sampel', 'no_sampel');
    }

    public function sample_diantar()
    {
        return $this->belongsTo(DataSampleDiantar::class, 'no_sampel', 'no_sample')->where('is_active', true);
    }

    public function codingSampling()
    {
        return $this->hasOne(CodingSampling::class, 'no_sampel', 'no_sampel');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* Update by Afryan at 2025-02-05 cause: salah penamaan foreign_key
        public function TrackingSatu()
        {
            return $this->hasOne(Ftc::class, 'no_sample', 'no_sample');
        }
        public function TrackingDua()
        {
            return $this->hasOne(FtcT::class, 'no_sample', 'no_sample');
        }
    */

    public function TrackingSatu()
    {
        return $this->hasOne(Ftc::class, 'no_sample', 'no_sampel');
    }
    public function TrackingDua()
    {
        return $this->hasOne(FtcT::class, 'no_sample', 'no_sampel');
    }

    public function wsValueAir()
    {
        return $this->hasMany(WsValueAir::class, 'no_sampel', 'no_sampel')->with('gravimetri', 'titrimetri', 'colorimetri', 'subkontrak')->where('is_active', true);
    }

    public function dataLapanganAir()
    {
        return $this->belongsTo(DataLapanganAir::class, 'no_sampel', 'no_sampel');
    }
    public function dataLapanganLingkunganHidup()
    {
        return $this->belongsTo(DataLapanganLingkunganHidup::class, 'no_sampel', 'no_sampel');
    }
    public function dataLapanganPsikologi()
    {
        return $this->hasMany(DataLapanganPsikologi::class, 'no_sampel', 'no_sampel');
    }
    public function dataPsikologi()
    {
        return $this->belongsTo(DataPsikologi::class, 'no_order', 'no_order')->where('is_active', true);
    }

    public function qr_psikologi()
    {
        return QrPsikologi::whereJsonContains('data->no_document', $this->no_document)->where('periode', $this->periode)->get();
    }

    public function dataLapanganLingkunganKerja()
    {
        return $this->belongsTo(DataLapanganLingkunganKerja::class, 'no_sampel', 'no_sampel');
    }
    public function DataLapanganSinarUV()
    {
        return $this->belongsTo(DataLapanganSinarUV::class, 'no_sampel', 'no_sampel');
    }
    public function detailLingkunganHidup()
    {
        return $this->belongsTo(DetailLingkunganHidup::class, 'no_sampel', 'no_sampel');
    }
    public function detailLingkunganKerja()
    {
        return $this->belongsTo(DetailLingkunganKerja::class, 'no_sampel', 'no_sampel');
    }

    public function konfigurasiprasampling()
    {
        return $this->belongsTo(KonfigurasiPraSampling::class, 'kategori_2', 'kategori');
    }

    public function psikologi_header()
    {
        return $this->belongsTo(PsikologiHeader::class, 'no_sampel', 'no_sampel')->with('data_lapangan')->where('is_active', true);
    }
    public function data_lapangan_psikologi()
    {
        return $this->belongsTo(DataLapanganPsikologi::class, 'no_sampel', 'no_sampel')->with('header');
    }
    public function data_lapangan_ergonomi()
    {
        return $this->belongsTo(DataLapanganErgonomi::class, 'no_sampel', 'no_sampel');
    }
    public function lhps_air()
    {
        return $this->belongsTo(LhpsAirHeader::class, 'no_sampel', 'no_sampel')->with('lhpsAirDetail', 'lhpsAirCustom')->where('is_active', true);
    }
    public function lhpp_psikologi()
    {
        return $this->belongsTo(LhppUdaraPsikologiHeader::class, 'no_order', 'no_order');
    }
    public function lhp_psikologi()
    {
        return $this->belongsTo(LhpUdaraPsikologiHeader::class, 'no_order', 'no_order');
    }

    public function lhps_emisi()
    {
        return $this->belongsTo(LhpsEmisiHeader::class, 'cfr', 'no_lhp')->with('lhpsEmisiDetail')->where('is_active', true);
    }

    public function lhps_emisi_c()
    {
        return $this->belongsTo(LhpsEmisiCHeader::class, 'cfr', 'no_lhp')->with('lhpsEmisiCDetail')->where('is_active', true);
    }

    public function lingkunganHeader()
    {
        return $this->belongsTo(LingkunganHeader::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function sampleDiantar()
    {
        return $this->belongsTo(DataSampleDiantar::class, 'no_sample', 'no_sampel');
    }

    // public function samplevalue()
    // {
    //     return $this->belongsTo('App\Models\CategoryValue', 'kategori_3', 'id');
    // }

    // public function category()
    // {
    //     return $this->belongsTo('App\Models\CategorySample', 'kategori_2', 'id');
    // }

    // public function ftcsatu()
    // {
    //     return $this->belongsTo('App\Models\Ftc', 'id', 'id_po');
    // }

    // public function ftcdua()
    // {
    //     return $this->belongsTo('App\Models\Ftcp', 'id', 'id_po');
    // }

    // public function addby()
    // {
    //     return $this->belongsTo('App\Models\User', 'create_by', 'id');
    // }

    // public function delby()
    // {
    //     return $this->belongsTo('App\Models\User', 'delete_by', 'id');
    // }

    // public function appby()
    // {
    //     return $this->belongsTo('App\Models\User', 'id_approve', 'id');
    // }

    // public function lhps()
    // {
    //     return $this->belongsTo('App\Models\Lhps', 'id', 'id_order_header');
    // }

    // public function bakumutu()
    // {
    //     return $this->belongsTo('App\Models\FcWs', 'id', 'id_order_header');
    // }

    // public function orderheader()
    // {
    //     return $this->belongsTo('App\Models\OrderH', 'id_order_header', 'id');
    // }

    // public function codingSampling()
    // {
    //     return $this->belongsTo('App\Models\CodingSampling', 'id', 'id_order_detail');
    // }

    public function headerSD()
    {
        return $this->hasOne(OrderHeader::class, 'no_order', 'no_order');
    }

    public function subCategory()
    {
        return $this->belongsTo(MasterSubKategori::class, 'kategori_3', 'id');
    }

    public function category()
    {
        return $this->belongsTo(MasterKategori::class, 'kategori_2', 'id');
    }

    //Udara
    public function lhps_getaran()
    {
        return $this->belongsTo(LhpsGetaranHeader::class, 'cfr', 'no_lhp')->with('lhpsGetaranDetail')->where('is_active', true);
    }
    public function lhps_kebisingan()
    {
        return $this->belongsTo(LhpsKebisinganHeader::class, 'cfr', 'no_lhp')->with('lhpsKebisinganDetail')->where('is_active', true);
    }
    public function lhps_ling()
    {
        return $this->belongsTo(LhpsLingHeader::class, 'no_sampel', 'no_sampel')->with('lhpsLingDetail')->where('is_active', true);
    }
    public function lhps_medanlm()
    {
        return $this->belongsTo(LhpsMedanLMHeader::class, 'cfr', 'no_lhp')->with('lhpsMedanLMDetail')->where('is_active', true);
    }
    public function lhps_pencahayaan()
    {
        return $this->belongsTo(LhpsPencahayaanHeader::class, 'cfr', 'no_lhp')->with('lhpsPencahayaanDetail')->where('is_active', true);
    }
    public function lhps_sinaruv()
    {
        return $this->belongsTo(LhpsSinarUVHeader::class, 'cfr', 'no_lhp')->with('lhpsSinarUVDetail')->where('is_active', true);
    }

    public function lhps_ergonomi()
    {
        return $this->belongsTo(LhpsErgonomiHeader::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function lhps_iklim()
    {
        return $this->belongsTo(LhpsIklimHeader::class, 'cfr', 'no_lhp')->with('lhpsIklimDetail')->where('is_active', true);
    }

    public function t_fct()
    {
        return $this->belongsTo(Ftc::class, 'no_sampel', 'no_sample')->where('is_active', true);
    }

    public function t_ftc_t()
    {
        return $this->belongsTo(FtcT::class, 'no_sampel', 'no_sample')->where('is_active', true);
    }

    public function union()
    {
        return $this->belongsTo(DataLapanganUnion::class, 'no_sampel', 'no_sampel');
    }
    // mix baru
    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'no_quotation', 'no_quotation');
    }
    public function qrgotrak()
    {
        return $this->belongsTo(QrGotrak::class, 'id_order_header', 'id_quotation');
    }

    public function tc_order_detail()
    {
        return $this->hasOne(TcOrderDetail::class, 'id_order_detail', 'id');
    }

    // Awas ngomong error klo ini ga di naikin !
    public function dataLapanganMicrobiologiUdara()
    {
        return $this->belongsTo(DataLapanganMicrobiologi::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganCahaya()
    {
        return $this->belongsTo(DataLapanganCahaya::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganSwab()
    {
        return $this->belongsTo(DataLapanganSwab::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganDirectLain()
    {
        return $this->belongsTo(DataLapanganDirectLain::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganIklimPanas()
    {
        return $this->belongsTo(DataLapanganIklimPanas::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganIklimDingin()
    {
        return $this->belongsTo(DataLapanganIklimDingin::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganDebuPersonal()
    {
        return $this->belongsTo(DataLapanganDebuPersonal::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganGetaran()
    {
        return $this->belongsTo(DataLapanganGetaran::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganGetaranPersonal()
    {
        return $this->belongsTo(DataLapanganGetaranPersonal::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganKebisingan()
    {
        return $this->belongsTo(DataLapanganKebisingan::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganKebisinganPersonal()
    {
        return $this->belongsTo(DataLapanganKebisinganPersonal::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganMedanLM()
    {
        return $this->belongsTo(DataLapanganMedanLM::class, 'no_sampel', 'no_sampel');
    }

    public function dataLapanganPartikulatMeter()
    {
        return $this->belongsTo(DataLapanganPartikulatMeter::class, 'no_sampel', 'no_sampel');
    }
    public function pencahayaanHeader()
    {
        return $this->belongsTo(PencahayaanHeader::class, 'no_sampel', 'no_sampel');
    }
    public function GetaranHeader()
    {
        return $this->belongsTo(GetaranHeader::class, 'no_sampel', 'no_sampel');
    }

    public function getAnyHeaderUdara()
    {
        if ($this->pencahayaanHeader()->exists()) {
            return $this->pencahayaanHeader;
        }
        if ($this->GetaranHeader()->exists()) {
            return $this->GetaranHeader;
        }
        return null;
    }
    public function getAnyDataLapanganUdara()
    {
        if ($this->dataLapanganLingkunganHidup()->exists()) {
            return $this->dataLapanganLingkunganHidup;
        }

        if ($this->dataLapanganLingkunganKerja()->exists()) {
            return $this->dataLapanganLingkunganKerja;
        }

        if ($this->dataLapanganDirectLain()->exists()) {
            return $this->dataLapanganDirectLain;
        }

        if ($this->dataLapanganIklimPanas()->exists()) {
            return $this->dataLapanganIklimPanas;
        }

        if ($this->dataLapanganPartikulatMeter()->exists()) {
            return $this->dataLapanganPartikulatMeter;
        }

        if ($this->dataLapanganMedanLM()->exists()) {
            return $this->dataLapanganMedanLM;
        }

        if ($this->dataLapanganKebisinganPersonal()->exists()) {
            return $this->dataLapanganKebisinganPersonal;
        }

        if ($this->dataLapanganKebisingan()->exists()) {
            return $this->dataLapanganKebisingan;
        }

        if ($this->dataLapanganGetaranPersonal()->exists()) {
            return $this->dataLapanganGetaranPersonal;
        }

        if ($this->dataLapanganGetaran()->exists()) {
            return $this->dataLapanganGetaran;
        }

        if ($this->dataLapanganDebuPersonal()->exists()) {
            return $this->dataLapanganDebuPersonal;
        }

        if ($this->dataLapanganIklimDingin()->exists()) {
            return $this->dataLapanganIklimDingin;
        }

        if ($this->dataLapanganMicrobiologiUdara()->exists()) {
            return $this->dataLapanganMicrobiologiUdara;
        }

        if ($this->dataLapanganSwab()->exists()) {
            return $this->dataLapanganSwab;
        }

        if ($this->dataLapanganCahaya()->exists()) {
            return $this->dataLapanganCahaya;
        }

        if ($this->dataLapanganPsikologi()->exists()) {
            return $this->dataLapanganPsikologi;
        }

        if ($this->data_lapangan_ergonomi()->exists()) {
            return $this->data_lapangan_ergonomi;
        }

        if ($this->dataLapanganSinarUV()->exists()) {
            return $this->dataLapanganSinarUV;
        }
        return null;
    }

    public function getAnyDataLapanganEmisi()
    {
        if ($this->dataLapanganEmisiCerobong()->exists()) {
            return $this->dataLapanganEmisiCerobong;
        }

        if ($this->dataLapanganEmisiKendaraan()->exists()) {
            return $this->dataLapanganEmisiKendaraan;
        }
    }

    public function getAnyDataLapanganPadatan()
    {
        return null;
    }
}
