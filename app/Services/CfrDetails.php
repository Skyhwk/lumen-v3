<?php

namespace App\Services;

use App\Helpers\LhpStepSequence;
use App\Http\Controllers\external\LHPHandleController;
use Illuminate\Support\Facades\DB;

class CfrDetails
{
    protected $orderHeader;
    protected $periode;

    /** @var LHPHandleController */
    private $rekapBuilder;

    public function __construct($orderHeader, $periode = null, LHPHandleController $rekapBuilder = null)
    {
        $this->orderHeader = $orderHeader;
        $this->periode = $periode;
        $this->rekapBuilder = $rekapBuilder ?: app(LHPHandleController::class);
    }

    public function get()
    {
        return $this->getCFRs($this->orderHeader, $this->periode);
    }

    private function getCFRs($orderHeader, $periode)
    {
        try {
            $noOrder = is_array($orderHeader) ? ($orderHeader['no_order'] ?? null) : ($orderHeader->no_order ?? null);

            $orderBerjalan = DB::table('order_berjalan')
                ->where('no_order', $noOrder)
                ->first();

            $dataOrder = $orderBerjalan ? json_decode($orderBerjalan->dataOrderDetail, true) : null;
            $fromBerjalan = $this->fromOrderBerjalan($dataOrder, $periode, $noOrder);

            if ($fromBerjalan !== null) {
                return $fromBerjalan;
            }

            return $this->fromOrderDetail($orderHeader, $periode);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error', 'error' => $th->getMessage()], 500);
        }
    }

    private function fromOrderBerjalan($dataOrder, $periode, $noOrder)
    {
        if (!is_array($dataOrder) || empty($dataOrder) || !isset($dataOrder[0]['detail'])) {
            return null;
        }

        $groups = collect($dataOrder)->filter(function ($group) {
            return is_array($group) && !empty($group['detail']) && is_array($group['detail']);
        });

        if ($periode !== null && $periode !== '') {
            $groups = $groups->filter(function ($group) use ($periode) {
                return $this->samePeriod($group['periode'] ?? null, $periode);
            });
        }

        if ($groups->isEmpty()) {
            return null;
        }

        $dataOrderDetails = [];

        foreach ($groups as $group) {
            $groupPeriode = $group['periode'] ?? $periode;

            foreach ($group['detail'] as $rawDetail) {
                $value = $rawDetail;
                $orderDetails = [];
                $normalizedSteps = LhpStepSequence::normalize($value['steps'] ?? []);
                $sampelNumbers = $value['sampelNumbers'] ?? [];

                foreach ($sampelNumbers as $idx => $sampelNo) {
                    $orderDetails[] = [
                        'no_sampel'    => $sampelNo,
                        'periode'      => $groupPeriode,
                        'kategori_3'   => is_array($value['categories'] ?? null) ? ($value['categories'][$idx] ?? '-') : ($value['kategori_3'] ?? '-'),
                        'keterangan_1' => is_array($value['points'] ?? null) ? ($value['points'][$idx] ?? '-') : '-',
                        'steps'        => $normalizedSteps,
                    ];
                }

                $dataOrderDetails[] = [
                    'cfr'             => $value['cfr'] ?? null,
                    'periode'         => $groupPeriode,
                    'keterangan_1'    => $value['points'] ?? [],
                    'kategori_3'      => $value['categories'] ?? [],
                    'no_sampel'       => $sampelNumbers,
                    'total_no_sampel' => $value['jumlah_sampel'] ?? count($sampelNumbers),
                    'order_details'   => $orderDetails,
                    'steps'           => $normalizedSteps,
                    'rekap_pengujian' => $this->rekapBuilder->getRekapPengujianFromOrderBerjalan($rawDetail),
                ];
            }
        }

        $resolver = new ResolveLhpFile();
        foreach ($dataOrderDetails as &$cfrItem) {
            $cfrItem['file_lhp'] = $resolver->byCfr(
                $noOrder,
                $cfrItem['cfr'],
                $cfrItem['no_sampel'][0] ?? null
            );
        }
        unset($cfrItem);

        return array_values($dataOrderDetails);
    }

    private function fromOrderDetail($orderHeader, $periode)
    {
        return collect((new GroupedCfrByLhp($orderHeader, $periode))->get())
            ->map(function ($item) {
                $item = is_array($item) ? $item : (array) $item;
                $item['rekap_pengujian'] = $this->rekapBuilder->getRekapPengujian($item['order_details'] ?? []);

                return $item;
            })
            ->values()
            ->all();
    }

    private function samePeriod($actual, $requested)
    {
        if ($requested === null || $requested === '') {
            return true;
        }

        return substr((string) $actual, 0, 7) === substr((string) $requested, 0, 7);
    }
}
