<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderHeader;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class MonitoringJadwalSamplingSalesController extends Controller
{
    public function index(Request $request)
    {
        $today = $request->tanggal;

        // cek programmer
        $isProgrammer = MasterKaryawan::where('nama_lengkap', $this->karyawan)
            ->whereIn('id_jabatan', [41, 42])
            ->exists();

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        // BASE QUERY
        $query = OrderHeader::where('is_active', true)
            // Gunakan whereHas untuk memfilter OrderHeader yang PUNYA sampling di tanggal tsb
            ->whereHas('orderDetail', function ($q) use ($today) {
                $q->whereDate('tanggal_sampling', $today)
                ->where('is_active', true);
            })
            // Tetap gunakan with agar datanya muncul di kolom tabel
            ->with([
                'orderDetail' => function ($q) use ($today) {
                    $q->whereDate('tanggal_sampling', $today)->where('is_active', true);
                },
                'jadwal' => function ($q) use ($today) {
                    $q->whereDate('tanggal', $today)->where('is_active', true);
                }
            ]);


        // 🔐 FILTER BERDASARKAN ROLE
        if (! $isProgrammer) {

            // SALES
            if (in_array($jabatan, [24, 86, 148])) {
                $query->where('sales_id', $this->user_id);

            // ATASAN SALES
            } elseif (in_array($jabatan, [21, 15, 154, 157])) {
                $bawahan = GetBawahan::where('id', $this->user_id)
                    ->pluck('id')
                    ->toArray();

                $bawahan[] = $this->user_id;

                $query->whereIn('sales_id', $bawahan);
            }
        }

        return DataTables::of($query)
        ->addColumn('tgl_sampling_display', function($order) {
            // Ambil baris pertama dari relasi orderDetail
            $detail = $order->orderDetail->first();
            return $detail ? $detail->tanggal_sampling : '-';
        })
        ->addColumn('durasi', function($order) {
            // Ambil baris pertama dari relasi orderDetail
            $detail = $order->jadwal->first();
            return $detail ? $detail->durasi : '-';
        })
        ->addColumn('need_attention', function($order) {
            // Logika flag dipindah ke sini agar lebih efisien
            $hasSampling = $order->orderDetail->isNotEmpty();
            $hasJadwal = $order->jadwal->isNotEmpty();
            return $hasSampling && !$hasJadwal;
        })
        ->make(true);
    }
}