<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\PersiapanSampelHeader;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FdlBasTimingExportService
{
    private $relations;

    public function __construct()
    {
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', '600');

        $this->relations = (new OrderDetail())->getAnyDataLapanganRelations();
    }

    public function export(array $filters = [])
    {
        $groups = [];

        $query = OrderDetail::withAnyDataLapangan()
            ->where('is_active', 1)
            ->whereNotNull('tanggal_sampling');

        if (empty($filters['include_sd_sp'])) {
            $query->whereNotIn('kategori_1', ['SD', 'SP']);
        }

        if (!empty($filters['tanggal_awal'])) {
            $query->whereDate('tanggal_sampling', '>=', $filters['tanggal_awal']);
        }

        if (!empty($filters['tanggal_akhir'])) {
            $query->whereDate('tanggal_sampling', '<=', $filters['tanggal_akhir']);
        }

        if (empty($filters['tanggal_awal']) && empty($filters['tanggal_akhir'])) {
            $tahun = !empty($filters['tahun']) ? (int) $filters['tahun'] : (int) date('Y');
            $bulan = !empty($filters['bulan']) ? (int) $filters['bulan'] : (int) date('m');

            $query->whereYear('tanggal_sampling', $tahun)
                ->whereMonth('tanggal_sampling', $bulan);
        }

        if (!empty($filters['no_quotation'])) {
            $query->where('no_quotation', $filters['no_quotation']);
        }

        if (!empty($filters['no_order'])) {
            $query->where('no_order', $filters['no_order']);
        }

        $query->orderBy('tanggal_sampling')
            ->orderBy('no_quotation')
            ->orderBy('no_order')
            ->orderBy('no_sampel')
            ->chunk(300, function ($orderDetails) use (&$groups) {
                $basByOrderSampling = $this->getBasHeadersByOrderSampling($orderDetails);

                foreach ($orderDetails as $orderDetail) {
                    $groupKey = $this->makeGroupKey($orderDetail);

                    if (!isset($groups[$groupKey])) {
                        $groups[$groupKey] = $this->makeEmptyGroup($orderDetail);
                    }

                    $basKey = $this->makeBasKey($orderDetail->no_order, $orderDetail->tanggal_sampling);
                    $this->appendOrderDetailToGroup($groups[$groupKey], $orderDetail, $basByOrderSampling->get($basKey));
                }
            });

        return $this->writeExcel($this->buildRows($groups), $filters);
    }

    private function getBasHeadersByOrderSampling($orderDetails)
    {
        $orders = $orderDetails->pluck('no_order')->filter()->unique()->values();
        $samplingDates = $orderDetails->pluck('tanggal_sampling')->filter()->map(function ($date) {
            return $this->formatDate($date);
        })->unique()->values();

        if ($orders->isEmpty() || $samplingDates->isEmpty()) {
            return collect();
        }

        return PersiapanSampelHeader::where('is_active', true)
            ->whereIn('no_order', $orders)
            ->whereIn('tanggal_sampling', $samplingDates)
            ->whereNotNull('detail_bas_documents')
            ->get()
            ->groupBy(function ($header) {
                return $this->makeBasKey($header->no_order, $header->tanggal_sampling);
            })
            ->map(function ($headers) {
                return $headers->sortBy(function ($header) {
                    return $this->basFinishedAt($header)->timestamp;
                })->first();
            });
    }

    private function makeBasKey($noOrder, $tanggalSampling)
    {
        return trim((string) $noOrder) . '|' . ($this->formatDate($tanggalSampling) ?: '-');
    }

    private function makeGroupKey($orderDetail)
    {
        return implode('|', [
            $orderDetail->no_quotation ?: '-',
            $orderDetail->no_order ?: '-',
            $this->formatDate($orderDetail->tanggal_sampling) ?: '-',
        ]);
    }

    private function makeEmptyGroup($orderDetail)
    {
        return [
            'no_quotation' => $orderDetail->no_quotation,
            'no_order' => $orderDetail->no_order,
            'nama_perusahaan' => $orderDetail->nama_perusahaan,
            'tanggal_sampling' => [],
            'kategori' => [],
            'sub_kategori' => [],
            'no_sampel' => [],
            'sampler' => [],
            'bas_sampler' => [],
            'fdl_events' => [],
            'bas_events' => [],
            'fdl_input_samples' => [],
            'bas_headers' => [],
        ];
    }

    private function appendOrderDetailToGroup(array &$group, $orderDetail, $bas)
    {
        $group['tanggal_sampling'][] = $this->formatDate($orderDetail->tanggal_sampling);
        $group['kategori'][] = $orderDetail->kategori_2;
        $group['sub_kategori'][] = $orderDetail->kategori_3;
        $group['no_sampel'][] = $orderDetail->no_sampel;

        $fdlEvents = $this->collectFdlEvents($orderDetail);

        foreach ($fdlEvents as $event) {
            $group['fdl_events'][] = $event;

            if (!empty($event['created_by'])) {
                $group['sampler'][] = $event['created_by'];
            }
        }

        if (!empty($fdlEvents)) {
            $group['fdl_input_samples'][] = $orderDetail->no_sampel;
        }

        if ($bas && !empty($bas->detail_bas_documents)) {
            $basHeaderKey = 'psh-' . $bas->id;
            $group['bas_headers'][] = $basHeaderKey;
            $group['bas_events'][$basHeaderKey] = [
                'no_sampel' => 'BAS Tim',
                'created_by' => $bas->sampler_jadwal,
                'created_at' => $this->basFinishedAt($bas),
            ];

            foreach (explode(',', $bas->sampler_jadwal ?? '') as $sampler) {
                if (trim($sampler) !== '') {
                    $group['bas_sampler'][] = trim($sampler);
                }
            }
        }
    }

    private function collectFdlEvents($orderDetail)
    {
        $events = [];

        foreach ($this->relations as $relation) {
            if (!$orderDetail->relationLoaded($relation) || !$orderDetail->{$relation}) {
                continue;
            }

            $relationValue = $orderDetail->{$relation};
            $items = $relationValue instanceof EloquentCollection ? $relationValue : collect([$relationValue]);

            foreach ($items as $item) {
                if (empty($item->created_at)) {
                    continue;
                }

                $events[] = [
                    'relation' => $relation,
                    'no_sampel' => $orderDetail->no_sampel,
                    'created_by' => $item->created_by,
                    'created_at' => Carbon::parse($item->created_at),
                ];
            }
        }

        return $events;
    }

    private function basFinishedAt($bas)
    {
        $fallback = Carbon::parse($bas->updated_at ?: $bas->created_at);
        $documents = $bas->detail_bas_documents;

        if (is_string($documents)) {
            $documents = json_decode($documents, true);
        }

        if (is_object($documents)) {
            $documents = (array) $documents;
        }

        if (!is_array($documents)) {
            return $fallback;
        }

        $documentItems = isset($documents[0]) && is_array($documents[0]) ? $documents : [$documents];
        $finishedTimes = [];

        foreach ($documentItems as $document) {
            if (!is_array($document) || empty($document['waktu_selesai'])) {
                continue;
            }

            try {
                $finishedTimes[] = Carbon::parse($document['waktu_selesai']);
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!empty($finishedTimes)) {
            usort($finishedTimes, function ($a, $b) {
                return $a->timestamp <=> $b->timestamp;
            });

            return $finishedTimes[0];
        }

        return $fallback;
    }

    private function buildRows(array $groups)
    {
        $rows = [];

        foreach ($groups as $group) {
            $latestFdl = $this->latestEvent($group['fdl_events']);
            $latestBas = $this->latestEvent($group['bas_events']);
            $durationMinutes = $this->durationMinutes($latestFdl, $latestBas);
            $jumlahSampel = count($this->uniqueValues($group['no_sampel']));
            $jumlahFdl = count($this->uniqueValues($group['fdl_input_samples']));
            $jumlahBas = count($this->uniqueValues($group['bas_headers']));

            $rows[] = [
                'no_quotation' => $group['no_quotation'],
                'no_order' => $group['no_order'],
                'nama_perusahaan' => $group['nama_perusahaan'],
                'tanggal_sampling' => $this->joinValues($group['tanggal_sampling']),
                'kategori' => $this->joinCleanCategoryValues($group['kategori']),
                'sub_kategori' => $this->joinCleanCategoryValues($group['sub_kategori']),
                'no_sampel' => $this->joinValues($group['no_sampel']),
                'jumlah_sampel' => $jumlahSampel,
                'jumlah_fdl_input' => $jumlahFdl,
                'jumlah_bas_selesai' => $jumlahBas,
                'sampler' => $this->joinValues(array_merge($group['sampler'], $group['bas_sampler'])),
                'fdl_terakhir_oleh' => $latestFdl ? ($latestFdl['created_by'] ?: '-') : '-',
                'waktu_fdl_terakhir' => $latestFdl ? $this->formatDateTime($latestFdl['created_at']) : '-',
                'waktu_bas_selesai' => $latestBas ? $this->formatDateTime($latestBas['created_at']) : '-',
                'selisih_menit' => $durationMinutes,
                'selisih' => $this->formatDuration($durationMinutes),
                'status' => $this->statusText($latestFdl, $latestBas, $jumlahSampel, $jumlahFdl, $durationMinutes),
                'detail_waktu_fdl' => $this->formatEventList($group['fdl_events']),
                'detail_waktu_bas' => $this->formatEventList($group['bas_events']),
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp($a['waktu_bas_selesai'], $b['waktu_bas_selesai']);
        });

        return $rows;
    }

    private function latestEvent(array $events)
    {
        if (empty($events)) {
            return null;
        }

        usort($events, function ($a, $b) {
            return $a['created_at']->timestamp <=> $b['created_at']->timestamp;
        });

        return end($events);
    }

    private function durationMinutes($latestFdl, $latestBas)
    {
        if (!$latestFdl || !$latestBas) {
            return null;
        }

        return (int) floor(($latestBas['created_at']->timestamp - $latestFdl['created_at']->timestamp) / 60);
    }

    private function statusText($latestFdl, $latestBas, $jumlahSampel, $jumlahFdl, $durationMinutes)
    {
        if (!$latestFdl && !$latestBas) {
            return 'Belum ada FDL dan BAS';
        }

        if (!$latestFdl) {
            return 'Belum ada FDL';
        }

        if (!$latestBas) {
            return 'Belum ada BAS';
        }

        if ($durationMinutes !== null && $durationMinutes < 0) {
            return 'BAS sebelum FDL terakhir';
        }

        if ($jumlahFdl < $jumlahSampel) {
            return 'Sebagian sampel belum lengkap';
        }

        return 'Lengkap';
    }

    private function writeExcel(array $rows, array $filters)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FDL BAS Timing');

        $periodText = $this->periodText($filters);

        $sheet->mergeCells('A1:T1');
        $sheet->setCellValue('A1', 'TRACKING JARAK INPUT FDL KE BAS SELESAI');
        $sheet->mergeCells('A2:T2');
        $sheet->setCellValue('A2', 'Periode: ' . $periodText);

        $headers = [
            'No',
            'No Quotation',
            'No Order',
            'Nama Perusahaan',
            'Tanggal Sampling',
            'Kategori',
            'Sub Kategori',
            'No Sampel',
            'Jumlah Sampel',
            'Jumlah FDL Terinput',
            'Jumlah BAS Selesai',
            'Sampler',
            'FDL Terakhir Oleh',
            'Waktu FDL Terakhir',
            'Waktu BAS Selesai',
            'Selisih Menit',
            'Selisih Waktu',
            'Status',
            'Detail Waktu FDL',
            'Detail Waktu BAS',
        ];

        $sheet->fromArray($headers, null, 'A4');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $sheet->setCellValue("A{$rowNumber}", $index + 1);
            $sheet->setCellValueExplicit("B{$rowNumber}", $row['no_quotation'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNumber}", $row['no_order'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$rowNumber}", $row['nama_perusahaan']);
            $sheet->setCellValue("E{$rowNumber}", $row['tanggal_sampling']);
            $sheet->setCellValue("F{$rowNumber}", $row['kategori']);
            $sheet->setCellValue("G{$rowNumber}", $row['sub_kategori']);
            $sheet->setCellValueExplicit("H{$rowNumber}", $row['no_sampel'], DataType::TYPE_STRING);
            $sheet->setCellValue("I{$rowNumber}", $row['jumlah_sampel']);
            $sheet->setCellValue("J{$rowNumber}", $row['jumlah_fdl_input']);
            $sheet->setCellValue("K{$rowNumber}", $row['jumlah_bas_selesai']);
            $sheet->setCellValue("L{$rowNumber}", $row['sampler']);
            $sheet->setCellValue("M{$rowNumber}", $row['fdl_terakhir_oleh']);
            $sheet->setCellValue("N{$rowNumber}", $row['waktu_fdl_terakhir']);
            $sheet->setCellValue("O{$rowNumber}", $row['waktu_bas_selesai']);
            $sheet->setCellValue("P{$rowNumber}", $row['selisih_menit']);
            $sheet->setCellValue("Q{$rowNumber}", $row['selisih']);
            $sheet->setCellValue("R{$rowNumber}", $row['status']);
            $sheet->setCellValue("S{$rowNumber}", $row['detail_waktu_fdl']);
            $sheet->setCellValue("T{$rowNumber}", $row['detail_waktu_bas']);
            $rowNumber++;
        }

        $lastRow = max(4, $rowNumber - 1);
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:T2')->applyFromArray([
            'font' => ['italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A4:T4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A4A4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A4:T{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
        ]);
        $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I5:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("P5:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H5:H{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("L5:T{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->setAutoFilter("A4:T{$lastRow}");
        $sheet->freezePane('A5');

        foreach (range('A', 'T') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        foreach (['D' => 35, 'H' => 45, 'L' => 35, 'S' => 55, 'T' => 55] as $column => $width) {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
        }

        $folderName = 'tracking_fdl_bas';
        $folderPath = public_path($folderName);

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0777, true);
        }

        $fileName = 'tracking_fdl_bas_timing_' . date('Ymd_His') . '.xlsx';
        $fullPath = $folderPath . DIRECTORY_SEPARATOR . $fileName;

        (new Xlsx($spreadsheet))->save($fullPath);

        return [
            'filename' => $fileName,
            'path' => $folderName . '/' . $fileName,
            'full_path' => $fullPath,
            'total' => count($rows),
            'period' => $periodText,
        ];
    }

    private function formatEventList(array $events)
    {
        if (empty($events)) {
            return '-';
        }

        usort($events, function ($a, $b) {
            return $a['created_at']->timestamp <=> $b['created_at']->timestamp;
        });

        return collect($events)->map(function ($event) {
            $by = !empty($event['created_by']) ? $event['created_by'] : '-';
            return $event['no_sampel'] . ' | ' . $this->formatDateTime($event['created_at']) . ' | ' . $by;
        })->unique()->implode("\n");
    }

    private function joinValues(array $values)
    {
        $values = $this->uniqueValues($values);

        return empty($values) ? '-' : implode(', ', $values);
    }

    private function joinCleanCategoryValues(array $values)
    {
        $values = array_map(function ($value) {
            return preg_replace('/^\s*\d+\s*-\s*/', '', (string) $value);
        }, $values);

        return $this->joinValues($values);
    }

    private function uniqueValues(array $values)
    {
        return collect($values)
            ->filter(function ($value) {
                return $value !== null && $value !== '';
            })
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function periodText(array $filters)
    {
        if (!empty($filters['tanggal_awal']) || !empty($filters['tanggal_akhir'])) {
            return ($filters['tanggal_awal'] ?? '-') . ' s/d ' . ($filters['tanggal_akhir'] ?? '-');
        }

        $tahun = !empty($filters['tahun']) ? (int) $filters['tahun'] : (int) date('Y');
        $bulan = !empty($filters['bulan']) ? str_pad((string) (int) $filters['bulan'], 2, '0', STR_PAD_LEFT) : date('m');

        return $tahun . '-' . $bulan;
    }

    private function formatDate($value)
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function formatDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function formatDuration($minutes)
    {
        if ($minutes === null) {
            return '-';
        }

        $prefix = $minutes < 0 ? '-' : '';
        $minutes = abs((int) $minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return sprintf('%s%d hari %02d jam %02d menit', $prefix, $days, $hours, $mins);
        }

        return sprintf('%s%02d jam %02d menit', $prefix, $hours, $mins);
    }
}


