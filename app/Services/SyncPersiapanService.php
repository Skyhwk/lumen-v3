<?php

namespace App\Services;

use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Models\OrderDetail;
use App\Models\HargaParameter;
use App\Models\KonfigurasiPraSampling;

class SyncPersiapanService
{
    public function sync($sampleNumbers)
    {
        $orderDetails = OrderDetail::whereIn('no_sampel', $sampleNumbers)->where('is_active', true)->latest('id')->get();

        foreach ($orderDetails as $orderDetail) {
            $shouldUpdatePersiapan = false;

            [$orderedCategoryId, $orderedCategoryName] = explode('-', $orderDetail->kategori_2);
            $orderedCategoryName = strtolower($orderedCategoryName);

            if (!$orderDetail->persiapan || $orderDetail->persiapan == '[]') {
                $updatedPersiapan = $this->updatePersiapan($orderDetail);
                if ($updatedPersiapan) {
                    $orderDetail->persiapan = json_encode($updatedPersiapan);
                    $orderDetail->save();
                }
                continue;
            }

            $orderedParams = collect(json_decode($orderDetail->parameter, true));
            foreach ($orderedParams as $orderedParam) {
                [$orderedParamId, $orderedParamName] = explode(';', $orderedParam);

                if ($orderedCategoryName == 'air') {
                    $bottleType = optional(HargaParameter::where([
                        'id_parameter' => $orderedParamId,
                        'is_active' => true
                    ])->first())->regen;

                    if ($bottleType) {
                        $orderedPersiapans = collect(json_decode($orderDetail->persiapan, true));

                        if (!$orderedPersiapans->firstWhere('type_botol', $bottleType)) {
                            $shouldUpdatePersiapan = true;
                        } else {
                            if (!isset($preparedParams[$orderedCategoryName][$bottleType])) {
                                $shouldUpdatePersiapan = true;
                            }
                        }
                    }
                } elseif ($orderedCategoryName == 'udara' || $orderedCategoryName == 'emisi') {
                    $isShouldPrepared = KonfigurasiPraSampling::where([
                        'id_kategori' => $orderedCategoryId,
                        'parameter' => $orderedParam,
                        'is_active' => true
                    ])->exists();

                    if ($isShouldPrepared && !isset($preparedParams[$orderedCategoryName][$orderedParamName])) {
                        $shouldUpdatePersiapan = true;
                    }
                }
            }

            if ($shouldUpdatePersiapan) {
                $updatedPersiapan = $this->updatePersiapan($orderDetail);
                if ($orderDetail->persiapan !== json_encode($updatedPersiapan)) {
                    $orderDetail->persiapan = json_encode($updatedPersiapan);
                    $orderDetail->save();
                }
            }
        }
    }

    private function updatePersiapan($orderDetail)
    {
        $oldPersiapan = collect(json_decode($orderDetail->persiapan, true));
        $newPersiapan = collect($this->generatePersiapan($orderDetail));

        [$orderedCategoryId, $orderedCategoryName] = explode('-', $orderDetail->kategori_2);
        $orderedCategoryName = strtolower($orderedCategoryName);

        if ($orderedCategoryName == 'air') {
            $finalPersiapan = $newPersiapan->map(function ($newItem) use ($oldPersiapan) {
                $match = $oldPersiapan->first(fn($oldItem) => $oldItem['type_botol'] === $newItem['type_botol']);

                if ($match) { // kalo ada yg lama pake yg lama
                    $newItem['koding']      = $match['koding'];
                    $newItem['type_botol']  = $match['type_botol'];
                    $newItem['volume']      = $newItem['volume'];
                    $newItem['file']        = $match['file'];
                    $newItem['disiapkan']   = $newItem['disiapkan'];
                } else {
                    if (!file_exists(public_path() . '/barcode/botol')) {
                        mkdir(public_path() . '/barcode/botol', 0777, true);
                    }

                    $this->generateQR($newItem['koding'], '/barcode/botol');
                }

                return $newItem;
            });

            return $finalPersiapan->toArray();
        } elseif ($orderedCategoryName == 'udara' || $orderedCategoryName == 'emisi') {
            $finalPersiapan = $newPersiapan->map(function ($newItem) use ($oldPersiapan) {
                $match = $oldPersiapan->first(fn($oldItem) => $oldItem['parameter'] === $newItem['parameter'] && $oldItem['disiapkan'] === $newItem['disiapkan']);

                if ($match) { // kalo ada yg lama pake yg lama
                    $newItem['parameter']   = $match['parameter'];
                    $newItem['disiapkan']   = $newItem['disiapkan'];
                    $newItem['koding']      = $match['koding'];
                    $newItem['file']        = $match['file'];
                } else {
                    if (!file_exists(public_path() . '/barcode/penjerap')) {
                        mkdir(public_path() . '/barcode/penjerap', 0777, true);
                    }

                    $this->generateQR($newItem['koding'], '/barcode/penjerap');
                }

                return $newItem;
            });

            return $finalPersiapan->toArray();
        }
    }

    private function generatePersiapan($orderDetail)
    {
        [$orderedCategoryId, $orderedCategoryName] = explode('-', $orderDetail->kategori_2);
        $orderedCategoryName = strtolower($orderedCategoryName);

        if ($orderedCategoryName == 'air') {
            $parameter_names = array_map(fn($p) => explode(';', $p)[1], json_decode($orderDetail->parameter) ?? []);

            $params = HargaParameter::where('id_kategori', $orderedCategoryId)
                ->where('is_active', true)
                ->whereIn('nama_parameter', $parameter_names)
                ->get();

            $param_map = [];
            foreach ($params as $param) {
                $param_map[$param->nama_parameter] = $param;
            }

            $botol_volumes = [];
            foreach (json_decode($orderDetail->parameter) ?? [] as $parameter) {
                $param_name = explode(';', $parameter)[1];
                if (isset($param_map[$param_name])) {
                    $param = $param_map[$param_name];
                    if (!isset($botol_volumes[$param->regen])) {
                        $botol_volumes[$param->regen] = 0;
                    }
                    $botol_volumes[$param->regen] += ($param->volume && $param->volume != "-" && $param->volume) ? (float) $param->volume : 0;
                }
            }

            $ketentuan_botol = [
                'ORI' => 1000,
                'H2SO4' => 1000,
                'M100' => 100,
                'HNO3' => 500,
                'M1000' => 1000,
                'BENTHOS' => 100
            ];

            $botol = [];
            foreach ($botol_volumes as $type => $volume) {
                $typeUpper = strtoupper($type);
                if (!isset($ketentuan_botol[$typeUpper])) continue;

                $koding = $orderDetail->koding_sampling . strtoupper(Str::random(5));
                $botol[] = [
                    'koding'        => $koding,
                    'type_botol'    => $type,
                    'volume'        => $volume,
                    'file'          => $koding . '.png',
                    'disiapkan'     => (int) ceil($volume / $ketentuan_botol[$typeUpper])
                ];
            }

            return $botol;
        } else {
            $cek_ketentuan_parameter = KonfigurasiPraSampling::whereIn('parameter', json_decode($orderDetail->parameter) ?? [])
                ->where('is_active', 1)
                ->get();

            $persiapan = [];
            foreach ($cek_ketentuan_parameter as $ketentuan) {
                $koding = $orderDetail->koding_sampling . strtoupper(Str::random(5));
                $persiapan[] = [
                    'parameter'     => \explode(';', $ketentuan->parameter)[1],
                    'disiapkan'     => $ketentuan->ketentuan,
                    'koding'        => $koding,
                    'file'          => $koding . '.png'
                ];
            }

            return $persiapan ?? [];
        }
    }

    private function generateQR($no_sampel, $directory)
    {
        $filename = \str_replace("/", "_", $no_sampel) . '.png';
        $path = public_path() . "$directory/$filename";

        QrCode::format('png')->size(200)->generate($no_sampel, $path);

        return $filename;
    }
}
