<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QuotationPromo
{
    public static function finalizeRequestDraft($model, $promo, array $rows, $date, $period = null)
    {
        if (!$promo) {
            self::validateRows(null, $rows);
            return;
        }
        [$rows] = self::requestRows($promo, $rows, $date);
        $fields = [1 => 'harga_air', 4 => 'harga_udara', 5 => 'harga_emisi', 6 => 'harga_padatan', 7 => 'harga_swab_test', 8 => 'harga_tanah', 9 => 'harga_pangan'];
        foreach ($fields as $field) $model->$field = 0;
        $total = 0;
        foreach ($rows as $row) {
            $field = $fields[explode('-', $row->kategori_1)[0]] ?? null;
            if (!$field) throw new \InvalidArgumentException('Kategori pengujian tidak dikenali.');
            $model->$field += $row->harga_total;
            $total += $row->harga_total;
        }
        $preparation = (float) ($model->total_biaya_preparasi ?? 0);
        $model->grand_total = $total + $preparation;
        $net = $model->grand_total; $outside = 0; $discount = 0;
        self::percentage($promo, $model, $net, $outside, $discount, ['preparasi' => $preparation], []);
        $model->promo_id = $promo->id;
        $model->total_dpp = $net;
        $model->total_discount = $discount;
        $model->piutang = $net;
        $model->biaya_akhir = $net;
        $model->data_pendukung_sampling = json_encode($period === null ? $rows : [['periode_kontrak' => $period, 'data_sampling' => $rows]]);
    }

    // Request QR has analysis prices only; discounts on transport/perdiem are applied in quotation.
    public static function requestRows($promo, array $rows, $date, $contract = false, $priceResolver = null)
    {
        $periods = $contract ? array_values(array_unique(array_merge(...array_map(fn($r) => (array) ($r->periode ?? []), $rows)))) : [''];
        if ($contract && !$periods) throw new \InvalidArgumentException('Periode pengujian wajib diisi.');
        $result = [];
        $total = 0;
        foreach ($periods as $period) {
            $selected = array_values(array_filter($rows, fn($r) => !$contract || in_array($period, (array) ($r->periode ?? []), true)));
            self::validateRows($promo, $selected);
            $used = false;
            $subtotal = 0;
            foreach ($selected as $source) {
                $row = clone $source;
                $prices = $priceResolver ? $priceResolver($row, $date) : self::parameterPrices($row, $date)[0];
                $unit = !empty($row->is_paket_analisa) ? (float) $row->harga_satuan : array_sum($prices);
                $normal = !empty($row->is_paket_analisa) ? (float) $row->harga_total : null;
                [$row->harga_satuan, $row->harga_total] = self::rowPrice($promo, $row, $unit, $prices, $used, $normal);
                if ($contract) $row->periode = [$period];
                $key = spl_object_id($source) . ':' . $row->harga_satuan . ':' . $row->harga_total;
                if ($contract && isset($result[$key])) {
                    $result[$key]->periode[] = $period;
                } else {
                    $result[$key] = $row;
                }
                $subtotal += $row->harga_total;
            }
            $outside = 0;
            $discount = 0;
            self::percentage($promo, new \stdClass(), $subtotal, $outside, $discount, [], []);
            $total += $subtotal;
        }
        return [array_values($result), round($total, 2)];
    }

    public static function parameterPrices($row, $date)
    {
        $prices = [];
        $volumes = [];
        foreach ($row->parameter as $parameter) {
            $price = \App\Models\HargaParameter::where('id_kategori', explode('-', $row->kategori_1)[0])
                ->where('id_parameter', explode(';', $parameter)[0])->where('tanggal_berlaku', '<=', $date)
                ->where('is_active', 1)->orderBy('tanggal_berlaku', 'desc')->first();
            if (!$price && empty($row->is_promo)) throw new \InvalidArgumentException('Harga parameter belum tersedia untuk tanggal penawaran. Periksa master harga.');
            $prices[] = (float) ($price->harga ?? 0);
            $volumes[] = (float) ($price->volume ?? 0);
        }
        return [$prices, $volumes];
    }

    public static function syncHeader($header)
    {
        if (!$header->promo_id) return;
        $details = \App\Models\QuotationKontrakD::where('id_request_quotation_kontrak_h', $header->id)->get();
        $rows = [];
        foreach ($details as $detail) {
            foreach (json_decode($detail->data_pendukung_sampling, true) ?: [] as $period) {
                if (($period['periode_kontrak'] ?? null) != $detail->periode_kontrak) continue;
                foreach ($period['data_sampling'] ?? [] as $row) {
                    $row['periode'] = [$detail->periode_kontrak];
                    $rows[] = $row;
                }
            }
        }
        $header->data_pendukung_sampling = json_encode($rows, JSON_UNESCAPED_UNICODE);
        foreach (['harga_air', 'harga_udara', 'harga_emisi', 'harga_padatan', 'harga_swab_test', 'harga_tanah', 'harga_pangan', 'grand_total', 'total_dpp', 'total_discount', 'total_discount_promo', 'total_ppn', 'total_pph', 'piutang', 'biaya_akhir'] as $field) {
            $header->$field = $details->sum($field);
        }
        $first = $details->first();
        $header->kode_promo = $first->kode_promo ?? null;
        $header->discount_promo = $first->discount_promo ?? null;
        $header->save();
    }

    public static function percentage($promo, $model, &$dpp, &$outsideNet, &$discountTotal, array $costs, $outside)
    {
        if (!$promo) return;
        $model->kode_promo = $promo->kode_promo;
        if ($promo->metode !== 'persentase') return;
        $rate = (float) ($promo->konfigurasi['persentase'] ?? 0);
        $basis = $promo->konfigurasi['dasar_diskon'] ?? '';
        if ($rate <= 0 || $rate > 100 || !in_array($basis, ['analisa', 'transport', 'perdiem', 'global'], true)) {
            throw new \InvalidArgumentException('Konfigurasi persentase promo tidak valid.');
        }
        $outside = (array) $outside;
        $isOutside = fn($key) => filter_var($outside[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        $analysis = $dpp - ($costs['preparasi'] ?? 0);
        foreach (['transportasi', 'perdiem', 'perdiem24jam', 'biayalain'] as $key) {
            if (!$isOutside($key)) $analysis -= $costs[$key] ?? 0;
        }
        $buckets = ['analisa' => max(0, $analysis), 'transportasi' => $costs['transportasi'] ?? 0,
            'perdiem' => $costs['perdiem'] ?? 0, 'perdiem24jam' => $costs['perdiem24jam'] ?? 0];
        $included = ['analisa' => ['analisa'], 'transport' => ['transportasi'], 'perdiem' => ['perdiem', 'perdiem24jam'], 'global' => array_keys($buckets)];
        $discount = 0;
        foreach ($included[$basis] as $key) {
            $amount = round(max(0, $buckets[$key]) * $rate / 100, 2);
            if ($key !== 'analisa' && $isOutside($key)) $outsideNet -= $amount;
            else $dpp -= $amount;
            $discount += $amount;
        }
        if ($dpp < -0.01 || $outsideNet < -0.01) throw new \InvalidArgumentException('Diskon melebihi biaya quotation. Periksa diskon manual.');
        $dpp = round(max(0, $dpp), 2);
        $outsideNet = round(max(0, $outsideNet), 2);
        $discountTotal += $discount;
        $model->total_discount_promo = $discount;
        $model->discount_promo = json_encode(['deskripsi_promo_discount' => $promo->nama_diskon, 'jumlah_promo_discount' => $rate, 'dasar_diskon' => $basis]);
    }

    public static function resolve($id)
    {
        if (!$id) return null;
        $promo = DB::table('promo_pengujian')->where('id', $id)->first();
        if (!$promo || $promo->status !== 'aktif') {
            throw new \InvalidArgumentException('Promo tidak ditemukan atau sudah tidak aktif. Pilih ulang promo.');
        }
        $promo->konfigurasi = json_decode($promo->konfigurasi, true) ?: [];
        if ($promo->metode === 'persentase') {
            $rate = $promo->konfigurasi['persentase'] ?? 0;
            if (!is_numeric($rate) || $rate <= 0 || $rate > 100 || !in_array($promo->konfigurasi['dasar_diskon'] ?? '', ['analisa', 'transport', 'perdiem', 'global'], true)) {
                throw new \InvalidArgumentException('Konfigurasi diskon promo tidak valid.');
            }
        } elseif ($promo->metode === 'free_parameter') {
            if ((int) ($promo->konfigurasi['jumlah_titik'] ?? 0) < 1 || empty($promo->konfigurasi['parameter_ids']) || empty($promo->konfigurasi['kategori_id'])) {
                throw new \InvalidArgumentException('Konfigurasi Free Parameter tidak valid.');
            }
        } elseif (!in_array($promo->metode, ['paket_pengujian', 'pengujian_free_pengujian', 'labeling'], true)) {
            throw new \InvalidArgumentException('Jenis promo tidak didukung.');
        }
        return $promo;
    }

    private static function signature($row)
    {
        $row = (array) $row;
        $parameters = array_map(fn($p) => explode(';', $p)[0], $row['parameter'] ?? []);
        $regulations = array_map(fn($r) => trim(explode('-', $r)[0]), array_filter($row['regulasi'] ?? []));
        sort($parameters);
        sort($regulations);
        return json_encode([trim(explode('-', $row['kategori_1'] ?? '')[0]), trim(explode('-', $row['kategori_2'] ?? '')[0]), $parameters, array_values($regulations)]);
    }

    public static function validateRows($promo, $rows)
    {
        $marked = array_values(array_filter($rows, fn($row) => !empty($row->is_promo)));
        $isPackage = $promo && in_array($promo->metode, ['paket_pengujian', 'pengujian_free_pengujian'], true);
        if (!$isPackage) {
            if ($marked) throw new \InvalidArgumentException('Pengujian promo tidak sesuai dengan promo yang dipilih.');
            return;
        }
        $templates = $promo->konfigurasi['data_pendukung_sampling'] ?? $promo->konfigurasi;
        $minimum = $promo->metode === 'pengujian_free_pengujian' ? 2 : 1;
        if (count($marked) !== count($templates) || count($templates) < $minimum) {
            throw new \InvalidArgumentException('Seluruh pengujian dalam paket promo wajib digunakan bersama.');
        }
        $multiple = null;
        foreach ($marked as $row) {
            $match = null;
            foreach ($templates as $key => $template) {
                if (self::signature($row) === self::signature($template)) { $match = $key; break; }
            }
            if ($match === null) throw new \InvalidArgumentException('Kategori, regulasi, atau parameter paket promo berubah. Pilih ulang promo.');
            $base = (int) ($templates[$match]['jumlah_titik'] ?? 0);
            $count = (int) ($row->jumlah_titik ?? 0);
            if (!is_numeric($templates[$match]['harga_paket'] ?? null) || $templates[$match]['harga_paket'] < 0 || !ctype_digit((string) ($row->jumlah_titik ?? ''))) {
                throw new \InvalidArgumentException('Harga paket atau jumlah titik promo tidak valid.');
            }
            if ($base < 1 || $count < $base || $count % $base !== 0 || ($multiple !== null && $multiple !== intdiv($count, $base))) {
                throw new \InvalidArgumentException('Jumlah titik seluruh pengujian promo harus mengikuti kelipatan paket yang sama.');
            }
            $multiple = intdiv($count, $base);
            unset($templates[$match]);
        }
    }

    public static function rowPrice($promo, $row, $normalUnit, array $parameterPrices, &$freeUsed, $normalTotal = null, $packageUnit = false)
    {
        $quantity = (int) $row->jumlah_titik;
        $total = $normalTotal ?? ($normalUnit * $quantity);
        if (!$promo) return [$normalUnit, $total];
        if (!empty($row->is_promo)) {
            if (!is_array($freeUsed)) $freeUsed = [];
            foreach (($promo->konfigurasi['data_pendukung_sampling'] ?? $promo->konfigurasi) as $key => $template) {
                if (isset($freeUsed[$key])) continue;
                if (self::signature($row) === self::signature($template)) {
                    $base = (int) $template['jumlah_titik'];
                    if ($base < 1 || $quantity % $base) throw new \InvalidArgumentException('Kelipatan promo tidak valid.');
                    $total = (float) $template['harga_paket'] * intdiv($quantity, $base);
                    $freeUsed[$key] = true;
                    return [$packageUnit ? (float) $template['harga_paket'] : $total / max(1, $quantity), $total];
                }
            }
            throw new \InvalidArgumentException('Konfigurasi paket promo tidak cocok.');
        }
        if ($promo->metode === 'free_parameter' && !$freeUsed) {
            $config = $promo->konfigurasi;
            $required = (int) ($config['jumlah_titik'] ?? 0);
            if ($required > 0 && $quantity >= $required && explode('-', $row->kategori_1)[0] == ($config['kategori_id'] ?? null)) {
                $freeIds = array_map('strval', $config['parameter_ids'] ?? []);
                $discount = 0;
                foreach ($row->parameter as $index => $parameter) {
                    if (in_array(explode(';', $parameter)[0], $freeIds, true)) {
                        $discount += (float) ($parameterPrices[$index] ?? 0) * $required;
                    }
                }
                if ($discount > 0) {
                    $freeUsed = true;
                    $total = max(0, $total - $discount);
                }
            }
        }
        return [$normalUnit, round($total, 2)];
    }
}
