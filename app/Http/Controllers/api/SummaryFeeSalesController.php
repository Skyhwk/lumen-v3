<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

use App\Models\SummaryFeeSales;

class SummaryFeeSalesController extends Controller
{
    private const MONTH_COLUMNS = [
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember',
    ];

    public function index(Request $request)
    {
        $summary = SummaryFeeSales::with('sales')
            ->where('tahun', $request->tahun)
            ->whereHas('sales', function ($query) {
                $query->where(function ($sales) {
                    $sales->where('id_jabatan', 24)
                        ->orWhereHas('jabatan', function ($jabatan) {
                            $jabatan->where('nama_jabatan', 'Sales Officer');
                        });
                });
            });

        $this->whereHasNonZeroMonth($summary);

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $summary->where('sales_id', $this->user_id);
                break;

            case 148: // Customer Relation Officer
                $summary->where('sales_id', $this->user_id);
                break;
        }

        return Datatables::of($summary)->make(true);
    }

    private function whereHasNonZeroMonth($query): void
    {
        $query->where(function ($outer) {
            foreach (self::MONTH_COLUMNS as $month) {
                $outer->orWhereRaw(
                    "(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(`{$month}`, '$.amount.achieved')) AS DECIMAL(20,4)), 0) <> 0
                     OR COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(`{$month}`, '$.amount.target')) AS DECIMAL(20,4)), 0) <> 0)"
                );
            }
        });
    }
}
