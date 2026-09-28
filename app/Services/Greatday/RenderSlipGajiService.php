<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;
use App\Models\Payroll;
use App\Models\PayrollHeader;
use App\Services\Greatday\SlipGajiPinService;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Log;

class RenderSlipGajiService
{
    public const PASSWORD_PROMPT = 'Silahkan masukan password proteksi berupa tanggal lahir anda dengan format DD/MM/YYYY';
    public const PIN_PASSWORD_PROMPT = 'Silahkan masukan password proteksi berupa PIN 6 digit Anda';
    private const IMAGE_DPI = 150;
    private const IMAGE_QUALITY = 85;

    private $data_karyawan;
    private $periode;
    private $data_gaji;
    private $tgl_transfer;
    private $output_mode = 'file';
    private $output_path;
    private $filename;
    private $pdf_access_pin;
    private $pdf_password_hint;

    private static $instance;

    /**
     * File wajib di proteksi (view, edit) dengan password tanggal lahir format ddmmyyyy.
     *
     * Contoh pemanggilan manual:
     * RenderSlipGaji::where('periode', 'Januari 2024')
     *     ->where('tgl_transfer', '25 Januari 2024')
     *     ->where('data_karyawan', (object) [
     *         'nama' => 'John Doe',
     *         'nik' => '123456',
     *         'divisi' => 'IT',
     *         'jabatan' => 'Staff',
     *         'start_date' => '01-01-2020',
     *         'nama_bank' => 'BCA',
     *         'no_rekening' => '1234567890',
     *         'tanggal_lahir' => '1990-01-01',
     *     ])
     *     ->where('data_gaji', $payrollObject)
     *     ->generate();
     *
     * Contoh dari controller (payroll id):
     * RenderSlipGaji::fromPayrollId($payrollId)->generate();
     */
    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        $instance = self::$instance;

        switch ($field) {
            case 'periode':
                if (empty($value)) {
                    throw new \Exception('Periode is required');
                }
                $instance->periode = $value;
                break;
            case 'data_karyawan':
                if (empty($value)) {
                    throw new \Exception('Data karyawan is required');
                }
                $instance->data_karyawan = is_array($value) ? (object) $value : $value;
                break;
            case 'data_gaji':
                if (empty($value)) {
                    throw new \Exception('Data gaji is required');
                }
                $instance->data_gaji = is_array($value) ? (object) $value : $value;
                break;
            case 'tgl_transfer':
                $instance->tgl_transfer = $value;
                break;
            case 'output_mode':
                $instance->output_mode = $value;
                break;
            case 'output_path':
                $instance->output_path = $value;
                break;
            case 'filename':
                $instance->filename = $value;
                break;
            case 'pdf_access_pin':
                $instance->pdf_access_pin = $value;
                break;
            case 'pdf_password_hint':
                $instance->pdf_password_hint = $value;
                break;
            default:
                throw new \Exception('Invalid field: ' . $field);
        }

        return $instance;
    }

    private static function createBuilder()
    {
        self::$instance = new self();

        return self::$instance;
    }

    public static function resolvePayrollKaryawan(Payroll $payroll): ?MasterKaryawan
    {
        if ($payroll->relationLoaded('karyawan')) {
            $relation = $payroll->getRelation('karyawan');

            if ($relation instanceof MasterKaryawan) {
                return $relation;
            }
        }

        if (empty($payroll->id_karyawan)) {
            return null;
        }

        return MasterKaryawan::find($payroll->id_karyawan);
    }

    public static function resolveKaryawanBirthDate($karyawan)
    {
        foreach (['tanggal_lahir', 'date_birth', 'tgl_lahir'] as $field) {
            if (!empty($karyawan->{$field})) {
                return $karyawan->{$field};
            }
        }

        return null;
    }

    public static function resolveBirthDatePassword($karyawan)
    {
        $birthDate = self::resolveKaryawanBirthDate($karyawan);

        if (empty($birthDate)) {
            return null;
        }

        return Carbon::parse($birthDate)->format('dmY');
    }

    public static function resolvePdfPasswordForKaryawan($karyawan, $verifiedPin = null, $karyawanId = null)
    {
        $karyawanId = $karyawanId ?? ($karyawan->id ?? null);

        if ($karyawanId && SlipGajiPinService::hasPin($karyawanId)) {
            if (empty($verifiedPin)) {
                throw new \Exception('PIN proteksi diperlukan untuk slip gaji ini', 403);
            }

            return [
                'password' => $verifiedPin,
                'hint' => self::PIN_PASSWORD_PROMPT,
                'type' => 'pin',
            ];
        }

        $birthDatePassword = self::resolveBirthDatePassword($karyawan);

        if ($birthDatePassword) {
            return [
                'password' => $birthDatePassword,
                'hint' => self::PASSWORD_PROMPT,
                'type' => 'dob',
            ];
        }

        throw new \Exception('Tanggal lahir karyawan belum diisi, proteksi PDF tidak dapat dibuat');
    }

    public static function buildSlipFilenames(Payroll $payroll)
    {
        $karyawan = self::resolvePayrollKaryawan($payroll);

        $periodeSlug = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower(self::formatPeriode($payroll->periode_payroll)));
        $nik = trim((string) (
            $payroll->nik_karyawan
            ?? optional($karyawan)->nik_karyawan
            ?? ''
        ));

        if ($nik === '') {
            throw new \Exception('NIK karyawan belum diisi');
        }

        $nikSlug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $nik);
        $pdfFilename = 'slip-gaji-' . $nikSlug . '-' . trim($periodeSlug, '-') . '.pdf';

        return [
            'pdf_filename' => $pdfFilename,
            'image_filename' => preg_replace('/\.pdf$/i', '.webp', $pdfFilename),
        ];
    }

    public static function convertExistingPayrollPdf(Payroll $payroll, $pdfPath, $verifiedPin = null)
    {
        $karyawan = self::resolvePayrollKaryawan($payroll);

        if (!$karyawan) {
            throw new \Exception('Data karyawan tidak ditemukan');
        }

        if (!is_file($pdfPath)) {
            throw new \Exception('File PDF slip gaji tidak ditemukan');
        }

        $service = new self();
        $karyawanId = $karyawan->id ?? null;
        $passwordMeta = self::resolvePdfPasswordForKaryawan($karyawan, $verifiedPin, $karyawanId);

        $passwordCandidates = [$passwordMeta['password']];
        if (!empty($verifiedPin) && !in_array($verifiedPin, $passwordCandidates, true)) {
            $passwordCandidates[] = $verifiedPin;
        }

        $birthDatePassword = self::resolveBirthDatePassword($karyawan);
        if (!empty($birthDatePassword) && !in_array($birthDatePassword, $passwordCandidates, true)) {
            $passwordCandidates[] = $birthDatePassword;
        }

        $image = $service->convertImageWithPasswordCandidates($pdfPath, $passwordCandidates);
        $filenames = self::buildSlipFilenames($payroll);

        return [
            'filename' => $filenames['pdf_filename'],
            'path' => $pdfPath,
            'url' => '/dokumen/payroll/slip_gaji/pdf/' . $filenames['pdf_filename'],
            'image' => $image,
            'image_url' => $image['url'],
            'image_filename' => $image['filename'],
            'password_hint' => $passwordMeta['hint'],
            'password_type' => $passwordMeta['type'],
        ];
    }

    public static function fromPayrollId($payrollId, $verifiedPin = null)
    {
        $payroll = Payroll::with([
            'karyawan.divisi',
            'karyawan.jabatan',
        ])
            ->where('id', $payrollId)
            ->where('is_active', true)
            ->first();

        if (!$payroll) {
            throw new \Exception('Data payroll tidak ditemukan');
        }

        $header = PayrollHeader::find($payroll->payroll_header_id);
        $karyawan = self::resolvePayrollKaryawan($payroll);

        if (!$karyawan) {
            throw new \Exception('Data karyawan tidak ditemukan');
        }

        $passwordMeta = self::resolvePdfPasswordForKaryawan($karyawan, $verifiedPin, $karyawan->id);
        $birthDate = self::resolveKaryawanBirthDate($karyawan);

        return self::createBuilder()
            ->where('periode', self::formatPeriode($payroll->periode_payroll))
            ->where('tgl_transfer', self::formatTglTransfer($header->tgl_transfer ?? null))
            ->where('filename', self::buildSlipFilenames($payroll)['pdf_filename'])
            ->where('pdf_access_pin', $passwordMeta['password'])
            ->where('pdf_password_hint', $passwordMeta['hint'])
            ->where('data_karyawan', (object) [
                'nama' => $karyawan->nama_lengkap,
                'nik' => $payroll->nik_karyawan ?? $karyawan->nik_karyawan,
                'divisi' => optional($karyawan->divisi)->nama_divisi ?? '-',
                'jabatan' => $payroll->nama_jabatan ?? '-',
                'start_date' => self::formatTglTransfer($karyawan->tgl_mulai_kerja ?? null),
                'tgl_mulai_kerja' => $karyawan->tgl_mulai_kerja,
                'nama_bank' => $payroll->nama_bank,
                'no_rekening' => $payroll->no_rekening,
                'tanggal_lahir' => $birthDate,
            ])
            ->where('data_gaji', $payroll);
    }

    public function generate()
    {
        try {
            $this->validateRequiredData();
            
            $viewData = $this->buildViewData();
            $userPassword = $this->resolvePassword();
            $passwordHint = $this->resolvePasswordHint();
            $passwordFormat = $this->resolvePasswordFormat();
            $ownerPassword = bin2hex(random_bytes(16));
            $filename = $this->resolveFilename($viewData['karyawan']->nik);
            $outputPath = $this->resolveOutputPath($filename);

            // Ukuran A4 = 210mm x 297mm, setengah A4 untuk tinggi berarti 210mm x 148.5mm
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [210, 148.5],
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'orientation' => 'P',
                'default_font' => 'dejavusans',
            ]);
    

            $mpdf->SetTitle($passwordHint ?: 'Slip Gaji');
            $mpdf->SetSubject($userPassword ? 'Slip Gaji - Dokumen Terproteksi' : 'Slip Gaji');
            $mpdf->SetAuthor('PT Inti Surya Laboratorium');
            $mpdf->SetCreator('Sistem Payroll ISL');

            if ($userPassword) {
                $mpdf->SetProtection(
                    [],
                    $userPassword,
                    $ownerPassword,
                    128,
                    [
                        'copy' => false,
                        'modify' => false,
                        'print' => false,
                        'annot-forms' => false,
                        'fill-forms' => false,
                        'extract' => false,
                        'assemble' => false,
                        'print-highres' => false,
                    ]
                );
            }

            $this->applyWatermark($mpdf);

            $html = view('Slip-Gaji', $viewData)->render();
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

            $pdfPathForImage = null;
            $tempPdfPath = null;

            switch ($this->output_mode) {
                case 'string':
                    $content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
                    $result = [
                        'filename' => $filename,
                        'content' => $content,
                        'password_hint' => $passwordHint,
                        'password_format' => $passwordFormat,
                    ];
                    $tempPdfPath = $this->writeTempPdf($content);
                    $pdfPathForImage = $tempPdfPath;
                    break;

                case 'download':
                    $content = $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);
                    $result = [
                        'filename' => $filename,
                        'content' => $content,
                        'password_hint' => $passwordHint,
                        'password_format' => $passwordFormat,
                        'headers' => [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="' . $filename . '"',
                        ],
                    ];
                    $tempPdfPath = $this->writeTempPdf($content);
                    $pdfPathForImage = $tempPdfPath;
                    break;

                case 'file':
                default:
                    $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);
                    $result = [
                        'filename' => $filename,
                        'path' => $outputPath,
                        'url' => '/dokumen/payroll/slip_gaji/pdf/' . $filename,
                        'password_hint' => $passwordHint,
                        'password_format' => $passwordFormat,
                    ];
                    $pdfPathForImage = $outputPath;
                    break;
            }

            if ($pdfPathForImage) {
                $result = $this->appendImageResult($result, $pdfPathForImage, $userPassword);
            }

            if ($tempPdfPath && is_file($tempPdfPath)) {
                @unlink($tempPdfPath);
            }

            return $result;
        } finally {
            self::$instance = null;
        }
    }

    private function resolveLogoPath()
    {
        $candidates = [
            public_path('img/isl_logo.png'),
            public_path('isl_logo.png'),
            resource_path('assets/isl_logo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolveWatermarkPath()
    {
        $candidates = [
            public_path('logo-watermark.png'),
            resource_path('assets/logo-watermark.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function applyWatermark(Mpdf $mpdf)
    {
        $watermarkPath = $this->resolveWatermarkPath();

        if (!$watermarkPath) {
            return;
        }

        $imageSize = @getimagesize($watermarkPath);
        if (!$imageSize) {
            return;
        }

        [$imgWidth, $imgHeight] = $imageSize;
        $pageWidth = 210;
        $pageHeight = 148.5;
        $imgAspect = $imgWidth / $imgHeight;

        $watermarkHeight = $pageHeight * 0.60;
        $watermarkWidth = $watermarkHeight * $imgAspect;

        $posX = ($pageWidth - $watermarkWidth) / 2;
        $posY = max(5, (($pageHeight - $watermarkHeight) / 2) - 5);

        $mpdf->SetWatermarkImage(
            $watermarkPath,
            -1,
            [$watermarkWidth, $watermarkHeight],
            [$posX, $posY]
        );
        $mpdf->showWatermarkImage = true;
    }

    private function buildPasswordInstructionHtml()
    {
        return '
            <div style="font-family: Arial, sans-serif; font-size: 11px; border: 1px solid #000; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                <strong>Dokumen Terproteksi</strong><br>
                ' . self::PASSWORD_PROMPT . '<br>
                <span style="font-size: 10px;">Masukkan password tanpa tanda baca, contoh: 01011990 untuk tanggal lahir 01/01/1990.</span>
            </div>
        ';
    }

    private function validateRequiredData()
    {
        if (empty($this->periode)) {
            throw new \Exception('Periode is required');
        }

        if (empty($this->data_karyawan)) {
            throw new \Exception('Data karyawan is required');
        }

        if (empty($this->data_gaji)) {
            throw new \Exception('Data gaji is required');
        }
    }

    private function buildViewData()
    {
        $gaji = $this->data_gaji;
        $karyawan = $this->data_karyawan;

        $gajiPokok = (float) ($gaji->gaji_pokok ?? 0);
        $tunjangan = (float) ($gaji->tunjangan ?? 0);
        $bonus = (float) ($gaji->bonus ?? 0);
        $incentive = (float) ($gaji->incentive ?? 0);
        $pencadanganUpah = (float) ($gaji->pencadangan_upah ?? 0);

        $absen = (float) ($gaji->potongan_absen ?? $gaji->absen ?? 0);
        $sanksi = (float) ($gaji->sanksi ?? 0);
        $potonganLain = (float) ($gaji->potongan_lainnya ?? $gaji->potongan_lain ?? 0);
        $jamsostek = (float) ($gaji->jamsostek ?? 0);
        $bpjsKesehatan = (float) ($gaji->bpjs_kesehatan ?? 0);
        $loan = (float) ($gaji->loan ?? 0);
        $pph = (float) ($gaji->pajak_pph ?? $gaji->pph ?? 0);

        $pendapatanPositif = max(0, $pencadanganUpah);
        $potonganPencadangan = $pencadanganUpah < 0 ? abs($pencadanganUpah) : 0;

        $isKaryawanBaruPeriodeIni = self::isKaryawanBaruPeriodeIni($karyawan, $gaji);
        $potonganProrata = $isKaryawanBaruPeriodeIni ? $absen : 0;
        $potonganIndisipliner = $isKaryawanBaruPeriodeIni
            ? ($sanksi + $potonganLain)
            : ($absen + $sanksi + $potonganLain);

        $totalPendapatan = $gajiPokok + $pendapatanPositif + $tunjangan + $bonus + $incentive;
        $totalPotongan = $potonganProrata + $potonganIndisipliner + $jamsostek + $bpjsKesehatan + $potonganPencadangan + $loan + $pph;
        $takeHomePay = (float) ($gaji->take_home_pay ?? ($totalPendapatan - $totalPotongan));

        $tglMulaiKerja = $karyawan->tgl_mulai_kerja ?? null;

        return [
            'tgl_transfer' => $this->tgl_transfer ?? self::formatTglTransfer($gaji->tgl_transfer ?? null),
            'periode' => $this->periode,
            'logo_path' => $this->resolveLogoPath(),
            'is_karyawan_baru_periode_ini' => $isKaryawanBaruPeriodeIni,
            'keterangan_gaji' => ($isKaryawanBaruPeriodeIni && $potonganProrata > 0)
                ? 'Karyawan mulai bergabung pada ' . self::formatTglTransfer($tglMulaiKerja)
                    . '. Gaji pokok dan tunjangan di atas merupakan nominal aktual per bulan; pembayaran periode ini dihitung proporsional sesuai tanggal mulai kerja.'
                : null,
            'karyawan' => (object) [
                'nama' => $karyawan->nama ?? $karyawan->nama_lengkap ?? '-',
                'nik' => $karyawan->nik ?? $karyawan->nik_karyawan ?? '-',
                'divisi' => $karyawan->divisi ?? $karyawan->nama_divisi ?? '-',
                'jabatan' => $karyawan->jabatan ?? $karyawan->nama_jabatan ?? '-',
                'start_date' => $karyawan->start_date ?? self::formatTglTransfer($tglMulaiKerja),
                'tgl_mulai_kerja' => $tglMulaiKerja,
                'nama_bank' => $karyawan->nama_bank ?? $gaji->nama_bank ?? '-',
                'no_rekening' => $karyawan->no_rekening ?? $gaji->no_rekening ?? '-',
            ],
            'pendapatan' => (object) [
                'gaji_pokok' => $gajiPokok,
                'pencadangan_upah' => $pendapatanPositif,
                'tunjangan' => $tunjangan,
                'bonus' => $bonus,
                'incentive' => $incentive,
                'total_pendapatan' => $totalPendapatan,
                'take_home_pay' => $takeHomePay,
            ],
            'potongan' => (object) [
                'absen' => $absen,
                'prorata' => $potonganProrata,
                'indisipliner' => $potonganIndisipliner,
                'sanksi' => $sanksi,
                'potongan_lain' => $potonganLain,
                'bpjs_tk' => $jamsostek,
                'jamsostek' => $jamsostek,
                'bpjs_kesehatan' => $bpjsKesehatan,
                'pencadangan_upah' => $pencadanganUpah,
                'loan' => $loan,
                'pph' => $pph,
                'total_potongan' => $totalPotongan,
            ],
        ];
    }

    private function resolvePassword()
    {
        if ($this->pdf_access_pin !== null && $this->pdf_access_pin !== '') {
            return $this->pdf_access_pin;
        }

        $birthDatePassword = self::resolveBirthDatePassword($this->data_karyawan);

        if ($birthDatePassword) {
            return $birthDatePassword;
        }

        throw new \Exception('Tanggal lahir karyawan belum diisi, proteksi PDF tidak dapat dibuat');
    }

    private function resolvePasswordHint()
    {
        if (!empty($this->pdf_password_hint)) {
            return $this->pdf_password_hint;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return null;
        }

        if (preg_match('/^\d{6}$/', $password)) {
            return self::PIN_PASSWORD_PROMPT;
        }

        return self::PASSWORD_PROMPT;
    }

    private function resolvePasswordFormat()
    {
        $password = $this->resolvePassword();

        if ($password === null) {
            return null;
        }

        return preg_match('/^\d{6}$/', $password) ? 'pin' : 'ddmmyyyy';
    }

    private function resolveFilename($nik)
    {
        if (!empty($this->filename)) {
            return $this->filename;
        }

        $periodeSlug = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($this->periode));
        $nikSlug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $nik);

        return 'slip-gaji-' . $nikSlug . '-' . trim($periodeSlug, '-') . '.pdf';
    }

    private function resolveOutputPath($filename)
    {
        if (!empty($this->output_path)) {
            $dir = rtrim($this->output_path, '/\\');
        } else {
            $dir = public_path('dokumen/payroll/slip_gaji/pdf');
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . DIRECTORY_SEPARATOR . $filename;
    }

    private static function isKaryawanBaruPeriodeIni($karyawan, $gaji)
    {
        $tglMulaiKerja = $karyawan->tgl_mulai_kerja ?? null;
        $periodePayroll = $gaji->periode_payroll ?? null;

        if (empty($tglMulaiKerja) || empty($periodePayroll)) {
            return false;
        }

        $mulaiKerja = Carbon::parse($tglMulaiKerja);
        $periode = Carbon::parse(
            preg_match('/^\d{4}-\d{2}$/', $periodePayroll) ? $periodePayroll . '-01' : $periodePayroll
        );

        return $mulaiKerja->format('Y-m') === $periode->format('Y-m');
    }

    private static function formatPeriode($periode)
    {
        if (empty($periode)) {
            return '-';
        }

        $value = preg_match('/^\d{4}-\d{2}$/', $periode) ? $periode . '-01' : $periode;

        return Carbon::parse($value)->locale('id')->translatedFormat('F Y');
    }

    private static function formatTglTransfer($tanggal)
    {
        if (empty($tanggal)) {
            return '-';
        }

        return Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
    }

    private function writeTempPdf($content)
    {
        $tempPdfPath = tempnam(sys_get_temp_dir(), 'slip-gaji-') . '.pdf';
        file_put_contents($tempPdfPath, $content);

        return $tempPdfPath;
    }

    private function appendImageResult(array $result, $pdfPath, $password)
    {
        $imageResult = $this->convertImage($pdfPath, $password);
        $result['image'] = $imageResult;
        $result['image_url'] = $imageResult['url'];
        $result['image_filename'] = $imageResult['filename'];

        return $result;
    }

    private function resolveImageOutputDir()
    {
        $dir = public_path('dokumen/payroll/slip_gaji/image');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function resolveImageFilename($pdfFilename)
    {
        return preg_replace('/\.pdf$/i', '.webp', $pdfFilename);
    }

    private function ensureImagickAvailable()
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            throw new \Exception('Extension Imagick belum terpasang.');
        }
    }

    private function isWindows()
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    private function resolveCommandInPath($command)
    {
        if ($this->isWindows()) {
            $output = shell_exec('where ' . $command . ' 2>nul');
        } else {
            $output = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        }

        $line = trim(explode("\n", str_replace("\r", '', trim((string) $output)))[0] ?? '');
        if ($line === '' || !is_file($line)) {
            return null;
        }

        return $line;
    }

    private function prependPathDirectory($directory)
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        $path = getenv('PATH') ?: '';
        if (strpos($path, $directory) === false) {
            putenv('PATH=' . $directory . PATH_SEPARATOR . $path);
        }
    }

    private function findGhostscriptBinary()
    {
        $candidates = [];

        $envGs = getenv('GHOSTSCRIPT_BINARY') ?: getenv('GS_PROG');
        if (!empty($envGs)) {
            $candidates[] = $envGs;
        }

        if ($this->isWindows()) {
            $candidates = array_merge($candidates, [
                base_path('tools/ghostscript/install/gs/bin/gswin64c.exe'),
                base_path('tools/ghostscript/bin/gswin64c.exe'),
            ]);

            foreach (['C:\\Program Files\\gs', 'C:\\Program Files (x86)\\gs'] as $base) {
                if (!is_dir($base)) {
                    continue;
                }

                $dirs = glob($base . '\\gs*', GLOB_ONLYDIR) ?: [];
                foreach ($dirs as $dir) {
                    $candidates[] = $dir . '\\bin\\gswin64c.exe';
                    $candidates[] = $dir . '\\bin\\gswin32c.exe';
                }
            }

            foreach (['gswin64c', 'gswin32c', 'gs'] as $command) {
                $resolved = $this->resolveCommandInPath($command);
                if ($resolved) {
                    $candidates[] = $resolved;
                }
            }
        } else {
            $candidates = array_merge($candidates, [
                '/usr/bin/gs',
                '/usr/local/bin/gs',
            ]);

            $resolved = $this->resolveCommandInPath('gs');
            if ($resolved) {
                $candidates[] = $resolved;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function configureGhostscriptForImagick()
    {
        $gsBinary = $this->findGhostscriptBinary();
        if (!$gsBinary) {
            return;
        }

        $gsBasename = strtolower(basename($gsBinary));

        // Linux/macOS: gs biasanya sudah tersedia di PATH (setup produksi existing).
        if (!$this->isWindows()) {
            $this->prependPathDirectory(dirname($gsBinary));
            return;
        }

        // Windows: ImageMagick memanggil "gs", bukan "gswin64c".
        if ($gsBasename === 'gs.exe' || $gsBasename === 'gs') {
            $this->prependPathDirectory(dirname($gsBinary));
            return;
        }

        // gswin64c/gswin32c: salin ke folder writable karena Program Files butuh admin.
        $gsAliasDir = storage_path('app/ghostscript');
        if (!is_dir($gsAliasDir)) {
            mkdir($gsAliasDir, 0777, true);
        }

        $gsAlias = $gsAliasDir . DIRECTORY_SEPARATOR . 'gs.exe';
        if (!is_file($gsAlias) || filemtime($gsBinary) > filemtime($gsAlias)) {
            if (!@copy($gsBinary, $gsAlias)) {
                throw new \Exception(
                    'Gagal membuat alias gs.exe di ' . $gsAliasDir
                    . '. Pastikan folder storage/app/ghostscript dapat ditulis.'
                );
            }
        }

        $this->prependPathDirectory($gsAliasDir);
    }

    private function ensureGhostscriptAvailable()
    {
        if ($this->findGhostscriptBinary()) {
            return;
        }

        if ($this->isWindows()) {
            throw new \Exception(
                'Ghostscript (gswin64c) diperlukan untuk konversi PDF ke gambar. '
                . 'Install dari https://ghostscript.com/releases/gsdnld.html lalu tambahkan ke PATH.'
            );
        }

        throw new \Exception(
            'Ghostscript (gs) diperlukan untuk konversi PDF ke gambar. '
            . 'Install paket ghostscript (apt/yum) dan pastikan perintah gs tersedia di PATH.'
        );
    }

    private function convertImageWithPasswordCandidates($pdfPath, array $passwordCandidates)
    {
        $lastError = null;

        foreach ($passwordCandidates as $password) {
            try {
                return $this->convertImage($pdfPath, $password);
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        try {
            return $this->convertImage($pdfPath, null);
        } catch (\Throwable $e) {
            throw $lastError ?? $e;
        }
    }

    private function convertImage($pdfPath, $password = null)
    {
        $this->ensureImagickAvailable();
        $this->configureGhostscriptForImagick();

        if (!is_file($pdfPath)) {
            throw new \Exception('File PDF tidak ditemukan: ' . $pdfPath);
        }

        $imageFilename = $this->resolveImageFilename(basename($pdfPath));
        $imageDir = $this->resolveImageOutputDir();
        $imagePath = $imageDir . DIRECTORY_SEPARATOR . $imageFilename;
        $tempPngPath = null;

        try {
            $tempPngPath = $this->renderPdfPageToPng($pdfPath, $password);
            $this->writeWebpFromPng($tempPngPath, $imagePath);
        } catch (\Throwable $e) {
            Log::error('Gagal konversi PDF ke gambar: ' . $e->getMessage(), [
                'pdf' => $pdfPath,
            ]);
            throw new \Exception('Gagal membaca PDF: ' . $e->getMessage());
        } finally {
            if ($tempPngPath && is_file($tempPngPath)) {
                @unlink($tempPngPath);
            }
        }

        return [
            'filename' => $imageFilename,
            'path' => $imagePath,
            'url' => '/dokumen/payroll/slip_gaji/image/' . $imageFilename,
        ];
    }

    private function findPdftoppmBinary()
    {
        $candidates = [];

        $envBinary = getenv('PDFTOPPM_BINARY');
        if (!empty($envBinary)) {
            $candidates[] = $envBinary;
        }

        if ($this->isWindows()) {
            foreach (['pdftoppm', 'pdftoppm.exe'] as $command) {
                $resolved = $this->resolveCommandInPath($command);
                if ($resolved) {
                    $candidates[] = $resolved;
                }
            }
        } else {
            $candidates = array_merge($candidates, [
                '/usr/bin/pdftoppm',
                '/usr/local/bin/pdftoppm',
            ]);

            $resolved = $this->resolveCommandInPath('pdftoppm');
            if ($resolved) {
                $candidates[] = $resolved;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildPdfPasswordArg($password)
    {
        if (empty($password)) {
            return '';
        }

        $sanitized = preg_replace('/[^0-9]/', '', (string) $password);

        return $sanitized !== '' ? '-sPDFPassword=' . $sanitized . ' ' : '';
    }

    private function renderPdfPageToPng($pdfPath, $password = null)
    {
        $errors = [];

        try {
            return $this->renderPdfPageToPngWithGhostscript($pdfPath, $password);
        } catch (\Throwable $e) {
            $errors[] = 'Ghostscript: ' . $e->getMessage();
        }

        try {
            return $this->renderPdfPageToPngWithPdftoppm($pdfPath, $password);
        } catch (\Throwable $e) {
            $errors[] = 'pdftoppm: ' . $e->getMessage();
        }

        throw new \Exception(implode(' | ', $errors));
    }

    private function renderPdfPageToPngWithGhostscript($pdfPath, $password = null)
    {
        $gsBinary = $this->findGhostscriptBinary();
        if (!$gsBinary) {
            throw new \Exception('Ghostscript (gs) tidak ditemukan');
        }

        $tempPngPath = tempnam(sys_get_temp_dir(), 'slip-gaji-png-') . '.png';
        $passwordArg = $this->buildPdfPasswordArg($password);

        $command = sprintf(
            '%s %s-dQUIET -dSAFER -dBATCH -dNOPAUSE -dNOPROMPT -dUseCropBox'
            . ' -dFirstPage=1 -dLastPage=1 -sDEVICE=png16m -r%d -sOutputFile=%s %s 2>&1',
            escapeshellarg($gsBinary),
            $passwordArg,
            self::IMAGE_DPI,
            escapeshellarg($tempPngPath),
            escapeshellarg($pdfPath)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($tempPngPath)) {
            if (is_file($tempPngPath)) {
                @unlink($tempPngPath);
            }

            throw new \Exception(trim(implode("\n", $output)) ?: 'Ghostscript gagal merender halaman PDF');
        }

        return $tempPngPath;
    }

    private function renderPdfPageToPngWithPdftoppm($pdfPath, $password = null)
    {
        $pdftoppmBinary = $this->findPdftoppmBinary();
        if (!$pdftoppmBinary) {
            throw new \Exception('pdftoppm tidak ditemukan');
        }

        $outputPrefix = tempnam(sys_get_temp_dir(), 'slip-gaji-png-');
        if ($outputPrefix === false) {
            throw new \Exception('Gagal menyiapkan file sementara untuk preview');
        }

        @unlink($outputPrefix);

        $commandParts = [
            escapeshellarg($pdftoppmBinary),
            '-png',
            '-f 1',
            '-l 1',
            '-r ' . self::IMAGE_DPI,
        ];

        if (!empty($password)) {
            $sanitized = preg_replace('/[^0-9]/', '', (string) $password);
            if ($sanitized !== '') {
                $commandParts[] = '-upw ' . escapeshellarg($sanitized);
            }
        }

        $commandParts[] = escapeshellarg($pdfPath);
        $commandParts[] = escapeshellarg($outputPrefix);
        $command = implode(' ', $commandParts) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $generatedCandidates = [
            $outputPrefix . '-1.png',
            $outputPrefix . '-01.png',
        ];

        $generatedPath = null;
        foreach ($generatedCandidates as $candidate) {
            if (is_file($candidate)) {
                $generatedPath = $candidate;
                break;
            }
        }

        if ($exitCode !== 0 || !$generatedPath) {
            foreach ($generatedCandidates as $candidate) {
                if (is_file($candidate)) {
                    @unlink($candidate);
                }
            }

            throw new \Exception(trim(implode("\n", $output)) ?: 'pdftoppm gagal merender halaman PDF');
        }

        $tempPngPath = tempnam(sys_get_temp_dir(), 'slip-gaji-png-') . '.png';
        if (!@rename($generatedPath, $tempPngPath)) {
            @copy($generatedPath, $tempPngPath);
            @unlink($generatedPath);
        }

        foreach ($generatedCandidates as $candidate) {
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }

        return $tempPngPath;
    }

    private function writeWebpFromPng($pngPath, $webpPath)
    {
        $imagick = new \Imagick($pngPath);
        $imagick->setIteratorIndex(0);
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality(self::IMAGE_QUALITY);
        $imagick->stripImage();
        $imagick->writeImage($webpPath);
        $imagick->clear();
        $imagick->destroy();
    }
}
