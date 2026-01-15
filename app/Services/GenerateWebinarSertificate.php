<?php

namespace App\Services;

use Mpdf\Mpdf;
use Exception;
use App\Helpers\Helper;
use App\Models\QrDocument;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\View;

class GenerateWebinarSertificate
{
    private $filename;
    private $options = [];
    private $mpdf;
    private $qr_code;
    private $top_distance = 30;
    private $outputDir = 'certificates';

    /**
     * Constructor for GenerateWebinarSertificate object
     * 
     * @param string $filename ex: "sertifikat-123.pdf"
     * 
     * @return void
     */
    private function __construct(string $filename) {
        $this->filename = $filename;
    }

    public static function make(string $filename): self {
        return new self($filename);
    }

    public function options(array $options): self {
        $this->options = array_merge([
            'template' => 'bg-biru.png',
            'layout' => 'layout-1',
            'font' => [
                'fontName' => 'roboto',
                'filename' => 'Roboto-Regular.ttf'
            ]
        ], $options);

        return $this;
    }

    /**
     * Generate sertifikat
     * 
     * @return string ex: "sertifikat-123.pdf"
     */
    public function generate(): string
    {
        try{
            // Validasi input
            $this->validateInputs();
            
            // Inisialisasi MPDF dengan font Great Vibes
            $this->initializeMpdf();
            
            // Generate sertifikat
            $this->createCertificate();
            // Simpan file
            $this->saveCertificate($this->options['output']);
            
            $this->resetParams();
            return $this->filename;
        } catch (Exception $e) {
            $this->resetParams();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 500
            ], 500);
        }
    }

    private function validateInputs(): void
    {
        // Validate required options
        $requiredOptions = ['recipientName', 'webinarTitle', 'webinarTopic', 'webinarDate', 'panelis', 'noSertifikat'];
        foreach ($requiredOptions as $option) {
            if (empty($this->options[$option])) {
                throw new Exception("Option '{$option}' is required");
            }
        }

        // Ensure template directory exists
        $templateDir = dirname(public_path('background-sertifikat/'));
        if (!is_dir($templateDir)) {
            if (!mkdir($templateDir, 02775, true)) {
                throw new Exception("Failed to create template directory: {$templateDir}");
            }
        }

        // Validate template file
        $templatePath = public_path('background-sertifikat/' . $this->options['template']);
        if (!file_exists($templatePath)) {
            $bgFiles = glob(public_path('background-sertifikat/*.{jpg,jpeg,png,gif,bmp,webp}'), GLOB_BRACE);
            if (empty($bgFiles)) {
                throw new Exception("Template file not found in: " . public_path('background-sertifikat'));
            }
            $templatePath = $bgFiles[0];
        }

        // Validate template extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Unsupported template format. Use: " . implode(', ', $allowedExtensions));
        }

        // Validate storage output sertificate on public path
        $outputDir = public_path($this->outputDir);
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 02775, true)) {
                throw new Exception("Failed to create template directory: {$outputDir}");
            }
        }

        // Store validated paths
        $this->options['templatePath'] = $templatePath;
        $this->options['output'] = $outputDir . '/' . $this->filename;
    }

    private function initializeMpdf(): void
    {
        if($this->options['font']['fontName'] !== 'roboto') {
            $fontPath = public_path('fonts/' . $this->options['font']['filename']);
            if (!file_exists($fontPath)) {
                $this->downloadFont();
            }
        }
        
        $defaultFont =  public_path('fonts/Roboto-Regular.ttf');
        if(!file_exists($defaultFont)) {
            $this->downloadDefaultFont();
        }

        // Configure MPDF
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L', // Landscape for certificate
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font_size' => 0,
            'tempDir' => storage_path('tmp/mpdf'),
            'default_font' => 'dejavusans',
            'fontDir' => array_merge(
                (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],
                [public_path('fonts')]
            ),
            'fontdata' => array_merge(
                (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'],
                []
            )
        ];

        if($this->options['font']['fontName'] !== 'roboto') {
            $config['fontdata'][$this->options['font']['fontName']] = [
                'R' => $this->options['font']['filename'],
            ];
        }
        
        $this->mpdf = new Mpdf($config);
        
        $this->mpdf->SetDisplayMode('fullpage');
        $this->mpdf->SetAutoPageBreak(false);
        
        // Set PDF metadata

        $this->mpdf->SetProtection(array('print'), '', 'skyhwk12');
        $this->mpdf->SetTitle("Certificate - " . $this->options['recipientName']);
        $this->mpdf->SetAuthor("Inti Surya Laboratorium");
        $this->mpdf->SetCreator("Inti Surya Laboratorium");
    }

    private function createCertificate(): void
    {
        try {
            // Tambah halaman baru
            $this->mpdf->AddPage();

            // Dapatkan dimensi halaman
            $pageWidth = $this->mpdf->w;
            $pageHeight = $this->mpdf->h;

            // Konversi nama ke format yang sesuai
            $convertedName = $this->formatName($this->options['recipientName']);
            $fontSize = $this->calculateFontSize(strlen($convertedName));
            
            // Get template image data
            $imageData = file_get_contents($this->options['templatePath']);
            if ($imageData === false) {
                throw new Exception("Failed to read template file: {$this->options['templatePath']}");
            }

            // Generate QR Code
            $this->qr_code = $this->generateQrCode();

            $tanggalWebinar = !empty($this->options['webinarDate']) ? 
                '<div>Tanggal ' . Helper::tanggal_indonesia($this->options['webinarDate']) . '</div>' : '';

            // Render template content
            $templateContent = $this->renderTemplate($convertedName, $tanggalWebinar);

            // HTML dan CSS untuk sertifikat dengan posisi tepat di tengah
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    @page {
                        margin: 0;
                        padding: 0;
                    }
                    
                    body {
                        width: ' . $pageWidth . 'mm;
                        height: ' . $pageHeight . 'mm;
                        position: relative;
                        overflow: hidden;
                        margin: 0;
                        padding: 0;
                    }
                    
                    .background-layer {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: 0;
                    }
                    
                    .background-image {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    
                    .text-container {
                        position: absolute;
                        top: ' . $this->top_distance . '%;
                        left: 10%;
                        z-index: 1;
                        width: 80%;
                        text-align: center;
                    }
                    
                    ul {
                        list-style: none;
                        margin: 0;
                        padding: 0;
                    }

                    ul li {
                        margin-bottom: 20px;
                    }

                    .webinar-topic {
                        font-size: 17pt;
                        font-weight: bold;
                        text-align: center;
                        line-height: 1.2;
                        margin: 0;
                        padding: 0;
                        font-style: normal;
                        letter-spacing: 1px;
                        word-spacing: 3px;
                    }
                    
                    .certificate-name {
                        font-family: "'. $this->options['font']['fontName'] .'", serif;
                        // font-size: ' . $fontSize . 'pt;
                        font-size: 50pt;
                        color: #0202EA;
                        text-align: center;
                        line-height: 1.2;
                        margin: 0;
                        padding: 0;
                        font-weight: bold;
                        font-style: normal;
                        letter-spacing: 1px;
                        word-spacing: 3px;
                    }

                    .webinar-detail-container {
                        font-family: "dejavusans", serif;
                    }

                    .layout-webinar-detail {
                        position: relative;
                        width: 100%;
                        height: 100%;
                    }

                    .qr-code-container {
                        position: absolute;
                        left: 10%;
                        z-index: 1;
                        width: 80%;
                    }

                    .qr-code-container img {
                        padding-top: 100px;
                    }
                </style>
            </head>
            <body>
                <!-- Background Layer -->
                <div class="background-layer">
                    <img src="' . $this->options['templatePath'] . '" class="background-image" alt="Certificate Background" />
                </div>
                
                '. $templateContent .'
            </body>
            </html>';
            
            $this->mpdf->WriteHTML($html);
        } catch (Exception $e) {
            throw new Exception("Gagal membuat sertifikat: " . $e->getMessage());
        }
    }

    private function formatName(string $name): string
    {
        // Konversi nama ke format yang sesuai
        $name = trim($name);
        
        // Hapus karakter khusus dan extra spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Konversi ke Title Case (huruf pertama setiap kata besar, lainnya kecil)
        $name = ucwords(strtolower($name));
        
        // Untuk font Great Vibes, kita bisa biarkan natural tanpa uppercase
        return $name;
    }

    private function calculateFontSize(int $nameLength): int
    {
        // Ukuran font default untuk nama pendek (≤ 22 karakter)
        $defaultFontSize = 64;
        $maxCharacters = 22;
        $minFontSize = 32;
        
        // Jika jumlah karakter ≤ 22, gunakan ukuran font default
        if ($nameLength <= $maxCharacters) {
            return $defaultFontSize;
        }
        
        // Hitung pengurangan font size secara proporsional
        // Setiap 5 karakter tambahan, kurangi 4pt dari font size
        $excessCharacters = $nameLength - $maxCharacters;
        $reduction = ceil($excessCharacters / 5) * 10;
        $calculatedSize = $defaultFontSize - $reduction;

        $this->top_distance = $this->top_distance + 2;
        
        // Pastikan ukuran font tidak di bawah minimum
        return max($calculatedSize, $minFontSize);
    }

    private function saveCertificate(string $fullPath): void
    {
        // Output to file
        $this->mpdf->Output($fullPath, 'F');
        
        // Verify file was created
        if (!file_exists($fullPath)) {
            throw new Exception("Failed to save certificate: {$fullPath}");
        }
        
        // Verify file is not empty
        if (filesize($fullPath) === 0) {
            throw new Exception("Certificate file is empty: {$fullPath}");
        }
    }

    /**
     * Method untuk mengubah lokasi background image
     */
    public function setBackgroundImage(string $bg_img_path): self
    {
        $this->bg_img_path = $bg_img_path;
        return $this;
    }

    /**
     * Method untuk render Blade template untuk pemateri dan QR code
     */
    private function renderTemplate(string $full_name, string $webinar_date): string
    {
        try {
            // Validate template file exists
            $templatePath = 'sertifikat-templates.' . $this->options['layout'];
            
            if (!View::exists($templatePath)) {
                throw new Exception("Template not found: resources/views/sertifikat-templates/{$this->options['layout']}.blade.php");
            }
            
            // Data to be passed to template
            $data = [
                'full_name' => $full_name,
                'pemateri' => $this->options['panelis'],
                'webinar_title' => $this->options['webinarTitle'],
                'webinar_topic' => $this->options['webinarTopic'],
                'webinar_date' => $webinar_date,
                'qr_code' => $this->qr_code
            ];

            // Render template
            return View::make($templatePath, $data)->render();
        } catch (Exception $e) {
            throw new Exception("Failed to render template: " . $e->getMessage());
        }
    }

    /**
     * Method untuk menghapus file sertifikat yang sudah dibuat
     */
    public static function deleteCertificate(string $filePath): bool
    {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Generate Qr Code For Tempalate
     * 
     */
    private function generateQrCode(): string
    {
        if($this->options['id']){
            $qr = QrDocument::where('id_document', $this->options['id'])
                    ->where('type_document', 'e_certificate_webinar')
                    ->where('is_active', 1)
                    ->first();
        }

        if (!$qr) $qr = new QrDocument();

        $qr->id_document = $this->options['id'] ?? null;
        $qr->type_document = 'e_certificate_webinar';
        $qr->kode_qr = $this->generateQr($this->options['noSertifikat']);
        $qr->file = str_replace("/", "_", $this->options['noSertifikat']);

        $qr->data = json_encode([
            'tipe_dokumen'          => 'E-certificate webinar',
            'penerima_sertifikat'   => $this->options['recipientName'],
            'no._sertifikat'        => $this->options['noSertifikat'],
            'judul_webinar'         => $this->options['webinarTitle'],
            'topik_webinar'         => $this->options['webinarTopic'],
            'tanggal_webinar'       => $this->options['webinarDate'],
            'panelis'               => $this->options['panelis']
        ]);

        $qr->created_by = 'SYSTEM';
        $qr->created_at = Carbon::now();

        $qr->save();

        // QR Image Render
        $qr_img = '<img class="qr-code" src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="80px" height="80px" >';

        return $qr_img;
    }

    private function generateQr($noDocument)
    {
        $filename = str_replace("/", "_", $noDocument);
        $dir = public_path("qr_documents");

        if (!file_exists($dir)) mkdir($dir, 0755, true);

        $path = $dir . "/$filename.svg";
        $link = 'https://www.intilab.com/validation/';
        $unique = 'isldc' . (int) floor(microtime(true) * 1000);

        QrCode::size(200)->color(255, 255, 255)        // QR putih
                ->backgroundColor(0, 0, 0, 0) // background transparan
                ->generate($link . $unique, $path);

        return $unique;
    }

    private function downloadDefaultFont(): void
    {
        $fontDir = public_path('fonts');
        
        // Buat folder fonts jika belum ada
        if (!is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }
        
        $robotoFonts = [
            'Roboto-Regular.ttf',
            'Roboto-Italic.ttf',
            'Roboto-Light.ttf',
            'Roboto-Medium.ttf',
            'Roboto-Bold.ttf'
        ];
        
        foreach ($robotoFonts as $fontFile) {
            $fontPath = $fontDir . '/' . $fontFile;
            
            if (!file_exists($fontPath)) {
                $fontUrl = 'https://github.com/Skyhwk/fonts/blob/main/' . $fontFile;
                
                // Download font dari repository
                $fontContent = @file_get_contents($fontUrl);
                if ($fontContent !== false) {
                    file_put_contents($fontPath, $fontContent);
                } else {
                    throw new Exception("Font {$fontFile} tidak ditemukan dan gagal didownload. Silakan upload font {$fontFile} ke folder public/fonts/");
                }
            }
        }
    }

    private function downloadFont(): void
    {
        $fontUrl = 'https://github.com/google/fonts/raw/main/ofl/'. $this->options['font']['fontName'] .'/'. $this->options['font']['filename'];
        $fontDir = public_path('fonts');
        $fontPath = $fontDir . '/' . $this->options['font']['filename'];
        
        // Buat folder fonts jika belum ada
        if (!is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }
        
        // Download font dari Google Fonts
        $fontContent = @file_get_contents($fontUrl);
        if ($fontContent !== false) {
            file_put_contents($fontPath, $fontContent);
        } else {
            // Fallback ke font default jika gagal download
            throw new Exception("Font ". $this->options['font']['fontName'] ." tidak ditemukan dan gagal didownload. Silakan upload font ". $this->options['font']['filename'] ." ke folder public/fonts/");
        }
    }

    private function resetParams()
    {
        $this->options = [];
        $this->qr_code = null;
    }
}