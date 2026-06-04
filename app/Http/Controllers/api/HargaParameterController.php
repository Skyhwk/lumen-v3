<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Models\HargaParameter;
use App\Models\Parameter;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class HargaParameterController extends Controller
{
    private function getEffectiveDateExpression($alias = 'mhp')
    {
        return "COALESCE({$alias}.tanggal_berlaku, DATE({$alias}.created_at), '1970-01-01')";
    }

    private function resolveRecordStatus(HargaParameter $item, $today = null, $effective = null)
    {
        $today = $today ?: Carbon::today()->toDateString();

        if ($effective === null) {
            $effectiveDate = $this->getEffectiveDateExpression('master_harga_parameter');
            $effective = HargaParameter::query()
                ->where('id_parameter', $item->id_parameter)
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

    private function softDeleteRecord(HargaParameter $data)
    {
        $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->is_active = false;
        $data->save();
    }

    private function getEffectiveHargaQuery($today = null)
    {
        $today = $today ?: Carbon::today()->toDateString();
        $effectiveDate = $this->getEffectiveDateExpression('mhp');
        $effectiveDateSub = $this->getEffectiveDateExpression('mhp2');
        $effectiveDatePrev = $this->getEffectiveDateExpression('mhp3');

        return DB::table('master_harga_parameter as mhp')
            ->leftJoin('parameter as mp', 'mhp.id_parameter', '=', 'mp.id')
            ->where('mhp.is_active', true)
            ->whereRaw("{$effectiveDate} <= ?", [$today])
            ->whereRaw("mhp.id = (
                SELECT mhp2.id FROM master_harga_parameter mhp2
                WHERE mhp2.id_parameter = mhp.id_parameter
                AND mhp2.deleted_at IS NULL
                AND {$effectiveDateSub} <= ?
                ORDER BY {$effectiveDateSub} DESC, mhp2.id DESC
                LIMIT 1
            )", [$today])
            ->select(
                'mhp.id',
                'mhp.id_kategori',
                'mhp.nama_kategori',
                'mhp.id_parameter',
                'mhp.nama_parameter',
                'mhp.harga',
                'mhp.tanggal_berlaku',
                'mhp.regen',
                'mhp.volume',
                'mhp.created_by',
                'mhp.created_at',
                'mp.is_blocked',
                DB::raw('(
                    SELECT COUNT(*) - 1 FROM master_harga_parameter h
                    WHERE h.id_parameter = mhp.id_parameter
                    AND h.deleted_at IS NULL
                ) as history_count'),
                DB::raw("(
                    SELECT mhp3.harga FROM master_harga_parameter mhp3
                    WHERE mhp3.id_parameter = mhp.id_parameter
                    AND mhp3.deleted_at IS NULL
                    AND {$effectiveDatePrev} <= '{$today}'
                    ORDER BY {$effectiveDatePrev} DESC, mhp3.id DESC
                    LIMIT 1 OFFSET 1
                ) as harga_sebelumnya")
            );
    }

    public function index()
    {
        $data = $this->getEffectiveHargaQuery();

        return Datatables::of($data)
            ->filterColumn('nama_kategori', function ($query, $keyword) {
                $query->where('mhp.nama_kategori', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_parameter', function ($query, $keyword) {
                $query->where('mhp.nama_parameter', 'like', "%{$keyword}%");
            })
            ->filterColumn('harga', function ($query, $keyword) {
                $query->where('mhp.harga', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_berlaku', function ($query, $keyword) {
                $query->where('mhp.tanggal_berlaku', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('mhp.created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('mhp.created_at', 'like', "%{$keyword}%");
            })
            ->orderColumn('nama_kategori', function ($query, $order) {
                $query->orderBy('mhp.nama_kategori', $order);
            })
            ->orderColumn('nama_parameter', function ($query, $order) {
                $query->orderBy('mhp.nama_parameter', $order);
            })
            ->orderColumn('harga', function ($query, $order) {
                $query->orderBy('mhp.harga', $order);
            })
            ->orderColumn('tanggal_berlaku', function ($query, $order) {
                $query->orderBy('mhp.tanggal_berlaku', $order);
            })
            ->orderColumn('created_by', function ($query, $order) {
                $query->orderBy('mhp.created_by', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('mhp.created_at', $order);
            })
            ->make(true);
    }

    public function getHistory(Request $request)
    {
        if ($request->id_parameter == '') {
            return response()->json(['message' => 'Parameter wajib dipilih'], 400);
        }

        $today = Carbon::today()->toDateString();
        $effectiveDate = $this->getEffectiveDateExpression('master_harga_parameter');

        $effective = HargaParameter::query()
            ->where('id_parameter', $request->id_parameter)
            ->where('is_active', true)
            ->whereRaw("{$effectiveDate} <= ?", [$today])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first();

        $data = HargaParameter::where('id_parameter', $request->id_parameter)
            ->where('is_active', true)
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->get(['id', 'harga', 'tanggal_berlaku', 'created_by', 'created_at'])
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
                $parameterOld = HargaParameter::find($request->id);

                if (!$parameterOld) {
                    return response()->json(['message' => 'Parameter tidak ditemukan'], 404);
                }

                $hargaLama = (int) preg_replace('/[^0-9]/', '', $parameterOld->harga);
                $hargaBaru = (int) preg_replace('/[^0-9]/', '', $request->harga);

                if ($hargaLama === $hargaBaru) {
                    return response()->json(['message' => 'Harga parameter sudah sama'], 400);
                }

                $newParam = new HargaParameter;
                $newParam->id_kategori = $parameterOld->id_kategori;
                $newParam->id_parameter = $parameterOld->id_parameter;
                $newParam->nama_parameter = $parameterOld->nama_parameter;
                $newParam->nama_kategori = $parameterOld->nama_kategori;
                $newParam->harga = str_replace('.', '', $request->harga);
                $newParam->regen = $parameterOld->regen;
                $newParam->volume = $parameterOld->volume;
                $newParam->tanggal_berlaku = $tanggalBerlaku;
                $newParam->id_hist = $parameterOld->id;
                $newParam->status = 1;
                $newParam->created_by = $this->karyawan;
                $newParam->created_at = Carbon::now();
                $newParam->save();
            } else {
                $cek_parameter = HargaParameter::where('id_parameter', $request->id_parameter)
                    ->where('is_active', true)
                    ->first();

                if ($cek_parameter) {
                    return response()->json(['message' => 'Parameter sudah ada harganya.!'], 400);
                }

                $ambil_parameter = Parameter::where('id', $request->id_parameter)->first();

                if (!$ambil_parameter) {
                    return response()->json(['message' => 'Parameter tidak ditemukan'], 404);
                }

                $parameter = new HargaParameter;
                $parameter->nama_kategori = $ambil_parameter->nama_kategori;
                $parameter->id_kategori = $ambil_parameter->id_kategori;
                $parameter->id_parameter = $request->id_parameter;
                $parameter->nama_parameter = $ambil_parameter->nama_lab;
                $parameter->harga = ($request->harga != '') ? str_replace('.', '', $request->harga) : '0.00';
                $parameter->tanggal_berlaku = $tanggalBerlaku;
                $parameter->created_by = $this->karyawan;
                $parameter->created_at = Carbon::now();
                $parameter->save();
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

        $data = HargaParameter::where('id', $request->id)->where('is_active', true)->first();
        
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

        $hasOtherHistory = HargaParameter::where('id_parameter', $data->id_parameter)
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
            'message' => 'Harga parameter berhasil dihapus',
        ], 201);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }

    public function getParameterNonHarga(Request $request)
    {
        try {
            $data = DB::table('parameter as mp')
                ->leftJoin('master_harga_parameter as mhp', function ($join) {
                    $join->on('mp.id', '=', 'mhp.id_parameter')
                        ->where('mhp.is_active', true);
                })
                ->whereNull('mhp.id')
                ->where('mp.is_active', true)
                ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori', 'mp.is_blocked');

            return Datatables::of($data)
                ->filterColumn('nama_lab', function ($query, $keyword) {
                    $query->where('mp.nama_lab', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_kategori', function ($query, $keyword) {
                    $query->where('mp.nama_kategori', 'like', "%{$keyword}%");
                })
                ->make(true);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getParameter(Request $request)
    {
        $idKategori = $request->id_kategori;

        $data = DB::table('parameter as mp')
            ->leftJoin('master_harga_parameter as mhp', function ($join) use ($idKategori) {
                $join->on('mp.id', '=', 'mhp.id_parameter')
                    ->where('mhp.id_kategori', $idKategori)
                    ->where('mhp.is_active', true);
            })
            ->whereNull('mhp.id')
            ->where('mp.is_active', true)
            ->where('mp.id_kategori', $idKategori)
            ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori')
            ->get();

        return response()->json([
            'data' => $data
        ], 201);
    }
}
