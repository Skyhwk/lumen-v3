<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use App\Models\PenggunaanMobilDetail;
use App\Models\PenggunaanMobilHeader;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class PenggunaanMobilController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->mode === 'history' ? 'history' : 'active';
        $today = Carbon::today()->format('Y-m-d');

        $data = PenggunaanMobilHeader::with(['mobil', 'driver', 'requester', 'details' => function ($query) {
            $query->where('is_active', true)->orderBy('tanggal_penggunaan');
        }])->where('is_active', true)
            ->when($mode === 'active', function ($query) use ($today) {
                $query->whereHas('details', function ($q) use ($today) {
                    $q->where('is_active', true)->where('tanggal_penggunaan', '>=', $today);
                });
            })
            ->when($mode === 'history', function ($query) use ($today) {
                $query->whereHas('details', function ($q) use ($today) {
                    $q->where('is_active', true)->where('tanggal_penggunaan', '<', $today);
                })->whereDoesntHave('details', function ($q) use ($today) {
                    $q->where('is_active', true)->where('tanggal_penggunaan', '>=', $today);
                });
            })
            ->orderByDesc('id');

        return Datatables::of($data)
            ->addColumn('tanggal_mulai', function ($row) {
                return optional($row->details->first())->tanggal_penggunaan;
            })
            ->addColumn('tanggal_selesai', function ($row) {
                return optional($row->details->last())->tanggal_penggunaan;
            })
            ->addColumn('total_hari', function ($row) {
                return $row->details->count();
            })
            ->filterColumn('mobil.plat_mobil', function ($query, $keyword) {
                $query->whereHas('mobil', function ($q) use ($keyword) {
                    $q->where('plat_mobil', 'like', "%{$keyword}%")
                        ->orWhere('merk_mobil', 'like', "%{$keyword}%")
                        ->orWhere('tipe_mobil', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('driver.nama_driver', function ($query, $keyword) {
                $query->whereHas('driver', function ($q) use ($keyword) {
                    $q->where('nama_driver', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('requester.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('requester', function ($q) use ($keyword) {
                    $q->where('nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('nik_karyawan', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getOptions(Request $request)
    {
        $mobil = DaftarMobil::where('is_active', true)
            ->orderBy('plat_mobil')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => trim($item->plat_mobil . ' - ' . $item->merk_mobil . ' ' . $item->tipe_mobil),
                ];
            });

        $driver = MasterDriver::where('is_active', true)
            ->orderBy('nama_driver')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->user_id,
                    'text' => $item->nama_driver,
                ];
            });

        $karyawan = MasterKaryawan::where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nik_karyawan', 'nama_lengkap'])
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => trim($item->nik_karyawan . ' - ' . $item->nama_lengkap),
                ];
            });

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => [
                'mobil' => $mobil,
                'driver' => $driver,
                'karyawan' => $karyawan,
            ],
        ], 200);
    }

    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobil_id' => ['required', 'integer'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'available' => false,
            ], 422);
        }

        $dates = $this->buildDateRange($request->tanggal_mulai, $request->tanggal_selesai);
        $conflicts = $this->getConflicts($request->mobil_id, $dates, $request->id);

        return response()->json([
            'message' => $conflicts->isEmpty() ? 'Mobil available' : 'Mobil tidak available pada tanggal yang dipilih',
            'available' => $conflicts->isEmpty(),
            'data' => $conflicts,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'integer'],
            'mobil_id' => ['required', 'integer'],
            'driver_id' => ['nullable'],
            'requester_id' => ['required', 'integer'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $dates = $this->buildDateRange($request->tanggal_mulai, $request->tanggal_selesai);
        $conflicts = $this->getConflicts($request->mobil_id, $dates, $request->id);

        if ($conflicts->isNotEmpty()) {
            return response()->json([
                'message' => 'Mobil tidak available pada tanggal: ' . $conflicts->pluck('tanggal_penggunaan')->implode(', '),
                'data' => $conflicts,
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->id) {
                $header = PenggunaanMobilHeader::where('id', $request->id)->where('is_active', true)->first();

                if (!$header) {
                    DB::rollBack();
                    return response()->json(['message' => 'Data Not Found.!'], 404);
                }

                $header->mobil_id = $request->mobil_id;
                $header->driver_id = $request->driver_id ?: null;
                $header->requester_id = $request->requester_id;
                $header->keterangan = trim($request->keterangan);
                $header->updated_at = date('Y-m-d H:i:s');
                $header->updated_by = $this->karyawan;
                $header->save();

                PenggunaanMobilDetail::where('header_id', $header->id)->update([
                    'is_active' => false,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $this->karyawan,
                ]);
            } else {
                $header = PenggunaanMobilHeader::create([
                    'mobil_id' => $request->mobil_id,
                    'driver_id' => $request->driver_id ?: null,
                    'requester_id' => $request->requester_id,
                    'keterangan' => trim($request->keterangan),
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }

            foreach ($dates as $date) {
                PenggunaanMobilDetail::create([
                    'header_id' => $header->id,
                    'mobil_id' => $request->mobil_id,
                    'tanggal_penggunaan' => $date,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => $request->id ? 'Penggunaan mobil updated successfully' : 'Penggunaan mobil created successfully',
            ], $request->id ? 200 : 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }

        try {
            DB::beginTransaction();

            $header = PenggunaanMobilHeader::where('id', $request->id)->where('is_active', true)->first();

            if (!$header) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            $header->deleted_at = date('Y-m-d H:i:s');
            $header->deleted_by = $this->karyawan;
            $header->is_active = false;
            $header->save();

            PenggunaanMobilDetail::where('header_id', $header->id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan,
                'is_active' => false,
            ]);

            DB::commit();

            return response()->json(['message' => 'Penggunaan mobil deleted successfully'], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    protected function buildDateRange($startDate, $endDate): array
    {
        $period = CarbonPeriod::create(Carbon::parse($startDate), Carbon::parse($endDate));
        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    protected function getConflicts($mobilId, array $dates, $excludeHeaderId = null)
    {
        return PenggunaanMobilDetail::with(['header.requester'])
            ->where('mobil_id', $mobilId)
            ->where('is_active', true)
            ->whereIn('tanggal_penggunaan', $dates)
            ->when($excludeHeaderId, function ($query) use ($excludeHeaderId) {
                $query->where('header_id', '!=', $excludeHeaderId);
            })
            ->whereHas('header', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('tanggal_penggunaan')
            ->get()
            ->map(function ($item) {
                return [
                    'tanggal_penggunaan' => $item->tanggal_penggunaan,
                    'requester' => optional(optional($item->header)->requester)->nama_lengkap,
                    'keterangan' => optional($item->header)->keterangan,
                ];
            });
    }
}
