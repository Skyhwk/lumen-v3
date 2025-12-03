<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\AlamatPelangganBlacklist;
use App\Models\KontakPelangganBlacklist;
use App\Models\MasterPelangganBlacklist;
use App\Models\PelangganBlacklist;
use App\Models\PicPelangganBlacklist;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
Carbon::setLocale('id');

class DaftarHitamPelangganController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterPelangganBlacklist::with(['pelanggan_blacklist', 'kontak_pelanggan_blacklist', 'alamat_pelanggan_blacklist', 'pic_pelanggan_blacklist', 'order_customer'])->where(['is_active' => true]);
        // dd($data->get());

        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $jabatan = $user->karyawan->id_jabatan;
            if ($jabatan == 24) {
                $data->where('sales_id', $this->user_id);
            }

            if ($jabatan == 21) {
                $bawahan = MasterKaryawan::where('is_active', true)->whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $data->whereIn('sales_id', $bawahan);
            }
        }

        return Datatables::of($data)
            ->filterColumn('order_customer', function ($query, $keyword) {
                if (str_contains('ordered', strtolower($keyword))) {
                    $query->whereHas('order_customer');
                } else {
                    $query->whereDoesntHave('order_customer');
                }
            })
            ->filterColumn('pelanggan_blacklist.alasan_blacklist', function ($query, $keyword) {
                $query->whereHas('pelanggan_blacklist', function ($q) use ($keyword) {
                    $q->where('alasan_blacklist', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getQuotationsHistory(Request $request)
    {
        $data = collect([
            QuotationKontrakH::class,
            QuotationNonKontrak::class
        ])->flatMap(
            fn($model) =>
            $model::with(['order', 'sales'])
                ->where([
                    'pelanggan_ID' => $request->id_pelanggan,
                    'is_active' => true,
                ])
                ->get()
        );

        return Datatables::of($data)->make(true);
    }

    public function restore(Request $request) 
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        if ($grade !== 'MANAGER') {
            // return response()->json(['message' => 'Anda tidak memiliki akses untuk merestore pelanggan'], 401);
        }

        DB::beginTransaction();
        try {
            // Master Pelanggan
            $masterPelangganBlacklist = MasterPelangganBlacklist::find($request->id);
            if (!$masterPelangganBlacklist) return response()->json(['message' => 'Pelanggan tidak ditemukan'], 404);

            $blacklist = PelangganBlacklist::where('id_pelanggan', $masterPelangganBlacklist->id)->first();
            $blacklist->restored_by = $this->karyawan;
            $blacklist->restored_at = Carbon::now();
            $blacklist->save();
    
            $a = $masterPelangganBlacklist->replicate();
            $a->setTable((new MasterPelanggan())->getTable());
            $a->id = $masterPelangganBlacklist->id;
            $a->save();

            // Kontak Pelanggan
            $kontakPelangganBlacklist = KontakPelangganBlacklist::where('pelanggan_id', $masterPelangganBlacklist->id)->get();
            foreach ($kontakPelangganBlacklist as $kp) {
                $b = $kp->replicate();
                $b->setTable((new KontakPelanggan())->getTable());
                $b->id = $kp->id;
                $b->save();

                $kp->delete();
            }

            // PIC Pelanggan
            $picPelangganBlacklist = PicPelangganBlacklist::where('pelanggan_id', $masterPelangganBlacklist->id)->get();
            foreach ($picPelangganBlacklist as $pp) {
                $c = $pp->replicate();
                $c->setTable((new PicPelanggan())->getTable());
                $c->id = $pp->id;
                $c->save();

                $pp->delete();
            }

            // Alamat Pelanggan
            $alamatPelanggan = AlamatPelangganBlacklist::where('pelanggan_id', $masterPelangganBlacklist->id)->get();
            foreach ($alamatPelanggan as $ap) {
                $d = $ap->replicate();
                $d->setTable((new AlamatPelanggan())->getTable());
                $d->id = $ap->id;
                $d->save();

                $ap->delete();
            }

            $masterPelangganBlacklist->delete();

            DB::commit();
    
            return response()->json(['message' => 'Pelanggan berhasil direstore'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
