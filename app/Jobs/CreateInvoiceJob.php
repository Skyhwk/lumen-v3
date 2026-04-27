<?php

namespace App\Jobs;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Jadwal;
use App\Models\JobTask;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

class CreateInvoiceJob extends Job
{
    protected $orderHeaderId;
    protected $quotationType;
    protected $quotationId;
    protected $payload;

    public function __construct($orderHeaderId, $quotationType, $quotationId, array $payload)
    {
        $this->orderHeaderId = $orderHeaderId;
        $this->quotationType = $quotationType;
        $this->quotationId = $quotationId;
        $this->payload = $payload;
    }

    public function handle()
    {
        $orderHeader = OrderHeader::where('id', $this->orderHeaderId)->first();
        $quotation = $this->getQuotation();

        if (!$orderHeader || !$quotation) {
            Log::error('CreateInvoiceJob: order header atau quotation tidak ditemukan.', [
                'order_header_id' => $this->orderHeaderId,
                'quotation_type' => $this->quotationType,
                'quotation_id' => $this->quotationId,
            ]);
            return;
        }

        $lockName = 'invoice-create-' . Carbon::now()->format('Y');
        $this->acquireLock($lockName);
        $createdInvoices = [];
        $transactionStarted = false;

        try {
            if (Invoice::where('no_order', $orderHeader->no_order)->where('is_active', 1)->exists()) {
                Log::info('CreateInvoiceJob: invoice order sudah ada, proses dilewati.', [
                    'no_order' => $orderHeader->no_order,
                ]);
                return;
            }

            DB::beginTransaction();
            $transactionStarted = true;

            if ($this->quotationType === 'kontrak' && ($this->payload['jenis_tagihan'] ?? null) === 'periode') {
                $createdInvoices = $this->createKontrakPeriodeInvoices($orderHeader, $quotation);
            } else {
                $createdInvoices = $this->createDefaultInvoices($orderHeader, $quotation);
            }

            DB::commit();
        } catch (\Throwable $th) {
            if ($transactionStarted) {
                DB::rollBack();
            }

            Log::error('CreateInvoiceJob: gagal membuat invoice. ' . $th->getMessage(), [
                'no_order' => $orderHeader->no_order,
                'no_document' => $quotation->no_document,
                'line' => $th->getLine(),
            ]);

            throw $th;
        } finally {
            $this->releaseLock($lockName);
        }

        if (count($createdInvoices) > 0) {
            $this->renderInvoice($createdInvoices, $quotation->no_document);
        }
    }

    private function getQuotation()
    {
        if ($this->quotationType === 'kontrak') {
            return QuotationKontrakH::with('detail')->where('id', $this->quotationId)->first();
        }

        return QuotationNonKontrak::where('id', $this->quotationId)->first();
    }

    private function createDefaultInvoices($orderHeader, $quotation)
    {
        $createdInvoices = [];
        $createdInvoices[] = $this->insertInvoice($orderHeader, $quotation, null, true);

        if ((int) str_replace(',', '', $quotation->biaya_akhir) > $this->tagihanAwal()) {
            $createdInvoices[] = $this->insertInvoice($orderHeader, $quotation, null, false);
        }

        return array_filter($createdInvoices);
    }

    private function createKontrakPeriodeInvoices($orderHeader, $quotation)
    {
        $createdInvoices = [];
        $periode = $quotation->detail->pluck('periode_kontrak')->toArray();

        foreach ($periode as $key => $value) {
            $createdInvoices[] = $this->insertInvoice($orderHeader, $quotation, $value, true, $key == 0);

            if ($key == 0 && isset($quotation->detail[0]) && (int) $quotation->detail[0]->biaya_akhir > $this->tagihanAwal()) {
                $createdInvoices[] = $this->insertInvoice($orderHeader, $quotation, $value, false, true);
            }
        }

        return array_filter($createdInvoices);
    }

    private function insertInvoice($orderHeader, $quotation, $periode = null, $first = true, $firstPeriode = false)
    {
        $detail = null;
        if ($periode !== null) {
            $detail = $quotation->detail()->where('periode_kontrak', $periode)->first();
            if (!$detail) {
                Log::warning('CreateInvoiceJob: detail periode kontrak tidak ditemukan.', [
                    'no_document' => $quotation->no_document,
                    'periode' => $periode,
                ]);
                return null;
            }
        }

        $ppnSource = $detail ?: $quotation;
        $rekening = ($ppnSource->total_ppn != null || $ppnSource->total_ppn != 0) ? 'ppn' : 'non-ppn';
        $cekRekening = $rekening == 'ppn' ? '4976688988' : '4978881988';
        $noInvoice = $this->generateNoInvoice($cekRekening, $rekening);
        $insert = $this->buildInvoiceData($orderHeader, $quotation, $detail, $periode, $noInvoice, $cekRekening, $first, $firstPeriode);

        Invoice::insert($insert);
        $this->generateQrInvoice($noInvoice, $insert);

        return $noInvoice;
    }

    private function generateNoInvoice($cekRekening, $rekening)
    {
        $invoiceYear = Carbon::now()->format('Y');
        $shortYear = substr($invoiceYear, -2);

        $lastInvoice = Invoice::where('rekening', $cekRekening)
            ->whereYear('tgl_invoice', $invoiceYear)
            ->where('no_invoice', 'like', '%' . $shortYear . '%')
            ->orderBy('no_invoice', 'desc')
            ->value('no_invoice');

        $prefix = $rekening == 'ppn' ? 'INV' : 'IV';
        $defaultNo = $invoiceYear == '2024'
            ? ($rekening == 'ppn' ? '06767' : '00531')
            : '00001';

        $no = $lastInvoice ? str_pad(intval(substr($lastInvoice, -5)) + 1, 5, '0', STR_PAD_LEFT) : $defaultNo;

        return "ISL/{$prefix}/{$shortYear}{$no}";
    }

    private function buildInvoiceData($orderHeader, $quotation, $detail, $periode, $noInvoice, $cekRekening, $first, $firstPeriode)
    {
        $source = $detail ?: $quotation;
        $jadwal = $this->getJadwal($quotation->no_document, $periode);
        $tanggalJatuhTempo = Carbon::parse($jadwal)->addDays(30)->format('Y-m-d');
        $expired = date('Y-m-d', strtotime($tanggalJatuhTempo . ' + 2 years'));
        $tagihanAwal = $periode !== null && !$firstPeriode ? $source->biaya_akhir : $this->tagihanAwal();
        $periodeInvoice = $periode;

        if ($periodeInvoice === null) {
            $noDoc = explode('/', $orderHeader->no_document);
            $periodeInvoice = isset($noDoc[1]) && $noDoc[1] == 'QTC' ? 'all' : null;
        }

        $namaPerusahaan = $quotation->konsultan != null
            ? $quotation->konsultan . ' (' . $quotation->nama_perusahaan . ')'
            : $quotation->nama_perusahaan;

        return [
            'no_quotation' => $orderHeader->no_document,
            'periode' => $periodeInvoice,
            'no_order' => $orderHeader->no_order,
            'nama_perusahaan' => $namaPerusahaan,
            'pelanggan_id' => $quotation->pelanggan_ID,
            'no_invoice' => $noInvoice,
            'faktur_pajak' => null,
            'no_faktur' => null,
            'no_spk' => null,
            'no_po' => null,
            'tgl_jatuh_tempo' => $tanggalJatuhTempo,
            'keterangan_tambahan' => null,
            'tgl_faktur' => date('Y-m-d H:i:s'),
            'tgl_invoice' => Carbon::now()->format('Y-m-d H:i:s'),
            'nilai_tagihan' => $first ? $tagihanAwal : $source->biaya_akhir - $tagihanAwal,
            'total_tagihan' => $first ? $source->biaya_akhir : $source->biaya_akhir - $tagihanAwal,
            'rekening' => $cekRekening,
            'nama_pj' => 'Yulia Agustina',
            'jabatan_pj' => 'Account Receivable Adm. Supervisor',
            'keterangan' => $this->payload['keterangan_tagihan'] ?? null,
            'alamat_penagihan' => $orderHeader->alamat_kantor,
            'nama_pic' => $orderHeader->nama_pic_order,
            'no_pic' => $orderHeader->no_pic_order,
            'email_pic' => $orderHeader->email_pic_order,
            'jabatan_pic' => $orderHeader->jabatan_pic_order,
            'ppnbm' => $source->total_diskon,
            'ppn' => $source->total_ppn,
            'piutang' => $first ? $source->biaya_akhir : $source->biaya_akhir - $tagihanAwal,
            'created_by' => 'System',
            'created_at' => date('Y-m-d H:i:s'),
            'is_emailed' => 0,
            'is_generate' => 0,
            'expired' => $expired,
        ];
    }

    private function getJadwal($noDocument, $periode = null)
    {
        $query = Jadwal::where('no_quotation', $noDocument);

        if ($periode !== null) {
            $query->where('periode', $periode);
        }

        $jadwal = $query->orderBy('tanggal', 'asc')->first();

        return $jadwal ? $jadwal->tanggal : Carbon::now()->format('Y-m-d');
    }

    private function generateQrInvoice($noInvoice, $insert)
    {
        $filename = str_replace('/', '_', $noInvoice);
        $path = public_path() . '/qr_documents/' . $filename . '.svg';

        if (!file_exists($path)) {
            $link = 'https://www.intilab.com/validation/';
            $unique = 'isldc' . (int) floor(microtime(true) * 1000);

            QrCode::size(200)->generate($link . $unique, $path);

            DB::table('qr_documents')->insert([
                'type_document' => 'invoice',
                'kode_qr' => $unique,
                'file' => $filename,
                'data' => json_encode([
                    'no_document' => $noInvoice,
                    'nama_customer' => $insert['nama_perusahaan'],
                    'type_document' => 'invoice',
                    'Tanggal_Pengesahan' => Carbon::parse($insert['tgl_invoice'])->locale('id')->isoFormat('DD MMMM YYYY'),
                    'Disahkan_Oleh' => $insert['nama_pj'],
                    'Jabatan' => $insert['jabatan_pj'],
                ]),
                'created_at' => Carbon::now(),
                'created_by' => 'System',
            ]);
        }
    }

    private function renderInvoice(array $invoiceNumbers, $noDocument)
    {
        JobTask::insert([
            'job' => 'RenderInvoice',
            'status' => 'processing',
            'no_document' => $noDocument,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        app(Dispatcher::class)->dispatch(new RenderInvoiceJob($invoiceNumbers));
    }

    private function acquireLock($lockName)
    {
        $lockResult = DB::select('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);
        $lockAcquired = isset($lockResult[0]) && (int) $lockResult[0]->acquired === 1;

        if (!$lockAcquired) {
            throw new \Exception('Gagal mendapatkan lock create invoice: ' . $lockName);
        }
    }

    private function releaseLock($lockName)
    {
        DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
    }

    private function tagihanAwal()
    {
        return (int) str_replace(',', '', $this->payload['tagihan_awal'] ?? 0);
    }
}
