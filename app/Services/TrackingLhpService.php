<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueUdara;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsAirHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsIklimHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsErgonomiHeader;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsSinarUVHeader;
use App\Models\LhpsLingHeader;
use Carbon\Carbon;

class TrackingLhpService
{
    protected string $no_sampel;
    protected string $order_date;
    protected $orderDetail;
    protected $ordered;
    protected $sampling;
    protected $analyst;
    protected $drafting;
    protected $lhp;

    public function __construct(string $no_sampel, $order_date)
    {
        $this->no_sampel = $no_sampel;
        $this->order_date = $order_date;
    }

    public function track()
    {
        try {
            $this->orderDetail = OrderDetail::where('no_sampel', $this->no_sampel)->first();
            
            if (!$this->orderDetail) {
                return null; // atau return response error
            }

            $this->ordered = (object)[
                'label' => 'Ordered',
                'date' => $this->order_date,
            ];

            $this->sampling = $this->getDataLapangan();

            $this->analyst = (object)[
                'label' => 'Analyst',
                'date' => $this->sampling->date ? $this->getAnalyst() : null,
            ];

            $this->drafting = (object)[
                'label' => 'Drafting',
                'date' => $this->analyst->date ? $this->getDrafting() : null,
            ];

            $this->lhp = (object)[
                'label' => 'LHP Release',
                'date' => $this->drafting->date ? $this->getLhp() : null,
            ];

            return [
                $this->ordered,
                $this->sampling,
                $this->analyst,
                $this->drafting,
                $this->lhp
            ];
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function getDataLapangan()
    {
        try {
            $kategori = strtoupper(explode('-', $this->orderDetail->kategori_2)[1]);
            $date = null;
            $label = "Sampling";

            if ($kategori === 'AIR') {
                $orderDetail = OrderDetail::with('dataLapanganAir')
                    ->where('no_sampel', $this->no_sampel)
                    ->first();

                if ($orderDetail->dataLapanganAir) {
                    $date = $orderDetail->dataLapanganAir->created_at;
                }

            }  else {
                $orderDetail = OrderDetail::where('no_sampel', $this->no_sampel)
                    ->first();

                

                if ($kategori === 'UDARA') {
                    $dataLapangan = $orderDetail->getAnyDataLapanganUdara();
                } else if ($kategori === 'EMISI') {
                    $dataLapangan = $orderDetail->getAnyDataLapanganEmisi();
                } else if ($kategori === 'PADATAN') {
                    $dataLapangan = $orderDetail->getAnyDataLapanganEmisi();
                }
                if($dataLapangan) {
                    if($dataLapangan->created_at) {
                        $date = $dataLapangan->created_at;
                    } else {
                        dd($dataLapangan);
                    }
                    // $date = $dataLapangan->created_at;
                }
            }

            if($date == null) {
                $date = $orderDetail->tanggal_terima;
                if($date){
                    $label = "Sampel Diantar";
                }
            }
            
            return (object)[
                'label' => $label,
                'date' => $date
            ];
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function getAnalyst()
    {
        try {
            $rawKategori = explode('-', $this->orderDetail->kategori_2);
            if(count($rawKategori) > 1) {
                $kategori = strtoupper($rawKategori[1]);
            } else {
                return null;
            }
            $noSampel = $this->no_sampel;
            $date = null;
            $dataAnalyst = null;
            if ($kategori === 'AIR') {
                $air = WsValueAir::where('no_sampel', $noSampel)->first();
                if($air){
                    $dataAnalyst = $air->getDataAnalyst();
                }
            } else if($kategori === 'EMISI') {
                $emisi = WsValueEmisiCerobong::where('no_sampel', $noSampel)->first();
                if($emisi) {
                    $dataAnalyst = $emisi->getDataAnalyst();
                }
            } else {
                $udara = WsValueUdara::where('no_sampel', $noSampel)->first();
                if($udara) {
                    $dataAnalyst = $udara->getDataAnalyst();
                }
            }

            if($dataAnalyst) {
                $date = $dataAnalyst->created_at;
            }

            return $date;
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function getDrafting()
    {
        try {
            $kategori = strtoupper(explode('-', $this->orderDetail->kategori_2)[1]);
            $noSampel = $this->no_sampel;
            $date = null;

            if ($kategori === 'AIR') {
                $air = LhpsAirHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                if($air){
                    $date = $air->created_at;
                }
            } else {
                $sub_kategori = explode('-', $this->orderDetail->kategori_3)[1];
                if (str_contains(strtolower($sub_kategori), 'emisi ')) {
                    if($sub_kategori === 'Emisi Sumber Tidak Bergerak' ){
                        $emisi = LhpsEmisiCHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    } else {
                        $emisi = LhpsEmisiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    }
                    if($emisi) {
                        $date = $emisi->created_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'ergonomi')) {
                    $ergonomi = LhpsErgonomiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($ergonomi) {
                        $date = $ergonomi->created_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'getaran ')) {
                    $getaran = LhpsGetaranHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($getaran) {
                        $date = $getaran->created_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'iklim ')){
                    $iklim = LhpsIklimHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($iklim) {
                        $date = $iklim->created_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'kebisingan ')){
                    $kebisingan = LhpsKebisinganHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($kebisingan) {
                        $date = $kebisingan->created_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'udara ambient') || str_contains(strtolower($sub_kategori), 'udara lingkungan kerja')){
                    $lingkungan = LhpsLingHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($lingkungan) {
                        $date = $lingkungan->created_at;
                    } else {
                        $sinarUv = LhpsSinarUVHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                        if($sinarUv) {
                            $date = $sinarUv->created_at;
                        } else {
                            $medanLm = LhpsMedanLMHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                            if($medanLm) {
                                $date = $medanLm->created_at;
                            }
                        }
                    }
                } else if(str_contains(strtolower($sub_kategori), 'pencahayaan')){
                    $pencahayaan = LhpsPencahayaanHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($pencahayaan) {
                        $date = $pencahayaan->created_at;
                    }
                }
            }

            return $date;
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function getLhp()
    {
        try {
            $kategori = strtoupper(explode('-', $this->orderDetail->kategori_2)[1]);
            $noSampel = $this->no_sampel;
            
            $date = null;

            if ($kategori === 'AIR') {
                $air = LhpsAirHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                if($air){
                    $date = $air->generated_at;
                }
            } else {
                $sub_kategori = explode('-', $this->orderDetail->kategori_3)[1];

                if (str_contains(strtolower($sub_kategori), 'emisi ')) {
                    if($sub_kategori === 'Emisi Sumber Tidak Bergerak' ){
                        $emisi = LhpsEmisiCHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    } else {
                        $emisi = LhpsEmisiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    }
                    if($emisi) {
                        $date = $emisi->generated_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'ergonomi')) {
                    $ergonomi = LhpsErgonomiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($ergonomi) {
                        $date = $ergonomi->generated_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'getaran ')) {
                    $getaran = LhpsGetaranHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($getaran) {
                        $date = $getaran->generated_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'iklim ')){
                    $iklim = LhpsIklimHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($iklim) {
                        $date = $iklim->generated_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'kebisingan ')){
                    $kebisingan = LhpsKebisinganHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($kebisingan) {
                        $date = $kebisingan->generated_at;
                    }
                } else if(str_contains(strtolower($sub_kategori), 'udara ambient') || str_contains(strtolower($sub_kategori), 'udara lingkungan kerja')){
                    $lingkungan = LhpsLingHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($lingkungan) {
                        $date = $lingkungan->generated_at;
                    } else {
                        $sinarUv = LhpsSinarUVHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                        if($sinarUv) {
                            $date = $sinarUv->generated_at;
                        } else {
                            $medanLm = LhpsMedanLMHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                            if($medanLm) {
                                $date = $medanLm->generated_at;
                            }
                        }
                    }
                } else if(str_contains(strtolower($sub_kategori), 'pencahayaan')){
                    $pencahayaan = LhpsPencahayaanHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if($pencahayaan) {
                        $date = $pencahayaan->generated_at;
                    }
                }
            }

            return $date;
        } catch (\Exception $th) {
            dd($th);
        }
    }
}

