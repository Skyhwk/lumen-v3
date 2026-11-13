<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\OrderDetail;
use App\Models\PengesahanDokumenSampling;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\TicketSkK3;
use Carbon\Carbon;
use Mpdf\Mpdf;

class TicketSkK3SuratService
{
    public const DIR_GENERATED = 'ticket_sk_k3/generated';
    public const DIR_UPLOADED = 'ticket_sk_k3/uploaded';

    public static function ensureDir(string $relative): string
    {
        $dir = public_path($relative);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function folderFor(TicketSkK3 $ticket): string
    {
        return $ticket->status === 'RELEASE DOKUMEN'
            ? self::DIR_UPLOADED
            : self::DIR_GENERATED;
    }

    public static function absolutePath(TicketSkK3 $ticket): ?string
    {
        $filename = trim((string) ($ticket->filename_surat ?? ''));
        if ($filename === '') {
            return null;
        }

        $candidates = [];
        if ($ticket->status === 'RELEASE DOKUMEN') {
            $candidates[] = public_path(self::DIR_UPLOADED . DIRECTORY_SEPARATOR . $filename);
        }
        $candidates[] = public_path(self::DIR_GENERATED . DIRECTORY_SEPARATOR . $filename);
        $candidates[] = public_path('ticket_sk_k3' . DIRECTORY_SEPARATOR . $filename);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function generate(TicketSkK3 $ticket): array
    {
        $quotation = $this->resolveQuotation($ticket);
        $payload = $this->buildPayload($ticket, $quotation);
        $noDocument = trim((string) ($ticket->no_document_surat ?? ''));
        if ($noDocument === '') {
            $noDocument = $this->nextDocumentNumber((int) ($quotation['id_cabang'] ?? 1));
        }
        $filename = str_replace(['/', '\\'], '-', $noDocument) . '.pdf';

        $dir = self::ensureDir(self::DIR_GENERATED);

        $now = Carbon::now();
        $pengesah = PengesahanDokumenSampling::latestForType(
            PengesahanDokumenSampling::TYPE_K3,
            $now
        );
        if (!$pengesah || trim((string) $pengesah->nama_karyawan) === '') {
            throw new \RuntimeException('Pengesah K3 belum di-set. Atur dulu di Administrator > Pengesahan Dokumen > K3.');
        }

        $logoPath = public_path('img/isl_logo.png');
        $logoW = 55;
        $logoH = 17;
        if (is_file($logoPath)) {
            [$pxW, $pxH] = getimagesize($logoPath);
            if ($pxW > 0 && $pxH > 0) {
                $logoH = round($logoW * ($pxH / $pxW), 2);
            }
        }

        $html = view('pdf.surat-pemberitahuan-sk-k3', [
            'logo' => $logoPath,
            'logoW' => $logoW,
            'logoH' => $logoH,
            'noDocument' => $noDocument,
            'namaPerusahaan' => $payload['nama_perusahaan'],
            'alamatPerusahaan' => $payload['alamat'],
            'tanggalSampling' => $payload['tanggal_sampling'],
            'pengujian' => $payload['pengujian'],
            'jumlahTitik' => $payload['jumlah_titik'],
            'tanggalSurat' => $this->tanggalIndonesia($now->format('Y-m-d')),
            'namaPengesah' => trim((string) $pengesah->nama_karyawan),
            'jabatanPengesah' => trim((string) ($pengesah->jabatan_karyawan ?: '')),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 14,
            'margin_bottom' => 28,
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_footer' => 6,
        ]);
        $mpdf->SetTitle('Surat Pemberitahuan Pengujian SK K3');
        $mpdf->SetAuthor('PT Inti Surya Laboratorium');
        $mpdf->SetHTMLHeader('<div style="text-align:right; font-size:8pt; color:#555;">Page {PAGENO} of {nbpg}</div>');

        $watermark = public_path('logo-watermark.png');
        if (is_file($watermark)) {
            $mpdf->watermarkImgBehind = true;
            $mpdf->SetWatermarkImage($watermark, 0.12, 'D', 'P');
            $mpdf->showWatermarkImage = true;
        }

        $generatedAt = $this->tanggalIndonesia($now->format('Y-m-d')) . ' ' . $now->format('H:i');
        $mpdf->SetHTMLFooter('
            <div style="text-align:center; font-size:8pt; color:#333; line-height:1.35;">
                Ruko Icon Business Park blok O no. 5 – 6<br>
                Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>
                www.intilab.com – contact@intilab.com
            </div>
            <div style="text-align:right; font-size:7pt; color:#888; margin-top:3px;">
                Dicetak: ' . htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') . '
            </div>
        ');
        $mpdf->WriteHTML($html);
        $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

        return [
            'no_document_surat' => $noDocument,
            'filename_surat' => $filename,
            'file_base64' => base64_encode((string) file_get_contents($filePath)),
        ];
    }

    private function resolveQuotation(TicketSkK3 $ticket): array
    {
        if ($ticket->tipe_quotation === 'kontrak') {
            $header = QuotationKontrakH::where('id', $ticket->quotation_id)->first()
                ?: QuotationKontrakH::where('no_document', $ticket->no_quotation)->first();
        } else {
            $header = QuotationNonKontrak::where('id', $ticket->quotation_id)->first()
                ?: QuotationNonKontrak::where('no_document', $ticket->no_quotation)->first();
        }

        return [
            'id_cabang' => $header->id_cabang ?? 1,
            'nama_perusahaan' => $header->nama_perusahaan ?? $ticket->nama_perusahaan,
            'alamat' => $this->cleanText($header->alamat_sampling ?? $header->alamat_kantor ?? ''),
            'header' => $header,
        ];
    }

    private function buildPayload(TicketSkK3 $ticket, array $quotation): array
    {
        $pengujian = $this->buildPengujianList($ticket, $quotation['header'] ?? null);
        $jadwal = Jadwal::where('id_sampling', $ticket->sampling_plan_id)
            ->where('is_active', true)
            ->orderBy('tanggal')
            ->get();

        $alamat = $quotation['alamat'];
        if ($alamat === '' && $jadwal->isNotEmpty()) {
            $alamat = $this->cleanText($jadwal->first()->alamat ?? '');
        }

        return [
            'nama_perusahaan' => $quotation['nama_perusahaan'] ?: ($ticket->nama_perusahaan ?: '-'),
            'alamat' => $alamat !== '' ? $alamat : '-',
            'tanggal_sampling' => $this->formatTanggalSampling($jadwal),
            'pengujian' => $pengujian['items'],
            'jumlah_titik' => $pengujian['jumlah_titik'],
        ];
    }

    private function buildPengujianList(TicketSkK3 $ticket, $header): array
    {
        $names = $this->pengujianFromQuotation($ticket, $header);
        if (count($names) === 0) {
            $names = $this->pengujianFromOrderDetail($ticket);
        }

        $items = [];
        foreach (array_count_values($names) as $name => $count) {
            $items[] = $name . ' ' . $count;
        }

        return [
            'items' => $items,
            'jumlah_titik' => count($names),
        ];
    }

    private function pengujianFromQuotation(TicketSkK3 $ticket, $header): array
    {
        $blocks = [];
        if ($ticket->tipe_quotation === 'kontrak') {
            $details = QuotationKontrakD::where('id_request_quotation_kontrak_h', $header->id ?? $ticket->quotation_id)
                ->when($ticket->periode, function ($query) use ($ticket) {
                    $query->where('periode_kontrak', $ticket->periode);
                })
                ->get();
            foreach ($details as $detail) {
                $blocks = array_merge($blocks, $this->normalizeSamplingBlocks($detail->data_pendukung_sampling));
            }
        } elseif ($header && !empty($header->data_pendukung_sampling)) {
            $blocks = $this->normalizeSamplingBlocks($header->data_pendukung_sampling);
        }

        $names = [];
        foreach ($blocks as $block) {
            foreach ((array) ($block['data_sampling'] ?? []) as $item) {
                $kategori = $this->kategori3Name($item['kategori_2'] ?? ($item['kategori_3'] ?? ''));
                if ($kategori === '') {
                    continue;
                }
                $titik = (int) ($item['jumlah_titik'] ?? 0);
                if ($titik < 1) {
                    $titik = count($item['penamaan_titik'] ?? []);
                }
                $titik = max($titik, 1);
                for ($i = 0; $i < $titik; $i++) {
                    $names[] = $kategori;
                }
            }
        }

        return $names;
    }

    private function pengujianFromOrderDetail(TicketSkK3 $ticket): array
    {
        $query = OrderDetail::where('no_quotation', $ticket->no_quotation)->where('is_active', true);
        if ($ticket->periode) {
            $query->where('periode', $ticket->periode);
        }

        return $query->orderBy('id')
            ->get()
            ->map(function ($row) {
                return $this->kategori3Name($row->kategori_3);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeSamplingBlocks($raw): array
    {
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded) || count($decoded) === 0) {
            return [];
        }

        if (isset($decoded['data_sampling'])) {
            return [$decoded];
        }

        $blocks = [];
        foreach ($decoded as $item) {
            if (is_array($item) && (isset($item['data_sampling']) || isset($item['periode_kontrak']))) {
                $blocks[] = $item;
            }
        }

        return $blocks;
    }

    private function kategori3Name($raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d+\s*-\s*(.+)$/', $value, $match)) {
            return trim($match[1]);
        }
        return $value;
    }

    private function formatTanggalSampling($jadwal): string
    {
        $dates = [];
        foreach ($jadwal as $row) {
            if (!$row->tanggal) {
                continue;
            }
            $start = Carbon::parse($row->tanggal)->startOfDay();
            $span = ((int) $row->durasi > 1) ? (int) $row->durasi : 1;
            for ($i = 0; $i < $span; $i++) {
                $dates[$start->copy()->addDays($i)->format('Y-m-d')] = true;
            }
        }

        ksort($dates);
        $labels = array_map(function ($date) {
            return $this->tanggalIndonesia($date);
        }, array_keys($dates));

        return count($labels) > 0 ? implode(', ', $labels) : '-';
    }

    private function nextDocumentNumber(int $cabangId): string
    {
        $year = Carbon::now()->format('y');
        $cabang = str_pad((string) $cabangId, 2, '0', STR_PAD_LEFT);
        $prefix = 'ISL-' . $cabang . '-SPMB-' . $year;

        $last = TicketSkK3::where('no_document_surat', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('no_document_surat');

        $seq = 1;
        if ($last && preg_match('/(\d{4})$/', $last, $match)) {
            $seq = ((int) $match[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function tanggalIndonesia($date): string
    {
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        $parsed = Carbon::parse($date);
        return $parsed->format('j') . ' ' . $bulan[(int) $parsed->format('n')] . ' ' . $parsed->format('Y');
    }

    private function cleanText($value): string
    {
        $text = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', (string) $value)));
        return $text;
    }
}
