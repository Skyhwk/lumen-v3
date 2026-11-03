<?php

namespace App\Services;

class QuotationPromoPresentation
{
    public static function sukaSukaLine($source)
    {
        $discount = json_decode($source->discount_promo ?? '{}');
        if (is_object($discount) && ($discount->tipe ?? '') === 'suka_suka') {
            $nama = trim((string) ($discount->deskripsi_promo_discount ?? ''));
            $nominal = (float) ($discount->jumlah_promo_discount ?? 0);
            if ($nama !== '' && $nominal > 0) {
                return ['nama' => $nama, 'nominal' => $nominal];
            }
        }
        if (empty($source->promo_id)) {
            return null;
        }
        $promo = \Illuminate\Support\Facades\DB::table('promo_pengujian')->where('id', $source->promo_id)->first();
        if (!$promo || $promo->metode !== 'suka_suka') {
            return null;
        }
        $config = json_decode($promo->konfigurasi, true) ?: [];
        $nominal = (float) ($config['nominal'] ?? 0);
        $nama = trim((string) ($promo->nama_diskon ?? ''));
        if ($nama === '' || $nominal <= 0) {
            return null;
        }

        return ['nama' => $nama, 'nominal' => $nominal];
    }

    public static function writeSukaSukaItemRow($pdf, $source, &$i, callable $rupiah, $fontSize = '13px', $nextNumberAlready = true)
    {
        $line = self::sukaSukaLine($source);
        if (!$line) {
            return false;
        }
        if (!$nextNumberAlready) {
            $i++;
        }
        $pdf->WriteHTML(
            ' <tr>
                <td style="vertical-align: middle; text-align:center;font-size: ' . $fontSize . ';">' . $i . '</td>
                <td style="font-size: ' . $fontSize . ';padding: 5px;">' . htmlspecialchars($line['nama'], ENT_QUOTES, 'UTF-8') . '</td>
                <td style="font-size: ' . $fontSize . '; text-align:center;"></td>
                <td style="font-size: ' . $fontSize . '; text-align:right; padding: 5px;">' . $rupiah($line['nominal']) . '</td>
                <td style="font-size: ' . $fontSize . '; text-align:right; padding: 5px;">' . $rupiah($line['nominal']) . '</td>
            </tr>'
        );
        if ($nextNumberAlready) {
            $i++;
        }

        return true;
    }

    public static function sukaSukaInvoiceLine($source)
    {
        $line = self::sukaSukaLine($source);
        if (!$line) {
            return null;
        }
        $line['nominal'] *= self::sukaSukaPeriodMultiplier($source);

        return $line;
    }

    public static function addSukaSukaAmount($source)
    {
        $line = self::sukaSukaInvoiceLine($source);

        return $line ? $line['nominal'] : 0;
    }

    public static function writeSukaSukaInvoiceRow($pdf, $source)
    {
        $line = self::sukaSukaInvoiceLine($source);
        if (!$line) {
            return false;
        }
        $nama = htmlspecialchars($line['nama'], ENT_QUOTES, 'UTF-8');
        $nominal = 'Rp ' . number_format($line['nominal'], 0, '.', ',');
        $pdf->writeHTML(
            '<tr>
                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $nama . '</td>
                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $nominal . '</td>
                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $nominal . '</td>
            </tr>'
        );

        return true;
    }

    public static function sukaSukaPeriodMultiplier($source)
    {
        if (($source->periode ?? '') !== 'all') {
            return 1;
        }
        $rows = json_decode($source->data_pendukung_sampling ?? '[]');
        if (!is_array($rows) || !$rows) {
            return 1;
        }
        $first = reset($rows);
        if (is_object($first) && isset($first->periode_kontrak)) {
            return max(1, count($rows));
        }
        $periods = [];
        foreach ($rows as $row) {
            foreach ((array) ($row->periode ?? []) as $period) {
                $periods[(string) $period] = true;
            }
        }

        return max(1, count($periods) ?: 1);
    }

    public static function forContractPdf($source, $details, $promo = null)
    {
        if (empty($source->promo_id)) return [$source, $details];
        $promo = $promo ?? \Illuminate\Support\Facades\DB::table('promo_pengujian')->where('id', $source->promo_id)->first();
        if (!$promo || in_array($promo->metode, ['labeling', 'parameter_khusus', 'harga_parameter', 'suka_suka'], true)) return [$source, $details];
        $header = clone $source;
        $views = [];
        $headerRows = [];
        $uplift = 0;
        $discount = 0;
        $label = null;
        foreach ($details as $detail) {
            $periods = json_decode($detail->data_pendukung_sampling, true);
            if (!is_array($periods)) throw new \InvalidArgumentException('Data periode quotation tidak valid.');
            $rows = [];
            foreach ($periods as $period) {
                if ((string) ($period['periode_kontrak'] ?? '') === (string) $detail->periode_kontrak) {
                    $rows = array_merge($rows, $period['data_sampling'] ?? []);
                }
            }
            $flat = clone $detail;
            $flat->promo_id = $source->promo_id;
            $flat->data_pendukung_sampling = json_encode($rows);
            // Each detail is independently presented; never reapply a percentage to the header.
            $view = self::forPdf($flat, $promo);
            $displayRows = json_decode($view->data_pendukung_sampling, true);
            foreach ($displayRows as $row) {
                $row['periode'] = [$detail->periode_kontrak];
                $row['penamaan_titik'] = QuotationPromo::headerPointNames($row['penamaan_titik'] ?? [], $row['jumlah_titik'] ?? 0);
                $row['_promo_display_total'] = $row['harga_total'];
                $headerRows[] = $row;
            }
            $view->data_pendukung_sampling = json_encode([[
                'periode_kontrak' => $detail->periode_kontrak, 'data_sampling' => $displayRows,
            ]]);
            $uplift += (float) $view->grand_total - (float) $detail->grand_total;
            $discount += (float) ($view->total_discount_promo ?? 0);
            if ((float) ($view->total_discount_promo ?? 0) > 0) $label = $view->discount_promo;
            $views[] = $view;
        }
        if (!$views) return [$source, $details];
        $header->data_pendukung_sampling = json_encode($headerRows);
        $header->grand_total = (float) $source->grand_total + $uplift;
        $header->total_discount_promo = $discount;
        $header->discount_promo = $label;
        return [$header, collect($views)];
    }

    public static function isSukaSukaDiscount($discountPromo)
    {
        $discount = is_string($discountPromo) || $discountPromo === null
            ? json_decode($discountPromo ?? '{}')
            : $discountPromo;
        return is_object($discount) && ($discount->tipe ?? '') === 'suka_suka';
    }

    public static function discountLabel($model)
    {
        if (self::isSukaSukaDiscount($model->discount_promo ?? null)) {
            return '';
        }
        $discount = json_decode($model->discount_promo ?? '{}');
        $rate = $discount->jumlah_promo_discount ?? '';
        return ($discount->deskripsi_promo_discount ?? '') . ($rate !== '' && $rate !== null ? ' ' . $rate . '%' : '');
    }

    public static function contractDiscountRow($pdf, $model, $details = null)
    {
        if (self::isSukaSukaDiscount($model->discount_promo ?? null)) return;
        if ((float) ($model->total_discount_promo ?? 0) <= 0) return;
        $html = '<tr><td style="text-align:center;padding:5px;">' . self::discountLabel($model) . '</td>';
        if ($details !== null) {
            foreach ($details as $detail) {
                $html .= '<td style="text-align:right;padding:5px;">Rp ' . number_format((float) ($detail->total_discount_promo ?? 0), 0, '.', ',') . '</td>';
            }
        }
        $html .= '<td style="text-align:right;padding:5px;">Rp ' . number_format((float) $model->total_discount_promo, 0, '.', ',') . '</td></tr>';
        $pdf->WriteHTML($html);
    }

    // Presentation only: never save this clone or recalculate payable amounts.
    public static function forPdf($source, $promo = null)
    {
        if (empty($source->promo_id)) return $source;
        $promo = $promo ?? \Illuminate\Support\Facades\DB::table('promo_pengujian')->where('id', $source->promo_id)->first();
        if (!$promo) return $source;
        $label = json_encode([
            'deskripsi_promo_discount' => htmlspecialchars('disc. ' . $promo->nama_diskon, ENT_QUOTES, 'UTF-8'),
            'jumlah_promo_discount' => '',
        ]);
        if ($promo->metode === 'persentase') {
            if ((float) $source->total_discount_promo <= 0) return $source;
            $data = clone $source;
            $data->discount_promo = $label;
            return $data;
        }
        $package = in_array($promo->metode, ['paket_pengujian', 'pengujian_free_pengujian'], true);
        if ($package) {
            // Display one full package as the unit; keep persisted totals untouched.
            $data = clone $source;
            $config = $promo->konfigurasi ?? [];
            $config = is_string($config) ? json_decode($config, true) : $config;
            $rows = json_decode($source->data_pendukung_sampling);
            if (is_array($config) && $config && is_array($rows)) {
                $packagePromo = clone $promo;
                $packagePromo->konfigurasi = $config;
                $used = [];
                foreach ($rows as $row) {
                    if (empty($row->is_promo)) continue;
                    try {
                        [$unit] = QuotationPromo::rowPrice($packagePromo, $row, $row->harga_satuan, [], $used, $row->harga_total, true);
                        $row->harga_satuan = $unit;
                    } catch (\InvalidArgumentException $e) {
                        // Older quotations may no longer match the master; retain their saved price.
                    }
                }
                $data->data_pendukung_sampling = json_encode($rows);
            }
            $data->discount_promo = null;
            $data->total_discount_promo = 0;
            return $data;
        }
        if ($promo->metode === 'suka_suka') return $source;
        if ($promo->metode !== 'free_parameter') return $source;
        $rows = json_decode($source->data_pendukung_sampling);
        if (!is_array($rows)) return $source;
        $discount = 0;
        foreach ($rows as $row) {
            // Fixed-price analysis packages do not use unit * quantity pricing.
            if (!empty($row->is_paket_analisa) || !isset($row->harga_satuan, $row->jumlah_titik, $row->harga_total)) continue;
            $gross = round((float) $row->harga_satuan * (int) $row->jumlah_titik, 2);
            $difference = round($gross - (float) $row->harga_total, 2);
            if ($difference <= 0) continue;
            $row->harga_total = $gross;
            $discount += $difference;
        }
        $discount = round($discount, 2);
        if ($discount <= 0) return $source;
        $data = clone $source;
        $data->data_pendukung_sampling = json_encode($rows);
        $data->grand_total = (float) $source->grand_total + $discount;
        $data->total_discount_promo = $discount;
        $data->discount_promo = $discount > 0 ? $label : null;
        return $data;
    }
}
