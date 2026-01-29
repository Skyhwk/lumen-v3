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
            ->with([
                'orderDetail' => function ($q) use ($today) {
                    $q->whereDate('tanggal_sampling', $today)
                    ->where('is_active', true);
                },
                'jadwal' => function ($q) use ($today) {
                    $q->whereDate('tanggal', $today)
                    ->where('is_active', true);
                }
            ]);

        // 🔐 FILTER BERDASARKAN ROLE
        if (! $isProgrammer) {

            // SALES
            if (in_array($jabatan, [24, 86, 148])) {
                $query->where('sales_id', $this->user_id);

            // ATASAN SALES
            } elseif (in_array($jabatan, [21, 15, 154, 157])) {
                $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('user_id')->toArray();

                $bawahan[] = $this->user_id;

                $query->whereIn('sales_id', $bawahan);
            }
        }

        if ($request->filled('tanggal')) {
            $query->whereHas('orderDetail', function ($q) use ($today) {
                $q->whereDate('tanggal_sampling', $today)
                ->where('is_active', true);
            });
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
        ->addColumn('created_by', function($order) {
            $detail = $order->jadwal->first();
            // Prioritas: created_by -> updated_by -> '-'
            return $detail ? ($detail->created_by ?? $detail->updated_by ?? '-') : '-';
        })
        ->addColumn('created_at', function($order) {
            $detail = $order->jadwal->first();
            // Prioritas: created_at -> updated_at -> '-'
            return $detail ? ($detail->created_at ?? $detail->updated_at ?? '-') : '-';
        })
        ->addColumn('penanggung_jawab', function($order) {
            $sales = $order->sales_id;
            $nama = MasterKaryawan::where('id', $sales)->first();
            return $nama ? $nama->nama_lengkap : '-';
        })
        ->addColumn('need_attention', function($order) {
            // Logika flag dipindah ke sini agar lebih efisien
            $hasSampling = $order->orderDetail->isNotEmpty();
            $hasJadwal = $order->jadwal->isNotEmpty();
            return $hasSampling && !$hasJadwal;
        })
        ->addColumn('kategori_list', function($order) {
            $allCategories = $order->jadwal->flatMap(function ($j) {
                $decoded = json_decode($j->kategori, true);
                return is_array($decoded) ? $decoded : [$j->kategori];
            })->filter()->unique()->values();

            return $allCategories->isNotEmpty() ? $allCategories->implode('|') : '-';
        })
        ->addColumn('sampler_list', function($order) {
            // Ambil semua isi kolom sampler dari tiap jadwal
            $allSamplers = $order->jadwal->map(function ($j) {
                return $j->sampler; // Mengambil string "Bambang Prasetyo"
            })
            ->filter()          // Menghapus data jika null/kosong
            ->unique()          // Jika nama yang sama ada di 2 jadwal, ambil 1 saja
            ->values();

            // Gabungkan dengan pemisah '|' untuk diproses di frontend
            return $allSamplers->isNotEmpty() ? $allSamplers->implode('|') : '-';
        })
        // Filter untuk kolom yang ada di database langsung
        ->filterColumn('no_document', function ($query, $keyword) {
            $query->where('no_document', 'LIKE', "%{$keyword}%");
        })

        ->filterColumn('nama_perusahaan', function ($query, $keyword) {
            $query->where('nama_perusahaan', 'LIKE', "%{$keyword}%");
        })
        // Filter untuk kolom yang ada di relasi
        ->filterColumn('kategori', function($q, $keyword) {
            $q->whereHas('jadwal', function($sub) use ($keyword) {
                $sub->where('kategori', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('sampler', function($q, $keyword) {
            $q->whereHas('jadwal', function($sub) use ($keyword) {
                $sub->where('sampler', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('tgl_sampling_display', function($q, $keyword) {
            $q->whereHas('orderDetail', function($sub) use ($keyword) {
                $sub->whereDate('tanggal_sampling', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('penanggung_jawab', function($q, $keyword) {
            $q->whereIn('sales_id', function($subQuery) use ($keyword) {
                $subQuery->select('id')
                    ->from('master_karyawan')
                    ->where('nama_lengkap', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('kategori_list', function($q, $keyword) {
            $q->whereHas('jadwal', function($sub) use ($keyword) {
                $sub->where('kategori', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('sampler_list', function($q, $keyword) {
            $q->whereHas('jadwal', function($sub) use ($keyword) {
                $sub->where('sampler', 'like', "%{$keyword}%");
            });
        })
        ->make(true);
    }
}