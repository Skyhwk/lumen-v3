<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\OrderDetail;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\TemplateKategoriSkK3;
use App\Models\TicketSkK3;
use Illuminate\Support\Facades\Schema;

class TicketSkK3KategoriService
{
  /** Label jadwal: {@see TodoSamplingController} — "Nama Sub Kategori - 001" */
    public static function parseJadwalLabel(string $label): array
    {
        $label = trim(html_entity_decode($label, ENT_QUOTES, 'UTF-8'));
        if ($label === '') {
            return ['nama' => '', 'titik' => '', 'label' => ''];
        }

        if (preg_match('/^(.+)\s-\s(\d+)\s*$/u', $label, $match)) {
            return [
                'nama' => trim($match[1]),
                'titik' => trim($match[2]),
                'label' => $label,
            ];
        }

        return ['nama' => $label, 'titik' => '', 'label' => $label];
    }

    public static function formatKategoriLabel(string $kategori3): string
    {
        $value = trim($kategori3);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d+)\s*-\s*(.+)$/', $value, $match)) {
            return trim($match[2]) . ' - ' . trim($match[1]);
        }

        return $value;
    }

    public static function normalizeName(string $name): string
    {
        $value = strtolower(trim($name));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = str_replace(['&', '(', ')'], '', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function allowedSubKategoriNames(): array
    {
        $query = TemplateKategoriSkK3::query()
            ->where('template_kategori_sk_k3.is_active', true)
            ->join('master_sub_kategori as msk', 'msk.id', '=', 'template_kategori_sk_k3.id_sub_kategori');

        if (Schema::hasColumn('master_sub_kategori', 'is_active')) {
            $query->where('msk.is_active', 1);
        }

        $nameColumn = Schema::hasColumn('template_kategori_sk_k3', 'nama_sub_kategori')
            ? 'template_kategori_sk_k3.nama_sub_kategori'
            : 'msk.nama_sub_kategori';

        return $query->pluck($nameColumn)
            ->map(fn ($name) => self::normalizeName((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function isAllowedKategoriName(string $name, array $allowedNormalized): bool
    {
        $normalized = self::normalizeName($name);

        return $normalized !== '' && in_array($normalized, $allowedNormalized, true);
    }

    public static function collectLabelsFromSamplingPlan(?int $samplingPlanId): array
    {
        if (!$samplingPlanId) {
            return [];
        }

        $jadwal = Jadwal::where('id_sampling', $samplingPlanId)
            ->where('is_active', true)
            ->get(['kategori']);

        $labels = [];
        foreach ($jadwal as $row) {
            $raw = html_entity_decode((string) ($row->kategori ?? ''), ENT_QUOTES, 'UTF-8');
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : [$raw];

            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $labels[$item] = true;
                }
            }
        }

        $list = array_keys($labels);
        usort($list, function ($a, $b) {
            $pa = self::parseJadwalLabel($a);
            $pb = self::parseJadwalLabel($b);
            $nameCmp = strcasecmp($pa['nama'], $pb['nama']);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            return (int) $pa['titik'] <=> (int) $pb['titik'];
        });

        return $list;
    }

    public static function collectLabelsFromQuotation(string $noQuotation, ?string $tipe = null, ?string $periode = null): array
    {
        $labels = [];
        $tipe = $tipe ?: self::detectTipe($noQuotation);

        if ($tipe === 'kontrak') {
            $header = QuotationKontrakH::where('no_document', $noQuotation)->where('is_active', true)->first(['id']);
            if ($header) {
                $details = QuotationKontrakD::where('id_request_quotation_kontrak_h', $header->id)
                    ->when($periode, fn ($q) => $q->where('periode_kontrak', $periode))
                    ->get();

                foreach ($details as $detail) {
                    foreach (self::labelsFromSamplingBlocks($detail->data_pendukung_sampling ?? null) as $label) {
                        $labels[$label] = true;
                    }
                }
            }
        } else {
            $non = QuotationNonKontrak::where('no_document', $noQuotation)->where('is_active', true)->first(['data_pendukung_sampling']);
            foreach (self::labelsFromSamplingBlocks($non->data_pendukung_sampling ?? null) as $label) {
                $labels[$label] = true;
            }
        }

        if (count($labels) === 0) {
            $query = OrderDetail::where('no_quotation', $noQuotation)->where('is_active', true);
            if ($periode) {
                $query->where('periode', $periode);
            }
            foreach ($query->get(['kategori_3', 'no_sampel']) as $row) {
                $label = self::labelFromOrderDetail($row);
                if ($label !== '') {
                    $labels[$label] = true;
                }
            }
        }

        return array_keys($labels);
    }

    public static function buildSelectionPayload(
        string $noQuotation,
        ?string $tipe,
        ?string $periode,
        ?array $savedSelection = null,
        ?int $samplingPlanId = null
    ): array {
        $allowed = self::allowedSubKategoriNames();

        $sourceLabels = self::collectLabelsFromSamplingPlan($samplingPlanId);
        if (count($sourceLabels) === 0) {
            $sourceLabels = self::collectLabelsFromQuotation($noQuotation, $tipe, $periode);
        }

        $selectableOptions = [];

        foreach ($sourceLabels as $label) {
            $parsed = self::parseJadwalLabel($label);
            if ($parsed['nama'] === '') {
                continue;
            }
            if (!self::isAllowedKategoriName($parsed['nama'], $allowed)) {
                continue;
            }

            $selectableOptions[] = [
                'value' => $label,
                'label' => $label,
                'nama' => $parsed['nama'],
                'titik' => $parsed['titik'],
            ];
        }

        $selectableValues = array_column($selectableOptions, 'value');

        $savedManual = [];
        if (is_array($savedSelection)) {
            foreach ($savedSelection as $item) {
                $code = is_array($item) ? ($item['value'] ?? $item['label'] ?? $item['code'] ?? '') : (string) $item;
                $code = trim((string) $code);
                if ($code !== '' && in_array($code, $selectableValues, true)) {
                    $savedManual[] = $code;
                }
            }
            $savedManual = array_values(array_unique($savedManual));
        }

        return [
            'selectable_options' => $selectableOptions,
            'selected_manual' => $savedManual,
            'selected_all' => $savedManual,
        ];
    }

    public static function decodeSelection($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public static function encodeSelection(array $manualCodes): string
    {
        return json_encode(array_values(array_unique($manualCodes)), JSON_UNESCAPED_UNICODE);
    }

    public static function selectionForTicket(TicketSkK3 $ticket): array
    {
        $saved = self::decodeSelection($ticket->kategori_terpilih ?? null);

        $payload = self::buildSelectionPayload(
            (string) $ticket->no_quotation,
            (string) ($ticket->tipe_quotation ?? ''),
            $ticket->periode,
            $saved,
            $ticket->sampling_plan_id ? (int) $ticket->sampling_plan_id : null
        );

        $selectedLookup = array_flip($payload['selected_manual'] ?? []);
        $payload['kategori_list'] = array_map(function ($opt) use ($selectedLookup) {
            return [
                'label' => $opt['label'],
                'value' => $opt['value'],
                'selected' => isset($selectedLookup[$opt['value']]),
            ];
        }, $payload['selectable_options'] ?? []);

        $payload['kategori_labels'] = array_values(array_filter(array_map(
            fn ($item) => $item['selected'] ? $item['label'] : null,
            $payload['kategori_list']
        )));

        $payload['kategori_semua_labels'] = array_column($payload['kategori_list'], 'label');

        return $payload;
    }

    private static function labelFromOrderDetail($row): string
    {
        $name = self::kategori3Name($row->kategori_3 ?? '');
        if ($name === '') {
            return '';
        }

        $noSampel = trim((string) ($row->no_sampel ?? ''));
        if ($noSampel !== '' && str_contains($noSampel, '/')) {
            $parts = explode('/', $noSampel);
            $titik = trim(end($parts));

            return $titik !== '' ? $name . ' - ' . $titik : $name;
        }

        return $name;
    }

    private static function labelsFromSamplingBlocks($raw): array
    {
        $labels = [];
        foreach (self::normalizeSamplingBlocks($raw) as $block) {
            foreach ((array) ($block['data_sampling'] ?? [$block]) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = self::kategori3Name($item['kategori_3'] ?? $item['kategori_2'] ?? '');
                if ($name === '') {
                    continue;
                }

                $titikList = $item['penamaan_titik'] ?? [];
                if (!is_array($titikList)) {
                    $titikList = json_decode((string) $titikList, true) ?: [];
                }

                if (count($titikList) === 0) {
                    $labels[] = $name;
                    continue;
                }

                foreach ($titikList as $penamaanTitik) {
                    if (is_array($penamaanTitik)) {
                        $noSampel = trim((string) array_key_first($penamaanTitik));
                    } else {
                        $props = get_object_vars($penamaanTitik);
                        $noSampel = trim((string) key($props));
                    }
                    if ($noSampel === '') {
                        continue;
                    }
                    $labels[] = $name . ' - ' . $noSampel;
                }
            }
        }

        return $labels;
    }

    private static function detectTipe(string $noQuotation): string
    {
        $kontrak = QuotationKontrakH::where('no_document', $noQuotation)->where('is_active', true)->exists();

        return $kontrak ? 'kontrak' : 'non_kontrak';
    }

    private static function kategori3Name($raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d+\s*-\s*(.+)$/', $value, $match)) {
            return trim($match[1]);
        }

        return $value;
    }

    private static function normalizeSamplingBlocks($raw): array
    {
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded) || count($decoded) === 0) {
            return [];
        }

        if (isset($decoded['data_sampling']) || isset($decoded['penamaan_titik'])) {
            return [$decoded];
        }

        $blocks = [];
        foreach ($decoded as $item) {
            if (is_array($item) && (isset($item['data_sampling']) || isset($item['periode_kontrak']) || isset($item['penamaan_titik']))) {
                $blocks[] = $item;
            }
        }

        return $blocks;
    }
}