<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\JadwalMobilisasi;
use App\Models\JadwalMobilisasiAset;
use App\Models\KategoriPeralatanSampling;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class PreparasiMonitoringAlatController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $this->tanggal($request);
        $status = $request->input('status', 'unset');

        $query = $this->baseQuery($tanggal);
        if ($status === 'set') {
            $query->whereNotNull('assigned.id_mobilisasi');
        } else {
            $query->whereNull('assigned.id_mobilisasi');
        }

        $rows = $query->get()->map(function ($row) {
            $peralatan = $this->decodePeralatan($row->peralatan_sampling);
            $row->request_alat = $this->requestSummary($peralatan);
            $row->jumlah_request = $this->requestCount($peralatan);
            $row->jumlah_set = (int) ($row->jumlah_set ?? 0);
            $row->sampler = $this->uniqueNames($row->sampler ?? '');
            return $row;
        });

        return Datatables::of($rows)->skipPaging()->make(true);
    }

    public function counts(Request $request)
    {
        $tanggal = $this->tanggal($request);

        return response()->json([
            'unset' => $this->baseQuery($tanggal)->whereNull('assigned.id_mobilisasi')->count(),
            'set' => $this->baseQuery($tanggal)->whereNotNull('assigned.id_mobilisasi')->count(),
        ]);
    }

    public function show(Request $request)
    {
        $mobilisasi = JadwalMobilisasi::where('is_active', true)->find($request->input('id'));
        if (!$mobilisasi) {
            return response()->json(['message' => 'Data mobilisasi tidak ditemukan.'], 404);
        }

        $assigned = JadwalMobilisasiAset::query()
            ->where('id_mobilisasi', $mobilisasi->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $peralatan = $this->decodePeralatan($mobilisasi->peralatan_sampling);
        $jenisByNama = $this->jenisPeralatanByNama($peralatan);
        $groups = [];
        $remainingAssigned = $assigned;
        foreach ($peralatan as $index => $item) {
            $nama = trim((string) ($item['nama_alat'] ?? ''));
            $jumlah = max(1, (int) ($item['jumlah_alat'] ?? 1));
            $kategoriId = $this->nullableInt($item['kategori_aset_id'] ?? null);
            $subKategoriId = $this->nullableInt($item['sub_kategori_aset_id'] ?? null);
            $jenis = trim((string) ($item['jenis_peralatan'] ?? ''));
            if ($jenis === '' && $nama !== '') {
                $jenis = $jenisByNama[strtolower($nama)] ?? '';
            }
            $matched = $this->takeAssigned($remainingAssigned, $nama, $kategoriId, $subKategoriId);
            $pickedIds = array_map(fn ($row) => (int) $row->id_aset, $matched);
            $slots = array_pad($pickedIds, max($jumlah, count($pickedIds)), null);
            $groups[] = [
                'key' => $index,
                'nama_alat' => $nama,
                'jenis_peralatan' => $jenis !== '' ? $jenis : null,
                'jumlah_alat' => $jumlah,
                'kategori_aset_id' => $kategoriId,
                'sub_kategori_aset_id' => $subKategoriId,
                'slots' => $slots,
                'options' => $this->assetOptions($kategoriId, $subKategoriId, $nama, array_filter($slots), $jenis),
            ];
        }

        return response()->json([
            'data' => [
                'id' => $mobilisasi->id,
                'no_quotation' => $mobilisasi->no_quotation,
                'tanggal' => optional($mobilisasi->tanggal)->format('Y-m-d'),
                'tujuan_sampling' => $mobilisasi->tujuan_sampling,
                'waktu_berangkat' => $mobilisasi->waktu_berangkat,
                'groups' => $groups,
                'assigned' => $assigned->values(),
            ],
        ]);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_mobilisasi' => 'required|integer',
            'items' => 'array',
            'items.*.id_aset' => 'required|integer',
            'items.*.nama_alat' => 'nullable|string',
            'items.*.kategori_aset_id' => 'nullable|integer',
            'items.*.sub_kategori_aset_id' => 'nullable|integer',
            'items.*.request_index' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $mobilisasi = JadwalMobilisasi::where('is_active', true)->find($request->input('id_mobilisasi'));
        if (!$mobilisasi) {
            return response()->json(['message' => 'Data mobilisasi tidak ditemukan.'], 404);
        }

        $items = array_values(array_filter($request->input('items', []), fn ($item) => !empty($item['id_aset'])));
        $ids = array_map(fn ($item) => (int) $item['id_aset'], $items);
        if (count($ids) !== count(array_unique($ids))) {
            return response()->json(['message' => 'Aset yang sama tidak boleh dipilih lebih dari sekali dalam tim ini.'], 422);
        }

        $requests = $this->decodePeralatan($mobilisasi->peralatan_sampling);
        foreach ($items as $item) {
            if ($this->matchRequestIndex($requests, $item) === null) {
                return response()->json(['message' => 'Ada aset yang tidak sesuai request alat mobilisasi.'], 422);
            }
        }

        $currentIds = JadwalMobilisasiAset::query()
            ->where('id_mobilisasi', $mobilisasi->id)
            ->where('is_active', true)
            ->pluck('id_aset')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($ids as $idAset) {
            if (in_array($idAset, $currentIds, true)) {
                continue;
            }
            if (!$this->eligibleAsset()->where('id', $idAset)->exists()) {
                return response()->json(['message' => 'Ada aset yang tidak tersedia untuk dipilih.'], 422);
            }
        }

        DB::transaction(function () use ($mobilisasi, $items) {
            JadwalMobilisasiAset::where('id_mobilisasi', $mobilisasi->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now(),
                ]);

            foreach ($items as $item) {
                JadwalMobilisasiAset::create([
                    'id_mobilisasi' => $mobilisasi->id,
                    'id_aset' => (int) $item['id_aset'],
                    'nama_alat' => $item['nama_alat'] ?? null,
                    'kategori_aset_id' => $this->nullableInt($item['kategori_aset_id'] ?? null),
                    'sub_kategori_aset_id' => $this->nullableInt($item['sub_kategori_aset_id'] ?? null),
                    'created_by' => $this->karyawan,
                    'updated_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }
        });

        return response()->json(['message' => 'Aset tim berhasil disimpan.']);
    }

    private function baseQuery($tanggal)
    {
        $assigned = DB::table('jadwal_mobilisasi_aset')
            ->select('id_mobilisasi', DB::raw('COUNT(*) as jumlah_set'), DB::raw('MAX(created_by) as set_by'), DB::raw('MAX(created_at) as set_at'))
            ->where('is_active', true)
            ->groupBy('id_mobilisasi');

        // Tim = sampler di batch jadwal yang sama dengan Sampling Plan (1–2 orang).
        // Jangan join by no_quotation+tanggal: itu menggabungkan semua tim QT hari itu + driver.
        $tim = DB::table('jadwal as j')
            ->join('jadwal_mobilisasi as m', function ($join) {
                $join->whereRaw('FIND_IN_SET(j.id, m.batch_id) > 0')
                    ->where('m.is_active', true);
            })
            ->where('j.is_active', true)
            ->where('j.tanggal', $tanggal)
            ->groupBy('m.id')
            ->select(
                'm.id as id_mobilisasi',
                DB::raw('MAX(j.nama_perusahaan) as nama_perusahaan'),
                DB::raw('MAX(j.wilayah) as wilayah'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(j.sampler), '') SEPARATOR ', ') as sampler")
            );

        return JadwalMobilisasi::query()
            ->from('jadwal_mobilisasi as m')
            ->leftJoinSub($assigned, 'assigned', function ($join) {
                $join->on('assigned.id_mobilisasi', '=', 'm.id');
            })
            ->leftJoinSub($tim, 'tim', function ($join) {
                $join->on('tim.id_mobilisasi', '=', 'm.id');
            })
            ->where('m.is_active', true)
            ->where('m.tanggal', $tanggal)
            ->select(
                'm.id',
                'm.id_sampling',
                'm.no_quotation',
                'm.tanggal',
                'm.tujuan_sampling',
                'm.waktu_berangkat',
                'm.peralatan_sampling',
                'm.warna',
                'tim.nama_perusahaan',
                'tim.wilayah',
                'tim.sampler',
                'assigned.jumlah_set',
                'assigned.set_by',
                'assigned.set_at'
            )
            ->orderBy('m.id');
    }

    private function eligibleAsset()
    {
        return GaAsset::query()
            ->where('is_active', true)
            ->where('publication_state', 'published')
            ->where('lifecycle_state', 'active')
            ->where('condition', 'normal')
            ->where('record_kind', GaAsset::RECORD_UNIT);
    }

    private function assetOptions($kategoriId, $subKategoriId, $namaAlat, array $keepIds = [], $jenisPeralatan = null)
    {
        $query = $this->eligibleAsset()->with([
            'subKategori:id,nama_sub_kategori',
            'kategori:id,nama_kategori',
        ]);
        if ($subKategoriId) {
            $query->where('sub_kategori_aset_id', $subKategoriId);
        } elseif ($kategoriId) {
            $query->where('kategori_aset_id', $kategoriId);
        } elseif ($namaAlat !== '') {
            $query->where('name', 'like', '%' . $namaAlat . '%');
        }

        $options = $query->orderBy('cs_code')->orderBy('name')->get();

        $keepIds = array_values(array_unique(array_map('intval', $keepIds)));
        $missing = array_diff($keepIds, $options->pluck('id')->map(fn ($id) => (int) $id)->all());
        if ($missing) {
            $options = $options->concat(
                GaAsset::with(['subKategori:id,nama_sub_kategori', 'kategori:id,nama_kategori'])
                    ->whereIn('id', $missing)
                    ->get()
            );
        }

        $jenisFallback = trim((string) $jenisPeralatan);

        return $options->map(function ($asset) use ($jenisFallback) {
            return $this->presentAssetOption($asset, $jenisFallback);
        })->values();
    }

    private function presentAssetOption(GaAsset $asset, $jenisFallback = '')
    {
        $cs = trim((string) ($asset->cs_code ?? ''));
        $name = trim((string) ($asset->name ?? ''));
        $jenis = trim((string) (
            optional($asset->subKategori)->nama_sub_kategori
            ?: optional($asset->kategori)->nama_kategori
            ?: $jenisFallback
        ));
        $label = $cs !== '' && $name !== '' && strcasecmp($cs, $name) !== 0
            ? $cs . ' — ' . $name
            : ($cs !== '' ? $cs : $name);

        return [
            'id' => $asset->id,
            'text' => $label,
            'label' => $label,
            'name' => $name,
            'cs_code' => $cs,
            'jenis_peralatan' => $jenis !== '' ? $jenis : null,
            'kategori_aset_id' => $asset->kategori_aset_id,
            'sub_kategori_aset_id' => $asset->sub_kategori_aset_id,
        ];
    }

    private function jenisPeralatanByNama(array $peralatan)
    {
        $names = collect($peralatan)
            ->map(fn ($item) => trim((string) ($item['nama_alat'] ?? '')))
            ->filter()
            ->unique()
            ->values();
        if ($names->isEmpty() || !class_exists(KategoriPeralatanSampling::class)) {
            return [];
        }

        return KategoriPeralatanSampling::query()
            ->where('is_active', true)
            ->whereIn('nama_alat', $names->all())
            ->whereNotNull('jenis_peralatan')
            ->whereRaw("TRIM(jenis_peralatan) <> ''")
            ->get(['nama_alat', 'jenis_peralatan'])
            ->mapWithKeys(function ($row) {
                return [strtolower(trim((string) $row->nama_alat)) => trim((string) $row->jenis_peralatan)];
            })
            ->all();
    }

    private function takeAssigned(&$assigned, $nama, $kategoriId, $subKategoriId)
    {
        $picked = [];
        $remaining = [];
        foreach ($assigned as $row) {
            $rowNama = trim((string) $row->nama_alat);
            $matchNama = $nama !== '' && $rowNama !== '' && strcasecmp($rowNama, $nama) === 0;
            $matchSub = $subKategoriId && (int) $row->sub_kategori_aset_id === $subKategoriId;
            $matchKat = !$subKategoriId && $kategoriId && (int) $row->kategori_aset_id === $kategoriId;
            if ($matchNama || ($rowNama === '' && ($matchSub || $matchKat))) {
                $picked[] = $row;
            } else {
                $remaining[] = $row;
            }
        }
        $assigned = collect($remaining);

        return $picked;
    }

    private function matchRequestIndex(array $requests, array $item)
    {
        if (array_key_exists('request_index', $item) && $item['request_index'] !== null && $item['request_index'] !== '') {
            $index = (int) $item['request_index'];
            if (isset($requests[$index]) && $this->requestItemMatches($requests[$index], $item)) {
                return $index;
            }
        }

        $nama = trim((string) ($item['nama_alat'] ?? ''));
        foreach ($requests as $index => $requestItem) {
            if ($this->requestItemMatches($requestItem, $item, $nama !== '')) {
                return $index;
            }
        }

        return null;
    }

    private function requestItemMatches(array $requestItem, array $item, $requireNama = false)
    {
        $reqNama = trim((string) ($requestItem['nama_alat'] ?? ''));
        $nama = trim((string) ($item['nama_alat'] ?? ''));
        $reqSub = $this->nullableInt($requestItem['sub_kategori_aset_id'] ?? null);
        $reqKat = $this->nullableInt($requestItem['kategori_aset_id'] ?? null);
        $sub = $this->nullableInt($item['sub_kategori_aset_id'] ?? null);
        $kat = $this->nullableInt($item['kategori_aset_id'] ?? null);

        if ($reqNama !== '' && $nama !== '') {
            return strcasecmp($reqNama, $nama) === 0;
        }
        if ($requireNama) {
            return false;
        }
        if ($reqSub && $sub) {
            return $reqSub === $sub;
        }
        if ($reqKat && $kat) {
            return $reqKat === $kat;
        }

        return $reqNama !== '' && $nama !== '' && strcasecmp($reqNama, $nama) === 0;
    }

    private function decodePeralatan($value)
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private function requestSummary(array $items)
    {
        if (!$items) {
            return '-';
        }

        return collect($items)
            ->map(fn ($item) => trim(($item['nama_alat'] ?? 'Alat') . ' x' . (int) ($item['jumlah_alat'] ?? 0)))
            ->implode(', ');
    }

    private function requestCount(array $items)
    {
        return (int) collect($items)->sum(fn ($item) => (int) ($item['jumlah_alat'] ?? 0));
    }

    private function tanggal(Request $request)
    {
        $tanggal = $request->input('tanggal');
        if (!$tanggal || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return Carbon::now('Asia/Jakarta')->toDateString();
        }

        return $tanggal;
    }

    private function uniqueNames($raw)
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
    }

    private function nullableInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value ?: null;
    }
}
