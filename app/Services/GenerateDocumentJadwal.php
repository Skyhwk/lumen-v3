<?php
namespace App\Services;

use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterKaryawan;

use App\Services\GetAtasan;
use App\Services\SendEmail;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class GenerateDocumentJadwal
{
    private $data;

    /** @var string|null */
    private $karyawan;

    /** @var bool */
    private $email = false;

    public static function onKontrak($id)
    {
        $data = QuotationKontrakH::with('detail', 'sampling')->where('id', $id)->first();
        // dd(json_decode($data->data_pendukung_sampling)->toArray());
        // dd($data->sampling->where('periode_kontrak','2025-02')->first()->jadwal()->where('periode' ,'2025-02')->get()->toArray());
        $self       = new self();
        $self->data = $data;

        return $self;
    }

    public static function onNonKontrak($id)
    {
        $data = QuotationNonKontrak::with('sampling')->where('id', $id)->first();

        $self       = new self();
        $self->data = $data;

        return $self;
    }

    public function setKaryawan($karyawan)
    {
        $this->karyawan = $karyawan;
        return $this;
    }

    public function setEmail(bool $email = false)
    {
        $this->email = $email;
        return $this;
    }

    public function save()
    {
        $quote     = $this->data;
        DB::beginTransaction();
        try {
            $filename  = $this->renderNonKontrak($quote);
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            if ($filename) {
                $key   = $quote->created_by . DATE('YmdHis');
                $gen   = MD5($key);
                $token = $this->encrypt($gen . '|' . $quote->email_pic_order);
                $data  = [
                    'token'            => $token,
                    'key'              => $gen,
                    'expired'          => Carbon::parse($quote->expired)->addMonths(3)->format('Y-m-d'),
                    'created_at'       => Carbon::parse($timestamp)->format('Y-m-d'),
                    'created_by'       => $this->karyawan,
                    'fileName_pdf'     => $filename,
                    'is_reschedule'    => 1,
                    'quotation_status' => 'non_kontrak',
                    'type'             => 'jadwal',
                    'id_quotation'     => $quote->id,
                ];
                $dataLink              = GenerateLink::insert($data);
                $quote->expired        = Carbon::parse($quote->expired)->addMonths(1)->format('Y-m-d');
                $quote->generated_at   = $timestamp;
                $quote->generated_by   = $this->karyawan;
                $quote->jadwalfile     = $filename;
                $quote->is_generated   = true;
                $quote->is_ready_order = 1;
                $quote->save();
            }

            JobTask::insert([
                'job'         => 'GenerateDocumentJadwal',
                'status'      => 'success',
                'no_document' => $quote->no_document,
                'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            DB::commit();

            $this->emailJadwalSampling($this->email, $quote);
            
            return true;
        } catch (\Throwable $th) {
            DB::rollback();
            JobTask::insert([
                'job'         => 'GenerateDocumentJadwal',
                'status'      => 'failed',
                'no_document' => $quote->no_document,
                'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            Log::error(['GenerateDocumentJadwal: ' . $th->getMessage() . ' - ' . $th->getFile() . ' - ' . $th->getLine()]);
            return false;
        }

    }

    public function saveKontrak()
    {
        $quote     = $this->data;
        DB::beginTransaction();
        try {
            $filename  = $this->renderKontrak($quote);
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            if ($filename) {
                $key   = $quote->created_by . DATE('YmdHis');
                $gen   = MD5($key);
                $token = $this->encrypt($gen . '|' . $quote->email_pic_order);
                $data  = [
                    'token'            => $token,
                    'key'              => $gen,
                    'expired'          => Carbon::parse($quote->expired)->addMonths(3)->format('Y-m-d'),
                    'created_at'       => Carbon::parse($timestamp)->format('Y-m-d'),
                    'created_by'       => $this->karyawan,
                    'fileName_pdf'     => $filename,
                    'is_reschedule'    => 1,
                    'quotation_status' => 'kontrak',
                    'type'             => 'jadwal',
                    'id_quotation'     => $quote->id,
                ];
                $dataLink              = GenerateLink::insert($data);
                $quote->expired        = Carbon::parse($quote->expired)->addMonths(1)->format('Y-m-d');
                $quote->generated_at   = $timestamp;
                $quote->generated_by   = $this->karyawan;
                $quote->jadwalfile     = $filename;
                $quote->is_generated   = true;
                $quote->is_ready_order = 1;
                $quote->save();
            }

            JobTask::insert([
                'job'         => 'GenerateDocumentJadwal',
                'status'      => 'success',
                'no_document' => $quote->no_document,
                'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            DB::commit();

            $this->emailJadwalSampling($this->email, $quote);

            return true;
        } catch (\Throwable $th) {
            DB::rollback();
            JobTask::insert([
                'job'         => 'GenerateDocumentJadwal',
                'status'      => 'failed',
                'no_document' => $quote->no_document,
                'timestamp'   => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            Log::error(['GenerateDocumentJadwal: ' . $th->getMessage() . ' - ' . $th->getFile() . ' - ' . $th->getLine()]);
            return false;
        }

    }

    private function renderNonKontrak($data)
    {
        try {
            $sampling = '';
            $data     = $data;

            if ($data->status_sampling == 'S24') {
                $sampling = 'SAMPLING 24 JAM';
            } else if ($data->status_sampling == 'SD') {
                $sampling = 'SAMPLING DATANG';
            } else if ($data->status_sampling == 'SP') {
                $sampling = 'SAMPLE PICKUP';
            } else if ($data->status_sampling == 'RS') {
                $sampling = 'RE-SAMPLING';
            } else {
                $sampling = 'SAMPLING';
            }

            if ($data->konsultan != '') {
                $perusahaan = strtoupper($data->konsultan) . ' ( ' . $data->nama_perusahaan . ' ) ';
            } else {
                $perusahaan = $data->nama_perusahaan;
            }

            $sampling_plan = $data->sampling->first();

            // Cek apakah ada jadwal dengan parsial
            $hasParsial = false;
            foreach ($sampling_plan->jadwal as $item) {
                if ($item->parsial !== null) {
                    $hasParsial = true;
                    break;
                }
            }

            $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_header' => 3, 'margin_footer' => 3, 'setAutoTopMargin' => 'stretch', 'orientation' => 'P']);

            $pdf->SetProtection(['print'], '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', [65, 60]);
            $pdf->showWatermarkImage = true;

            $qr_img = '';
            $qr     = DB::table('qr_documents')->where(['id_document' => $data->id, 'type_document' => 'jadwal_non_kontrak'])
            // ->whereJsonContains('data->no_document', $data->no_document)
                ->first();

            $qr_data = $qr && $qr->data ? json_decode($qr->data, true) : null;

            if ($qr && ($qr_data['no_document'] == $data->no_document)) {
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
            }

            $pdf->setFooter([
                'odd' => [
                    'C'    => ['content' => 'Hal {PAGENO} dari {nbpg}', 'font-size' => 6, 'font-style' => 'I', 'font-family' => 'serif', 'color' => '#606060'],
                    'R'    => ['content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}', 'font-size' => 5, 'font-style' => 'I', 'font-family' => 'serif', 'color' => '#000000'],
                    'L'    => [
                        'content'     => '' . $qr_img . '',
                        'font-size'   => 4,
                        'font-style'  => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color'       => '#000000',
                    ],
                    'line' => -1,
                ],
            ]);

            $periode_       = '';
            $status_kontrak = 'NON CONTRACT';

            if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                $status_kontrak = 'CONTRACT';
                $periode_       = self::tanggal_indonesia($sampling_plan->periode_kontrak, 'period');
            }

            $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';

            $commonView = [
                'data'                 => $data,
                'sampling_plan'        => $sampling_plan,
                'periode_'             => $periode_,
                'status_kontrak'       => $status_kontrak,
                'sampling'             => $sampling,
                'perusahaan'           => preg_replace('/&AMP;+/', '&', $perusahaan),
                'tanggalCetak'         => self::tanggal_indonesia(date('Y-m-d')),
                'jamCetak'             => date('G:i'),
                'isQtc'                => explode('/', $sampling_plan->no_quotation)[1] == 'QTC',
                'jadwalSection'        => self::buildJadwalSamplingSection($sampling_plan),
                'jadwalTableMarginTop' => '5px',
            ];

            $pdf->SetHTMLHeader(view('pdf.jadwal.non-kontrak', array_merge($commonView, ['part' => 'header']))->render());
            $pdf->WriteHTML(view('pdf.jadwal.non-kontrak', array_merge($commonView, ['part' => 'body']))->render());

            $this->writeJadwalLampiranPage($pdf, 'pdf.jadwal.lampiran-non-kontrak', $commonView, $sampling_plan, true);

            $dir = public_path('quotation/');

            if (! file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $filePath = $dir . '/' . $fileName;

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $ex) {
            Log::error(['RenderNonKontrakDocumentJadwal: ' . $ex->getMessage() . ' - ' . $ex->getFile() . ' - ' . $ex->getLine()]);
            return false;
        }
    }

    public function renderKontrak($quote = null)
    {
        try {
            $sampling = '';
            $data     = $this->data;

            if ($data->status_sampling == 'S24') {
                $sampling = 'SAMPLING 24 JAM';
            } else if ($data->status_sampling == 'SD') {
                $sampling = 'SAMPLING DATANG';
            } else if ($data->status_sampling == 'SP') {
                $sampling = 'SAMPLE PICKUP';
            } else if ($data->status_sampling == 'RS') {
                $sampling = 'RE-SAMPLING';
            } else {
                $sampling = 'SAMPLING';
            }

            if ($data->konsultan != '') {
                $perusahaan = strtoupper($data->konsultan) . ' ( ' . $data->nama_perusahaan . ' ) ';
            } else {
                $perusahaan = $data->nama_perusahaan;
            }

            $sampling_plans = $data->sampling
                ->where('no_quotation', $data->no_document)
                ->sortBy('periode_kontrak')
                ->values();
            

            // Inisialisasi PDF di LUAR loop - hanya sekali
            $mpdfConfig = [
                'mode'             => 'utf-8',
                'format'           => 'A4',
                'margin_header'    => 3,
                'margin_footer'    => 3,
                'setAutoTopMargin' => 'stretch',
                'orientation'      => 'P',
            ];

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(['print'], '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', [65, 60]);
            $pdf->showWatermarkImage = true;

            $qr_img = '';
            $qr     = DB::table('qr_documents')
                ->where(['id_document' => $data->id, 'type_document' => 'jadwal_kontrak'])
                ->whereJsonContains('data->no_document', $data->no_document)
                ->first();

            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $data->no_document) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

            $footer = [
                'odd' => [
                    'C'    => [
                        'content'     => 'Hal {PAGENO} dari {nbpg}',
                        'font-size'   => 6,
                        'font-style'  => 'I',
                        'font-family' => 'serif',
                        'color'       => '#606060',
                    ],
                    'R'    => [
                        'content'     => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size'   => 5,
                        'font-style'  => 'I',
                        'font-family' => 'serif',
                        'color'       => '#000000',
                    ],
                    'L'    => [
                        'content'     => '' . $qr_img . '',
                        'font-size'   => 4,
                        'font-style'  => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color'       => '#000000',
                    ],
                    'line' => -1,
                ],
            ];

            $pdf->setFooter($footer);

            $fileName = preg_replace('/\\//', '-', 'JADWAL-SAMPLING-' . $data->no_document) . '.pdf';

            // Loop untuk setiap periode
            foreach ($sampling_plans as $key => $sampling_plan) {

                if ($key > 0) {
                    $pdf->addPage();
                }


                // Reset hasParsial untuk setiap periode
                $hasParsial = false;

                foreach ($sampling_plan->jadwal as $item) {
                    if ($item->parsial !== null) {
                        $hasParsial = true;
                        break;
                    }
                }

                $periode_       = '';
                $status_kontrak = 'NON CONTRACT';

                if (explode("/", $sampling_plan->no_quotation)[1] == 'QTC') {
                    $status_kontrak = 'CONTRACT';
                    $periode_       = self::tanggal_indonesia($sampling_plan->periode_kontrak, 'period');
                }

                $commonView = [
                    'data'                 => $data,
                    'sampling_plan'        => $sampling_plan,
                    'periode_'             => $periode_,
                    'status_kontrak'       => $status_kontrak,
                    'sampling'             => $sampling,
                    'perusahaan'           => preg_replace('/&AMP;+/', '&', $perusahaan),
                    'tanggalCetak'         => self::tanggal_indonesia(date('Y-m-d')),
                    'jamCetak'             => date('G:i'),
                    'isQtc'                => explode('/', $sampling_plan->no_quotation)[1] == 'QTC',
                    'jadwalSection'        => self::buildJadwalSamplingSection($sampling_plan),
                    'jadwalTableMarginTop' => '30px',
                ];

                $pdf->WriteHTML(view('pdf.jadwal.kontrak', array_merge($commonView, ['part' => 'header']))->render());
                $pdf->WriteHTML(view('pdf.jadwal.kontrak', array_merge($commonView, ['part' => 'body']))->render());

                $this->writeJadwalLampiranPage($pdf, 'pdf.jadwal.lampiran-kontrak', $commonView, $sampling_plan, false);

            } // End foreach sampling_plans

            // Output PDF setelah semua periode diproses
            $dir = public_path('quotation/');

            if (! file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $filePath = $dir . '/' . $fileName;

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
          
            return $fileName;
        } catch (\Exception $ex) {
            Log::error(['RenderKontrakDocumentJadwal: ' . $ex->getMessage() . ' - ' . $ex->getFile() . ' - ' . $ex->getLine()]);
            return false;
        }
    }

    /**
     * Satu halaman lampiran (ringkas) digabung ke PDF; header HTML dokumen dinonaktifkan bila $clearHtmlHeader (non-kontrak).
     */
    private function writeJadwalLampiranPage(Mpdf $pdf, string $viewName, array $commonView, $sampling_plan, bool $clearHtmlHeader): void
    {
        if ($clearHtmlHeader) {
            $pdf->SetHTMLHeader('');
        }
        $pdf->addPage();
        $pdf->WriteHTML(view($viewName, array_merge($commonView, [
            'lampiranRows' => self::lampiranRowsFromJadwal($sampling_plan->jadwal),
        ]))->render());
    }

    /**
     * Baris lampiran PDF (No, Tanggal, Jam, Kategori) dari koleksi jadwal.
     * Baris digabung bila tanggal, jam (mulai–selesai), dan kategori sama.
     */
    public static function lampiranRowsFromJadwal($jadwalIterable): array
    {
        $groups    = [];
        $orderKeys = [];

        foreach ($jadwalIterable as $jadwal) {
            $tanggalKey = date('Y-m-d', strtotime($jadwal->tanggal));
            $jam        = substr($jadwal->jam_mulai, 0, 5) . ' - ' . substr($jadwal->jam_selesai, 0, 5);
            $decoded    = json_decode($jadwal->kategori, true);
            if (is_array($decoded)) {
                $sorted       = array_map('strval', $decoded);
                sort($sorted);
                $kategoriText = implode(', ', $sorted);
            } else {
                $kategoriText = (string) $jadwal->kategori;
            }

            $groupKey = $tanggalKey . "\0" . $jam . "\0" . $kategoriText;
            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'tanggal'  => self::tanggal_indonesia($tanggalKey),
                    'jam'      => $jam,
                    'kategori' => $kategoriText,
                ];
                $orderKeys[] = $groupKey;
            }
        }

        usort($orderKeys, function ($ka, $kb) {
            $a = explode("\0", $ka, 3);
            $b = explode("\0", $kb, 3);
            $cmp = strcmp($a[0], $b[0]);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp($a[1], $b[1]);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a[2] ?? '', $b[2] ?? '');
        });

        $rows = [];
        $no   = 1;
        foreach ($orderKeys as $groupKey) {
            $rows[] = array_merge(
                ['no' => $no++],
                $groups[$groupKey]
            );
        }

        return $rows;
    }

    /**
     * Data tabel JADWAL SAMPLING (sama logika pengelompokan tanggal seperti sebelumnya).
     */
    private static function buildJadwalSamplingSection($sampling_plan): array
    {
        $groupedKategori   = [];
        $groupedSampler    = [];
        $groupedDate       = [];
        $groupedJamMulai   = [];
        $groupedJamSelesai = [];

        foreach ($sampling_plan->jadwal as $item) {
            array_push($groupedKategori, json_decode($item->kategori));
            array_push($groupedSampler, $item->sampler);
            array_push($groupedDate, $item->tanggal);
            array_push($groupedJamMulai, $item->jam_mulai);
            array_push($groupedJamSelesai, $item->jam_selesai);
        }

        $kategories = collect($groupedKategori)->flatten()->unique()->values()->toArray();
        $samplers   = array_unique($groupedSampler);
        $dates      = array_unique($groupedDate);
        $jamMulai   = array_unique($groupedJamMulai);
        $jamSelesai = array_unique($groupedJamSelesai);

        $groupedData = [];
        foreach ($kategories as $item) {
            $parts    = explode(' - ', $item);
            $kategori = $parts[0];

            if (array_key_exists($kategori, $groupedData)) {
                $groupedData[$kategori]++;
            } else {
                $groupedData[$kategori] = 1;
            }
        }

        if (! is_array($groupedData) || count($groupedData) == 0) {
            return ['show' => false, 'rows' => []];
        }

        $jadwalGrouped = [];
        foreach ($sampling_plan->jadwal as $jadwal) {
            $tanggal = $jadwal->tanggal;
            if (! isset($jadwalGrouped[$tanggal])) {
                $jadwalGrouped[$tanggal] = [];
            }
            $jadwalGrouped[$tanggal][] = $jadwal;
        }

        $tanggalKeys = array_keys($jadwalGrouped);
        usort($tanggalKeys, function ($a, $b) {
            return strtotime($a) <=> strtotime($b);
        });

        $rows = [];
        foreach ($tanggalKeys as $tanggal) {
            $jadwals = $jadwalGrouped[$tanggal];
            $samplerList   = [];
            $minJamMulai   = '23:59:59';
            $maxJamSelesai = '00:00:00';

            foreach ($jadwals as $jadwal) {
                if (! in_array($jadwal->sampler, $samplerList)) {
                    $samplerList[] = $jadwal->sampler;
                }
                if ($jadwal->jam_mulai < $minJamMulai) {
                    $minJamMulai = $jadwal->jam_mulai;
                }
                if ($jadwal->jam_selesai > $maxJamSelesai) {
                    $maxJamSelesai = $jadwal->jam_selesai;
                }
            }

            $rows[] = [
                'tanggal'     => self::tanggal_indonesia(date('Y-m-d', strtotime($tanggal))),
                'jam_mulai'   => substr($minJamMulai, 0, 5),
                'jam_selesai' => substr($maxJamSelesai, 0, 5),
                'samplers'    => implode(', ', $samplerList),
            ];
        }

        return ['show' => true, 'rows' => $rows];
    }

    public static function tanggal_indonesia($tanggal, $mode = '')
    {
        $bulan = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        switch (date('D', strtotime($tanggal))) {
            case "Sun":
                $hari = "Minggu";
                break;
            case "Mon":
                $hari = "Senin";
                break;
            case "Tue":
                $hari = "Selasa";
                break;
            case "Wed":
                $hari = "Rabu";
                break;
            case "Thu":
                $hari = "Kamis";
                break;
            case "Fri":
                $hari = "Jum'at";
                break;
            case "Sat":
                $hari = "Sabtu";
                break;
        }

        $var = explode('-', $tanggal);
        if ($mode == 'period') {
            return $bulan[(int) $var[1]] . ' ' . $var[0];
        } else if ($mode == 'hari') {
            return $hari . ' / ' . $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        }
    }

    private function encrypt($data)
    {
        $ENCRYPTION_KEY       = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey        = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText        = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return               = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    /**
     * Subjek email pemberitahuan ke sales (bisa dipakai service pengirim).
     */
    public static function subjectPemberitahuanSalesEmail($quote): string
    {
        $no = self::resolveNoQtForEmail($quote);

        return 'Pemberitahuan: Jadwal ' . $no . ' telah divalidasi';
    }

    /**
     * HTML body email untuk sales: QT telah divalidasi + tabel ringkas (sama struktur lampiran jadwal).
     */
    public static function renderPemberitahuanSalesEmailHtml($quote, string $namaSales): string
    {
        $lampiranRows = self::lampiranRowsFromJadwal(self::collectJadwalForPemberitahuanEmail($quote));

        return view('TemplateEmailJadwal.pemberitahuan-validasi-sales', [
            'namaSales'       => $namaSales,
            'noQt'            => self::resolveNoQtForEmail($quote),
            'namaPelanggan'   => self::namaPelangganUntukEmail($quote),
            'alamatSampling'  => isset($quote->alamat_sampling) ? (string) $quote->alamat_sampling : '',
            'lampiranRows'    => $lampiranRows,
            'tanggalCetak'    => self::tanggal_indonesia(date('Y-m-d')),
            'jamCetak'        => date('G:i'),
        ])->render();
    }

    private static function collectJadwalForPemberitahuanEmail($quote): iterable
    {
        if ($quote instanceof QuotationNonKontrak) {
            $sp = $quote->sampling->first();
            if (! $sp || ! $sp->jadwal) {
                return [];
            }

            return $sp->jadwal;
        }

        if ($quote instanceof QuotationKontrakH) {
            $coll = collect();
            foreach ($quote->sampling->where('no_quotation', $quote->no_document) as $sp) {
                foreach ($sp->jadwal as $j) {
                    $coll->push($j);
                }
            }

            return $coll;
        }

        return [];
    }

    private static function resolveNoQtForEmail($quote): string
    {
        $first = $quote->sampling->first();

        return ($first && ! empty($first->no_quotation))
            ? (string) $first->no_quotation
            : (string) $quote->no_document;
    }

    private static function namaPelangganUntukEmail($quote): string
    {
        if (isset($quote->konsultan) && $quote->konsultan != '') {
            return strtoupper($quote->konsultan) . ' ( ' . $quote->nama_perusahaan . ' ) ';
        }

        return (string) $quote->nama_perusahaan;
    }

    /**
     * Siapkan penerima & body HTML; pengiriman dilakukan oleh service email terpisah.
     */
    private function emailJadwalSampling($isEmail, $dataQt)
    {
        if (! $isEmail) {
            return;
        }

        try {
            $sales = MasterKaryawan::where('id', $dataQt->sales_id)->first();
            if (! $sales || empty($sales->email)) {
                return;
            }

            $admSales = null;
            if (! empty($dataQt->updated_by)) {
                $admSales = MasterKaryawan::where('nama_lengkap', $dataQt->updated_by)->first();
            }

            $atasanSales = GetAtasan::where('id', $dataQt->sales_id)->get();
            $emailAtasanSales = [];
            if ($atasanSales->count() > 0) {
                $emailAtasanSales = $atasanSales->pluck('email')->toArray();
            }

            $emailSales = $sales->email;
            $emailAtasanSales = array_values(array_filter($emailAtasanSales, function ($email) use ($emailSales) {
                return $email && $email !== $emailSales;
            }));

            $emailAdmSales = ($admSales && ! empty($admSales->email)) ? $admSales->email : null;
            $emailBcc      = array_merge($emailAtasanSales, array_filter([$emailAdmSales]));
            $emailTo       = $emailSales;

            $htmlBody = self::renderPemberitahuanSalesEmailHtml($dataQt, $sales->nama_lengkap);
            $subject  = self::subjectPemberitahuanSalesEmail($dataQt);

            $send = SendEmail::where('to', $emailTo)
                ->where('subject', $subject)
                ->where('body', $htmlBody)
                ->where('bcc', $emailBcc)
                ->noReply()
                ->send();
            
            return $send;

        } catch (\Throwable $e) {
            Log::error([
                'emailJadwalSampling: ' . $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }
}
