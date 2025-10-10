<?php

namespace App\Services;

use App\Models\OrderDetail;
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

class TrackingLhpServiceByCFR
{
    protected string $no_sampel;
    protected string $order_date;
    protected $orderDetail;
    protected $ordered;
    protected $sampling;
    protected $analyst;
    protected $drafting;
    protected $lhp;

    public function __construct(string $no_sampel, $order_date, $orderDetail = null)
    {
        $this->no_sampel = $no_sampel;
        $this->order_date = $order_date;
        $this->orderDetail = $orderDetail;
    }

    public function track()
    {
        try {
            // Only fetch OrderDetail if not provided
            if (!$this->orderDetail) {
                $this->orderDetail = OrderDetail::with('TrackingSatu')->where('no_sampel', $this->no_sampel)->first();
            }

            if (!$this->orderDetail) {
                return null;
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
            $date = null;
            $label = 'Sampling';

            if ($this->orderDetail->TrackingSatu) {
                if ($this->orderDetail->TrackingSatu->ftc_verifier) {
                    $date = $this->orderDetail->TrackingSatu->ftc_verifier;
                } else if ($this->orderDetail->TrackingSatu->ftc_sd) {
                    $date = $this->orderDetail->TrackingSatu->ftc_sd;
                    $label = 'Sampel Diterima';
                }
            }

            return (object)[
                'label' => $label,
                'date' => $date,
            ];
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function getAnalyst()
    {
        try {
            $date = null;

            if ($this->orderDetail->TrackingSatu) {
                $date = $this->orderDetail->TrackingSatu->ftc_laboratory;
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
                if ($air) {
                    $date = $air->created_at;
                }
            } else {
                $sub_kategori = explode('-', $this->orderDetail->kategori_3)[1];
                if (str_contains(strtolower($sub_kategori), 'emisi ')) {
                    if ($sub_kategori === 'Emisi Sumber Tidak Bergerak') {
                        $emisi = LhpsEmisiCHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    } else {
                        $emisi = LhpsEmisiHeader::where('no_order', $this->orderDetail->no_order)->where('is_active', true)->first();
                    }
                    if ($emisi) {
                        $date = $emisi->created_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'ergonomi')) {
                    $ergonomi = LhpsErgonomiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($ergonomi) {
                        $date = $ergonomi->created_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'getaran ')) {
                    $getaran = LhpsGetaranHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($getaran) {
                        $date = $getaran->created_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'iklim ')) {
                    $iklim = LhpsIklimHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($iklim) {
                        $date = $iklim->created_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'kebisingan ')) {
                    $kebisingan = LhpsKebisinganHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($kebisingan) {
                        $date = $kebisingan->created_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'udara ambient') || str_contains(strtolower($sub_kategori), 'udara lingkungan kerja')) {
                    $lingkungan = LhpsLingHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($lingkungan) {
                        $date = $lingkungan->created_at;
                    } else {
                        $sinarUv = LhpsSinarUVHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                        if ($sinarUv) {
                            $date = $sinarUv->created_at;
                        } else {
                            $medanLm = LhpsMedanLMHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                            if ($medanLm) {
                                $date = $medanLm->created_at;
                            }
                        }
                    }
                } else if (str_contains(strtolower($sub_kategori), 'pencahayaan')) {
                    $pencahayaan = LhpsPencahayaanHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($pencahayaan) {
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
                if ($air) {
                    $date = $air->approved_at;
                }
            } else {
                $sub_kategori = explode('-', $this->orderDetail->kategori_3)[1];

                if (str_contains(strtolower($sub_kategori), 'emisi ')) {
                    if ($sub_kategori === 'Emisi Sumber Tidak Bergerak') {
                        $emisi = LhpsEmisiCHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    } else {
                        $emisi = LhpsEmisiHeader::where('no_order', $this->orderDetail->no_order)->where('is_active', true)->first();
                    }
                    if ($emisi) {
                        $date = $emisi->approved_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'ergonomi')) {
                    $ergonomi = LhpsErgonomiHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($ergonomi) {
                        $date = $ergonomi->approved_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'getaran ')) {
                    $getaran = LhpsGetaranHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($getaran) {
                        $date = $getaran->approved_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'iklim ')) {
                    $iklim = LhpsIklimHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($iklim) {
                        $date = $iklim->approved_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'kebisingan ')) {
                    $kebisingan = LhpsKebisinganHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($kebisingan) {
                        $date = $kebisingan->approved_at;
                    }
                } else if (str_contains(strtolower($sub_kategori), 'udara ambient') || str_contains(strtolower($sub_kategori), 'udara lingkungan kerja')) {
                    $lingkungan = LhpsLingHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($lingkungan) {
                        $date = $lingkungan->approved_at;
                    } else {
                        $sinarUv = LhpsSinarUVHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                        if ($sinarUv) {
                            $date = $sinarUv->approved_at;
                        } else {
                            $medanLm = LhpsMedanLMHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                            if ($medanLm) {
                                $date = $medanLm->approved_at;
                            }
                        }
                    }
                } else if (str_contains(strtolower($sub_kategori), 'pencahayaan')) {
                    $pencahayaan = LhpsPencahayaanHeader::where('no_sampel', $noSampel)->where('is_active', true)->first();
                    if ($pencahayaan) {
                        $date = $pencahayaan->approved_at;
                    }
                }
            }

            return $date;
        } catch (\Exception $th) {
            dd($th);
        }
    }
}
