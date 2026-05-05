<?php

namespace App\Jobs;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterKaryawan;
use App\Services\GetAtasan;
use App\Services\SendEmail;

class SendNotifPerubahanJadwalJob extends Job
{
    protected $noQuotation;
    protected $before;
    protected $after;

    public function __construct($noQuotation, array $before, array $after)
    {
        $this->noQuotation = $noQuotation;
        $this->before      = $before;
        $this->after       = $after;
    }

    public function handle()
    {
        try {
            $quotation = QuotationKontrakH::where('no_document', $this->noQuotation)->first()
                      ?: QuotationNonKontrak::where('no_document', $this->noQuotation)->first();

            if (!$quotation) {
                \Log::warning('SendNotifPerubahanJadwalJob: quotation tidak ditemukan', [
                    'no_quotation' => $this->noQuotation,
                ]);
                return;
            }

            if (empty($quotation->sales_id)) {
                \Log::warning('SendNotifPerubahanJadwalJob: sales_id kosong', [
                    'no_quotation' => $this->noQuotation,
                ]);
                return;
            }

            $sales = MasterKaryawan::where('id', $quotation->sales_id)->first();
            if (!$sales || empty($sales->email)) {
                \Log::warning('SendNotifPerubahanJadwalJob: sales/email tidak ditemukan', [
                    'no_quotation' => $this->noQuotation,
                    'sales_id'     => $quotation->sales_id,
                ]);
                return;
            }

            $admSales = !empty($quotation->updated_by)
                ? MasterKaryawan::where('nama_lengkap', $quotation->updated_by)->first()
                : null;

            $atasanSales = GetAtasan::where('id', $quotation->sales_id)->get();
            $emailAtasanSales = $atasanSales->count() > 0
                ? $atasanSales->pluck('email')->toArray()
                : [];

            $emailSales = $sales->email;
            $emailAtasanSales = array_values(array_filter($emailAtasanSales, function ($email) use ($emailSales) {
                return $email && $email !== $emailSales;
            }));

            $emailAdmSales = ($admSales && !empty($admSales->email)) ? $admSales->email : null;
            $emailBcc      = array_merge($emailAtasanSales, array_filter([$emailAdmSales]));

            $cc = ['admsales03@intilab.com', 'admsales04@intilab.com'];

            $subject = 'Pemberitahuan Perubahan Jadwal dengan no QT ' . $this->noQuotation;
            if (!empty($quotation->konsultan)) {
                $subject .= ' (' . $quotation->konsultan . ')';
            } else {
                $subject .= ' - ' . $quotation->nama_perusahaan;
            }

            $htmlBody = $this->renderHtml($sales->nama_lengkap);

            \Log::info('SendNotifPerubahanJadwalJob: mencoba kirim', [
                'to'      => $emailSales,
                'subject' => $subject,
            ]);

            SendEmail::where('to', $emailSales)
                ->where('subject', $subject)
                ->where('bcc', $emailBcc)
                ->where('cc', $cc)
                ->where('body', $htmlBody)
                ->noReply()
                ->send();

            \Log::info('SendNotifPerubahanJadwalJob: berhasil dikirim', [
                'no_quotation' => $this->noQuotation,
            ]);

        } catch (\Throwable $e) {
            \Log::error('SendNotifPerubahanJadwalJob gagal: ' . $e->getMessage()
                . ' — ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function renderHtml($namaSales)
    {
        $formatValue = function ($key, $value) {
            if ($key === 'Kategori' && is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $lastIdx = count($decoded) - 1;
                    $lines = [];
                    foreach ($decoded as $i => $item) {
                        $lines[] = htmlspecialchars($item) . ($i < $lastIdx ? ',' : '');
                    }
                    return implode('<br>', $lines);
                }
            }
            return htmlspecialchars((string) $value);
        };

        $rows = '';
        foreach ($this->before as $key => $valBefore) {
            $valAfter = isset($this->after[$key]) ? $this->after[$key] : '-';
            $changed  = ((string) $valBefore !== (string) $valAfter);

            $bgBefore = $changed ? 'background-color:#fff3cd;' : '';
            $bgAfter  = $changed ? 'background-color:#d4edda;' : '';

            $rows .= '<tr>'
                  . '<td style="padding:6px 10px;border:1px solid #ddd;vertical-align:top;"><strong>'
                  .   htmlspecialchars($key) . '</strong></td>'
                  . '<td style="padding:6px 10px;border:1px solid #ddd;vertical-align:top;' . $bgBefore . '">'
                  .   $formatValue($key, $valBefore) . '</td>'
                  . '<td style="padding:6px 10px;border:1px solid #ddd;vertical-align:top;' . $bgAfter . '">'
                  .   $formatValue($key, $valAfter) . '</td>'
                  . '</tr>';
        }

        return '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;">'
             . '<p>Yth. ' . htmlspecialchars($namaSales) . ',</p>'
             . '<p>Berikut pemberitahuan perubahan jadwal sampling untuk No. Quotation '
             .   '<strong>' . htmlspecialchars($this->noQuotation) . '</strong>:</p>'
             . '<table style="border-collapse:collapse;width:100%;max-width:720px;">'
             . '<thead><tr style="background-color:#f0f0f0;">'
             . '<th style="padding:8px 10px;border:1px solid #ddd;text-align:left;">Field</th>'
             . '<th style="padding:8px 10px;border:1px solid #ddd;text-align:left;">Sebelum</th>'
             . '<th style="padding:8px 10px;border:1px solid #ddd;text-align:left;">Sesudah</th>'
             . '</tr></thead>'
             . '<tbody>' . $rows . '</tbody>'
             . '</table>'
             . '</body></html>';
    }
}