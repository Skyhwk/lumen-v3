<?php

namespace App\Services;

use App\Models\Colorimetri;
use App\Models\DebuPersonalHeader;
use App\Models\DirectLainHeader;
use App\Models\DustFallHeader;
use App\Models\EmisiCerobongHeader;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\PartikulatHeader;
use App\Models\Subkontrak;
use App\Models\Titrimetri;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonitorKeterlambatanAnalisaService
{
    public const START_DATE = '2026-01-01';

    public function collectDelayedRecords(string $kategori): array
    {
        $startDate = Carbon::parse(self::START_DATE)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $pencarian = $this->kategoriPencarian($kategori);

        $orderDetails = OrderDetail::select(
            'order_detail.no_sampel',
            'order_detail.parameter',
            'order_detail.kategori_2',
            't_ftc.ftc_laboratory',
            't_ftc.ftc_verifier'
        )
            ->where('order_detail.kategori_2', $kategori)
            ->where('order_detail.is_active', true)
            ->join('t_ftc', 't_ftc.no_sample', '=', 'order_detail.no_sampel')
            ->whereNotNull('t_ftc.ftc_laboratory')
            ->whereBetween('t_ftc.ftc_laboratory', [$startDate, $endDate]);

        if ($pencarian) {
            $orderDetails->whereIn('order_detail.kategori_3', $pencarian);
        }

        $orderDetails = $orderDetails->get();

        if ($orderDetails->isEmpty()) {
            return [];
        }

        $noSampelList = $orderDetails->pluck('no_sampel')->unique()->toArray();
        $testedParams = $this->getTestedParameters($kategori, $noSampelList);
        $excluded = $this->parameterExcluded($kategori);

        $records = [];

        foreach ($orderDetails as $item) {
            $paramAll = $this->parseParameterNames($item->parameter);

            $paramTested = $testedParams->has($item->no_sampel)
                ? collect(explode(',', $testedParams[$item->no_sampel]))->map(fn ($p) => trim($p))
                : collect();

            $belumDiinput = $paramAll
                ->diff($paramTested)
                ->reject(fn ($p) => in_array(strtolower($p), $excluded))
                ->values();

            foreach ($belumDiinput as $namaParameter) {
                $records[] = [
                    'no_sampel' => $item->no_sampel,
                    'nama_parameter' => $namaParameter,
                    'kategori_2' => $item->kategori_2,
                    'ftc_laboratory' => $item->ftc_laboratory,
                    'ftc_verifier' => $item->ftc_verifier,
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        return $records;
    }

    public function parseParameterNames(?string $parameterJson): Collection
    {
        if (empty($parameterJson)) {
            return collect();
        }

        $decoded = json_decode($parameterJson, true);

        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->map(function ($p) {
                $parts = explode(';', $p, 2);
                return trim($parts[1] ?? $parts[0] ?? '');
            })
            ->filter()
            ->values();
    }

    private function getTestedParameters(string $kategori, array $noSampel): Collection
    {
        if (in_array($kategori, ['1-Air', '6-Padatan'])) {
            $models = [
                Colorimetri::class,
                Gravimetri::class,
                Titrimetri::class,
                Subkontrak::class,
            ];
        } elseif ($kategori === '4-Udara') {
            $models = [
                DustFallHeader::class,
                DebuPersonalHeader::class,
                LingkunganHeader::class,
                MicrobioHeader::class,
                Subkontrak::class,
                DirectLainHeader::class,
                PartikulatHeader::class,
            ];
        } elseif ($kategori === '5-Emisi') {
            $models = [EmisiCerobongHeader::class];
        } else {
            return collect();
        }

        return collect($models)
            ->flatMap(function ($model) use ($noSampel) {
                return $model::where('is_active', true)
                    ->whereIn('no_sampel', $noSampel)
                    ->get(['no_sampel', 'parameter']);
            })
            ->groupBy('no_sampel')
            ->map(fn ($items) => $items->pluck('parameter')->implode(','));
    }

    private function kategoriPencarian(string $kategori): ?array
    {
        $map = [
            '4-Udara' => ['11-Udara Ambient', '27-Udara Lingkungan Kerja', '12-Udara Angka Kuman', '46-Udara Swab Test'],
            '5-Emisi' => ['34-Emisi Sumber Tidak Bergerak'],
        ];

        return $map[$kategori] ?? null;
    }

    public function parameterExcluded(string $kategori): array
    {
        if ($kategori === '1-Air') {
            return [
                'ph',
                'suhu',
                'suhu (na)',
                'dhl',
                'debit air',
                'debit air (m3/ton)',
                'debit air (m3/hari)',
                'debit air (l/orang/hari)',
                'debit air (l/kg)',
                'debit air (l/l)',
                'debit air (m3/l)',
                'debit air (l/hari)',
                'debit air (m3/dtk)',
                'debit air (l/dtk)',
                'debit air (l/jam)',
                'debit air (l/hari)',
            ];
        }

        if ($kategori === '4-Udara') {
            return [
                'suhu',
                'kelembaban',
                'o2',
                'co2',
                'co2 (24 jam)',
                'co2 (8 jam)',
                'c o',
                'voc',
                'voc (8 jam)',
                'hcho',
                'hcho (8 jam)',
                'h2co',
                'tekanan udara',
                'pertukaran udara',
                'laju ventilasi',
                'laju ventilasi (8 jam)',
            ];
        }

        if ($kategori === '5-Emisi') {
            return [
                'suhu',
                'co2 (estb)',
                'o2 (estb)',
                'opasitas (estb)',
                'velocity',
                'co2',
                'o2',
                'opasitas',
                'no2',
                'no-no2',
                'nox',
                'nox-no2',
                'no',
                'so2',
                'co',
                'c o',
                'tekanan udara',
                'so2 (p)',
                'co (p)',
                'o2 (p)',
                'no2-nox (p)',
                'effisiensi pembakaran',
                'eff. pembakaran',
            ];
        }

        return [];
    }
}
