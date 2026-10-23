<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AlamatPelanggan;
use App\Models\AlamatPelangganBackup;
use App\Models\KontakPelanggan;
use App\Models\KontakPelangganBackup;
use App\Models\MasterPelanggan;
use App\Models\MasterPelangganBackup;
use App\Models\PicPelanggan;
use App\Models\PicPelangganBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class BackupMasterPelangganController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterPelangganBackup::with([
            'kontak_pelanggan',
            'alamat_pelanggan',
            'pic_pelanggan',
            'order_customer',
        ])->where('is_active', true);

        return Datatables::of($data)
            ->filterColumn('order_customer', function ($query, $keyword) {
                if (str_contains('ordered', strtolower($keyword))) {
                    $query->whereHas('order_customer');
                } else {
                    $query->whereDoesntHave('order_customer');
                }
            })
            ->filterColumn('telpon', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('telpon', function ($query, $orderDirection) {
                $query->with(['kontak_pelanggan' => function ($q) use ($orderDirection) {
                    $q->orderBy('no_tlp_perusahaan', $orderDirection);
                }]);
            })
            ->make(true);
    }

    public function restore(Request $request)
    {
        if ($request->id === null || $request->id === '') {
            return response()->json(['message' => 'ID wajib diisi.'], 422);
        }

        $backupMaster = MasterPelangganBackup::find((int) $request->id);
        if (!$backupMaster) {
            return response()->json(['message' => 'Data backup pelanggan tidak ditemukan.'], 404);
        }

        if (MasterPelanggan::where('id_pelanggan', $backupMaster->id_pelanggan)->where('is_active', true)->exists()) {
            return response()->json([
                'message' => "Restore dibatalkan: ID Pelanggan {$backupMaster->id_pelanggan} sudah terdaftar di master pelanggan.",
            ], 409);
        }

        DB::beginTransaction();
        try {
            $master = $backupMaster->replicate();
            $master->setTable((new MasterPelanggan())->getTable());
            $master->id = null;
            $master->save();

            $newPelangganId = $master->id;

            $kontakBackup = KontakPelangganBackup::where('pelanggan_id', $backupMaster->id)->get();
            foreach ($kontakBackup as $row) {
                $item = $row->replicate();
                $item->setTable((new KontakPelanggan())->getTable());
                $item->id = null;
                $item->pelanggan_id = $newPelangganId;
                $item->save();
                $row->delete();
            }

            $picBackup = PicPelangganBackup::where('pelanggan_id', $backupMaster->id)->get();
            foreach ($picBackup as $row) {
                $item = $row->replicate();
                $item->setTable((new PicPelanggan())->getTable());
                $item->id = null;
                $item->pelanggan_id = $newPelangganId;
                $item->save();
                $row->delete();
            }

            $alamatBackup = AlamatPelangganBackup::where('pelanggan_id', $backupMaster->id)->get();
            foreach ($alamatBackup as $row) {
                $item = $row->replicate();
                $item->setTable((new AlamatPelanggan())->getTable());
                $item->id = null;
                $item->pelanggan_id = $newPelangganId;
                $item->save();
                $row->delete();
            }

            $backupMaster->delete();

            DB::commit();

            return response()->json(['message' => 'Pelanggan berhasil direstore ke master pelanggan.'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
