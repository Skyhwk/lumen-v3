<?php

namespace App\Services\OrderBackdate;

use App\Jobs\GenerateDocumentSamplingJob;
use App\Jobs\OrderBackdateRegenerateStepJob;
use App\Jobs\RenderLhp;
use App\Models\OrderBackdatePackage;
use App\Models\OrderBackdateStep;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\GenerateQrDocumentLhp;
use App\Services\RenderKontrak;
use App\Services\RenderNonKontrak;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;

class OrderBackdateDocumentRegenerator
{
    /** @var OrderBackdateLhpLocator */
    private $lhpLocator;

    public function __construct(OrderBackdateLhpLocator $lhpLocator)
    {
        $this->lhpLocator = $lhpLocator;
    }

    public function queueAfterSave(OrderBackdatePackage $package, string $stepKey): void
    {
        if (!$this->stepSupportsAutoRegenerate($stepKey)) {
            return;
        }

        $step = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_key', $stepKey)
            ->first();

        if (!$step) {
            return;
        }

        $step->document_status = 'queued';
        $step->updated_at = now();
        $step->save();

        $job = new OrderBackdateRegenerateStepJob((int) $package->id, $stepKey);
        app(Dispatcher::class)->dispatch($job);
    }

    public function regenerate(OrderBackdatePackage $package, string $stepKey): void
    {
        $order = OrderHeader::find($package->id_order_header);
        if (!$order) {
            throw new \RuntimeException('Order tidak ditemukan');
        }

        switch ($stepKey) {
            case 'quotation':
                $this->regenerateQuotation($order);
                break;
            case 'order':
                $this->regenerateOrder($order);
                break;
            case 'sampling_doc':
                $this->regenerateSamplingDoc($order);
                break;
            case 'lhp':
                $this->regenerateLhp($order);
                break;
            case 'qr':
                $this->regenerateQr($order);
                break;
        }
    }

    private function stepSupportsAutoRegenerate(string $stepKey): bool
    {
        return in_array($stepKey, ['quotation', 'order', 'sampling_doc', 'lhp', 'qr'], true);
    }

    private function regenerateQuotation(OrderHeader $order): void
    {
        if ($order->quotationNonKontrak) {
            $qt = QuotationNonKontrak::find($order->quotationNonKontrak->id);
            if ($qt) {
                (new RenderNonKontrak())->renderHeader((int) $qt->id);

                return;
            }
        }

        if ($order->quotationKontrakH) {
            $qt = QuotationKontrakH::find($order->quotationKontrakH->id);
            if ($qt) {
                (new RenderKontrak())->renderHeader((int) $qt->id, 'id');

                return;
            }
        }

        Log::warning('OrderBackdate: quotation PDF dilewati — QT tidak ditemukan', [
            'no_order' => $order->no_order,
        ]);
    }

    private function regenerateOrder(OrderHeader $order): void
    {
        // Belum ada job PDF order terpusat di codebase; tanggal order dipakai modul lain saat render berikutnya.
        Log::info('OrderBackdate: regenerate order skipped (DB-only dates)', [
            'no_order' => $order->no_order,
        ]);
    }

    private function regenerateSamplingDoc(OrderHeader $order): void
    {
        $plan = SamplingPlan::where('no_quotation', $order->no_document)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$plan) {
            Log::warning('OrderBackdate: sampling plan tidak ditemukan', [
                'no_document' => $order->no_document,
            ]);

            return;
        }

        $isKontrak = stripos((string) $order->status_quotation, 'kontrak') !== false;
        $type = $isKontrak ? 'QTC' : 'QT';
        $job = new GenerateDocumentSamplingJob(
            $type,
            (int) $plan->quotation_id,
            $plan->periode_kontrak ?? null,
            'OrderBackdate'
        );
        app(Dispatcher::class)->dispatch($job);
    }

    private function regenerateLhp(OrderHeader $order): void
    {
        foreach ($this->lhpLocator->bundlesForOrder($order) as $bundle) {
            $header = $bundle['header'];
            $detailClass = $bundle['detail_class'];
            $customClass = $bundle['custom_class'];

            $detail = $detailClass::where('id_header', $header->id)->get();
            $custom = $customClass::where('id_header', $header->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $row = is_array($item) ? $item : $item->toArray();
                $page = $row['page'] ?? 0;
                if (!isset($groupedByPage[$page])) {
                    $groupedByPage[$page] = [];
                }
                $groupedByPage[$page][] = $row;
            }

            $modes = ['downloadLHP', 'downloadLHPFinal'];
            foreach ($modes as $mode) {
                $job = new RenderLhp($header, $detail, $mode, $groupedByPage);
                app(Dispatcher::class)->dispatch($job);
            }
        }
    }

    private function regenerateQr(OrderHeader $order): void
    {
        $qrService = new GenerateQrDocumentLhp();

        foreach ($this->lhpLocator->bundlesForOrder($order) as $bundle) {
            $header = $bundle['header'];
            if (empty($header->tanggal_lhp)) {
                continue;
            }

            $updated = $qrService->update($bundle['qr_type'], $header);
            if ($updated === false) {
                $qrService->insert($bundle['qr_type'], $header, 'OrderBackdate');
            }
        }
    }
}
