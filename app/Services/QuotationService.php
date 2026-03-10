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

        $invoices = Invoice::where('no_quotation', 'like', '%' . $baseQuotation . '%')
            ->where('nilai_pelunasan', '>', 0)
            ->get();

        if ($invoices->isNotEmpty()) {

            $invoiceNumbers = $invoices->pluck('no_invoice')->implode(', ');

            return [
                'status' => false,
                'message' => "Tidak dapat melakukan void pada quotation {$quotationNumber} karena sudah terdapat pembayaran pada invoice: {$invoiceNumbers}",
            ];
        }

        return [
            'status' => true
        ];
    }
}