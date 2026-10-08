<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetSequence;
use App\Models\MasterSubKategoriAset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class GaAssetSequenceController extends Controller
{
    public function index(Request $request)
    {
        $data = GaAssetSequence::query()
            ->leftJoin('master_sub_kategori_aset', 'master_sub_kategori_aset.id', '=', 'ga_asset_sequences.sub_kategori_aset_id')
            ->leftJoin('master_kategori_aset', 'master_kategori_aset.id', '=', 'master_sub_kategori_aset.id_kategori')
            ->select([
                'ga_asset_sequences.id',
                'ga_asset_sequences.sub_kategori_aset_id',
                'ga_asset_sequences.last_number',
                'ga_asset_sequences.updated_at',
                'master_sub_kategori_aset.nama_sub_kategori',
                'master_kategori_aset.nama_kategori',
            ]);

        if ($request->filled('sub_kategori_aset_id')) {
            $data->where('ga_asset_sequences.sub_kategori_aset_id', (int) $request->sub_kategori_aset_id);
        }

        return Datatables::of($data)
            ->addColumn('next_cs_code', function ($row) {
                $next = ((int) $row->last_number) + 1;

                return $this->formatCsCode((string) ($row->nama_sub_kategori ?? ''), $next);
            })
            ->make(true);
    }

    public function jenisOptions()
    {
        $rows = MasterSubKategoriAset::query()
            ->leftJoin('master_kategori_aset', 'master_kategori_aset.id', '=', 'master_sub_kategori_aset.id_kategori')
            ->where('master_sub_kategori_aset.is_active', true)
            ->orderBy('master_sub_kategori_aset.nama_sub_kategori')
            ->get([
                'master_sub_kategori_aset.id',
                'master_sub_kategori_aset.nama_sub_kategori',
                'master_kategori_aset.nama_kategori',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $subKategoriId = (int) $request->input('sub_kategori_aset_id');
        $validator = Validator::make(['sub_kategori_aset_id' => $subKategoriId], [
            'sub_kategori_aset_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $subKategori = MasterSubKategoriAset::where('id', $subKategoriId)->where('is_active', true)->first();
        if (!$subKategori) {
            return response()->json(['message' => 'Jenis aset tidak ditemukan.'], 422);
        }

        $existing = GaAssetSequence::where('sub_kategori_aset_id', $subKategoriId)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Penghitung nomor untuk jenis ini sudah ada.',
                'data' => $this->present($existing, $subKategori->nama_sub_kategori),
            ]);
        }

        $sequence = GaAssetSequence::create([
            'sub_kategori_aset_id' => $subKategoriId,
            'last_number' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penghitung nomor berhasil dibuat.',
            'data' => $this->present($sequence, $subKategori->nama_sub_kategori),
        ], 201);
    }

    public function nextPreview(Request $request)
    {
        $subKategoriId = (int) $request->input('sub_kategori_aset_id');
        $subKategori = MasterSubKategoriAset::where('id', $subKategoriId)->where('is_active', true)->first();
        if (!$subKategori) {
            return response()->json(['message' => 'Jenis aset tidak ditemukan.'], 422);
        }

        $sequence = GaAssetSequence::where('sub_kategori_aset_id', $subKategoriId)->first();
        $lastNumber = $sequence ? (int) $sequence->last_number : 0;
        $next = $lastNumber + 1;

        return response()->json([
            'success' => true,
            'data' => [
                'sub_kategori_aset_id' => $subKategoriId,
                'nama_sub_kategori' => $subKategori->nama_sub_kategori,
                'last_number' => $lastNumber,
                'next_number' => $next,
                'cs_code' => $this->formatCsCode($subKategori->nama_sub_kategori, $next),
            ],
        ]);
    }

    public function allocate(Request $request)
    {
        $subKategoriId = (int) $request->input('sub_kategori_aset_id');
        $subKategori = MasterSubKategoriAset::where('id', $subKategoriId)->where('is_active', true)->first();
        if (!$subKategori) {
            return response()->json(['message' => 'Jenis aset tidak ditemukan.'], 422);
        }

        $allocated = DB::transaction(function () use ($subKategoriId, $subKategori) {
            MasterSubKategoriAset::where('id', $subKategoriId)->lockForUpdate()->first();

            $sequence = GaAssetSequence::where('sub_kategori_aset_id', $subKategoriId)->lockForUpdate()->first();
            if (!$sequence) {
                $sequence = new GaAssetSequence();
                $sequence->sub_kategori_aset_id = $subKategoriId;
                $sequence->last_number = 0;
            }

            $next = (int) $sequence->last_number + 1;
            $sequence->last_number = $next;
            $sequence->updated_at = date('Y-m-d H:i:s');
            $sequence->save();

            return [
                'id' => $sequence->id,
                'sub_kategori_aset_id' => $subKategoriId,
                'nama_sub_kategori' => $subKategori->nama_sub_kategori,
                'last_number' => $next,
                'cs_code' => $this->formatCsCode($subKategori->nama_sub_kategori, $next),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Nomor CS berhasil dialokasikan.',
            'data' => $allocated,
        ]);
    }

    private function present(GaAssetSequence $sequence, string $name): array
    {
        return [
            'id' => $sequence->id,
            'sub_kategori_aset_id' => $sequence->sub_kategori_aset_id,
            'nama_sub_kategori' => $name,
            'last_number' => (int) $sequence->last_number,
            'updated_at' => $sequence->updated_at,
        ];
    }

    private function formatCsCode(string $name, int $number): string
    {
        $slug = strtoupper(trim($name));
        $slug = preg_replace('/[^A-Z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'ASET';
        }

        return 'CS-' . $slug . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
