<?php

namespace App\Services;

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

            if (empty($dataOrder) || !isset($dataOrder[0]['detail'])) {
                return collect((new GroupedCfrByLhp($orderHeader, $periode))->get())
                    ->map(function ($item) {
                        $item = is_array($item) ? $item : (array) $item;
                        $item['rekap_pengujian'] = $this->rekapBuilder->getRekapPengujian($item['order_details'] ?? []);

                        return $item;
                    })
                    ->values()
                    ->all();
            }

            $dataOrderDetails = [];

            foreach ($dataOrder[0]['detail'] as $rawDetail) {
                $value = $rawDetail;
                $orderDetails = [];

                foreach ($value['sampelNumbers'] as $idx => $sampelNo) {
                    $orderDetails[] = [
                        'no_sampel'    => $sampelNo,
                        'periode'      => $periode ?? null,
                        'kategori_3'   => is_array($value['categories'] ?? null) ? ($value['categories'][$idx] ?? '-') : ($value['kategori_3'] ?? '-'),
                        'keterangan_1' => is_array($value['points'] ?? null) ? ($value['points'][$idx] ?? '-') : '-',
                        'steps'        => $value['steps'] ?? [],
                    ];
                }

                $cfrItem = [
                    'cfr'             => $value['cfr'],
                    'periode'         => $periode ?? null,
                    'keterangan_1'    => $value['points'],
                    'kategori_3'      => $value['categories'],
                    'no_sampel'       => $value['sampelNumbers'],
                    'total_no_sampel' => $value['jumlah_sampel'],
                    'order_details'   => $orderDetails,
                    'steps'           => $value['steps'],
                    'rekap_pengujian' => $this->rekapBuilder->getRekapPengujianFromOrderBerjalan($rawDetail),
                ];

                $dataOrderDetails[] = $cfrItem;
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

            return $dataOrderDetails;
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error', 'error' => $th->getMessage()], 500);
        }
    }
}
