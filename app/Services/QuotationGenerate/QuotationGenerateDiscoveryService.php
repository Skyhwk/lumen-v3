<?php

namespace App\Services\QuotationGenerate;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuotationGenerateDiscoveryService
{
    private const KONSULTAN_EMPTY = '(rq.konsultan IS NULL OR rq.konsultan = \'\')';

    public function countQuotationCandidates(Carbon $since): int
    {
        $sql = $this->quotationRankedSubquerySql();

        return (int) DB::selectOne(
            "SELECT COUNT(*) AS aggregate FROM ({$sql}) AS ranked WHERE rn = 1",
            [$since->toDateTimeString()]
        )->aggregate;
    }

    public function countOrderCandidates(Carbon $since): int
    {
        $sql = $this->orderRankedSubquerySql();

        return (int) DB::selectOne(
            "SELECT COUNT(*) AS aggregate FROM ({$sql}) AS ranked WHERE rn = 1",
            [$since->toDateTimeString()]
        )->aggregate;
    }

    /**
     * @return Collection<int, object{
     *   customer_id: string,
     *   customer_name: string,
     *   customer_type: string,
     *   reference_order_id: int|null,
     *   reference_order_no: string|null,
     *   order_created_at: string|null,
     *   reference_quotation_id: int,
     *   reference_quotation_no: string,
     *   quotation_created_at: string,
     *   status: string
     * }>
     */
    public function fetchEligibleNew(Carbon $since, ?int $limit = null): Collection
    {
        $ranked = $this->quotationRankedSubquerySql();
        $limitSql = $limit !== null && $limit > 0 ? ' LIMIT '.(int) $limit : '';

        $rows = DB::select(
            <<<SQL
SELECT
    r.customer_id,
    mp.nama_pelanggan AS customer_name,
    'NEW' AS customer_type,
    NULL AS reference_order_id,
    NULL AS reference_order_no,
    NULL AS order_created_at,
    r.quotation_id AS reference_quotation_id,
    r.quotation_no AS reference_quotation_no,
    r.quotation_created_at,
    'ELIGIBLE' AS status
FROM ({$ranked}) AS r
INNER JOIN master_pelanggan mp
    ON mp.id_pelanggan = r.customer_id
    AND mp.is_active = 1
WHERE r.rn = 1
  AND (r.kode_promo IS NULL OR TRIM(r.kode_promo) = '')
  AND NOT EXISTS (
      SELECT 1
      FROM order_header oh
      WHERE oh.id_pelanggan = r.customer_id
        AND oh.is_active = 1
  )
{$this->newAutoDuplicateGuardSql()}
ORDER BY r.quotation_created_at DESC, r.customer_id
{$limitSql}
SQL,
            $this->newEligibleBindings($since)
        );

        return collect($rows);
    }

    /**
     * @return Collection<int, object>
     */
    public function fetchEligibleExisting(Carbon $since, ?int $limit = null): Collection
    {
        $ranked = $this->orderRankedSubquerySql();
        $limitSql = $limit !== null && $limit > 0 ? ' LIMIT '.(int) $limit : '';

        $rows = DB::select(
            <<<SQL
SELECT
    r.customer_id,
    mp.nama_pelanggan AS customer_name,
    'EXISTING' AS customer_type,
    r.order_id AS reference_order_id,
    r.order_no AS reference_order_no,
    r.order_created_at,
    rq.id AS reference_quotation_id,
    rq.no_document AS reference_quotation_no,
    rq.created_at AS quotation_created_at,
    'ELIGIBLE' AS status
FROM ({$ranked}) AS r
INNER JOIN request_quotation rq
    ON rq.no_document = r.order_quotation_doc
    AND rq.is_active = 1
    AND (rq.konsultan IS NULL OR rq.konsultan = '')
INNER JOIN master_pelanggan mp
    ON mp.id_pelanggan = r.customer_id
    AND mp.is_active = 1
WHERE r.rn = 1
  AND (rq.kode_promo IS NULL OR TRIM(rq.kode_promo) = '')
{$this->existingAutoDuplicateGuardSql()}
ORDER BY r.order_created_at DESC, r.customer_id
{$limitSql}
SQL,
            $this->existingEligibleBindings($since)
        );

        return collect($rows);
    }

    public function countEligibleNew(Carbon $since): int
    {
        $ranked = $this->quotationRankedSubquerySql();

        return (int) DB::selectOne(
            <<<SQL
SELECT COUNT(*) AS aggregate
FROM ({$ranked}) AS r
INNER JOIN master_pelanggan mp
    ON mp.id_pelanggan = r.customer_id
    AND mp.is_active = 1
WHERE r.rn = 1
  AND (r.kode_promo IS NULL OR TRIM(r.kode_promo) = '')
  AND NOT EXISTS (
      SELECT 1 FROM order_header oh
      WHERE oh.id_pelanggan = r.customer_id
        AND oh.is_active = 1
  )
{$this->newAutoDuplicateGuardSql()}
SQL,
            $this->newEligibleBindings($since)
        )->aggregate;
    }

    public function countEligibleExisting(Carbon $since): int
    {
        $ranked = $this->orderRankedSubquerySql();

        return (int) DB::selectOne(
            <<<SQL
SELECT COUNT(*) AS aggregate
FROM ({$ranked}) AS r
INNER JOIN request_quotation rq
    ON rq.no_document = r.order_quotation_doc
    AND rq.is_active = 1
    AND (rq.konsultan IS NULL OR rq.konsultan = '')
INNER JOIN master_pelanggan mp
    ON mp.id_pelanggan = r.customer_id
    AND mp.is_active = 1
WHERE r.rn = 1
  AND (rq.kode_promo IS NULL OR TRIM(rq.kode_promo) = '')
{$this->existingAutoDuplicateGuardSql()}
SQL,
            $this->existingEligibleBindings($since)
        )->aggregate;
    }

    /**
     * @return array<int, object>
     */
    public function explainNewDiscovery(Carbon $since): array
    {
        $ranked = $this->quotationRankedSubquerySql();

        return DB::select(
            "EXPLAIN SELECT r.customer_id FROM ({$ranked}) AS r
             INNER JOIN master_pelanggan mp ON mp.id_pelanggan = r.customer_id AND mp.is_active = 1
             WHERE r.rn = 1
               AND (r.kode_promo IS NULL OR TRIM(r.kode_promo) = '')
               AND NOT EXISTS (
                   SELECT 1 FROM order_header oh
                   WHERE oh.id_pelanggan = r.customer_id AND oh.is_active = 1
               )
               {$this->newAutoDuplicateGuardSql()}",
            $this->newEligibleBindings($since)
        );
    }

    /**
     * @return array<int, object>
     */
    public function explainExistingDiscovery(Carbon $since): array
    {
        $ranked = $this->orderRankedSubquerySql();

        return DB::select(
            "EXPLAIN SELECT r.customer_id FROM ({$ranked}) AS r
             INNER JOIN request_quotation rq ON rq.no_document = r.order_quotation_doc AND rq.is_active = 1
               AND (rq.konsultan IS NULL OR rq.konsultan = '')
             INNER JOIN master_pelanggan mp ON mp.id_pelanggan = r.customer_id AND mp.is_active = 1
             WHERE r.rn = 1
               AND (rq.kode_promo IS NULL OR TRIM(rq.kode_promo) = '')
               {$this->existingAutoDuplicateGuardSql()}",
            $this->existingEligibleBindings($since)
        );
    }

    /**
     * NEW: QT referensi bukan hasil copy pipeline; belum ada QT AUTO sejak QT terbaru ini.
     */
    private function newAutoDuplicateGuardSql(): string
    {
        return <<<'SQL'

  AND NOT EXISTS (
      SELECT 1
      FROM request_quotation rq_src
      WHERE rq_src.id = r.quotation_id
        AND rq_src.created_by = ?
  )
  AND NOT EXISTS (
      SELECT 1
      FROM request_quotation rq_auto
      WHERE rq_auto.pelanggan_ID = r.customer_id
        AND rq_auto.is_active = 1
        AND rq_auto.kode_promo = ?
        AND rq_auto.created_by = ?
        AND rq_auto.created_at >= r.quotation_created_at
  )
SQL;
    }

    /**
     * @return array<int, string>
     */
    private function newEligibleBindings(Carbon $since): array
    {
        $createdBy = (string) config('quotation_auto.generate.created_by', 'Quotation Auto Generate');
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO');

        return [
            $since->toDateTimeString(),
            $createdBy,
            $marker,
            $createdBy,
        ];
    }

    /**
     * EXISTING: sudah pernah auto-generate untuk siklus order terbaru (QT AUTO sejak order_created_at).
     */
    private function existingAutoDuplicateGuardSql(): string
    {
        return <<<'SQL'

  AND NOT EXISTS (
      SELECT 1
      FROM request_quotation rq_auto
      WHERE rq_auto.pelanggan_ID = r.customer_id
        AND rq_auto.is_active = 1
        AND rq_auto.kode_promo = ?
        AND rq_auto.created_by = ?
        AND rq_auto.created_at >= r.order_created_at
  )
SQL;
    }

    /**
     * @return array<int, string>
     */
    private function existingEligibleBindings(Carbon $since): array
    {
        return [
            $since->toDateTimeString(),
            (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO'),
            (string) config('quotation_auto.generate.created_by', 'Quotation Auto Generate'),
        ];
    }

    private function quotationRankedSubquerySql(): string
    {
        $konsultan = self::KONSULTAN_EMPTY;

        return <<<SQL
SELECT
    rq.pelanggan_ID AS customer_id,
    rq.id AS quotation_id,
    rq.no_document AS quotation_no,
    rq.created_at AS quotation_created_at,
    rq.kode_promo AS kode_promo,
    ROW_NUMBER() OVER (
        PARTITION BY rq.pelanggan_ID
        ORDER BY rq.created_at DESC, rq.id DESC
    ) AS rn
FROM request_quotation rq
WHERE rq.created_at >= ?
  AND rq.is_active = 1
  AND rq.pelanggan_ID IS NOT NULL
  AND rq.pelanggan_ID != ''
  AND {$konsultan}
SQL;
    }

    private function orderRankedSubquerySql(): string
    {
        return <<<'SQL'
SELECT
    oh.id_pelanggan AS customer_id,
    oh.id AS order_id,
    oh.no_order AS order_no,
    oh.no_document AS order_quotation_doc,
    oh.created_at AS order_created_at,
    ROW_NUMBER() OVER (
        PARTITION BY oh.id_pelanggan
        ORDER BY oh.created_at DESC, oh.id DESC
    ) AS rn
FROM order_header oh
WHERE oh.created_at >= ?
  AND oh.is_active = 1
  AND oh.id_pelanggan IS NOT NULL
  AND oh.id_pelanggan != ''
  AND oh.no_document IS NOT NULL
  AND oh.no_document != ''
SQL;
    }
}
