<?php

namespace App\Services\QuotationGenerate;

use App\Models\MasterKaryawan;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

class QuotationGenerateEmailService
{
    /** @var QuotationGenerateLinkService */
    private $linkService;

    /** @var QuotationGenerateEmailSignatureBuilder */
    private $signatureBuilder;

    public function __construct(
        QuotationGenerateLinkService $linkService = null,
        QuotationGenerateEmailSignatureBuilder $signatureBuilder = null
    ) {
        $this->linkService = $linkService ?: new QuotationGenerateLinkService();
        $this->signatureBuilder = $signatureBuilder ?: new QuotationGenerateEmailSignatureBuilder();
    }

    public function sendNonKontrakPenawaran(QuotationNonKontrak $quotation, string $emailedBy): QuotationNonKontrak
    {
        $quotation->loadMissing(['sales', 'sales.jabatan']);
        if (empty($quotation->sales_id)) {
            throw new \RuntimeException('sales_id kosong untuk ' . $quotation->no_document);
        }

        $recipients = $this->resolveRecipients($quotation);
        $to = $recipients['to'];
        $bcc = $recipients['bcc'];

        $portalLink = $this->linkService->portalTrackingLink($quotation);
        $subject = $this->buildSubject($quotation);
        $body = $this->buildBody($quotation, $portalLink);
        $attachments = $this->buildAttachments($quotation);

        $sent = SendEmail::where('to', $to)
            ->where('subject', $subject)
            ->where('body', $body)
            ->where('cc', [])
            ->where('bcc', $bcc)
            ->where('attachments', $attachments)
            ->where('karyawan', $emailedBy)
            ->fromAdmsales()
            ->send();

        if (!$sent) {
            throw new \RuntimeException('Gagal mengirim email penawaran untuk ' . $quotation->no_document);
        }

        return $this->applyPostEmailFlags($quotation, $emailedBy);
    }

    /**
     * @return array{to: string, bcc: array<int, string>}
     */
    private function resolveRecipients(QuotationNonKontrak $quotation): array
    {
        if (config('quotation_auto.generate.email_test_mode')) {
            $emails = $this->parseTestEmails((string) config('quotation_auto.generate.email_test_to', ''));
            if ($emails === []) {
                throw new \RuntimeException('Email test kosong. Isi --email=alamat@domain.com');
            }

            $to = $emails[0];
            $sales = $this->findKaryawanByEmail($to);
            $quotation->loadMissing(['sales']);
            if ($sales === null) {
                $sales = $quotation->sales;
            }

            $bcc = array_merge(
                $this->buildSalesBccList($sales, $to),
                array_slice($emails, 1)
            );

            return [
                'to' => $to,
                'bcc' => $this->uniqueValidEmails($bcc, $to),
            ];
        }

        $to = trim((string) $quotation->email_pic_order);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('email_pic_order tidak valid untuk ' . $quotation->no_document);
        }

        return [
            'to' => $to,
            'bcc' => $this->buildBccList($quotation, $to),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseTestEmails(string $raw): array
    {
        $emails = [];
        foreach (preg_split('/[,;]+/', $raw) as $item) {
            $email = strtolower(trim($item));
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Email test tidak valid: ' . $item);
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function buildSubject(QuotationNonKontrak $quotation): string
    {
        $company = $this->decodeCompanyName((string) $quotation->nama_perusahaan);
        if (!empty($quotation->konsultan)) {
            return sprintf(
                'Surat Penawaran (%s) - %s (%s)',
                $quotation->no_document,
                $quotation->konsultan,
                $company
            );
        }

        return sprintf('Surat Penawaran (%s) - %s', $quotation->no_document, $company);
    }

    private function buildBody(QuotationNonKontrak $quotation, string $portalLink): string
    {
        $sales = $quotation->sales;
        $signatureHtml = $this->signatureBuilder->buildForSalesId($quotation->sales_id);

        $officialEmail = (string) config('quotation_auto.generate.signature_company_email', 'admsales01@intilab.com');

        return trim(View::make('TemplateEmail.quotation_generate_penawaran', [
            'namaPicOrder' => $quotation->nama_pic_order,
            'namaPerusahaan' => $this->decodeCompanyName((string) $quotation->nama_perusahaan),
            'greeting' => $this->greeting(),
            'noDocument' => $quotation->no_document,
            'statusSamplingLabel' => $this->statusSamplingLabel($quotation->status_sampling),
            'kategoriHtml' => $this->kategoriHtml($quotation->data_pendukung_sampling),
            'portalLink' => $portalLink,
            'salesName' => $sales ? $sales->nama_lengkap : '',
            'salesPhone' => $sales ? (!empty($sales->no_telpon_perusahaan) ? $sales->no_telpon_perusahaan : ($sales->no_telpon ?: '')) : '',
            'officialEmail' => $officialEmail,
            'signatureHtml' => $signatureHtml,
        ])->render());
    }

    /**
     * BCC: sales@intilab.com + sales PJ + atasan langsung ber-grade SUPERVISOR saja.
     *
     * @return array<int, string>
     */
    private function buildBccList(QuotationNonKontrak $quotation, string $to): array
    {
        $quotation->loadMissing(['sales']);

        return $this->buildSalesBccList($quotation->sales, $to);
    }

    /**
     * @return array<int, string>
     */
    private function buildSalesBccList($sales, string $to): array
    {
        $bcc = (array) config('quotation_auto.generate.default_email_bcc', ['sales@intilab.com']);
        if ($sales !== null && !empty($sales->email)) {
            $bcc[] = trim((string) $sales->email);
        }
        foreach ($this->supervisorAtasanEmails($sales) as $email) {
            $bcc[] = $email;
        }

        return $this->uniqueValidEmails($bcc, $to);
    }

    private function findKaryawanByEmail(string $email): ?MasterKaryawan
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    /**
     * Atasan langsung yang grade-nya SUPERVISOR. Manager / senior manager tidak ikut.
     *
     * @return array<int, string>
     */
    private function supervisorAtasanEmails($karyawan): array
    {
        if ($karyawan === null) {
            return [];
        }

        $raw = $karyawan->atasan_langsung ?? null;
        $ids = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if ($ids === []) {
            return [];
        }

        return MasterKaryawan::query()
            ->whereIn('id', $ids)
            ->where('is_active', 1)
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereRaw("UPPER(TRIM(grade)) = 'SUPERVISOR'")
                    ->orWhereRaw("UPPER(TRIM(grade)) = 'SPV'");
            })
            ->pluck('email')
            ->map(function ($email) {
                return trim((string) $email);
            })
            ->all();
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, string>
     */
    private function uniqueValidEmails(array $emails, string $to): array
    {
        $toLower = strtolower(trim($to));
        $seen = [];
        $valid = [];
        foreach ($emails as $email) {
            if (!is_string($email)) {
                continue;
            }
            $email = strtolower(trim($email));
            if ($email === '' || $email === $toLower || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $seen[$email] = true;
            $valid[] = $email;
        }

        return $valid;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildAttachments(QuotationNonKontrak $quotation): array
    {
        if (empty($quotation->filename)) {
            return [];
        }

        return [[
            'path' => 'quotation/' . $quotation->filename,
            'name' => $quotation->filename,
        ]];
    }

    private function applyPostEmailFlags(QuotationNonKontrak $quotation, string $emailedBy): QuotationNonKontrak
    {
        $quotation->flag_status = 'emailed';
        $quotation->is_emailed = 1;
        $quotation->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
        $quotation->emailed_by = $emailedBy;

        $nonPengujian = empty(json_decode((string) $quotation->data_pendukung_sampling, true));
        $statusSampling = [$quotation->status_sampling];

        if ($quotation->data_lama !== null && $quotation->data_lama !== 'null') {
            $dataLama = json_decode($quotation->data_lama);
            if ($dataLama && isset($dataLama->status_sp) && $dataLama->status_sp == 'false') {
                $cekSp = SamplingPlan::where('no_quotation', $quotation->no_document)
                    ->where('is_active', 1)
                    ->where('is_approved', 1)
                    ->exists();
                if ($cekSp) {
                    $quotation->flag_status = 'sp';
                    $quotation->is_ready_order = 1;
                }
            }
        }

        $statusSampling = array_unique(array_filter($statusSampling));
        if (count($statusSampling) === 1) {
            if (in_array($statusSampling[0], ['SD', 'SAR'], true) || $nonPengujian) {
                $quotation->flag_status = 'sp';
                $quotation->is_ready_order = 1;
            }
        }

        if ((int) $quotation->is_generate_data_lab === 0) {
            $quotation->flag_status = 'sp';
            $quotation->is_ready_order = 1;
        }

        $quotation->save();

        return $quotation->fresh();
    }

    private function greeting(): string
    {
        $hour = (int) Carbon::now()->format('G');
        if ($hour >= 4 && $hour < 10) {
            return 'Selamat pagi,';
        }
        if ($hour >= 10 && $hour < 15) {
            return 'Selamat siang,';
        }
        if ($hour >= 15 && $hour < 18) {
            return 'Selamat sore,';
        }

        return 'Selamat malam,';
    }

    /**
     * Sama dengan mapping status di Qt Approved ComposeMail.js (non_kontrak).
     */
    private function statusSamplingLabel(?string $code): string
    {
        $map = [
            'S' => 'SAMPLING',
            'SAR' => 'SAMPLING ANTI RIBET',
            'SD' => 'SAMPEL DIANTAR',
            'S24' => 'SAMPLING 24 JAM',
            'RS' => 'RE-SAMPLING',
        ];

        return $map[$code] ?? '';
    }

    private function kategoriHtml(?string $json): string
    {
        $normalized = str_replace('&quot;', '"', (string) $json);
        $items = json_decode($normalized);
        if (!is_array($items) && !($items instanceof \Traversable)) {
            return '';
        }

        $labels = [];
        foreach ($items as $item) {
            if (!isset($item->kategori_2)) {
                continue;
            }
            $parts = explode('-', (string) $item->kategori_2);
            $labels[] = strtoupper(trim(end($parts)));
        }
        $labels = array_values(array_unique(array_filter($labels)));

        if ($labels === []) {
            return '';
        }

        $html = '';
        foreach ($labels as $i => $label) {
            $html .= '<br><b>' . ($i + 1) . '. ' . e($label) . '</b>';
        }

        return $html;
    }

    private function decodeCompanyName(string $namaPerusahaan): string
    {
        $decoded = html_entity_decode($namaPerusahaan, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_replace('&amp;', '&', $decoded);
    }
}
