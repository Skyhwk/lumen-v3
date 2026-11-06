<?php

namespace App\Services\QuotationGenerate;

use App\Helpers\ParameterLabelConfigHelper;
use App\Models\HargaParameter;
use App\Models\QuotationNonKontrak;
use App\Models\TemplatePaketAnalisa;
use Carbon\Carbon;

class QuotationSamplingPriceRecalculator
{
    private const CATEGORY_FIELDS = [
        '1' => 'harga_air',
        '4' => 'harga_udara',
        '5' => 'harga_emisi',
        '6' => 'harga_padatan',
        '7' => 'harga_swab_test',
        '8' => 'harga_tanah',
        '9' => 'harga_pangan',
    ];

    public function refresh(QuotationNonKontrak $quotation): QuotationNonKontrak
    {
        $rows = json_decode((string) $quotation->data_pendukung_sampling, true);
        if (!is_array($rows) || $rows === []) {
            return $quotation;
        }

        $asOf = $quotation->tanggal_penawaran
            ? Carbon::parse($quotation->tanggal_penawaran)->toDateString()
            : Carbon::today()->toDateString();

        $totals = array_fill_keys(array_values(self::CATEGORY_FIELDS), 0.0);
        $refreshed = [];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $priced = $this->priceRow($row, $asOf);
            $field = $this->categoryField($priced['kategori_1'] ?? '');
            if ($field !== null) {
                $totals[$field] += (float) $priced['harga_total'];
            }
            $refreshed[] = $priced;
        }

        $quotation->data_pendukung_sampling = json_encode(array_values($refreshed), JSON_UNESCAPED_UNICODE);
        foreach ($totals as $field => $amount) {
            $quotation->{$field} = $amount;
        }

        $this->rebuildFinancials($quotation, $totals);

        return $quotation;
    }

    private function priceRow(array $row, string $asOf): array
    {
        $parameters = array_values((array) ($row['parameter'] ?? []));
        $titik = (int) ($row['jumlah_titik'] ?? 0);
        $kategori = explode('-', (string) ($row['kategori_1'] ?? ''))[0] ?? '';
        $isPaket = !empty($row['is_paket_analisa']) || !empty($row['is_paket']);

        [$hargaDb, $volumeDb] = $this->lookupParameterPrices($parameters, $kategori, $asOf);
        $satuan = array_sum($hargaDb);
        $total = $satuan * $titik;
        $volume = array_sum($volumeDb);

        if ($isPaket) {
            [$paketSatuan, $paketTotal, $kelipatan] = $this->lookupPaketPrice($row, $titik);
            if ($paketSatuan !== null) {
                $satuan = $paketSatuan;
                $total = $paketTotal;
                $row['kelipatan_dasar'] = $kelipatan;
                $row['kelipatan_titik'] = $kelipatan;
                $row['is_paket_analisa'] = true;
            }
        }

        $row['parameter'] = $parameters;
        $row['jumlah_titik'] = $titik;
        $row['total_parameter'] = count($parameters);
        $row['harga_satuan'] = $satuan;
        $row['harga_total'] = $total;
        $row['volume'] = $volume;

        return $row;
    }

    /**
     * @return array{0:array<int,float>,1:array<int,float>}
     */
    private function lookupParameterPrices(array $parameters, string $kategori, string $asOf): array
    {
        $harga = [];
        $volume = [];

        foreach ($parameters as $parameter) {
            $idParam = explode(';', (string) $parameter)[0];
            $master = HargaParameter::where('id_kategori', $kategori)
                ->where('id_parameter', $idParam)
                ->where('tanggal_berlaku', '<=', $asOf)
                ->where('is_active', 1)
                ->orderBy('tanggal_berlaku', 'desc')
                ->first();

            $harga[] = (float) ($master->harga ?? 0);
            $volume[] = ParameterLabelConfigHelper::resolveVolume($idParam, $kategori, $master->volume ?? 0);
        }

        return [$harga, $volume];
    }

    /**
     * @return array{0:?float,1:?float,2:int}
     */
    private function lookupPaketPrice(array $row, int $titik): array
    {
        $paketId = $row['paket_id'] ?? $row['is_paket'] ?? null;
        if ($paketId === null || $paketId === '' || $paketId === false) {
            return [null, null, 0];
        }

        $template = TemplatePaketAnalisa::where('id', $paketId)->first();
        if (!$template) {
            return [null, null, 0];
        }

        $items = json_decode((string) $template->data_pendukung_sampling, true) ?: [];
        foreach ($items as $paket) {
            if (
                ($paket['regulasi'] ?? null) == ($row['regulasi'] ?? null)
                && ($paket['parameter'] ?? null) == ($row['parameter'] ?? null)
                && ($paket['kategori_1'] ?? null) == ($row['kategori_1'] ?? null)
                && ($paket['kategori_2'] ?? null) == ($row['kategori_2'] ?? null)
            ) {
                $kelipatan = (int) ($paket['jumlah_titik'] ?? 0);
                $hargaPaket = (float) ($paket['harga_paket'] ?? 0);
                $pengali = $kelipatan > 0 ? ($titik / $kelipatan) : 0;

                return [$hargaPaket, $hargaPaket * $pengali, $kelipatan];
            }
        }

        return [null, null, 0];
    }

    private function categoryField(string $kategori1): ?string
    {
        $id = explode('-', $kategori1)[0] ?? '';

        return self::CATEGORY_FIELDS[$id] ?? null;
    }

    private function rebuildFinancials(QuotationNonKontrak $quotation, array $totals): void
    {
        $hargaAir = (float) $totals['harga_air'];
        $hargaUdara = (float) $totals['harga_udara'];
        $hargaEmisi = (float) $totals['harga_emisi'];
        $hargaPadatan = (float) $totals['harga_padatan'];
        $hargaSwab = (float) $totals['harga_swab_test'];
        $hargaTanah = (float) $totals['harga_tanah'];
        $hargaPangan = (float) $totals['harga_pangan'];

        $transport = (float) ($quotation->harga_transportasi_total ?? 0);
        $perdiem = (float) ($quotation->harga_perdiem_personil_total ?? 0);
        $jam = (float) ($quotation->harga_24jam_personil_total ?? 0);
        $biayaLain = (float) ($quotation->total_biaya_lain ?? 0);
        $preparasi = (float) ($quotation->total_biaya_preparasi ?? 0);

        $discAir = $this->percent($quotation->discount_air);
        $discNonAir = $this->percent($quotation->discount_non_air);
        $discUdara = $this->percent($quotation->discount_udara);
        $discEmisi = $this->percent($quotation->discount_emisi);
        $discTransport = $this->percent($quotation->discount_transport);
        $discPerdiem = $this->percent($quotation->discount_perdiem);
        $discJam = $this->percent($quotation->discount_perdiem_24jam);
        $discGabungan = $this->percent($quotation->discount_gabungan);
        $discGroup = $this->percent($quotation->discount_group);
        $discConsultant = $this->percent($quotation->discount_consultant);
        $discCashPct = $this->percent($quotation->cash_discount_persen);
        $cashNominal = (float) ($quotation->cash_discount ?? 0);
        $customNominal = $this->customDiscountTotal($quotation->custom_discount);

        $totalDiskon = 0.0;
        $hargaTotal = 0.0;
        $grandTotal = $hargaAir + $hargaUdara + $hargaEmisi + $hargaPadatan + $hargaSwab + $hargaTanah + $hargaPangan + $preparasi;

        $quotation->discount_promo = null;
        $quotation->total_discount_promo = 0;

        $quotation->total_discount_air = $hargaAir * $discAir / 100;
        $totalDiskon += $quotation->total_discount_air;
        $hargaTotal += $hargaAir - $quotation->total_discount_air;

        $nonAirBase = $hargaUdara + $hargaEmisi + $hargaPadatan + $hargaSwab + $hargaTanah;
        if ($discNonAir > 0) {
            $quotation->total_discount_non_air = $nonAirBase * $discNonAir / 100;
            $totalDiskon += $quotation->total_discount_non_air;
            $hargaTotal += $nonAirBase - $quotation->total_discount_non_air;

            $quotation->total_discount_udara = $discUdara > 0 ? ($hargaUdara * $discUdara / 100) : 0;
            $quotation->total_discount_emisi = $discEmisi > 0 ? ($hargaEmisi * $discEmisi / 100) : 0;
            $totalDiskon += $quotation->total_discount_udara + $quotation->total_discount_emisi;
            $hargaTotal -= $quotation->total_discount_udara + $quotation->total_discount_emisi;
        } else {
            $quotation->total_discount_non_air = 0;
            $hargaTotal += $hargaPadatan + $hargaSwab + $hargaTanah;
            if ($discUdara > 0 && $hargaUdara != 0) {
                $quotation->total_discount_udara = $hargaUdara * $discUdara / 100;
                $totalDiskon += $quotation->total_discount_udara;
                $hargaTotal += $hargaUdara - $quotation->total_discount_udara;
            } else {
                $quotation->total_discount_udara = 0;
                $hargaTotal += $hargaUdara;
            }
            if ($discEmisi > 0 && $hargaEmisi != 0) {
                $quotation->total_discount_emisi = $hargaEmisi * $discEmisi / 100;
                $totalDiskon += $quotation->total_discount_emisi;
                $hargaTotal += $hargaEmisi - $quotation->total_discount_emisi;
            } else {
                $quotation->total_discount_emisi = 0;
                $hargaTotal += $hargaEmisi;
            }
        }

        $hargaTotal += $hargaPangan + $preparasi;

        $quotation->total_discount_transport = $transport * $discTransport / 100;
        $quotation->total_discount_perdiem = $perdiem * $discPerdiem / 100;
        $quotation->total_discount_perdiem_24jam = $jam * $discJam / 100;
        $totalDiskon += $quotation->total_discount_transport + $quotation->total_discount_perdiem + $quotation->total_discount_perdiem_24jam;

        $transportNet = $transport - $quotation->total_discount_transport;
        $perdiemNet = $perdiem - $quotation->total_discount_perdiem;
        $jamNet = $jam - $quotation->total_discount_perdiem_24jam;
        $hargaTransport = $transportNet + $perdiemNet + $jamNet;

        if ($discGabungan > 0) {
            $quotation->total_discount_gabungan = ($hargaTotal + $hargaTransport) * $discGabungan / 100;
            $totalDiskon += $quotation->total_discount_gabungan;
            $hargaTotal -= $quotation->total_discount_gabungan;
        } else {
            $quotation->total_discount_gabungan = 0;
        }

        if ($discGroup > 0) {
            $quotation->total_discount_group = $hargaTotal * $discGroup / 100;
            $totalDiskon += $quotation->total_discount_group;
            $hargaTotal -= $quotation->total_discount_group;
        } else {
            $quotation->total_discount_group = 0;
        }

        if ($discConsultant > 0) {
            $quotation->total_discount_consultant = $hargaTotal * $discConsultant / 100;
            $totalDiskon += $quotation->total_discount_consultant;
            $hargaTotal -= $quotation->total_discount_consultant;
        } else {
            $quotation->total_discount_consultant = 0;
        }

        if ($discCashPct > 0) {
            $quotation->total_cash_discount_persen = $hargaTotal * $discCashPct / 100;
            $totalDiskon += $quotation->total_cash_discount_persen;
            $hargaTotal -= $quotation->total_cash_discount_persen;
        } else {
            $quotation->total_cash_discount_persen = 0;
        }

        if ($cashNominal > 0) {
            $hargaTotal -= $cashNominal;
        }
        if ($customNominal > 0) {
            $hargaTotal -= $customNominal;
            $totalDiskon += $customNominal;
        }

        $diluar = json_decode((string) $quotation->diluar_pajak);
        $biayaAkhir = 0.0;
        $biayaDiluar = 0.0;

        if (!$this->flagOn($diluar, 'transportasi')) {
            $grandTotal += $transport;
            $hargaTotal += $transportNet;
        } else {
            $biayaAkhir += $transportNet;
            $biayaDiluar += $transport;
        }
        if (!$this->flagOn($diluar, 'perdiem')) {
            $grandTotal += $perdiem;
            $hargaTotal += $perdiemNet;
        } else {
            $biayaAkhir += $perdiemNet;
            $biayaDiluar += $perdiem;
        }
        if (!$this->flagOn($diluar, 'perdiem24jam')) {
            $grandTotal += $jam;
            $hargaTotal += $jamNet;
        } else {
            $biayaAkhir += $jamNet;
            $biayaDiluar += $jam;
        }
        if (!$this->flagOn($diluar, 'biayalain')) {
            $grandTotal += $biayaLain;
            $hargaTotal += $biayaLain;
        } else {
            $biayaAkhir += $biayaLain;
            $biayaDiluar += $biayaLain;
        }

        $diluarBody = json_decode((string) $quotation->biaya_di_luar_pajak, true);
        foreach (($diluarBody['body'] ?? []) as $item) {
            $amount = (float) ($item['harga'] ?? $item->harga ?? 0);
            $biayaDiluar += $amount;
            $biayaAkhir += $amount;
        }

        $quotation->grand_total = $grandTotal;
        $quotation->total_dpp = $hargaTotal;
        $quotation->total_discount = $totalDiskon;
        $quotation->total_biaya_di_luar_pajak = $biayaDiluar;

        $ppnPct = $this->percent($quotation->ppn);
        $pphPct = $this->percent($quotation->pph);
        $quotation->total_ppn = $ppnPct > 0 ? ($hargaTotal * $ppnPct / 100) : 0;
        $quotation->total_pph = ($ppnPct > 0 && $pphPct > 0) ? ($hargaTotal * $pphPct / 100) : 0;

        $piutang = $hargaTotal + (float) $quotation->total_ppn - (float) $quotation->total_pph;
        $quotation->piutang = $piutang;
        $quotation->biaya_akhir = $biayaAkhir + $piutang;
    }

    private function percent($value): float
    {
        return (float) str_replace('%', '', (string) ($value ?? 0));
    }

    private function customDiscountTotal($raw): float
    {
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($items)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $item = (array) $item;
            $total += (float) ($item['discount'] ?? 0);
        }

        return $total;
    }

    private function flagOn($diluar, string $key): bool
    {
        if (!is_object($diluar) && !is_array($diluar)) {
            return false;
        }

        $value = is_array($diluar) ? ($diluar[$key] ?? null) : ($diluar->{$key} ?? null);

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
