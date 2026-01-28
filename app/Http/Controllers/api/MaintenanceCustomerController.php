<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class MaintenanceCustomerController extends Controller
{
    public function index(Request $request)
    {
        $now          = Carbon::now();
        $sixMonthsAgo = Carbon::now()->subMonths(6)->format('Y-m-d');

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $lastOrder = DB::table('order_header')
            ->select(
                DB::raw('MAX(id) as id'),
                'id_pelanggan',
                DB::raw('MAX(tanggal_order) as tanggal_order')
            )
            ->where('flag_status', 'ordered')
            ->where('is_active', true)
            ->groupBy('id_pelanggan');

        $orderHeader = OrderHeader::joinSub($lastOrder, 'last_order', function ($join) {
            $join->on('order_header.id_pelanggan', '=', 'last_order.id_pelanggan')
                ->on('order_header.tanggal_order', '=', 'last_order.tanggal_order')
                ->on('order_header.id', '=', 'last_order.id');
        })
            ->join('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
            ->select(
                'order_header.id',
                'order_header.id_pelanggan',
                'order_header.tanggal_order',
                'order_header.nama_perusahaan',
                'order_header.konsultan',
                'order_header.no_tlp_perusahaan',
                'order_header.nama_pic_order',
                'order_header.no_pic_order',
                'order_header.sales_id',
                'master_karyawan.nama_lengkap'
            )
            ->where('order_header.tanggal_order', '<=', $sixMonthsAgo);

        switch ($jabatan) {
            case 24:
            case 148:
                $orderHeader->where('sales_id', $this->user_id);
                break;

            case 21:
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);

                $orderHeader->whereIn('sales_id', $bawahan);
                break;
        }

        $orderHeader = $orderHeader->orderBy('order_header.tanggal_order', 'desc')->get();

        return DataTables::of($orderHeader)->make(true);
    }

    public function getDetail(Request $request)
    {
        $data = OrderHeader::where('id_pelanggan', $request->id_pelanggan)
            ->join('master_karyawan', 'sales_id', '=', 'master_karyawan.id')
            ->where('nama_perusahaan', $request->nama_perusahaan)
            ->where('order_header.is_active', true)
            ->orderBy('tanggal_order', 'desc')
            ->get();

        return DataTables::of($data)->make(true);

    }

    private static function latestQuot($kontrak, $nonKontrak, $id)
    {
        $latestKontrak    = null;
        $latestNonKontrak = null;

        if ($kontrak && $kontrak->periode_kontrak_akhir != null) {
            // formatnya contoh: "05-2024"
            $periodeAkhir = Carbon::createFromFormat('m-Y', $kontrak->periode_kontrak_akhir)->endOfMonth();

            // kalau periode akhir sudah lewat bulan sekarang
            if ($periodeAkhir->lt(Carbon::now()->startOfMonth())) {
                $latestKontrak = $kontrak->updated_at ?? $kontrak->created_at;
            }
        }

        if ($nonKontrak) {
            $latestNonKontrak = $nonKontrak->updated_at ?? $nonKontrak->created_at;
        }

        // kalau dua-duanya null, ya null aja
        if (! $latestNonKontrak && ! $latestKontrak) {
            return null;
        }

        // kalau salah satu null, ambil yang gak null
        if (! $latestNonKontrak) {
            return $latestKontrak;
        }

        if (! $latestKontrak) {
            return $latestNonKontrak;
        }

        // ambil yang paling baru (tertinggi)
        return Carbon::parse($latestNonKontrak)->gt(Carbon::parse($latestKontrak))
            ? $latestNonKontrak
            : $latestKontrak;
    }
}
