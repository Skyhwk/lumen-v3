<?php
namespace App\Services;

use Auth;
use Validator;
use Exception;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\TemplateOrderDetail;
use Illuminate\Support\Facades\DB;
use App\Services\KalibrasiNoSample;

class GenerateSampleKontrak
{
    public function get(array $data = [])
    {
        $id_order = $data['id_order'];

        TemplateOrderDetail::where('id_order_header', $id_order)->delete();

        $query = "INSERT INTO template_order_detail SELECT * FROM `order_detail` where id_order_header = '$id_order'";

        DB::select($query);
        $qt_lama = QuotationKontrakH::join('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
            ->where('request_quotation_kontrak_H.no_document', $data['no_qt_lama'])
            // ->where('request_quotation_kontrak_H.active', 0)
            ->select('request_quotation_kontrak_D.*')
            ->orderBy('periode_kontrak', 'ASC')
            ->get();

        $qt_baru = QuotationKontrakH::join('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_H')
            ->where('request_quotation_kontrak_H.no_document', $data['no_qt_baru'])
            ->select('request_quotation_kontrak_D.*')
            ->where('request_quotation_kontrak_H.is_active', true)
            ->orderBy('periode_kontrak', 'ASC')
            ->get();


        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];
        $perubahan_periode = [];
        $count_periode_lama = $qt_lama->count();
        $count_periode_baru = $qt_baru->count();

        $array_periode_lama = [];
        $array_periode_baru = [];

        $data_lama = [];
        $data_baru = [];
        foreach ($qt_lama as $z => $xx) {
            array_push($array_periode_lama, $xx->periode_kontrak);
        }
        foreach ($qt_baru as $z => $xx) {
            array_push($array_periode_baru, $xx->periode_kontrak);
        }
        // dump($array_periode_lama, $array_periode_baru);
        $pengurangan_periode_kontrak = array_values(array_diff($array_periode_lama, $array_periode_baru));
        $penambahan_periode_kontrak = array_values(array_diff($array_periode_baru, $array_periode_lama));
        // dd($pengurangan_periode_kontrak, $penambahan_periode_kontrak);
        if ($pengurangan_periode_kontrak != null) {
            foreach ($qt_lama as $z => $xx) {
                if (in_array($xx->periode_kontrak, $pengurangan_periode_kontrak)) { // cari periode

                    /**
                     * Apabila periode pengurangan isinya sama dengan periode penambahan
                     * maka data yang ada di periode pengurangan akan dimasukan ke perubahan periode yang baru
                     */
                    $key_pengurangan = array_search($xx->periode_kontrak, $pengurangan_periode_kontrak);

                    $periode_pengganti = $penambahan_periode_kontrak[$key_pengurangan] ?? null;

                    $data_pendukung_sampling_periode_lama = array_values(array_map(function ($item) {
                        return (array) $item['data_sampling'];
                    }, json_decode($qt_lama->where('periode_kontrak', $xx->periode_kontrak)->first()->data_pendukung_sampling, true)))[0];
                    if ($periode_pengganti != null) {
                        $data_pendukung_sampling_periode_baru = array_values(array_map(function ($item) {
                            return (array) $item['data_sampling'];
                        }, json_decode($qt_baru->where('periode_kontrak', $periode_pengganti)->first()->data_pendukung_sampling, true)))[0];
                    } else {
                        $data_pendukung_sampling_periode_baru = [];
                    }

                    // dd($data_pendukung_sampling_periode_lama, $data_pendukung_sampling_periode_baru);
                    if ($data_pendukung_sampling_periode_lama == $data_pendukung_sampling_periode_baru) {
                        /**
                         * Apabila data pendukung samplingnya sama
                         * maka data yang ada di periode pengurangan akan dimasukan ke perubahan periode yang baru
                         */
                        $perubahan_periode[] = [
                            'before' => $xx->periode_kontrak,
                            'after' => $periode_pengganti
                        ];
                        // unset($penambahan_periode_kontrak[$key_pengurangan]);
                    } else {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                            foreach ($g->data_sampling as $key => $pe) {
                                $pengurangan_data[$xx->periode_kontrak][] = $pe;
                            }
                        }
                    }

                } else {
                    foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                        foreach ($g->data_sampling as $key => $value) {
                            $value->status_sampling = $xx->status_sampling;
                            $data_lama[$g->periode_kontrak][] = $value;
                        }
                    }
                }
            }

            foreach ($pengurangan_periode_kontrak as $vv => $mm) {
                unset($qt_lama[$vv]);
            }
            $qt_lama = array_values($qt_lama->toArray());
        } else {
            foreach ($qt_lama as $z => $xx) {
                foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                    foreach ($g->data_sampling as $key => $value) {
                        $value->status_sampling = $xx->status_sampling;
                        $data_lama[$g->periode_kontrak][] = $value;
                    }
                }
            }
        }

        if ($penambahan_periode_kontrak != null) {
            foreach ($qt_baru as $z => $xx) {
                if (in_array($xx->periode_kontrak, $penambahan_periode_kontrak)) {
                    // dd($xx->periode_kontrak, $penambahan_periode_kontrak);

                    /**
                     * Apabila periode penambahan isinya sama dengan periode pengurangan
                     * maka data yang ada di periode penambahan akan dimasukan ke perubahan periode yang baru
                     */
                    $key_penambahan = array_search($xx->periode_kontrak, $penambahan_periode_kontrak);
                    $periode_pengganti = $pengurangan_periode_kontrak[$key_penambahan] ?? null;

                    // dd($pengurangan_periode_kontrak[$key_penambahan]);
                    // dump($xx->periode_kontrak . ' pengganti dari periode ' . $periode_pengganti);
                    if ($periode_pengganti != null) {
                        unset($pengurangan_periode_kontrak[$key_penambahan]);
                        unset($penambahan_periode_kontrak[$key_penambahan]);
                    } else {
                        foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                            foreach ($g->data_sampling as $key => $value) {

                                $value->status_sampling = $xx->status_sampling;
                                $penambahan_data[$xx->periode_kontrak][] = $value;
                            }
                        }
                    }

                } else {
                    foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                        foreach ($g->data_sampling as $key => $value) {
                            $value->status_sampling = $xx->status_sampling;
                            $data_baru[$g->periode_kontrak][] = $value;
                        }
                    }
                }
            }

            foreach ($penambahan_periode_kontrak as $vv => $mm) {
                unset($qt_baru[$vv]);
            }
            $qt_baru = array_values($qt_baru->toArray());
        } else {
            foreach ($qt_baru as $z => $xx) {
                foreach ((array) json_decode($xx->data_pendukung_sampling) as $g) {
                    foreach ($g->data_sampling as $key => $value) {
                        $value->status_sampling = $xx->status_sampling;
                        $data_baru[$g->periode_kontrak][] = $value;
                    }
                }
            }
        }

        function deep_array_diff($array1, $array2)
        {
            $diff = [];
            foreach ($array1 as $key => $value1) {
                if (!isset($array2[$key])) {
                    $diff[$key] = $value1;
                } elseif (is_array($value1) && is_array($array2[$key])) {
                    $deep_diff = deep_array_diff($value1, $array2[$key]);
                    if (!empty($deep_diff)) {
                        $diff[$key] = $deep_diff;
                    }
                } elseif ($value1 !== $array2[$key]) {
                    $diff[$key] = $value1;
                }
            }
            return $diff;
        }

        $different = deep_array_diff($data_baru, $data_lama);

        // Mencari data analisa yang berbeda secara menyeluruh di setiap periodenya dan dieliminasi data yang sama sehingga mempersingkat proses compare
        // $different = array_map('json_decode', array_diff(array_map('json_encode', $data_baru), array_map('json_encode', $data_lama)));
        // dd($different);
        // dd($different);
        foreach ($different as $s => $fn) {

            $array_a = json_decode(json_encode($data_lama[$s]), true);
            $array_b = json_decode(json_encode($fn), true);

            $different_kanan = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_b), array_map('json_encode', $array_a))));

            $different_kiri = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_a), array_map('json_encode', $array_b))));

            // if($s == '2024-10') dd($array_a, $array_b);

            if ($different_kanan != null) {
                foreach ($different_kanan as $z => $detail_baru) {
                    if (count($array_a) > 0) {
                        foreach ($array_a as $_x => $ss) {
                            $detail_lama = (object) $ss;
                            // var_dump($detail_lama, $detail_baru);
                            if
                            (
                                $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                $detail_lama->parameter == $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * Data ditemukan yang artinya ada pengurangan / penambahan titik
                                 */
                                if ((int) $detail_lama->jumlah_titik > (int) $detail_baru->jumlah_titik) {
                                    /**
                                     * Pengurangan titik
                                     */
                                    $selisih = abs($detail_lama->jumlah_titik - $detail_baru->jumlah_titik);
                                    $detail_baru->jumlah_titik = $selisih;
                                    $pengurangan_data[$s][] = $detail_baru;
                                } else if ((int) $detail_lama->jumlah_titik < (int) $detail_baru->jumlah_titik) {
                                    /**
                                     * penambahan titik
                                     */
                                    $selisih = abs($detail_baru->jumlah_titik - $detail_lama->jumlah_titik);
                                    $detail_baru->jumlah_titik = $selisih;
                                    $penambahan_data[$s][] = $detail_baru;
                                }

                                foreach ($different_kiri as $xxx => $sss) {
                                    if (
                                        $detail_lama->kategori_1 == $sss->kategori_1 &&
                                        $detail_lama->kategori_2 == $sss->kategori_2 &&
                                        $detail_lama->parameter == $sss->parameter &&
                                        (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                        && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                    ) {
                                        unset($different_kiri[$xxx]);
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                    }
                                }

                                break;
                            } else if (
                                $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                $detail_lama->parameter == $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan Regulasi 
                                 */
                                $array_perubahan = [
                                    'before' => $detail_lama,
                                    'after' => $detail_baru
                                ];
                                $perubahan_data[$s][] = $array_perubahan;

                                foreach ($different_kiri as $xxx => $sss) {
                                    if (
                                        $detail_lama->kategori_1 == $sss->kategori_1 &&
                                        $detail_lama->kategori_2 == $sss->kategori_2 &&
                                        $detail_lama->parameter == $sss->parameter &&
                                        (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                        && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                    ) {
                                        unset($different_kiri[$xxx]);
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                    }
                                }
                                // unset($array_a[$_x]);
                                break;
                            } else if (
                                $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                $detail_lama->kategori_2 != $detail_baru->kategori_2 &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : []) &&
                                $detail_lama->parameter == $detail_baru->parameter
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan sub kategori 
                                 */

                                $array_perubahan = [
                                    'before' => $detail_lama,
                                    'after' => $detail_baru
                                ];
                                $perubahan_data[$s][] = $array_perubahan;

                                foreach ($different_kiri as $xxx => $sss) {
                                    if (
                                        $detail_lama->kategori_1 == $sss->kategori_1 &&
                                        $detail_lama->kategori_2 == $sss->kategori_2 &&
                                        $detail_lama->parameter == $sss->parameter &&
                                        (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                        && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                    ) {
                                        unset($different_kiri[$xxx]);
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                    }
                                }
                                // unset($array_a[$_x]);
                                break;
                            } else if (
                                $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                $detail_lama->parameter != $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan parameter 
                                 */
                                $array_perubahan = [
                                    'before' => $detail_lama,
                                    'after' => $detail_baru
                                ];
                                $perubahan_data[$s][] = $array_perubahan;

                                foreach ($different_kiri as $xxx => $sss) {
                                    if (
                                        $detail_lama->kategori_1 == $sss->kategori_1 &&
                                        $detail_lama->kategori_2 == $sss->kategori_2 &&
                                        $detail_lama->parameter == $sss->parameter &&
                                        (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : [])
                                        && $detail_lama->penamaan_titik == $sss->penamaan_titik
                                    ) {
                                        unset($different_kiri[$xxx]);
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                    }
                                }
                                // unset($array_a[$_x]);
                                break;
                            } else if (
                                $detail_lama->kategori_1 == $detail_baru->kategori_1 &&
                                $detail_lama->kategori_2 == $detail_baru->kategori_2 &&
                                $detail_lama->parameter != $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) != (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan regulasi dan parameter
                                 */

                                $array_perubahan = [
                                    'before' => $detail_lama,
                                    'after' => $detail_baru
                                ];
                                $perubahan_data['non_kontrak'][] = $array_perubahan;

                                foreach ($different_kiri as $xxx => $sss) {
                                    if (
                                        $detail_lama->kategori_1 == $sss->kategori_1 &&
                                        $detail_lama->kategori_2 == $sss->kategori_2 &&
                                        $detail_lama->parameter == $sss->parameter &&
                                        (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($sss->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $sss->regulasi) : []) &&
                                        $detail_lama->penamaan_titik == $sss->penamaan_titik
                                    ) {
                                        /*
                                         * &&
                                         * $detail_lama->penamaan_titik == $sss->penamaan_titik
                                         */
                                        unset($different_kiri[$xxx]);
                                        unset($array_a[$_x]);
                                        $array_a = array_values($array_a);
                                    }
                                }
                            } else {
                                /**
                                 * data tidak di temukan yang menandakan penambahan kategori 
                                 */

                                if ($_x == (count($array_a) - 1)) {
                                    $penambahan_data[$s][] = $detail_baru;
                                    unset($array_a[$_x]);
                                    $array_a = array_values($array_a);
                                    break;
                                }
                            }
                        }
                    } else {
                        $penambahan_data[$s][] = $detail_baru;
                    }
                }
            }

            if ($different_kiri) {
                // dd($different_kiri);
                foreach ($different_kiri as $z => $detail_baru) {
                    $pengurangan_data[$s][] = $detail_baru;
                }
            }
        }
        // dd($perubahan_data, $penambahan_data, $pengurangan_data);

        $cek_no_header = TemplateOrderDetail::where('id_order_header', $id_order)->first();
        $no_order = $cek_no_header->no_order;
        $n = 0;
        $no_urut_cfr = 0;
        if ($perubahan_data != null) {
            foreach ($perubahan_data as $key => $value) {
                $periode = $key;
                foreach ($value as $k => $v) {
                    $data_qt_lama = $v['before'];
                    $data_qt_baru = $v['after'];
                    // dd($data_qt_lama, $data_qt_baru);
                    $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                        ->where('periode', $periode)
                        ->where('kategori_2', $data_qt_lama->kategori_1)
                        ->where('kategori_3', $data_qt_lama->kategori_2)
                        // ->where('regulasi', str_replace('\/', '/', json_encode($data_qt_lama->regulasi)))
                        // ->where('parameter', $data_qt_lama->parameter)
                        ->where('regulasi', json_encode($data_qt_lama->regulasi))
                        ->where('is_active', 1)
                        ->orderBy('no_sampel', 'DESC')
                        ->get()
                        ->filter(function ($item) use ($data_qt_lama, $key) {
                            return collect(json_decode($item->parameter))->sort()->values()->all() == collect($data_qt_lama->parameter)->sort()->values()->all();
                        });

                    $titik = $cek_order_detail_lama->take($data_qt_lama->jumlah_titik);

                    foreach ($titik as $kk => $vv) {
                        $vv->kategori_2 = $data_qt_baru->kategori_1;
                        $vv->kategori_3 = $data_qt_baru->kategori_2;
                        $vv->parameter = json_encode($data_qt_baru->parameter);
                        $vv->regulasi = json_encode($data_qt_baru->regulasi);
                        $vv->updated_at = DATE('Y-m-d H:i:s');

                        if (($kk + 1) <= (int) $data_qt_baru->jumlah_titik) {
                            $vv->is_active = 1;
                        } else {
                            $vv->is_active = 0;
                        }

                        $vv->save();
                    }

                    if ((int) $data_qt_baru->jumlah_titik > (int) $data_qt_lama->jumlah_titik) {
                        /**
                         * Apabila ada penambahan titik
                         */
                        $selisih = (int) $data_qt_baru->jumlah_titik - (int) $data_qt_lama->jumlah_titik;
                        $data_qt_baru->jumlah_titik = $selisih;
                        $penambahan_data[$periode][] = $data_qt_baru;
                    }

                }
            }
        }

        $added_no_sampel = [];
        if ($penambahan_data != null) {
            // Add data
            $cek_detail = TemplateOrderDetail::where('id_order_header', $id_order)
                ->orderBy('no_sampel', 'DESC')
                ->first();

            $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
            $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
            $n = $no_urut_sample + 1;
            $trigger = 0;
            $kategori = '';

            // dd($penambahan_data);
            foreach ($penambahan_data as $key => $values) {
                foreach ($values as $keys => $value) {

                    for ($f = 0; $f < $value->jumlah_titik; $f++) {
                        // =================================================================

                        $no_sample = $no_order . '/' . sprintf("%03d", $n);

                        if (count($value->parameter) <= 2) {
                            if ($kategori != $value->kategori_2) {
                                $no_urut_cfr++;
                            }
                            // Keep $no the same if condition is true
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        } else {
                            // Increment $no from its previous value and ensure it's not 1
                            $no_urut_cfr++;
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        }
                        // dd($no_sample, $no_cfr);
                        // =================================================================
                        $dataD = new TemplateOrderDetail;
                        $dataD->id_order_header = $id_order;
                        $dataD->no_order = $no_order;
                        $dataD->no_sampel = $no_sample;
                        $dataD->periode = $key;
                        $dataD->kategori_1 = $value->status_sampling;
                        $dataD->kategori_2 = $value->kategori_1;
                        $dataD->kategori_3 = $value->kategori_2;
                        $dataD->cfr = $no_cfr;
                        $dataD->parameter = json_encode($value->parameter);
                        $dataD->regulasi = json_encode($value->regulasi);
                        $dataD->created_at = date('Y-m-d h:i:s');

                        // =================================================================
                        $dataD->save();

                        $n++;
                        $kategori = $value->kategori_2;
                        array_push($added_no_sampel, $dataD->no_sampel);
                    }
                }
            }
        }

        if ($pengurangan_data != null) {
            // non aktifkan data

            foreach ($pengurangan_data as $key => $value) {
                foreach ($value as $keys => $values) {

                    if (isset($values->status_sampling)) { // && $values->status_sampling != null
                        $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                            ->whereNotIn('no_sampel', $added_no_sampel)
                            ->where('periode', $key)
                            ->where('kategori_2', $values->kategori_1)
                            ->where('kategori_3', $values->kategori_2)
                            // ->where('regulasi', str_replace('\/', '/', json_encode($values->regulasi)))
                            // ->where('parameter', json_encode($values->parameter))
                            ->where('regulasi', json_encode($values->regulasi))
                            ->where('is_active', 1)
                            ->orderBy('no_sampel', 'DESC')
                            ->get()
                            ->filter(function ($item) use ($values, $key) {
                                return collect(json_decode($item->parameter))->sort()->values()->all() == collect($values->parameter)->sort()->values()->all();
                            });


                        $titik = $cek_order_detail_lama->take($values->jumlah_titik);
                    } else {
                        //penghapusan periode
                        $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                            ->where('periode', $key)
                            ->get();
                        $titik = $cek_order_detail_lama;
                    }

                    if ($cek_order_detail_lama != null) {
                        foreach ($titik as $hh => $change) {
                            $change->is_active = 0;
                            $change->save();

                        }
                    }
                }
            }
        }

        if ($perubahan_periode != null) {
            // Perubahan Periode
            foreach ($perubahan_periode as $key => $value) {
                $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                    ->where('periode', $value['before'])
                    ->get();

                foreach ($cek_order_detail_lama as $kk => $vv) {
                    $vv->periode = $value['after'];
                    $vv->save();
                }
            }
        }
        // dd($penambahan_data, $pengurangan_data, $perubahan_data);

        $getData = TemplateOrderDetail::where('id_order_header', $id_order)
            ->where('is_active', 1)
            ->orderBy('no_sampel', 'ASC')
            ->get();

        $array = [];
        $response = [];
        foreach ($getData as $key => $value) {
            $response[$value->periode]['kategori'][] = \explode('-', $value->kategori_3)[1] . ' - ' . \explode('/', $value->no_sampel)[1];
            $response[$value->periode]['total_param'][] = count(json_decode($value->parameter)) . 'p';

        }

        TemplateOrderDetail::where('id_order_header', $id_order)->delete();
        // dd($response);
        return $response;

    }

    public function new(array $data = [])
    {
        $detail = QuotationKontrakH::join('request_quotation_kontrak_D', 'request_quotation_kontrak_H.id', '=', 'request_quotation_kontrak_D.id_request_quotation_kontrak_h')
            ->where('no_document', $data['no_qt_baru'])
            // ->select('request_quotation_kontrak_D.*')
            ->orderBy('periode_kontrak', 'ASC')
            ->get();

        $n = 1;
        $response = [];
        foreach ($detail as $k => $t) {
            foreach (json_decode($t->data_pendukung_sampling) as $ky => $val) {

                foreach ($val->data_sampling as $key => $value) {

                    for ($f = 0; $f < $value->jumlah_titik; $f++) {
                        // =================================================================
                        $response[$t->periode_kontrak]['kategori'][] = \explode('-', $value->kategori_2)[1] . ' - ' . sprintf("%03d", $n);
                        $response[$t->periode_kontrak]['total_param'][] = count($value->parameter) . 'p';
                        // =================================================================
                        $n++;
                    }
                }
            }
        }
        return $response;
    }

    protected function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }
}