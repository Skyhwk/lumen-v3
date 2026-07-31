<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CfrDetails
{
    protected $orderHeader;
    protected $periode;

    public function __construct($orderHeader, $periode = null)
    {
        $this->orderHeader = $orderHeader;
        $this->periode = $periode;
    }

    public function get()
    {
        $data = $this->getCFRs($this->orderHeader, $this->periode);
        return $data;
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
                return (new GroupedCfrByLhp($orderHeader, $periode))->get();
            }

            $dataOrderDetails = $dataOrder[0]['detail'];

            foreach ($dataOrderDetails as $detail => &$value) {
                $orderDetails = [];

                foreach ($value['sampelNumbers'] as $idx => $sampelNo) {
                    $orderDetails[] = [
                        'no_sampel'    => $sampelNo,
                        'periode'      => $periode ?? null,
                        'kategori_3'   => is_array($value['categories'] ?? null) ? ($value['categories'][$idx] ?? '-') : ($value['kategori_3'] ?? '-'),
                        'keterangan_1' => is_array($value['points'] ?? null) ? ($value['points'][$idx] ?? '-') : '-',
                        'steps'        => $value['steps'] ?? []
                    ];
                }

                $value = [
                    'cfr'             => $value['cfr'],
                    'periode'         => $periode ?? null,
                    'keterangan_1'    => $value['points'],
                    'kategori_3'      => $value['categories'],
                    'no_sampel'       => $value['sampelNumbers'],
                    'total_no_sampel' => $value['jumlah_sampel'],
                    'order_details'   => $orderDetails,
                    'steps'           => $value['steps']
                ];
            }

            unset($value);


            return $dataOrderDetails;
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error', 'error' => $th->getMessage()], 500);
        }
    }
}