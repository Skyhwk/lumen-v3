<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\PerubahanSampel;
use App\Models\FtcT;
use App\Models\Ftc;
use App\Models\Subkontrak;
use App\Models\{DataLapanganAir, Gravimetri, Titrimetri, Colorimetri, WsValueAir};
use App\Models\{
    WsValueUdara,
    DataLapanganKebisingan,
    KebisinganHeader,
    DataLapanganGetaranPersonal,
    DataLapanganGetaran,
    GetaranHeader,
    DataLapanganCahaya,
    PencahayaanHeader,
    DataLapanganIklimPanas,
    DataLapanganIklimDingin,
    IklimHeader,
    DataLapanganErgonomi,
    ErgonomiHeader,
    DataLapanganLingkunganHidup,
    DataLapanganLingkunganKerja,
    DataLapanganSenyawaVolatile,
    LingkunganHeader,
    DataLapanganDirectLain,
    DirectLainHeader,
    DataLapanganMedanLM,
    MedanLmHeader,
    DataLapanganPsikologi,
    PsikologiHeader,
    DataLapanganSinarUV,
    SinarUvHeader,
    DataLapanganDebuPersonal,
    WsValueLingkungan,
    DataLapanganPartikulatMeter,
    PartikulatHeader,
    DetailFlowMeter,
    DetailLingkunganHidup,
    DetailLingkunganKerja,
    DetailSenyawaVolatile,
    DetailMicrobiologi,
    DetailSoundMeter,
};
use App\Models\{WsValueMicrobio, MicrobioHeader, DataLapanganMicrobiologi};
use App\Models\{WsValueSwab, SwabTestHeader, DataLapanganSwab};
use App\Models\{
    WsValueEmisiCerobong,
    EmisiCerobongHeader,
    DataLapanganEmisiCerobong,
    DataLapanganIsokinetikBeratMolekul,
    DataLapanganIsokinetikHasil,
    DataLapanganIsokinetikKadarAir,
    DataLapanganIsokinetikPenentuanKecepatanLinier,
    DataLapanganIsokinetikPenentuanPartikulat,
    IsokinetikHeader
};
use App\Models\{DataLapanganEmisiOrder, DataLapanganEmisiKendaraan};
use Carbon\Carbon;

class PerubahanSampelService
{
    public function run($no_order, $userid)
    {
        $perubahanSampel = PerubahanSampel::where('no_order', $no_order)->pluck('perubahan');
        foreach ($perubahanSampel as $jsonString) {
            foreach (json_decode($jsonString, true) as $item) {
                $oldDetail = OrderDetail::where('no_sampel', $item['old'])->first();
                if ($oldDetail->status >= 2) {
                    continue;
                }
                if ($oldDetail) {
                    // Cek selisih hari dari tanggal_terima sampai hari ini
                    $selisihHari = Carbon::parse($oldDetail->tanggal_terima)->diffInDays(Carbon::now());

                    if ($selisihHari > 10) {
                        continue;
                    }
                }
                OrderDetail::where('no_sampel', $item['new'])->update([
                    'tanggal_terima' => $oldDetail->tanggal_terima,
                    'keterangan_1' => $oldDetail->keterangan_1,
                    'persiapan' => $oldDetail->persiapan
                ]);

                $oldFtc = Ftc::where('no_sample', $item['old'])->first();
                if ($oldFtc) {
                    $updateFtc = collect($oldFtc->toArray())
                        ->except(['id', 'no_sample', 'is_active'])
                        ->all();

                    Ftc::where('no_sample', $item['new'])->update($updateFtc);
                }

                $oldFtcT = FtcT::where('no_sample', $item['old'])->first();
                if ($oldFtcT) {
                    $updateFtcT = collect($oldFtcT->toArray())
                        ->except(['id', 'no_sample', 'is_active'])
                        ->all();

                    FtcT::where('no_sample', $item['new'])->update($updateFtcT);
                }

                $oldSample = (object) [
                    'add_by' => $userid,
                    'update_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'no_sampel_lama' => $item['old']
                ];

                switch ($oldDetail->kategori_2) {
                    case '1-Air':
                        DataLapanganAir::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                        $wsAir = WsValueAir::where('no_sampel', $item['old'])->get();
                        foreach ($wsAir as $ws) {
                            if (isset($ws->id_titrimetri) && $ws->id_titrimetri != null) {
                                Titrimetri::where('id', $ws->id_titrimetri)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }

                            if (isset($ws->id_gravimetri) && $ws->id_gravimetri != null) {
                                Gravimetri::where('id', $ws->id_gravimetri)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }

                            if (isset($ws->id_colorimetri) && $ws->id_colorimetri != null) {
                                Colorimetri::where('id', $ws->id_colorimetri)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }

                            if (isset($ws->id_subkontrak) && $ws->id_subkontrak != null) {
                                Subkontrak::where('id', $ws->id_subkontrak)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                        }
                        WsValueAir::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        break;
                    case '4-Udara':
                        if (in_array(explode('-', $oldDetail->kategori_3)[0], [11, 27, 22, 118])) {
                            // lingkungan header || directlain header || medan lm header || psikologi header || sinar uv header|| debu personal header || partikulat header
                            DataLapanganLingkunganHidup::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganLingkunganKerja::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganSenyawaVolatile::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganPartikulatMeter::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganDebuPersonal::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganDirectLain::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganMedanLM::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganSinarUV::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            DetailLingkunganHidup::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DetailLingkunganKerja::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DetailSenyawaVolatile::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            // DetailFlowMeter::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            DataLapanganPsikologi::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            PsikologiHeader::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $idWsUdara = [];
                            $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_lingkungan_header) && $ws->id_lingkungan_header != null) {
                                    LingkunganHeader::where('id', $ws->id_lingkungan_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsUdara, $ws->id);
                                }

                                if (isset($ws->id_direct_lain_header) && $ws->id_direct_lain_header != null) {
                                    DirectLainHeader::where('id', $ws->id_direct_lain_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsUdara, $ws->id);
                                }

                                if (isset($ws->id_medan_lm_header) && $ws->id_medan_lm_header != null) {
                                    MedanLmHeader::where('id', $ws->id_medan_lm_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsUdara, $ws->id);
                                }

                                if (isset($ws->id_sinaruv_header) && $ws->id_sinaruv_header != null) {
                                    SinarUvHeader::where('id', $ws->id_sinaruv_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsUdara, $ws->id);
                                }

                                if (isset($ws->id_partikulat_header) && $ws->id_partikulat_header != null) {
                                    PartikulatHeader::where('id', $ws->id_partikulat_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsUdara, $ws->id);
                                }
                            }
                            WsValueUdara::where('no_sampel', $item['old'])->whereIn('id', $idWsUdara)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $idWsLingkungan = [];
                            $wsLingkungan = WsValueLingkungan::where('no_sampel', $item['old'])->get();
                            foreach ($wsLingkungan as $ws) {
                                if (isset($ws->lingkungan_header_id) && $ws->lingkungan_header_id != null) {
                                    LingkunganHeader::where('id', $ws->lingkungan_header_id)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsLingkungan, $ws->id);
                                }

                                if (isset($ws->id_subkontrak) && $ws->id_subkontrak != null) {
                                    Subkontrak::where('id', $ws->id_subkontrak)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsLingkungan, $ws->id);
                                }
                            }
                            WsValueLingkungan::where('no_sampel', $item['old'])->whereIn('id', $idWsLingkungan)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [23, 24, 25])) {
                            // kebisingan header
                            DataLapanganKebisingan::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            // DetailSoundMeter::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_kebisingan_header) && $ws->id_kebisingan_header == null) {
                                    continue;
                                }

                                KebisinganHeader::where('id', $ws->id_kebisingan_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_kebisingan_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [20, 17, 13, 14, 15, 18, 19])) {
                            // getaran header 
                            DataLapanganGetaranPersonal::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganGetaran::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_getaran_header) && $ws->id_getaran_header == null) {
                                    continue;
                                }

                                GetaranHeader::where('id', $ws->id_getaran_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_getaran_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [28])) {
                            // pencahayaan header
                            DataLapanganCahaya::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_pencahayaan_header) && $ws->id_pencahayaan_header == null) {
                                    continue;
                                }

                                PencahayaanHeader::where('id', $ws->id_pencahayaan_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_pencahayaan_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [53])) {
                            // ergonomi header
                            $idLapanganErgonomi = DataLapanganErgonomi::where('no_sampel', $item['old'])->pluck('id')->values()->toArray();
                            DataLapanganErgonomi::whereIn('id', $idLapanganErgonomi)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            ErgonomiHeader::whereIn('id_lapangan', $idLapanganErgonomi)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [46])) {
                            // swab test header
                            DataLapanganSwab::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueSwab::where('no_sampel', $item['old'])->get();
                            // $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_swab_header) && $ws->id_swab_header == null) {
                                    continue;
                                }

                                SwabTestHeader::where('id', $ws->id_swab_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueSwab::where('no_sampel', $item['old'])->whereNotNull('id_swab_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            // WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_swab_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [21])) {
                            // iklim header
                            DataLapanganIklimPanas::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIklimDingin::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_iklim_header) && $ws->id_iklim_header == null) {
                                    continue;
                                }

                                IklimHeader::where('id', $ws->id_iklim_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_iklim_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [33, 12])) {
                            // mikrobiologi
                            DataLapanganMicrobiologi::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DetailMicrobiologi::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $wsUdara = WsValueMicrobio::where('no_sampel', $item['old'])->get();
                            // $wsUdara = WsValueUdara::where('no_sampel', $item['old'])->get();
                            foreach ($wsUdara as $ws) {
                                if (isset($ws->id_microbio_header) && $ws->id_microbio_header == null) {
                                    continue;
                                }

                                MicrobioHeader::where('id', $ws->id_microbio_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            }
                            WsValueMicrobio::where('no_sampel', $item['old'])->whereNotNull('id_microbio_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            // WsValueUdara::where('no_sampel', $item['old'])->whereNotNull('id_microbio_header')->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [29])) {
                            // udara umum
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [12])) {
                            // udara angka kuman
                        }
                        break;
                    case '5-Emisi':
                        if (in_array(explode('-', $oldDetail->kategori_3)[0], [30, 31, 32, 116])) {
                            // ESB
                            $idLapanganEmisi = DataLapanganEmisiKendaraan::where('no_sampel', $item['old'])->pluck('id')->values()->toArray();
                            DataLapanganEmisiKendaraan::whereIn('id', $idLapanganEmisi)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            DataLapanganEmisiOrder::whereIn('id_fdl', $idLapanganEmisi)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                        } else if (in_array(explode('-', $oldDetail->kategori_3)[0], [34])) {
                            // ESTB
                            DataLapanganEmisiCerobong::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            $idWsCerobong = [];
                            $wsCerobong = WsValueEmisiCerobong::where('no_sampel', $item['old'])->get();
                            foreach ($wsCerobong as $ws) {
                                if (isset($ws->id_emisi_cerobong_header) && $ws->id_emisi_cerobong_header != null) {
                                    EmisiCerobongHeader::where('id', $ws->id_emisi_cerobong_header)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsCerobong, $ws->id);
                                }

                                if (isset($ws->id_subkontrak) && $ws->id_subkontrak != null) {
                                    Subkontrak::where('id', $ws->id_subkontrak)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                                    array_push($idWsCerobong, $ws->id);
                                }
                            }
                            WsValueEmisiCerobong::where('no_sampel', $item['old'])->where('id', $idWsCerobong)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikBeratMolekul::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikKadarAir::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikHasil::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            IsokinetikHeader::where('no_sampel', $item['old'])->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            /* Siapa tau butuh idsurvey
                            $idSurvey = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', $item['old'])->pluck('id_lapangan')->values()->toArray();
                            DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikBeratMolekul::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikKadarAir::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);
                            DataLapanganIsokinetikHasil::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);

                            IsokinetikHeader::where('no_sampel', $item['old'])->whereIn('id_lapangan', $idSurvey)->update(['no_sampel' => $item['new'], 'no_sampel_lama' => json_encode($oldSample)]);*/
                        }
                        break;
                }
            }
        }
    }
}
