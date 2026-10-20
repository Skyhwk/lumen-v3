<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\RequestQR;
use App\Services\GetBawahan;
use Exception;
use Illuminate\Http\Request;

class QuotationPipelineController extends Controller
{
    public function pipelineOverview(Request $request)
    {
        try {
            $year = (int) $request->input('year', date('Y'));
            if ($year < 2000 || $year > 2100) {
                $year = (int) date('Y');
            }

            $range = $this->yearRange($year);
            $salesIds = $this->salesIds($request);
            $cabang = $request->input('id_cabang', 1);
            if (!is_numeric($cabang) || (int) $cabang <= 0) {
                $cabang = 1;
            }
            $cabang = (int) $cabang;

            $requestQr = $this->countRequestQr($request, $range);
            $nonKontrak = $this->countQuotationStages(QuotationNonKontrak::query(), $year, $salesIds, $cabang, true);
            $kontrak = $this->countQuotationStages(QuotationKontrakH::query(), $year, $salesIds, $cabang, false);

            $keys = array(
                'quote_request',
                'need_approve',
                'qt_approved',
                'follow_up_qt',
                'konfirmasi_order',
                'ready_order',
                'qt_ordered',
            );

            $steps = array(
                'request_qr' => $requestQr,
            );

            foreach ($keys as $key) {
                $steps[$key] = $this->bucket(
                    isset($kontrak[$key]) ? $kontrak[$key] : 0,
                    isset($nonKontrak[$key]) ? $nonKontrak[$key] : 0
                );
            }

            $total = 0;
            foreach ($steps as $bucket) {
                $total += (int) $bucket['total'];
            }

            return response()->json(array(
                'status' => 'success',
                'data' => array(
                    'year' => $year,
                    'total' => $total,
                    'steps' => $steps,
                ),
            ), 200);
        } catch (Exception $e) {
            return response()->json(array(
                'status' => 'error',
                'message' => 'Gagal memuat ringkasan pipeline quotation: ' . $e->getMessage(),
            ), 500);
        }
    }

    private function yearRange($year)
    {
        return array(
            'start' => $year . '-01-01 00:00:00',
            'end' => ($year + 1) . '-01-01 00:00:00',
        );
    }

    private function bucket($kontrak, $nonKontrak)
    {
        $kontrak = (int) $kontrak;
        $nonKontrak = (int) $nonKontrak;

        return array(
            'total' => $kontrak + $nonKontrak,
            'kontrak' => $kontrak,
            'non_kontrak' => $nonKontrak,
        );
    }

    private function getJabatan(Request $request)
    {
        $user = $request->attributes->get('user');
        if (!$user || !isset($user->karyawan) || !$user->karyawan) {
            return null;
        }

        return $user->karyawan->id_jabatan;
    }

    private function salesIds(Request $request)
    {
        $jabatan = $this->getJabatan($request);

        if ($jabatan == 24) {
            return array($this->user_id);
        }

        if ($jabatan == 21) {
            $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                ->pluck('id')
                ->toArray();
            array_push($bawahan, $this->user_id);
            return $bawahan;
        }

        return null;
    }

    private function applyYearRange($query, $range, $column)
    {
        return $query->where($column, '>=', $range['start'])
            ->where($column, '<', $range['end']);
    }

    private function countRequestQr(Request $request, $range)
    {
        $query = RequestQR::where('is_active', true);
        $this->applyYearRange($query, $range, 'created_at');

        $jabatan = $this->getJabatan($request);
        if ($jabatan == 21) {
            $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
            $query->whereIn('created_by', $bawahan);
        } elseif ($jabatan == 24) {
            $query->where('created_by', $this->karyawan);
        } else {
            $query->where('is_rejected', false)->where('is_processed', false);
        }

        $rows = $query->selectRaw('tipe, COUNT(*) as total')
            ->groupBy('tipe')
            ->pluck('total', 'tipe');

        return $this->bucket(
            isset($rows['kontrak']) ? $rows['kontrak'] : 0,
            isset($rows['non_kontrak']) ? $rows['non_kontrak'] : 0
        );
    }

    private function countQuotationStages($query, $year, $salesIds, $cabang, $isNonKontrak)
    {
        $query->whereYear('tanggal_penawaran', $year);

        if (is_array($salesIds)) {
            $query->whereIn('sales_id', $salesIds);
        }

        $followUpExtra = $isNonKontrak
            ? " AND status_sampling <> 'SD'"
            : '';
        $cabangSql = ' AND id_cabang = ' . (int) $cabang;

        $row = $query->selectRaw("
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 0
                        AND COALESCE(flag_status, 'rejected') = 'rejected'
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS quote_request,
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 0 AND flag_status = 'draft'
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS need_approve,
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 1 AND flag_status = 'draft'
                        AND is_emailed = 0
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS qt_approved,
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 1 AND is_emailed = 1
                        AND is_generate_data_lab = 1
                        AND flag_status IN ('emailed', 'sp')
                        {$followUpExtra}
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS follow_up_qt,
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 1 AND is_emailed = 1
                        AND flag_status = 'sp'
                        AND is_ready_order = 1
                        AND konfirmasi_order = 0
                    THEN 1 ELSE 0 END), 0) AS konfirmasi_order,
                COALESCE(SUM(CASE
                    WHEN is_active = 1 AND is_approved = 1 AND is_emailed = 1
                        AND flag_status = 'sp'
                        AND is_ready_order = 1
                        AND konfirmasi_order = 1
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS ready_order,
                COALESCE(SUM(CASE
                    WHEN is_approved = 1 AND is_emailed = 1 AND flag_status = 'ordered'
                        {$cabangSql}
                    THEN 1 ELSE 0 END), 0) AS qt_ordered
            ")
            ->first();

        return array(
            'quote_request' => $row ? $row->quote_request : 0,
            'need_approve' => $row ? $row->need_approve : 0,
            'qt_approved' => $row ? $row->qt_approved : 0,
            'follow_up_qt' => $row ? $row->follow_up_qt : 0,
            'konfirmasi_order' => $row ? $row->konfirmasi_order : 0,
            'ready_order' => $row ? $row->ready_order : 0,
            'qt_ordered' => $row ? $row->qt_ordered : 0,
        );
    }
}
