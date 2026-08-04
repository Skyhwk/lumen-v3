<?php

namespace App\Services;

use App\Helpers\WsNilaiUjiResolver;
use App\Models\DebuPersonalHeader;
use App\Models\DetailLingkunganHidup;
use App\Models\DirectLainHeader;
use App\Models\ErgonomiHeader;
use App\Models\LingkunganHeader;
use App\Models\MasterBakumutu;
use App\Models\MdlUdara;
use App\Models\MedanLmHeader;
use App\Models\MicrobioHeader;
use App\Models\PartikulatHeader;
use App\Models\SinarUvHeader;
use App\Models\Subkontrak;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WsFinalUdaraLingkunganKerjaDetailService
{
    private const SOURCE_LABELS = [
        'lingkungan' => 'Lingkungan',
        'subKontrak' => 'Subkontrak',
        'direct' => 'Direct Lain',
        'partikulat' => 'Partikulat',
        'microbio' => 'Mikrobiologi',
        'debu_personal' => 'Debu Personal',
    ];

    private const WS_UDARA_FK = [
        'lingkungan' => 'id_lingkungan_header',
        'direct' => 'id_direct_lain_header',
        'partikulat' => 'id_partikulat_header',
        'subKontrak' => 'id_subkontrak',
        'microbio' => 'id_microbiologi_header',
        'debu_personal' => 'id_debu_personal_header',
    ];

    private const SINAR_UV_NAB = [
        [1, 5, 0.05],
        [5, 10, 0.01],
        [10, 15, 0.005],
        [15, 30, 0.0033],
        [30, 60, 0.0017],
        [60, 120, 0.0008],
        [120, 240, 0.0004],
        [240, 480, 0.0002],
        [480, PHP_INT_MAX, 0.0001],
    ];

    private const HEADER_SELECT = [
        'id', 'no_sampel', 'id_parameter', 'parameter', 'lhps',
        'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active',
    ];

    /**
     * @return Builder|Collection
     */
    public function buildDetail(Request $request)
    {
        $parameters = json_decode(html_entity_decode($request->parameter), true);
        $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
        $parameterName = $parameterArray[1] ?? null;

        if ($parameterName === 'Ergonomi') {
            return $this->buildErgonomiDetail($request->no_sampel);
        }

        if ($parameterName === 'Sinar UV') {
            return $this->buildSinarUvDetail($request->no_sampel);
        }

        if (in_array($parameterName, ['Medan Magnit Statis', 'Medan Listrik', 'Power Density'], true)) {
            return $this->buildMedanLmDetail($request->no_sampel);
        }

        return $this->buildStandardDetail($request);
    }

    private function buildErgonomiDetail(string $noSampel): Builder
    {
        return ErgonomiHeader::with('datalapangan')
            ->where('no_sampel', $noSampel)
            ->where('is_approve', true)
            ->where('is_active', true)
            ->select('*')
            ->addSelect(DB::raw("'ergonomi' as data_type"));
    }

    private function buildSinarUvDetail(string $noSampel): Collection
    {
        $data = SinarUvHeader::with('datalapangan', 'ws_udara', 'order_detail')
            ->where('no_sampel', $noSampel)
            ->where('is_approved', true)
            ->where('is_active', true)
            ->select('*')
            ->addSelect(DB::raw("'sinar_uv' as data_type"))
            ->get();

        foreach ($data as $item) {
            $item->nab = $this->resolveSinarUvNab($item->datalapangan->waktu_pemaparan ?? null);
            $regulasi = json_decode($item->order_detail->regulasi ?? 'null');
            $item->method = $regulasi ? explode('-', $regulasi[0])[1] : null;
        }

        return $data;
    }

    private function buildMedanLmDetail(string $noSampel): Collection
    {
        $data = MedanLmHeader::with('datalapangan', 'ws_udara')
            ->where('no_sampel', $noSampel)
            ->where('is_approve', true)
            ->where('is_active', true)
            ->select('*')
            ->addSelect(DB::raw("'medan_lm' as data_type"))
            ->get();

        foreach ($data as $item) {
            $regulasi = json_decode($item->orderDetail->regulasi ?? 'null');
            $item->method = $regulasi ? explode('-', $regulasi[0])[1] : null;
        }

        return $data;
    }

    private function buildStandardDetail(Request $request): Collection
    {
        $noSampel = $request->no_sampel;
        $items = $this->fetchHeaders($noSampel);
        $this->attachWsValues($items);
        $this->enrichMetadata($items, $noSampel, $request->regulasi);
        $this->appendNilaiUji($items, $request->parameter);

        return $items;
    }

    private function fetchHeaders(string $noSampel): Collection
    {
        $direct = DirectLainHeader::query()
            ->where('no_sampel', $noSampel)->where('is_approve', 1)->where('status', 0)
            ->select(array_merge(self::HEADER_SELECT, ['is_approve']))
            ->addSelect(DB::raw("'direct' as data_type"))
            ->get();

        $partikulat = PartikulatHeader::query()
            ->where('no_sampel', $noSampel)->where('is_approve', 1)->where('status', 0)
            ->select(array_merge(self::HEADER_SELECT, ['is_approve']))
            ->addSelect(DB::raw("'partikulat' as data_type"))
            ->get();

        $lingkungan = LingkunganHeader::query()
            ->where('no_sampel', $noSampel)->where('is_approved', 1)->where('status', 0)
            ->select(array_merge(self::HEADER_SELECT, ['is_approved']))
            ->addSelect(DB::raw("'lingkungan' as data_type"))
            ->get();

        $subkontrak = Subkontrak::query()
            ->where('no_sampel', $noSampel)->where('is_approve', 1)
            ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'is_active')
            ->addSelect(DB::raw('lhps as status'))
            ->addSelect(DB::raw("'subKontrak' as data_type"))
            ->get();

        $microbio = MicrobioHeader::query()
            ->where('no_sampel', $noSampel)->where('is_approved', 1)->where('status', 0)
            ->select(array_merge(self::HEADER_SELECT, ['is_approved']))
            ->addSelect(DB::raw("'microbio' as data_type"))
            ->get();

        $debuPersonal = DebuPersonalHeader::query()
            ->where('no_sampel', $noSampel)->where('is_approved', 1)->where('is_active', 1)
            ->select(array_merge(self::HEADER_SELECT, ['is_approved']))
            ->addSelect(DB::raw("'debu_personal' as data_type"))
            ->get();

        return $lingkungan
            ->merge($subkontrak)
            ->merge($partikulat)
            ->merge($direct)
            ->merge($microbio)
            ->merge($debuPersonal)
            ->each(function ($item) {
                $item->source = self::SOURCE_LABELS[$item->data_type] ?? null;
            });
    }

    private function attachWsValues(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $idsByFk = [];
        foreach (self::WS_UDARA_FK as $dataType => $fkColumn) {
            $ids = $items->where('data_type', $dataType)->pluck('id')->filter()->values();
            if ($ids->isNotEmpty()) {
                $idsByFk[$fkColumn] = $ids;
            }
        }

        $wsUdaraByFk = $this->loadWsUdaraMaps($idsByFk);
        $lingkunganIds = $items->where('data_type', 'lingkungan')->pluck('id');
        $wsLingkunganMap = $lingkunganIds->isEmpty()
            ? collect()
            : WsValueLingkungan::whereIn('lingkungan_header_id', $lingkunganIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('lingkungan_header_id');

        foreach ($items as $item) {
            $fkColumn = self::WS_UDARA_FK[$item->data_type] ?? null;
            if ($fkColumn) {
                $item->setRelation('ws_udara', $wsUdaraByFk[$fkColumn][$item->id] ?? null);
            }

            if ($item->data_type === 'lingkungan') {
                $item->setRelation('ws_value_linkungan', $wsLingkunganMap->get($item->id));
            }
        }
    }

    private function loadWsUdaraMaps(array $idsByFk): array
    {
        $maps = [];
        foreach (array_keys($idsByFk) as $fkColumn) {
            $maps[$fkColumn] = [];
        }

        if ($idsByFk === []) {
            return $maps;
        }

        $rows = WsValueUdara::query()
            ->where('is_active', true)
            ->where(function ($query) use ($idsByFk) {
                foreach ($idsByFk as $fkColumn => $ids) {
                    $query->orWhereIn($fkColumn, $ids);
                }
            })
            ->get();

        foreach ($idsByFk as $fkColumn => $ids) {
            $idSet = array_flip($ids->all());
            foreach ($rows as $row) {
                $headerId = $row->$fkColumn;
                if ($headerId !== null && isset($idSet[$headerId])) {
                    $maps[$fkColumn][$headerId] = $row;
                }
            }
        }

        return $maps;
    }

    private function enrichMetadata(Collection $items, string $noSampel, $idRegulasi): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $parameters = $items->pluck('parameter')->filter()->unique()->values();

        $durasiMap = DetailLingkunganHidup::where('no_sampel', $noSampel)
            ->whereIn('parameter', $parameters)
            ->pluck('durasi_pengambilan', 'parameter');

        $bakuMutuMap = MasterBakumutu::whereIn('parameter', $parameters)
            ->where('id_regulasi', $idRegulasi)
            ->where('is_active', 1)
            ->get()
            ->keyBy('parameter');

        foreach ($items as $item) {
            $bakuMutu = $bakuMutuMap->get($item->parameter);
            $item->durasi = $durasiMap->get($item->parameter);
            $item->satuan = $bakuMutu->satuan ?? null;
            $item->baku_mutu = $bakuMutu->baku_mutu ?? null;
            $item->method = $bakuMutu->method ?? null;
            $item->nama_header = $bakuMutu->nama_header ?? null;
        }
    }

    private function appendNilaiUji(Collection $items, ?string $parameterJson): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $parameterIds = collect(json_decode($parameterJson ?? '[]'))
            ->map(fn ($item) => explode(';', $item)[0] ?? null)
            ->filter()
            ->values();

        $satuanMap = WsNilaiUjiResolver::satuanIndexMap();
        $mdlLimitMap = WsNilaiUjiResolver::buildMdlLimitMap(
            MdlUdara::whereIn('parameter_id', $parameterIds)->get()
        );

        foreach ($items as $item) {
            $item->nilai_uji = WsNilaiUjiResolver::resolve($item, $satuanMap, $mdlLimitMap);
        }
    }

    private function resolveSinarUvNab(?float $waktu): ?float
    {
        if ($waktu === null) {
            return null;
        }

        foreach (self::SINAR_UV_NAB as [$min, $max, $nab]) {
            if ($waktu >= $min && $waktu < $max) {
                return $nab;
            }
        }

        return null;
    }
}
