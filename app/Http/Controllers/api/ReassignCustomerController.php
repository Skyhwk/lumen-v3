<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryPerubahanSales;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\RandomSalesAssigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class ReassignCustomerController extends Controller
{
    public function index(Request $request)
    {

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $query = HistoryPerubahanSales::with([
            'detailPelanggan:id_pelanggan,nama_pelanggan,wilayah,id',
            'salesLama:id,nama_lengkap',
            'salesBaru:id,nama_lengkap',
            'status'
        ])->orderByDesc('id');

        switch ($jabatan) {
            case 24: // Sales Staff
                $query->where('id_sales_baru', $this->user_id);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $query->whereIn('id_sales_baru', $bawahan);
                break;
        }

        $query = $query->filterReassignList();


        return datatables()->eloquent($query)
            ->filterColumn('detail_pelanggan.nama_pelanggan', function ($query, $keyword) {
                $query->whereHas('detailPelanggan', function ($q) use ($keyword) {
                    $q->where('nama_pelanggan', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('detail_pelanggan.wilayah', function ($query, $keyword) {
                $query->whereHas('detailPelanggan', function ($q) use ($keyword) {
                    $q->where('wilayah', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('sales_lama.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('salesLama', function ($q) use ($keyword) {
                    $q->where('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('sales_baru.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('salesBaru', function ($q) use ($keyword) {
                    $q->where('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('tanggal_rotasi', function ($query, $keyword) {
                $query->whereDate('tanggal_rotasi', 'like', "%{$keyword}%");
            })
            ->make(true);
    }


    public function test()
    {
        $randomSales = new RandomSalesAssigner;
        $randomSales->run();
    }
}