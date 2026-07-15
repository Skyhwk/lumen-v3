<?php

namespace App\Services;

use App\Models\WsValueAir;
use App\Models\WsValueUdara;
use App\Models\WsValueEmisiCerobong;

class ParameterResultService
{
    // =============================================
    // SINGLE (dipakai cekParameter)
    // =============================================

    public function getNamaYangSudahAda(string $noSampel, string $idKategori): \Illuminate\Support\Collection
    {
        if ($idKategori == "1-Air") {
            return $this->checkAir($noSampel);
        } else if ($idKategori == "4-Udara") {
            return $this->checkUdara($noSampel);
        } else if ($idKategori == "5-Emisi") {
            return $this->checkEmisi($noSampel);
        }

        return collect();
    }

    public function mapParameter(array $parameterRaw, \Illuminate\Support\Collection $namaYangSudahAda): \Illuminate\Support\Collection
    {
        return collect($parameterRaw)->map(function ($item) use ($namaYangSudahAda) {
            $parts = explode(';', $item, 2);
            return [
                'id'         => $parts[0],
                'nama_lab'   => $parts[1],
                'has_result' => $namaYangSudahAda->contains(strtolower(trim($parts[1]))),
            ];
        });
    }

    private function checkAir(string $noSampel): \Illuminate\Support\Collection
    {
        $ws = WsValueAir::where('no_sampel', $noSampel)
            ->with(['titrimetri', 'gravimetri', 'colorimetri', 'subkontrak'])
            ->where('is_active', 1)
            ->get();

        if ($ws->isEmpty()) {
            return collect();
        }

        return $ws->map(function ($row) {
            $header = null;
            if ($row->titrimetri) {
                $header = $row->titrimetri;
            } elseif ($row->gravimetri) {
                $header = $row->gravimetri;
            } elseif ($row->colorimetri) {
                $header = $row->colorimetri;
            } elseif ($row->subkontrak) {
                $header = $row->subkontrak;
            }

            if ($header) {
                return strtolower(trim($header->parameter));
            }
            return null;
        })->filter()->unique()->values();
    }

    private function checkUdara(string $noSampel): \Illuminate\Support\Collection
    {
        $relations = [
            'lingkungan', 'microbiologi', 'medanLm', 'sinaruv',
            'iklim', 'getaran', 'kebisingan', 'direct_lain',
            'partikulat', 'pencahayaan', 'swab', 'subkontrak',
            'dustfall', 'debuPersonal'
        ];

        $ws = WsValueUdara::where('no_sampel', $noSampel)
            ->with($relations)
            ->where('is_active', 1)
            ->get();

        if ($ws->isEmpty()) {
            return collect();
        }

        return $ws->map(function ($row) use ($relations) {
            $header = null;
            foreach ($relations as $relation) {
                if ($row->$relation) {
                    $header = $row->$relation;
                    break;
                }
            }

            if ($header) {
                return strtolower(trim($header->parameter));
            }
            return null;
        })->filter()->unique()->values();
    }

    private function checkEmisi(string $noSampel): \Illuminate\Support\Collection
    {
        $ws = WsValueEmisiCerobong::where('no_sampel', $noSampel)
            ->with(['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'])
            ->where('is_active', 1)
            ->get();

        if ($ws->isEmpty()) {
            return collect();
        }

        return $ws->map(function ($row) {
            $header = null;
            if ($row->emisi_cerobong_header) {
                $header = $row->emisi_cerobong_header;
            } elseif ($row->emisi_isokinetik) {
                $header = $row->emisi_isokinetik;
            } elseif ($row->subkontrak) {
                $header = $row->subkontrak;
            }

            if ($header) {
                return strtolower(trim($header->parameter));
            }
            return null;
        })->filter()->unique()->values();
    }

    // =============================================
    // BATCH (dipakai index DataTables)
    // =============================================

    public function getNamaYangSudahAdaBatch(array $noSampelList, string $idKategori): array
    {
        if ($idKategori == "1-Air") {
            return $this->checkAirBatch($noSampelList);
        } else if ($idKategori == "4-Udara") {
            return $this->checkUdaraBatch($noSampelList);
        } else if ($idKategori == "5-Emisi") {
            return $this->checkEmisiBatch($noSampelList);
        }

        return [];
    }

    public function mapParameterWithStatus(array $parameterRaw, array $namaYangSudahAda): string
    {
        $result = collect($parameterRaw)->map(function ($item) use ($namaYangSudahAda) {
            $parts = explode(';', $item, 2);
            return [
                'id'         => $parts[0],
                'nama_lab'   => $parts[1],
                'has_result' => in_array(strtolower(trim($parts[1])), $namaYangSudahAda),
            ];
        });

        return json_encode($result->toArray());
    }

    private function checkAirBatch(array $noSampelList): array
    {
        $ws = WsValueAir::whereIn('no_sampel', $noSampelList)
            ->with(['titrimetri', 'gravimetri', 'colorimetri', 'subkontrak'])
            ->where('is_active', 1)
            ->get();

        $result = [];
        foreach ($ws as $row) {
            $header = null;
            if ($row->titrimetri) {
                $header = $row->titrimetri;
            } elseif ($row->gravimetri) {
                $header = $row->gravimetri;
            } elseif ($row->colorimetri) {
                $header = $row->colorimetri;
            } elseif ($row->subkontrak) {
                $header = $row->subkontrak;
            }

            if ($header) {
                $result[$row->no_sampel][] = strtolower(trim($header->parameter));
            }
        }

        foreach ($result as $noSampel => $params) {
            $result[$noSampel] = array_unique($params);
        }

        return $result;
    }

    private function checkUdaraBatch(array $noSampelList): array
    {
        $relations = [
            'lingkungan', 'microbiologi', 'medanLm', 'sinaruv',
            'iklim', 'getaran', 'kebisingan', 'direct_lain',
            'partikulat', 'pencahayaan', 'swab', 'subkontrak',
            'dustfall', 'debuPersonal'
        ];

        $ws = WsValueUdara::whereIn('no_sampel', $noSampelList)
            ->with($relations)
            ->where('is_active', 1)
            ->get();

        $result = [];
        foreach ($ws as $row) {
            $header = null;
            foreach ($relations as $relation) {
                if ($row->$relation) {
                    $header = $row->$relation;
                    break;
                }
            }

            if ($header) {
                $result[$row->no_sampel][] = strtolower(trim($header->parameter));
            }
        }

        foreach ($result as $noSampel => $params) {
            $result[$noSampel] = array_unique($params);
        }

        return $result;
    }

    private function checkEmisiBatch(array $noSampelList): array
    {
        $ws = WsValueEmisiCerobong::whereIn('no_sampel', $noSampelList)
            ->with(['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'])
            ->where('is_active', 1)
            ->get();

        $result = [];
        foreach ($ws as $row) {
            $header = null;
            if ($row->emisi_cerobong_header) {
                $header = $row->emisi_cerobong_header;
            } elseif ($row->emisi_isokinetik) {
                $header = $row->emisi_isokinetik;
            } elseif ($row->subkontrak) {
                $header = $row->subkontrak;
            }

            if ($header) {
                $result[$row->no_sampel][] = strtolower(trim($header->parameter));
            }
        }

        foreach ($result as $noSampel => $params) {
            $result[$noSampel] = array_unique($params);
        }

        return $result;
    }
}