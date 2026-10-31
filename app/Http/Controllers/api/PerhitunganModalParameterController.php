<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\Parameter;
use App\Models\PerhitunganModalParameter;
use App\Services\PerhitunganModalParameterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PerhitunganModalParameterController extends Controller
{
    private function getEffectiveDateExpression($alias = 'mpmp')
    {
        return PerhitunganModalParameterService::effectiveDateExpression($alias);
    }

    private function resolveRecordStatus(PerhitunganModalParameter $item, $today = null, $effective = null)
    {
        $today = $today ?: Carbon::today()->toDateString();

        if ($effective === null) {
            $effectiveDate = $this->getEffectiveDateExpression('master_perhitungan_modal_parameter');
            $effective = PerhitunganModalParameter::query()
                ->where('id_parameter', $item->id_parameter)
                ->where('is_active', true)
                ->whereNull('deleted_at')
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

    private function softDeleteRecord(PerhitunganModalParameter $data)
    {
        $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->is_active = false;
        $data->save();
    }

    private function getEffectiveModalQuery($today = null)
    {
        $today = $today ?: Carbon::today()->toDateString();
        $effectiveDateSub = $this->getEffectiveDateExpression('mpmp2');
        $effectiveDateFuture = $this->getEffectiveDateExpression('mpmp4');
        $effectiveDatePrev = $this->getEffectiveDateExpression('mpmp3');

        return DB::table('master_perhitungan_modal_parameter as mpmp')
            ->leftJoin('parameter as mp', 'mpmp.id_parameter', '=', 'mp.id')
            ->where('mpmp.is_active', true)
            ->whereNull('mpmp.deleted_at')
            ->whereRaw("mpmp.id = (
                SELECT COALESCE(
                    (
                        SELECT mpmp2.id FROM master_perhitungan_modal_parameter mpmp2
                        WHERE mpmp2.id_parameter = mpmp.id_parameter
                        AND mpmp2.deleted_at IS NULL
                        AND mpmp2.is_active = 1
                        AND {$effectiveDateSub} <= ?
                        ORDER BY {$effectiveDateSub} DESC, mpmp2.id DESC
                        LIMIT 1
                    ),
                    (
                        SELECT mpmp4.id FROM master_perhitungan_modal_parameter mpmp4
                        WHERE mpmp4.id_parameter = mpmp.id_parameter
                        AND mpmp4.deleted_at IS NULL
                        AND mpmp4.is_active = 1
                        AND {$effectiveDateFuture} > ?
                        ORDER BY {$effectiveDateFuture} ASC, mpmp4.id DESC
                        LIMIT 1
                    )
                )
            )", [$today, $today])
            ->select(
                'mpmp.id',
                'mpmp.id_kategori',
                'mpmp.nama_kategori',
                'mpmp.id_parameter',
                'mpmp.nama_parameter',
                DB::raw('mp.nama_lab as nama_parameter_lab'),
                DB::raw('mp.nama_regulasi as nama_parameter_regulasi'),
                'mpmp.total_modal',
                'mpmp.tanggal_berlaku',
                'mpmp.catatan',
                'mpmp.created_by',
                'mpmp.created_at',
                'mp.is_blocked',
                DB::raw('(
                    SELECT COUNT(*) - 1 FROM master_perhitungan_modal_parameter h
                    WHERE h.id_parameter = mpmp.id_parameter
                    AND h.deleted_at IS NULL
                    AND h.is_active = 1
                ) as history_count'),
                DB::raw("(
                    SELECT mpmp3.total_modal FROM master_perhitungan_modal_parameter mpmp3
                    WHERE mpmp3.id_parameter = mpmp.id_parameter
                    AND mpmp3.deleted_at IS NULL
                    AND mpmp3.is_active = 1
                    AND {$effectiveDatePrev} <= '{$today}'
                    ORDER BY {$effectiveDatePrev} DESC, mpmp3.id DESC
                    LIMIT 1 OFFSET 1
                ) as modal_sebelumnya"),
                DB::raw("(
                    SELECT COALESCE(
                        JSON_ARRAYAGG(comp.row_json),
                        JSON_ARRAY()
                    )
                    FROM (
                        SELECT JSON_OBJECT(
                            'label', k.label,
                            'harga', CAST(k.harga AS DECIMAL(18, 2)),
                            'urutan', k.urutan
                        ) AS row_json
                        FROM master_perhitungan_modal_parameter_komponen k
                        WHERE k.id_perhitungan_modal = mpmp.id
                        ORDER BY k.urutan ASC, k.id ASC
                    ) AS comp
                ) as komponen_perhitungan")
            );
    }

    public function getAll(Request $request)
    {
        $data = $this->getEffectiveModalQuery()->get();

        return response()->json(['data' => $data], 200);
    }

    public function index()
    {
        $data = $this->getEffectiveModalQuery();

        return Datatables::of($data)
            ->filterColumn('nama_kategori', function ($query, $keyword) {
                $query->where('mpmp.nama_kategori', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_parameter_lab', function ($query, $keyword) {
                $query->where(function ($nested) use ($keyword) {
                    $nested->where('mp.nama_lab', 'like', "%{$keyword}%")
                        ->orWhere('mpmp.nama_parameter', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_parameter_regulasi', function ($query, $keyword) {
                $query->where('mp.nama_regulasi', 'like', "%{$keyword}%");
            })
            ->filterColumn('total_modal', function ($query, $keyword) {
                $query->where('mpmp.total_modal', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_berlaku', function ($query, $keyword) {
                $query->where('mpmp.tanggal_berlaku', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('mpmp.created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('mpmp.created_at', 'like', "%{$keyword}%");
            })
            ->filterColumn('komponen_perhitungan', function ($query, $keyword) {
                $query->whereExists(function ($sub) use ($keyword) {
                    $sub->select(DB::raw(1))
                        ->from('master_perhitungan_modal_parameter_komponen as k')
                        ->whereColumn('k.id_perhitungan_modal', 'mpmp.id')
                        ->where(function ($nested) use ($keyword) {
                            $nested->where('k.label', 'like', "%{$keyword}%")
                                ->orWhere('k.harga', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderColumn('nama_kategori', function ($query, $order) {
                $query->orderBy('mpmp.nama_kategori', $order);
            })
            ->orderColumn('nama_parameter_lab', function ($query, $order) {
                $query->orderByRaw('COALESCE(mp.nama_lab, mpmp.nama_parameter) ' . (strtolower((string) $order) === 'desc' ? 'DESC' : 'ASC'));
            })
            ->orderColumn('nama_parameter_regulasi', function ($query, $order) {
                $query->orderBy('mp.nama_regulasi', $order);
            })
            ->orderColumn('komponen_perhitungan', function ($query, $order) {
                $direction = strtolower((string) $order) === 'desc' ? 'DESC' : 'ASC';
                $query->orderByRaw(
                    "(SELECT COUNT(*) FROM master_perhitungan_modal_parameter_komponen k WHERE k.id_perhitungan_modal = mpmp.id) {$direction}"
                );
            })
            ->orderColumn('total_modal', function ($query, $order) {
                $query->orderBy('mpmp.total_modal', $order);
            })
            ->orderColumn('tanggal_berlaku', function ($query, $order) {
                $query->orderBy('mpmp.tanggal_berlaku', $order);
            })
            ->orderColumn('created_by', function ($query, $order) {
                $query->orderBy('mpmp.created_by', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('mpmp.created_at', $order);
            })
            ->make(true);
    }

    public function show(Request $request)
    {
        if ($request->id == '') {
            return response()->json(['message' => 'Data tidak ditemukan'], 400);
        }

        $header = PerhitunganModalParameter::with('komponen')
            ->where('id', $request->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$header) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $today = Carbon::today()->toDateString();
        $header->status = $this->resolveRecordStatus($header, $today);

        return response()->json(['data' => $header], 200);
    }

    public function getHistory(Request $request)
    {
        if ($request->id_parameter == '') {
            return response()->json(['message' => 'Parameter wajib dipilih'], 400);
        }

        $today = Carbon::today()->toDateString();
        $effectiveDate = $this->getEffectiveDateExpression('master_perhitungan_modal_parameter');

        $effective = PerhitunganModalParameter::query()
            ->where('id_parameter', $request->id_parameter)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$today])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first();

        $data = PerhitunganModalParameter::with('komponen')
            ->where('id_parameter', $request->id_parameter)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->get()
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

            $komponen = PerhitunganModalParameterService::normalizeKomponenInput($request->komponen);
            if (count($komponen) === 0) {
                return response()->json(['message' => 'Minimal satu komponen perhitungan wajib diisi'], 400);
            }

            $totalModal = PerhitunganModalParameterService::sumKomponen($komponen);
            $fingerprint = PerhitunganModalParameterService::fingerprintKomponen($komponen);
            $tanggalBerlaku = Carbon::parse($request->tanggal_berlaku)->format('Y-m-d');
            $now = Carbon::now()->format('Y-m-d H:i:s');

            if ($request->id != '') {
                $parameterOld = PerhitunganModalParameter::with('komponen')
                    ->where('id', $request->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$parameterOld) {
                    return response()->json(['message' => 'Data tidak ditemukan'], 404);
                }

                $oldKomponen = $parameterOld->komponen->map(function ($row) {
                    return [
                        'label' => $row->label,
                        'harga' => (int) $row->harga,
                    ];
                })->all();

                $oldFingerprint = PerhitunganModalParameterService::fingerprintKomponen($oldKomponen);
                if ($oldFingerprint === $fingerprint && (int) $parameterOld->total_modal === $totalModal) {
                    return response()->json(['message' => 'Perhitungan modal sudah sama dengan versi sebelumnya'], 400);
                }

                $newParam = new PerhitunganModalParameter;
                $newParam->id_kategori = $parameterOld->id_kategori;
                $newParam->id_parameter = $parameterOld->id_parameter;
                $newParam->nama_parameter = trim($parameterOld->nama_parameter);
                $newParam->nama_kategori = trim($parameterOld->nama_kategori);
                $newParam->total_modal = $totalModal;
                $newParam->tanggal_berlaku = $tanggalBerlaku;
                $newParam->id_hist = $parameterOld->id;
                $newParam->catatan = $request->catatan ? trim($request->catatan) : null;
                $newParam->created_by = $this->karyawan;
                $newParam->created_at = $now;
                $newParam->save();

                PerhitunganModalParameterService::persistKomponen((int) $newParam->id, $komponen);
            } else {
                if ($request->id_parameter == '') {
                    return response()->json(['message' => 'Parameter wajib dipilih'], 400);
                }

                $cek = PerhitunganModalParameter::where('id_parameter', $request->id_parameter)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($cek) {
                    return response()->json(['message' => 'Parameter sudah memiliki perhitungan modal. Gunakan ubah data untuk versi baru.'], 400);
                }

                $ambilParameter = Parameter::where('id', $request->id_parameter)->first();
                if (!$ambilParameter) {
                    return response()->json(['message' => 'Parameter tidak ditemukan'], 404);
                }

                $parameter = new PerhitunganModalParameter;
                $parameter->nama_kategori = trim($ambilParameter->nama_kategori);
                $parameter->id_kategori = $ambilParameter->id_kategori;
                $parameter->id_parameter = $request->id_parameter;
                $parameter->nama_parameter = trim($ambilParameter->nama_lab);
                $parameter->total_modal = $totalModal;
                $parameter->tanggal_berlaku = $tanggalBerlaku;
                $parameter->catatan = $request->catatan ? trim($request->catatan) : null;
                $parameter->created_by = $this->karyawan;
                $parameter->created_at = $now;
                $parameter->save();

                PerhitunganModalParameterService::persistKomponen((int) $parameter->id, $komponen);
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

        $data = PerhitunganModalParameter::where('id', $request->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$data) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $today = Carbon::today()->toDateString();
        $status = $this->resolveRecordStatus($data, $today);
        $source = $request->source ?: 'main';

        if ($source === 'history') {
            if ($status !== 'Akan Berlaku') {
                return response()->json([
                    'message' => 'Hanya perhitungan dengan status Akan Berlaku yang dapat dihapus dari history',
                ], 400);
            }

            $this->softDeleteRecord($data);

            return response()->json([
                'message' => 'Perhitungan modal yang akan berlaku berhasil dihapus',
            ], 201);
        }

        if ($status !== 'Berlaku') {
            return response()->json([
                'message' => 'Hanya perhitungan yang sedang berlaku yang dapat dihapus dari menu utama',
            ], 400);
        }

        $hasOtherHistory = PerhitunganModalParameter::where('id_parameter', $data->id_parameter)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('id', '!=', $data->id)
            ->exists();

        $this->softDeleteRecord($data);

        if ($hasOtherHistory) {
            return response()->json([
                'message' => 'Perhitungan modal berhasil dihapus dan dikembalikan ke versi sebelumnya',
            ], 201);
        }

        return response()->json([
            'message' => 'Perhitungan modal parameter berhasil dihapus',
        ], 201);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();

        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }

    public function getParameterBelumModalAll(Request $request)
    {
        $data = DB::table('parameter as mp')
            ->leftJoin('master_perhitungan_modal_parameter as mpmp', function ($join) {
                $join->on('mp.id', '=', 'mpmp.id_parameter')
                    ->where('mpmp.is_active', true)
                    ->whereNull('mpmp.deleted_at');
            })
            ->whereNull('mpmp.id')
            ->where('mp.is_active', true)
            ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori', 'mp.is_blocked')
            ->orderBy('mp.nama_kategori')
            ->orderBy('mp.nama_lab')
            ->get();

        return response()->json(['data' => $data], 200);
    }

    public function getParameterBelumModal(Request $request)
    {
        try {
            $data = DB::table('parameter as mp')
                ->leftJoin('master_perhitungan_modal_parameter as mpmp', function ($join) {
                    $join->on('mp.id', '=', 'mpmp.id_parameter')
                        ->where('mpmp.is_active', true)
                        ->whereNull('mpmp.deleted_at');
                })
                ->whereNull('mpmp.id')
                ->where('mp.is_active', true)
                ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori', 'mp.is_blocked');

            return Datatables::of($data)
                ->filterColumn('nama_lab', function ($query, $keyword) {
                    $query->where('mp.nama_lab', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_kategori', function ($query, $keyword) {
                    $query->where('mp.nama_kategori', 'like', "%{$keyword}%");
                })
                ->orderColumn('nama_kategori', function ($query, $order) {
                    $query->orderBy('mp.nama_kategori', $order);
                })
                ->orderColumn('nama_lab', function ($query, $order) {
                    $query->orderBy('mp.nama_lab', $order);
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getParameter(Request $request)
    {
        $idKategori = $request->id_kategori;

        $data = DB::table('parameter as mp')
            ->leftJoin('master_perhitungan_modal_parameter as mpmp', function ($join) {
                $join->on('mp.id', '=', 'mpmp.id_parameter')
                    ->where('mpmp.is_active', true)
                    ->whereNull('mpmp.deleted_at');
            })
            ->whereNull('mpmp.id')
            ->where('mp.is_active', true)
            ->where('mp.id_kategori', $idKategori)
            ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori')
            ->get();

        return response()->json([
            'data' => $data,
        ], 201);
    }
}
