<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\OrderHeader;
use App\Services\GetAtasan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class RecapDailyQuoteController extends Controller
{
    public function index(Request $request)
    {
        switch ($request->type) {
            case 'penawaran':
                $data = $this->penawaran($request->date);
                return response()->json(['data' => $data], 200);
                break;
            case 'panggilan':
                $data = $this->panggilan();
                break;
        }
    }

    private function penawaranOld($date)
    {
        try {
            $query = "
                WITH sales_staff AS (
                    SELECT 

                        u.id AS sales_id,
                        u.nama_lengkap AS sales_name,
                        u.atasan_langsung AS supervisor_ids
                    FROM 
                        master_karyawan u
                    WHERE 
                        u.id_jabatan = 24 AND u.is_active = 1
                ),

                combined_quotations AS (
                    SELECT 
                        rq.sales_id,
                        rq.created_at,
                        rq.pelanggan_ID,
                        rq.biaya_akhir,
                        'request_quotation' AS source_table
                    FROM 
                        request_quotation rq
                    WHERE 
                        rq.is_active = 1 AND DATE(rq.created_at) = '$date'
                    UNION ALL
                    SELECT 
                        rqk.sales_id,
                        rqk.created_at,
                        rqk.pelanggan_ID,
                        rqk.biaya_akhir,

                        'request_quotation_kontrak_H' AS source_table
                    FROM 
                        request_quotation_kontrak_H rqk
                    WHERE 
                        rqk.is_active = 0 AND DATE(rqk.created_at) = '$date'
                ),


                summary AS (
                    SELECT 
                        cq.sales_id,
                        cq.biaya_akhir,
                        CASE 
                            WHEN oh.id IS NOT NULL THEN 'Pelanggan Lama'
                            ELSE 'Pelanggan Baru'
                        END AS pelanggan_status
                    FROM 
                        combined_quotations cq
                    LEFT JOIN 
                        order_header oh ON cq.pelanggan_ID = oh.id_pelanggan
                ),

                sales_summary AS (
                    SELECT 
                        s.sales_id,
                        COUNT(*) AS total_penawaran,
                        SUM(CASE WHEN s.pelanggan_status = 'Pelanggan Baru' THEN 1 ELSE 0 END) AS penawaran_baru,
                        SUM(CASE WHEN s.pelanggan_status = 'Pelanggan Lama' THEN 1 ELSE 0 END) AS penawaran_lama,
                        SUM(s.biaya_akhir) AS total_biaya_akhir,
                        SUM(CASE WHEN s.pelanggan_status = 'Pelanggan Baru' THEN s.biaya_akhir ELSE 0 END) AS biaya_baru,
                        SUM(CASE WHEN s.pelanggan_status = 'Pelanggan Lama' THEN s.biaya_akhir ELSE 0 END) AS biaya_lama
                    FROM 
                        summary s
                    GROUP BY 
                        s.sales_id
                )
                SELECT 
                    'Siti Nur Faidhah' AS manager,
                    spv.nama_lengkap AS supervisor_name,
                    ss.sales_id,
                    ss.sales_name,
                    COALESCE(s.total_penawaran, 0) AS total_penawaran,
                    COALESCE(s.penawaran_baru, 0) AS penawaran_baru,
                    COALESCE(s.penawaran_lama, 0) AS penawaran_lama,
                    COALESCE(s.total_biaya_akhir, 0) AS total_biaya_akhir,
                    COALESCE(s.biaya_baru, 0) AS biaya_baru,
                    COALESCE(s.biaya_lama, 0) AS biaya_lama
                FROM 
                    sales_staff ss
                LEFT JOIN 
                    sales_summary s ON ss.sales_id = s.sales_id
                LEFT JOIN 
                    master_karyawan spv ON JSON_CONTAINS(ss.supervisor_ids, CONCAT('\"', spv.id, '\"'))
                WHERE 
                    spv.id IN ('14', '22')
                GROUP BY 
                    spv.nama_lengkap, 
                    ss.sales_id, 
                    ss.sales_name, 
                    s.total_penawaran, 
                    s.penawaran_baru, 
                    s.penawaran_lama, 
                    s.total_biaya_akhir, 
                    s.biaya_baru, 
                    s.biaya_lama
                ORDER BY
                    spv.nama_lengkap ASC
            ";
            // Execute Query
            $data = DB::select($query);

            return $data;
        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function penawaran($date)
    {
        try {
            // Buat subquery untuk data penawaran
            $quotations = QuotationNonKontrak::getQuotationSummary($date)
                ->unionAll(QuotationKontrakH::getQuotationKontrakSummary($date));

            $quotationSubquery = DB::table(DB::raw("({$quotations->toSql()}) as combined"))
                ->mergeBindings($quotations->getQuery())
                ->selectRaw('
                sales_id,
                SUM(total_request_quotation) as total_request_quotation,
                SUM(total_biaya_akhir) as total_biaya_akhir,
                SUM(total_biaya_pelanggan_baru) as total_biaya_pelanggan_baru,
                SUM(total_biaya_pelanggan_lama) as total_biaya_pelanggan_lama,
                SUM(pelanggan_baru) as pelanggan_baru,
                SUM(pelanggan_lama) as pelanggan_lama
            ')
                ->groupBy('sales_id');

            // Query utama dimulai dari master_karyawan
            $data = DB::table('master_karyawan')
                ->select(
                    'master_karyawan.id as sales_id',
                    'master_karyawan.nama_lengkap as sales_name',
                    DB::raw('COALESCE(q.total_request_quotation, 0) as total_request_quotation'),
                    DB::raw('COALESCE(q.total_biaya_akhir, 0) as total_biaya_akhir'),
                    DB::raw('COALESCE(q.total_biaya_pelanggan_baru, 0) as total_biaya_pelanggan_baru'),
                    DB::raw('COALESCE(q.total_biaya_pelanggan_lama, 0) as total_biaya_pelanggan_lama'),
                    DB::raw('COALESCE(q.pelanggan_baru, 0) as pelanggan_baru'),
                    DB::raw('COALESCE(q.pelanggan_lama, 0) as pelanggan_lama')
                )
                ->leftJoinSub($quotationSubquery, 'q', function ($join) {
                    $join->on('master_karyawan.id', '=', 'q.sales_id');
                })
                ->where(function ($query) {
                    $query->whereIn('master_karyawan.id_jabatan', [15, 21, 24, 157, 148]) // Filter cuma sales aja
                        ->orWhere('master_karyawan.id', 41);
                })
                ->where('master_karyawan.is_active', true) // Opsional: filter cuma yang aktif
                ->get();

            // Transform data untuk nambahin supervisor dan manager
            $data->transform(function ($quotation) {
                if ($quotation->sales_id) {
                    $sales = MasterKaryawan::where('id', $quotation->sales_id)->where('is_active', true)->first();
                    if ($sales && $sales->atasan_langsung) {
                        $atasanIds = json_decode($sales->atasan_langsung, true);

                        $quotation->supervisor = MasterKaryawan::whereIn('id', $atasanIds)
                            ->select('nama_lengkap')
                            ->where('grade', 'SUPERVISOR')
                            ->where('department', 'SALES')
                            ->where('is_active', true)
                            ->first();

                        if ($quotation->supervisor === null) {
                            $quotation->supervisor = (object) [
                                'nama_lengkap' => $sales->nama_lengkap
                            ];
                        }

                        $quotation->manager = MasterKaryawan::whereIn('id', $atasanIds)
                            ->select('nama_lengkap')
                            ->where('grade', 'Manager')
                            ->where('department', 'SALES')
                            ->where('is_active', true)
                            ->first();

                        if ($quotation->manager === null) {
                            $quotation->manager = (object) [
                                'nama_lengkap' => $sales->nama_lengkap
                            ];
                        }
                    } else {
                        // Fallback kalo ga ada data atasan
                        $quotation->supervisor = (object) ['nama_lengkap' => $quotation->sales_name];
                        $quotation->manager = (object) ['nama_lengkap' => $quotation->sales_name];
                    }
                }
                return $quotation;
            });

            // Sort berdasarkan supervisor name
            $data = $data->sortBy(function ($quotation) {
                return optional($quotation->supervisor)->nama_lengkap ?? '';
            })->values();

            return $data;

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* backup penawaran 
    private function penawaran($date)
    {
        try {
            $quotations = QuotationNonKontrak::getQuotationSummary($date)
                ->unionAll(QuotationKontrakH::getQuotationKontrakSummary($date));

            // $data = $quotations->get();
            $data = DB::table(DB::raw("({$quotations->toSql()}) as combined"))
                ->mergeBindings($quotations->getQuery())
                ->selectRaw('
                    master_karyawan.nama_lengkap as sales_name,
                    sales_id,
                    SUM(total_request_quotation) as total_request_quotation,
                    SUM(total_biaya_akhir) as total_biaya_akhir,
                    SUM(total_biaya_pelanggan_baru) as total_biaya_pelanggan_baru,
                    SUM(total_biaya_pelanggan_lama) as total_biaya_pelanggan_lama,
                    SUM(pelanggan_baru) as pelanggan_baru,
                    SUM(pelanggan_lama) as pelanggan_lama
                ')
                ->leftJoin('master_karyawan', 'sales_id', '=', 'master_karyawan.id')
                ->groupBy('sales_id')
                ->get();
            $data->transform(function ($quotation) {
                if ($quotation->sales_id) {
                    $sales = MasterKaryawan::where('id', $quotation->sales_id)->first();
                    $atasanIds = json_decode($sales->atasan_langsung, true);
                    $quotation->supervisor = MasterKaryawan::whereIn('id', $atasanIds)->where('grade', 'SUPERVISOR')->where('department', 'SALES')->first();
                    if ($quotation->supervisor === null) {
                        $quotation->supervisor = (object) [
                            'nama_lengkap' => $sales->nama_lengkap
                        ];
                    }
                    $quotation->manager = MasterKaryawan::whereIn('id', $atasanIds)->where('grade', 'Manager')->where('department', 'SALES')->first();
                    if ($quotation->manager === null) {
                        $quotation->manager = (object) [
                            'nama_lengkap' => $sales->nama_lengkap
                        ];
                    }
                }
                return $quotation;
            });

            $data = $data->sortBy(function ($quotation) {
                return optional($quotation->supervisor)->nama_lengkap ?? ''; 
            })->values();

            return $data;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }*/



    private function panggilan()
    {

    }

}
