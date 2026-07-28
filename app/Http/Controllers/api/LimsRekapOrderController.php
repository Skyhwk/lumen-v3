<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Lims\OrderHeader;
use DataTables;

class LimsRekapOrderController extends Controller
{
    public function index(Request $request)
    {
        // Ambil data langsung dari OrderHeader saja sesuai permintaan
        $data = OrderHeader::with(['orderDetail'])
            ->where('is_active', 1);

        if ($request->has('date_start') || $request->has('date_end')) {
            $dateStart = $request->date_start;
            $dateEnd   = $request->date_end;

            // Kita filter berdasarkan tanggal order atau tanggal terima jika di OrderHeader
            // Atau jika tanggal_sampling ada di orderDetail
            if (!empty($dateStart) || !empty($dateEnd)) {
                $data->whereHas('orderDetail', function ($q) use ($dateStart, $dateEnd) {
                    if (!empty($dateStart) && !empty($dateEnd)) {
                        $q->whereBetween('tanggal_sampling', [$dateStart, $dateEnd]);
                    } elseif (!empty($dateStart)) {
                        $q->where('tanggal_sampling', '>=', $dateStart);
                    } elseif (!empty($dateEnd)) {
                        $q->where('tanggal_sampling', '<=', $dateEnd);
                    }
                });
            }
        }

        $data = $data->orderBy('id', 'desc');

        $allRecords = (clone $data)->get();
        $total_biaya_akhir = 0;
        
        foreach ($allRecords as $record) {
            $total_biaya_akhir += (float) ($record->biaya_akhir ?? 0);
        }

        return DataTables::of($data)
            ->addColumn('tanggal_sampling', function ($row) {
                // Ambil tanggal sampling dari order detail pertama jika ada
                if ($row->orderDetail && $row->orderDetail->count() > 0) {
                    return $row->orderDetail->first()->tanggal_sampling;
                }
                return null;
            })
            ->addColumn('kategori_1', function ($row) {
                // Ambil kategori_1 dari order detail pertama jika ada
                if ($row->orderDetail && $row->orderDetail->count() > 0) {
                    return $row->orderDetail->first()->kategori_1;
                }
                return null;
            })
            ->with('total_biaya_akhir', $total_biaya_akhir)
            ->addIndexColumn()
            ->make(true);
    }
}
