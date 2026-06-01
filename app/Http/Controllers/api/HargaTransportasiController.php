<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HargaTransportasi;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class HargaTransportasiController extends Controller
{
    private function getEffectiveDateExpression($alias = 'mht')
    {
        return HargaTransportasi::effectiveDateExpression($alias);
    }

    private function normalizePrice($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return $normalized;
    }

    private function priceToInteger($value)
    {
        $normalized = $this->normalizePrice($value);

        if ($normalized === null || $normalized === '') {
            return 0;
        }

        return (int) round((float) $normalized);
    }

    private function copyTransportPricesFrom(HargaTransportasi $source, HargaTransportasi $target)
    {
        $target->status = $source->status;
        $target->wilayah = $source->wilayah;
        $target->transportasi = $source->transportasi;
        $target->per_orang = $source->per_orang;
        $target->total = $source->total;
        $target->tiket = $source->tiket;
        $target->penginapan = $source->penginapan;
        $target->{'24jam'} = $source->{'24jam'};
    }

    private function calculateAdjustedPrice($currentValue, $direction, $persentase)
    {
        $currentValue = (float) $currentValue;

        if ($currentValue <= 0) {
            return null;
        }

        if ($direction === 'Naik') {
            $newValue = $currentValue + ($currentValue * $persentase / 100);
        } else {
            $newValue = $currentValue - ($currentValue * $persentase / 100);
        }

        return max(0, round($newValue, 2));
    }

    private function resolveRecordStatus(HargaTransportasi $item, $today = null, $effective = null)
    {
        $today = $today ?: Carbon::today()->toDateString();

        if ($effective === null) {
            $effectiveDate = $this->getEffectiveDateExpression('master_harga_transportasi');
            $effective = HargaTransportasi::query()
                ->where('wilayah', $item->wilayah)
                ->where('status', $item->status)
                ->where('is_active', true)
                ->whereRaw("{$effectiveDate} <= ?", [$today])
                ->orderByRaw("{$effectiveDate} DESC")
                ->orderBy('id', 'desc')
                ->first();
        }

        $itemDate = $item->tanggal_berlaku
            ?: ($item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : '1970-01-01');

        if ($itemDate > $today) {
            return 'Akan Berlaku';
        }

        if ($effective && (int) $item->id === (int) $effective->id) {
            return 'Berlaku';
        }

        return 'Riwayat';
    }

    private function softDeleteRecord(HargaTransportasi $data)
    {
        $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->is_active = false;
        $data->save();
    }

    private function previousValueSubquery($field, $today)
    {
        $effectiveDatePrev = $this->getEffectiveDateExpression('mht3');

        return "(
            SELECT mht3.{$field} FROM master_harga_transportasi mht3
            WHERE mht3.wilayah = mht.wilayah
            AND mht3.status = mht.status
            AND mht3.deleted_at IS NULL
            AND {$effectiveDatePrev} <= '{$today}'
            ORDER BY {$effectiveDatePrev} DESC, mht3.id DESC
            LIMIT 1 OFFSET 1
        )";
    }

    private function getEffectiveHargaQuery($today = null, $statusFilter = null)
    {
        $today = $today ?: Carbon::today()->toDateString();
        $effectiveDate = $this->getEffectiveDateExpression('mht');
        $effectiveDateSub = $this->getEffectiveDateExpression('mht2');

        $query = DB::table('master_harga_transportasi as mht')
            ->where('mht.is_active', true)
            ->whereNull('mht.deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$today])
            ->whereRaw("mht.id = (
                SELECT mht2.id FROM master_harga_transportasi mht2
                WHERE mht2.wilayah = mht.wilayah
                AND mht2.status = mht.status
                AND mht2.deleted_at IS NULL
                AND mht2.is_active = true
                AND {$effectiveDateSub} <= ?
                ORDER BY {$effectiveDateSub} DESC, mht2.id DESC
                LIMIT 1
            )", [$today])
            ->select(
                'mht.id',
                'mht.status',
                'mht.wilayah',
                'mht.transportasi',
                'mht.per_orang',
                'mht.total',
                'mht.tiket',
                'mht.penginapan',
                'mht.24jam',
                'mht.tanggal_berlaku',
                'mht.created_by',
                'mht.created_at',
                DB::raw('(
                    SELECT COUNT(*) - 1 FROM master_harga_transportasi h
                    WHERE h.wilayah = mht.wilayah
                    AND h.status = mht.status
                    AND h.deleted_at IS NULL
                ) as history_count'),
                DB::raw($this->previousValueSubquery('transportasi', $today) . ' as transportasi_sebelumnya'),
                DB::raw($this->previousValueSubquery('per_orang', $today) . ' as per_orang_sebelumnya'),
                DB::raw($this->previousValueSubquery('tiket', $today) . ' as tiket_sebelumnya'),
                DB::raw($this->previousValueSubquery('penginapan', $today) . ' as penginapan_sebelumnya'),
                DB::raw($this->previousValueSubquery('24jam', $today) . ' as 24jam_sebelumnya')
            );

        if ($statusFilter) {
            $query->where('mht.status', $statusFilter);
        }

        return $query;
    }

    public function getAll(Request $request)
    {
        $data = $this->getEffectiveHargaQuery(null, $request->status ?: null)->get();

        return response()->json(['data' => $data], 200);
    }

    public function index(Request $request)
    {
        $data = $this->getEffectiveHargaQuery(null, $request->status ?: null);

        return Datatables::of($data)
            ->filterColumn('status', function ($query, $keyword) {
                $query->where('mht.status', 'like', "%{$keyword}%");
            })
            ->filterColumn('wilayah', function ($query, $keyword) {
                $query->where('mht.wilayah', 'like', "%{$keyword}%");
            })
            ->filterColumn('transportasi', function ($query, $keyword) {
                $query->where('mht.transportasi', 'like', "%{$keyword}%");
            })
            ->filterColumn('per_orang', function ($query, $keyword) {
                $query->where('mht.per_orang', 'like', "%{$keyword}%");
            })
            ->filterColumn('tiket', function ($query, $keyword) {
                $query->where('mht.tiket', 'like', "%{$keyword}%");
            })
            ->filterColumn('penginapan', function ($query, $keyword) {
                $query->where('mht.penginapan', 'like', "%{$keyword}%");
            })
            ->filterColumn('24jam', function ($query, $keyword) {
                $query->where('mht.24jam', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_berlaku', function ($query, $keyword) {
                $query->where('mht.tanggal_berlaku', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('mht.created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('mht.created_at', 'like', "%{$keyword}%");
            })
            ->orderColumn('status', fn($query, $order) => $query->orderBy('mht.status', $order))
            ->orderColumn('wilayah', fn($query, $order) => $query->orderBy('mht.wilayah', $order))
            ->orderColumn('transportasi', fn($query, $order) => $query->orderBy('mht.transportasi', $order))
            ->orderColumn('per_orang', fn($query, $order) => $query->orderBy('mht.per_orang', $order))
            ->orderColumn('tiket', fn($query, $order) => $query->orderBy('mht.tiket', $order))
            ->orderColumn('penginapan', fn($query, $order) => $query->orderBy('mht.penginapan', $order))
            ->orderColumn('24jam', fn($query, $order) => $query->orderBy('mht.24jam', $order))
            ->orderColumn('tanggal_berlaku', fn($query, $order) => $query->orderBy('mht.tanggal_berlaku', $order))
            ->orderColumn('created_by', fn($query, $order) => $query->orderBy('mht.created_by', $order))
            ->orderColumn('created_at', fn($query, $order) => $query->orderBy('mht.created_at', $order))
            ->make(true);
    }

    public function getHistory(Request $request)
    {
        if ($request->wilayah == '' || $request->status == '') {
            return response()->json(['message' => 'Wilayah dan kategori wilayah wajib dipilih'], 400);
        }

        $today = Carbon::today()->toDateString();
        $effectiveDate = $this->getEffectiveDateExpression('master_harga_transportasi');

        $effective = HargaTransportasi::query()
            ->where('wilayah', $request->wilayah)
            ->where('status', $request->status)
            ->where('is_active', true)
            ->whereRaw("{$effectiveDate} <= ?", [$today])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first();

        $data = HargaTransportasi::where('wilayah', $request->wilayah)
            ->where('status', $request->status)
            ->where('is_active', true)
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->get([
                'id',
                'transportasi',
                'per_orang',
                'total',
                'tiket',
                'penginapan',
                '24jam',
                'tanggal_berlaku',
                'created_by',
                'created_at',
            ])
            ->map(function ($item) use ($today, $effective) {
                $item->status = $this->resolveRecordStatus($item, $today, $effective);
                $item->can_delete = $item->status === 'Akan Berlaku';
                return $item;
            });

        return response()->json(['data' => $data], 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->tanggal_berlaku == '') {
                return response()->json(['message' => 'Tanggal berlaku wajib diisi'], 400);
            }

            $tanggalBerlaku = Carbon::parse($request->tanggal_berlaku)->format('Y-m-d');

            if ($request->id != '') {
                $transportOld = HargaTransportasi::find($request->id);

                if (!$transportOld) {
                    return response()->json(['message' => 'Wilayah tidak ditemukan'], 404);
                }

                $fields = ['transportasi', 'per_orang', 'total', 'tiket', 'penginapan', '24jam'];
                $hasChange = false;

                foreach ($fields as $field) {
                    $oldVal = $this->priceToInteger($transportOld->{$field} ?? 0);
                    $newVal = $this->priceToInteger($request->{$field} ?? 0);
                    if ($oldVal !== $newVal) {
                        $hasChange = true;
                        break;
                    }
                }

                if (!$hasChange) {
                    return response()->json(['message' => 'Harga transportasi sudah sama'], 400);
                }

                $newTransport = new HargaTransportasi;
                $newTransport->status = $transportOld->status;
                $newTransport->wilayah = $transportOld->wilayah;
                $newTransport->transportasi = $this->normalizePrice($request->transportasi) ?? $transportOld->transportasi;
                $newTransport->per_orang = $this->normalizePrice($request->per_orang) ?? $transportOld->per_orang;
                $newTransport->total = $this->normalizePrice($request->total) ?? $transportOld->total;
                $newTransport->tiket = $this->normalizePrice($request->tiket) ?? $transportOld->tiket;
                $newTransport->penginapan = $this->normalizePrice($request->penginapan) ?? $transportOld->penginapan;
                $newTransport->{'24jam'} = $this->normalizePrice($request->{'24jam'}) ?? $transportOld->{'24jam'};
                $newTransport->tanggal_berlaku = $tanggalBerlaku;
                $newTransport->id_hist = $transportOld->id;
                $newTransport->created_by = $this->karyawan;
                $newTransport->created_at = Carbon::now();
                $newTransport->save();
            } else {
                $existingHargaTransport = HargaTransportasi::where('wilayah', $request->wilayah)
                    ->where('status', $request->status)
                    ->where('is_active', true)
                    ->first();

                if ($existingHargaTransport) {
                    return response()->json(['message' => 'Data untuk wilayah dan status tersebut sudah ada'], 400);
                }

                $harga_transport = new HargaTransportasi;
                $harga_transport->status = $request->status != '' ? $request->status : null;
                $harga_transport->wilayah = $request->wilayah != '' ? $request->wilayah : null;
                $harga_transport->transportasi = $this->normalizePrice($request->transportasi);
                $harga_transport->per_orang = $this->normalizePrice($request->per_orang);
                $harga_transport->total = $this->normalizePrice($request->total);
                $harga_transport->tiket = $this->normalizePrice($request->tiket);
                $harga_transport->penginapan = $this->normalizePrice($request->penginapan);
                $harga_transport->{'24jam'} = $this->normalizePrice($request->{'24jam'});
                $harga_transport->tanggal_berlaku = $tanggalBerlaku;
                $harga_transport->created_by = $this->karyawan;
                $harga_transport->created_at = Carbon::now();
                $harga_transport->save();
            }

            DB::commit();
            return response()->json(['message' => 'Data telah disimpan'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        if ($request->id == '') {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }

        $data = HargaTransportasi::where('id', $request->id)->where('is_active', true)->first();

        if (!$data) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $today = Carbon::today()->toDateString();
        $status = $this->resolveRecordStatus($data, $today);
        $source = $request->source ?: 'main';

        if ($source === 'history') {
            if ($status !== 'Akan Berlaku') {
                return response()->json([
                    'message' => 'Hanya harga dengan status Akan Berlaku yang dapat dihapus dari history',
                ], 400);
            }

            $this->softDeleteRecord($data);

            return response()->json([
                'message' => 'Harga yang akan berlaku berhasil dihapus',
            ], 201);
        }

        if ($status !== 'Berlaku') {
            return response()->json([
                'message' => 'Hanya harga yang sedang berlaku yang dapat dihapus dari menu utama',
            ], 400);
        }

        $hasOtherHistory = HargaTransportasi::where('wilayah', $data->wilayah)
            ->where('status', $data->status)
            ->where('is_active', true)
            ->where('id', '!=', $data->id)
            ->exists();

        $this->softDeleteRecord($data);

        if ($hasOtherHistory) {
            return response()->json([
                'message' => 'Harga berhasil dihapus dan dikembalikan ke harga sebelumnya',
            ], 201);
        }

        return response()->json([
            'message' => 'Harga transportasi berhasil dihapus',
        ], 201);
    }

    public function updateGlobal(Request $request)
    {
        DB::beginTransaction();

        try {
            $required = [
                'kategori_wilayah' => 'Kategori Wilayah Tidak Boleh Kosong',
                'kategori_harga'   => 'Kategori Harga Tidak Boleh Kosong',
                'status'           => 'Status Kenaikan Tidak Boleh Kosong',
                'persentase'       => 'Persentase Tidak Boleh Kosong',
            ];

            foreach ($required as $field => $message) {
                if (!$request->$field) {
                    return response()->json(['message' => $message], 400);
                }
            }

            if ($request->tanggal_berlaku == '') {
                return response()->json(['message' => 'Tanggal berlaku wajib diisi'], 400);
            }

            $allowedFields = ['transportasi', 'per_orang', 'total', 'tiket', 'penginapan', '24jam'];
            $field = $request->kategori_harga;

            if (!in_array($field, $allowedFields, true)) {
                return response()->json(['message' => 'Kategori harga tidak valid'], 400);
            }

            if (!in_array($request->kategori_wilayah, ['DALAM KOTA', 'LUAR KOTA'], true)) {
                return response()->json(['message' => 'Kategori wilayah tidak valid'], 400);
            }

            if (in_array($field, ['tiket', 'penginapan'], true) && $request->kategori_wilayah !== 'LUAR KOTA') {
                return response()->json(['message' => 'Tiket dan penginapan hanya berlaku untuk Luar Kota'], 400);
            }

            if (!in_array($request->status, ['Naik', 'Turun'], true)) {
                return response()->json(['message' => 'Status kenaikan tidak valid'], 400);
            }

            $persentase = (float) $request->persentase;
            if ($persentase <= 0) {
                return response()->json(['message' => 'Persentase harus lebih dari 0'], 400);
            }

            $tanggalBerlaku = Carbon::parse($request->tanggal_berlaku)->format('Y-m-d');
            $effectiveIds = $this->getEffectiveHargaQuery(null, $request->kategori_wilayah)->pluck('id');

            if ($effectiveIds->isEmpty()) {
                return response()->json(['message' => 'Tidak ada data harga berlaku untuk diupdate'], 404);
            }

            $olds = HargaTransportasi::whereIn('id', $effectiveIds)->get();
            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($olds as $old) {
                $newValue = $this->calculateAdjustedPrice($old->{$field} ?? 0, $request->status, $persentase);

                if ($newValue === null) {
                    $skippedCount++;
                    continue;
                }

                if ($this->priceToInteger($newValue) === $this->priceToInteger($old->{$field} ?? 0)) {
                    $skippedCount++;
                    continue;
                }

                $newTransport = new HargaTransportasi;
                $this->copyTransportPricesFrom($old, $newTransport);
                $newTransport->{$field} = $newValue;
                $newTransport->tanggal_berlaku = $tanggalBerlaku;
                $newTransport->id_hist = $old->id;
                $newTransport->created_by = $this->karyawan;
                $newTransport->created_at = Carbon::now();
                $newTransport->save();

                $updatedCount++;
            }

            if ($updatedCount === 0) {
                DB::rollBack();
                return response()->json(['message' => 'Tidak ada harga yang berhasil diupdate'], 400);
            }

            DB::commit();

            $message = "Berhasil mengupdate {$updatedCount} data harga";
            if ($skippedCount > 0) {
                $message .= " ({$skippedCount} dilewati karena harga kosong atau tidak berubah)";
            }

            return response()->json(['message' => $message], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
