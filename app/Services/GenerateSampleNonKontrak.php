<?php
namespace App\Services;

use Auth;
use Validator;
use Exception;
use App\Models\QuotationNonKontrak;
use App\Models\TemplateOrderDetail;
use Illuminate\Support\Facades\DB;

class GenerateSampleNonKontrak
{

    public function get(array $data = [])
    {
        $id_order = $data['id_order'];
        TemplateOrderDetail::where('id_order_header', $id_order)->delete();

        $query = "INSERT INTO template_order_detail SELECT * FROM `order_detail` where id_order_header = '$id_order'";
        DB::select($query);

        $qt_lama = QuotationNonKontrak::where('no_document', $data['no_qt_lama'])->first();
        $qt_baru = QuotationNonKontrak::where('no_document', $data['no_qt_baru'])->first();

        $set = 0;
        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];

        $data_lama = [];
        $data_baru = [];

        foreach ((array) json_decode($qt_lama->data_pendukung_sampling) as $value) {
            $value->status_sampling = $qt_lama->status_sampling;
            $data_lama['non_kontrak'][] = $value;
        }

        foreach ((array) json_decode($qt_baru->data_pendukung_sampling) as $value) {
            $value->status_sampling = $qt_baru->status_sampling;
            $data_baru['non_kontrak'][] = $value;
        }

        $different = array_map('json_decode', array_diff(array_map('json_encode', $data_baru), array_map('json_encode', $data_lama)));
        foreach ($different as $s => $fn) {
            $array_a = json_decode(json_encode($data_lama[$s]), true);
            $array_b = json_decode(json_encode($fn), true);

            $different_kanan = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_b), array_map('json_encode', $array_a))));
            $different_kiri = array_values(array_map('json_decode', array_diff(array_map('json_encode', $array_a), array_map('json_encode', $array_b))));
            if ($different_kanan != null) {
                foreach ($different_kanan as $z => $detail_baru) {
                    if (count($array_a) > 0) {
                        $set_num = [];
                        foreach ($array_a as $_x => $ss) {
                            $detail_lama = (object) $ss;

                            if (
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
                                    $pengurangan_data['non_kontrak'][] = $detail_baru;
                                } else if ((int) $detail_lama->jumlah_titik < (int) $detail_baru->jumlah_titik) {
                                    /**
                                     * penambahan titik
                                     */
                                    $selisih = abs($detail_baru->jumlah_titik - $detail_lama->jumlah_titik);
                                    $detail_baru->jumlah_titik = $selisih;
                                    $penambahan_data['non_kontrak'][] = $detail_baru;
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

                                $perubahan_data['non_kontrak'][] = $array_perubahan;

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
                                $detail_lama->parameter == $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan sub parameter 
                                 * &&
                                 * $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
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
                                $detail_lama->parameter != $detail_baru->parameter &&
                                (is_array($detail_lama->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_lama->regulasi) : []) == (is_array($detail_baru->regulasi) ? array_map(fn($item) => explode('-', $item)[0], $detail_baru->regulasi) : [])
                                && $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
                            ) {
                                /**
                                 * data ditemukan dengan adanya perubahan sub kategori 
                                 * &&
                                 * $detail_lama->penamaan_titik == $detail_baru->penamaan_titik
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
                                break;
                            } else {
                                /**
                                 * data tidak di temukan yang menandakan penambahan kategori 
                                 */
                                if ($_x == (count($array_a) - 1)) {
                                    $penambahan_data['non_kontrak'][] = $detail_baru;
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
                foreach ($different_kiri as $z => $detail_baru) {
                    $pengurangan_data['non_kontrak'][] = $detail_baru;
                }
            }
        }

        // dd($penambahan_data, $pengurangan_data, $perubahan_data);

        $cek_no_header = TemplateOrderDetail::where('id_order_header', $id_order)->first();
        // dd($cek_no_header);
        if ($cek_no_header != null) {
            $no_order = $cek_no_header->no_order;
            $n = 0;
            $no_urut_cfr = 0;

            if ($perubahan_data != null) {
                foreach ($perubahan_data as $key => $value) {
                    $periode = $key;
                    foreach ($value as $k => $v) {
                        $data_qt_lama = $v['before'];
                        $data_qt_baru = $v['after'];
                        $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                            ->where('kategori_2', $data_qt_lama->kategori_1)
                            ->where('kategori_3', $data_qt_lama->kategori_2)
                            // ->where('parameter', json_encode($data_qt_lama->parameter))
                            ->where('regulasi', json_encode($data_qt_lama->regulasi))
                            // ->where('regulasi', str_replace('\/', '/', json_encode($data_qt_lama->regulasi)))
                            ->where('is_active', 1)
                            ->orderBy('no_sampel', 'DESC')
                            ->get()
                            ->filter(function ($item) use ($data_qt_lama, $key) {
                                return collect(json_decode($item->parameter))->sort()->values()->all() == collect($data_qt_lama->parameter)->sort()->values()->all();
                            });

                        $data_titik_lama = $cek_order_detail_lama->take($data_qt_lama->jumlah_titik);
                        foreach ($data_titik_lama as $kk => $vv) {
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
                    ->where('is_active', 1)
                    ->orderBy('no_sampel', 'DESC')
                    ->first();
                // dd($cek_detail, $id_order);
                $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
                $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
                $n = $no_urut_sample + 1;
                $trigger = 0;
                $kategori = '';
                foreach ($penambahan_data as $key => $values) {
                    foreach ($values as $keys => $value) {
                        for ($f = 0; $f < $value->jumlah_titik; $f++) {
                            // =================================================================
                            $no_sampel = $no_order . '/' . sprintf("%03d", $n);
                            // =================================================================
                            $dataD = new TemplateOrderDetail;
                            $dataD->id_order_header = $id_order;
                            $dataD->no_order = $no_order;
                            $dataD->no_sampel = $no_sampel;
                            $dataD->kategori_1 = $value->status_sampling;
                            $dataD->kategori_2 = $value->kategori_1;
                            $dataD->kategori_3 = $value->kategori_2;
                            $dataD->parameter = json_encode($value->parameter);
                            $dataD->regulasi = json_encode($value->regulasi);
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
                        $cek_order_detail_lama = TemplateOrderDetail::where('id_order_header', $id_order)
                            ->whereNotIn('no_sampel', $added_no_sampel)
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

                        if ($cek_order_detail_lama->isNotEmpty()) {
                            foreach ($cek_order_detail_lama->take($values->jumlah_titik) as $hh => $change) {
                                // dd($values->jumlah_titik);
                                $change->is_active = 0;
                                $change->save();
                            }
                        }
                    }
                }
            }

            $getData = TemplateOrderDetail::where('id_order_header', $id_order)
                ->where('is_active', 1)
                ->orderBy('no_sampel', 'ASC')
                ->get();

            $array = [];
            $sum_par = [];
            foreach ($getData as $key => $value) {
                array_push($array, \explode('-', $value->kategori_3)[1] . ' - ' . \explode('/', $value->no_sampel)[1]);
                array_push($sum_par, count(json_decode($value->parameter)) . 'p');
            }
            $result['non_kontrak']['kategori'] = $array;
            $result['non_kontrak']['total_param'] = $sum_par;
            TemplateOrderDetail::where('id_order_header', $id_order)->delete();
            return $result;
        }
    }

    public function new(array $data = [])
    {
        $cek = QuotationNonKontrak::where('no_document', $data['no_qt_baru'])->first();

        $n = 1;
        foreach (json_decode($cek->data_pendukung_sampling) as $key => $value) {
            // =================================================================
            for ($f = 0; $f < $value->jumlah_titik; $f++) {
                // =================================================================
                $result['non_kontrak']['kategori'][] = explode('-', $value->kategori_2)[1] . ' - ' . sprintf("%03d", $n);
                $result['non_kontrak']['total_param'][] = count($value->parameter) . 'p';
                // =================================================================
                $n++;
            }
        }

        return $result ?? [];
    }

    protected function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }
}