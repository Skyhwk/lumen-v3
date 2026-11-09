<?php

namespace App\Services\OrderBackdate;

use App\Models\Jadwal;
use App\Models\LhpsAirHeader;
use App\Models\OrderHeader;
use App\Models\PersiapanSampelHeader;
use App\Models\QrDocument;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderBackdateFieldResolver
{
    /** @var OrderBackdateLhpLocator */
    private $lhpLocator;

    public function __construct(OrderBackdateLhpLocator $lhpLocator)
    {
        $this->lhpLocator = $lhpLocator;
    }

    public function resolveStep(string $stepKey, OrderHeader $order): array
    {
        switch ($stepKey) {
            case 'quotation':
                return $this->quotationStep($order);
            case 'order':
                return $this->orderStep($order);
            case 'schedule':
                return $this->scheduleStep($order);
            case 'field_data':
                return $this->fieldDataStep($order);
            case 'sampling_doc':
                return $this->samplingDocStep($order);
            case 'lhp':
                return $this->lhpStep($order);
            case 'qr':
                return $this->qrStep($order);
            default:
                return [];
        }
    }

    private function quotationStep(OrderHeader $order): array
    {
        $items = $this->rowFields('order_header', (int) $order->id, [
            'tanggal_penawaran' => 'Tanggal penawaran (order)',
        ], $order->no_document);

        if ($order->quotationNonKontrak) {
            $items = array_merge($items, $this->rowFields(
                'request_quotation',
                (int) $order->quotationNonKontrak->id,
                ['tanggal_penawaran' => 'Tanggal penawaran (QT)'],
                $order->no_document
            ));
        }
        if ($order->quotationKontrakH) {
            $items = array_merge($items, $this->rowFields(
                'request_quotation_kontrak_H',
                (int) $order->quotationKontrakH->id,
                ['tanggal_penawaran' => 'Tanggal penawaran (QTC)'],
                $order->no_document
            ));
        }

        return $items;
    }

    private function orderStep(OrderHeader $order): array
    {
        return $this->rowFields('order_header', (int) $order->id, [
            'tanggal_order' => 'Tanggal order',
        ], $order->no_order);
    }

    private function scheduleStep(OrderHeader $order): array
    {
        $items = [];
        $jadwals = Jadwal::where('no_quotation', $order->no_document)
            ->where('is_active', true)
            ->get(['id', 'tanggal', 'no_quotation']);

        foreach ($jadwals as $jadwal) {
            $items = array_merge($items, $this->rowFields('jadwal', (int) $jadwal->id, [
                'tanggal' => 'Jadwal #' . $jadwal->id,
            ], $order->no_document));
        }

        return $items;
    }

    private function fieldDataStep(OrderHeader $order): array
    {
        $items = [];

        if (OrderBackdateDomainProfile::isAir($order)) {
            $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                'lhps_air_header',
                $order,
                [
                    'tanggal_terima' => 'Terima sampel',
                    'tanggal_sampling' => 'Sampling lapangan',
                ],
                $this
            ));
        }

        if (OrderBackdateDomainProfile::isEmisi($order)) {
            $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                'lhps_emisi_header',
                $order,
                [
                    'tanggal_sampling' => 'Sampling (emisi bergerak)',
                    'tgl_tugas' => 'Tanggal tugas (emisi bergerak)',
                ],
                $this
            ));
            $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                'lhps_emisic_header',
                $order,
                [
                    'tanggal_sampling' => 'Sampling (emisi cerobong)',
                    'tanggal_terima' => 'Terima (emisi cerobong)',
                    'tanggal_tugas' => 'Tanggal tugas (emisi cerobong)',
                ],
                $this
            ));
            $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                'lhps_emisi_isokinetik_header',
                $order,
                [
                    'tanggal_terima' => 'Terima (isokinetik)',
                    'tanggal_tugas' => 'Tanggal tugas (isokinetik)',
                ],
                $this
            ));
        }

        return $items;
    }

    private function samplingDocStep(OrderHeader $order): array
    {
        $items = [];
        $headers = PersiapanSampelHeader::where('no_order', $order->no_order)
            ->where('is_active', true)
            ->get(['id', 'tanggal_sampling']);

        foreach ($headers as $row) {
            $items = array_merge($items, $this->rowFields('persiapan_sampel_header', (int) $row->id, [
                'tanggal_sampling' => 'Persiapan sampel #' . $row->id,
            ], $order->no_order));
        }

        return $items;
    }

    private function lhpStep(OrderHeader $order): array
    {
        $items = [];

        if (OrderBackdateDomainProfile::isAir($order)) {
            $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                'lhps_air_header',
                $order,
                ['tanggal_lhp' => 'Tanggal LHP'],
                $this
            ));
        }

        if (OrderBackdateDomainProfile::isEmisi($order)) {
            foreach (['lhps_emisi_header', 'lhps_emisic_header', 'lhps_emisi_isokinetik_header'] as $table) {
                $items = array_merge($items, $this->lhpLocator->fieldItemsForTable(
                    $table,
                    $order,
                    ['tanggal_lhp' => 'Tanggal LHP'],
                    $this
                ));
            }
        }

        return $items;
    }

    private function qrStep(OrderHeader $order): array
    {
        $items = [];

        foreach ($this->lhpLocator->bundlesForOrder($order) as $bundle) {
            $header = $bundle['header'];
            if (empty($header->no_lhp)) {
                continue;
            }

            $qr = $this->findQrForHeader($header, $bundle['qr_type']);
            if (!$qr) {
                continue;
            }

            $payload = json_decode($qr->data ?? '{}', true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $label = 'QR Tanggal pengesahan (' . $header->no_lhp . ')';

            $items[] = [
                'key' => $this->makeKey('qr_documents', (int) $qr->id, 'data.Tanggal_Pengesahan'),
                'table' => 'qr_documents',
                'id' => (int) $qr->id,
                'column' => 'data.Tanggal_Pengesahan',
                'label' => $label,
                'value' => $payload['Tanggal_Pengesahan'] ?? ($header->tanggal_lhp
                    ? Carbon::parse($header->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY')
                    : null),
                'meta' => [
                    'lhp_table' => $bundle['table'],
                    'related_lhp_id' => (int) $header->id,
                    'qr_type' => $bundle['qr_type'],
                ],
            ];
        }

        return $items;
    }

    public function findQrForHeader(object $header, string $qrType): ?QrDocument
    {
        $byType = QrDocument::where('id_document', $header->id)
            ->where('type_document', $qrType)
            ->first();
        if ($byType) {
            return $byType;
        }

        if (empty($header->no_lhp)) {
            return null;
        }

        $parts = explode('/', $header->no_lhp);
        if (count($parts) >= 2) {
            $docKey = 'LHP_' . $parts[0] . '_' . $parts[1];
            $qr = QrDocument::where('file', $docKey)->first();
            if ($qr) {
                return $qr;
            }
        }

        $filename = 'LHP_' . str_replace('/', '_', $header->no_lhp);

        return QrDocument::where('file', $filename)->first();
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    public function rowFieldsPublic(string $table, int $id, array $columns, ?string $context = null): array
    {
        return $this->rowFields($table, $id, $columns, $context);
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function rowFields(string $table, int $id, array $columns, ?string $context = null): array
    {
        if ($id <= 0 || !Schema::hasTable($table)) {
            return [];
        }

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) {
            return [];
        }

        $items = [];
        foreach ($columns as $column => $labelBase) {
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            $raw = $row->{$column} ?? null;
            $label = $context ? $labelBase . ' (' . $context . ')' : $labelBase;
            $items[] = [
                'key' => $this->makeKey($table, $id, $column),
                'table' => $table,
                'id' => $id,
                'column' => $column,
                'label' => $label,
                'value' => $this->normalizeDateValue($raw),
                'meta' => $context ? ['context' => $context] : [],
            ];
        }

        return $items;
    }

    public function makeKey(string $table, int $id, string $column): string
    {
        return $table . ':' . $id . ':' . $column;
    }

    private function normalizeDateValue($raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00') {
            return null;
        }
        if (is_string($raw) && strpos($raw, 'Tanggal') !== false) {
            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $raw;
        }
    }
}
