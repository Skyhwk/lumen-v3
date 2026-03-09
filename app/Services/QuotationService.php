<?php

namespace App\Services;

use App\Models\Invoice;

class QuotationService
{
    private function getBaseQuotation($quotationNumber)
    {
        return preg_replace('/R\d+$/', '', $quotationNumber);
    }

    public function validateVoidQuotation($quotationNumber)
    {
        $baseQuotation = $this->getBaseQuotation($quotationNumber);

        $invoices = Invoice::where('no_quotation', $baseQuotation)
            ->where('nilai_pelunasan', '>', 0)
            ->get();

        if ($invoices->isNotEmpty()) {

            $invoiceNumbers = $invoices->pluck('no_invoice')->implode(', ');

            return [
                'status' => false,
                'message' => "Tidak dapat melakukan void karena Quotation original {$baseQuotation} sudah memiliki pembayaran pada invoice {$invoiceNumbers}",
            ];
        }

        return [
            'status' => true
        ];
    }
}